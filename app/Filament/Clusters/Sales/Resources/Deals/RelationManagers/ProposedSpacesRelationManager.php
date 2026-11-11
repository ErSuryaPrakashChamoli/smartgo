<?php

namespace App\Filament\Clusters\Sales\Resources\Deals\RelationManagers;

use App\Actions\Crm\ProposeDealSpace;
use App\Actions\Crm\WithdrawDealSpace;
use App\Enums\Access\Capability;
use App\Enums\Inventory\InventoryMode;
use App\Enums\Inventory\SpaceStatus;
use App\Filament\Clusters\Properties\Concerns\RunsInventorySteps;
use App\Models\Deal;
use App\Models\DealSpace;
use App\Models\Space;
use App\Models\User;
use App\Services\Inventory\SpaceAvailability;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

/**
 * The spaces proposed on a deal (FR-CRM-4), from the canonical inventory, each with its availability today
 * as staff see it (BR-AV-3), and, once the deal is won, the allocation made for it (BR-CRM-7). Proposing
 * reserves nothing. Spaces are offered only at the enquiry's location (any location the user works on for
 * an enquiry without one), and only while in use.
 *
 * @property Deal $ownerRecord
 */
class ProposedSpacesRelationManager extends RelationManager
{
    use RunsInventorySteps;

    protected static string $relationship = 'proposedSpaces';

    protected static ?string $title = 'Proposed spaces';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Deal::isAvailable() && Gate::allows('view', $ownerRecord);
    }

    /**
     * Shows at once the allocations a win recorded on the deal's page.
     */
    #[On('deal-changed')]
    public function refreshProposedSpaces(): void {}

    public function table(Table $table): Table
    {
        $canChange = fn (): bool => $this->ownerRecord->isOpen() && Gate::allows('update', $this->ownerRecord);

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['space.property', 'space.seats', 'seat', 'allocation.seat', 'allocation.space']))
            ->columns([
                TextColumn::make('space')
                    ->label('Space')
                    ->state(fn (DealSpace $record): string => $record->space->displayName())
                    ->description(fn (DealSpace $record): string => 'SmartGro '.$record->space->property->name.' · '.$record->space->inventory_mode->label())
                    ->weight('semibold'),
                TextColumn::make('portion')->label('Proposed')->state(fn (DealSpace $record): string => $record->portion()),
                TextColumn::make('available_today')
                    ->label('Available today')
                    ->state(fn (DealSpace $record): string => app(SpaceAvailability::class)->on($record->space)->summary($record->space->inventory_mode === InventoryMode::Exclusive)),
                TextColumn::make('allocated')
                    ->label('Allocation')
                    ->state(fn (DealSpace $record): ?string => $record->allocation?->summary())
                    ->placeholder('Not allocated')
                    ->visibleFrom('md'),
            ])
            ->headerActions([
                Action::make('propose')
                    ->label('Propose a space')
                    ->icon(Heroicon::OutlinedPlus)
                    ->visible($canChange)
                    ->schema([
                        Select::make('space_id')
                            ->label('Space')
                            ->options(fn (): array => $this->spaceOptions())
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('space_seat_id', null);
                                $set('seats', null);
                            })
                            ->helperText(fn (Get $get): string => ($space = $this->chosenSpace($get)) === null
                                ? 'Only spaces in use at the enquiry\'s location are listed.'
                                : $space->inventory_mode->label().'. Today: '.app(SpaceAvailability::class)->on($space)->summary($space->inventory_mode === InventoryMode::Exclusive).'. Nothing is reserved until the deal is won.'),
                        Select::make('space_seat_id')
                            ->label(fn (Get $get): string => $this->chosenSpace($get)?->inventory_mode === InventoryMode::Hybrid ? 'Seat (or give a number of seats instead)' : 'Seat')
                            ->options(fn (Get $get): array => $this->chosenSpace($get)?->seats->where('is_active', true)->sortBy('label', SORT_NATURAL)->mapWithKeys(fn ($seat): array => [$seat->getKey() => 'Seat '.$seat->label])->all() ?? [])
                            ->required(fn (Get $get): bool => $this->chosenSpace($get)?->inventory_mode === InventoryMode::PerSeat)
                            ->visible(fn (Get $get): bool => $this->chosenSpace($get)?->inventory_mode->hasSeats() ?? false)
                            ->live(),
                        TextInput::make('seats')
                            ->label('Number of seats')
                            ->integer()
                            ->minValue(1)
                            ->required(fn (Get $get): bool => $this->chosenSpace($get)?->inventory_mode === InventoryMode::Pooled)
                            ->visible(fn (Get $get): bool => in_array($this->chosenSpace($get)?->inventory_mode, [InventoryMode::Pooled, InventoryMode::Hybrid], true) && blank($get('space_seat_id'))),
                    ])
                    ->action(fn (array $data) => $this->runInventoryStep(fn (User $user) => app(ProposeDealSpace::class)->handle($user, $this->ownerRecord, $data), 'Space proposed')),
            ])
            ->recordActions([
                Action::make('withdraw')
                    ->label('Withdraw')
                    ->icon(Heroicon::OutlinedMinusCircle)
                    ->color('gray')
                    ->visible($canChange)
                    ->requiresConfirmation()
                    ->modalDescription('The space is taken off this deal. Nothing was reserved for it, so nothing is released.')
                    ->action(fn (DealSpace $record) => $this->runInventoryStep(fn (User $user) => app(WithdrawDealSpace::class)->handle($user, $record), 'Space withdrawn')),
            ])
            ->emptyStateHeading('No spaces proposed')
            ->emptyStateDescription('Propose spaces from the inventory. Nothing is reserved until the deal is won, when each is allocated if it is still free.');
    }

    /**
     * Spaces in use that the user works on, at the enquiry's location when it names one.
     *
     * @return array<int, string>
     */
    private function spaceOptions(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $propertyId = $this->ownerRecord->lead->property_id;

        return Space::query()
            ->with('property')
            ->visibleTo($user, Capability::ManageLeads)
            ->where('operational_status', SpaceStatus::Active)
            ->when($propertyId !== null, fn (Builder $query) => $query->where('property_id', $propertyId))
            ->orderBy('property_id')
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Space $space): array => [$space->getKey() => 'SmartGro '.$space->property->name.': '.$space->displayName()])
            ->all();
    }

    private function chosenSpace(Get $get): ?Space
    {
        return filled($get('space_id')) ? Space::query()->with('seats')->find($get('space_id')) : null;
    }
}
