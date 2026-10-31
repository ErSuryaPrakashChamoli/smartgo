<?php

namespace App\Filament\Clusters\Properties\Resources\Spaces\RelationManagers;

use App\Actions\Inventory\ChangeAllocation;
use App\Actions\Inventory\RecordAllocation;
use App\Enums\Access\Capability;
use App\Enums\Inventory\AllocationStatus;
use App\Enums\Inventory\InventoryMode;
use App\Filament\Clusters\Properties\Concerns\RunsInventorySteps;
use App\Models\Space;
use App\Models\SpaceAllocation;
use App\Models\User;
use App\Services\Inventory\BusinessDay;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * A space's allocations: long-term occupancy under an agreement (FR-INV-3). Recording one, notice, a
 * new end date or a cancellation locks the space and re-checks every day concerned (ADR-0004); a
 * refusal names the allocations in the way. Seen by the roles of SECURITY_MODEL §3.1 "Allocations".
 *
 * @property Space $ownerRecord
 */
class AllocationsRelationManager extends RelationManager
{
    use RunsInventorySteps;

    protected static string $relationship = 'allocations';

    protected static ?string $title = 'Allocations';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $ownerRecord instanceof Space && ($user?->hasCapabilityForProperty(Capability::ViewAllocations, $ownerRecord->property) ?? false);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $today = BusinessDay::today();
        $canChange = fn (SpaceAllocation $record): bool => $record->statusOn($today)->occupies() && Gate::allows('update', $record);

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['seat', 'creator']))
            ->defaultSort('starts_on', 'desc')
            ->description('Who occupies this space under which agreement. Not bookings: those come with Phase 8.')
            ->columns([
                TextColumn::make('agreement_reference')->label('Agreement')->weight('semibold')->searchable(),
                TextColumn::make('what')->label('Takes')
                    ->state(fn (SpaceAllocation $record): string => match (true) {
                        $record->seat !== null => 'Seat '.$record->seat->label,
                        $this->ownerRecord->inventory_mode === InventoryMode::Exclusive => 'Whole space',
                        default => $record->seats_allocated.' '.str('seat')->plural($record->seats_allocated),
                    }),
                TextColumn::make('starts_on')->label('From')->date('j M Y'),
                TextColumn::make('ends_on')->label('Until')->state(fn (SpaceAllocation $record): string => $record->ends_on?->format('j M Y') ?? 'Open-ended'),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (SpaceAllocation $record): AllocationStatus => $record->statusOn($today))
                    ->formatStateUsing(fn (AllocationStatus $state): string => $state->label())
                    ->color(fn (AllocationStatus $state): string => $state->color()),
                TextColumn::make('creator.name')->label('Recorded by'),
            ])
            ->emptyStateHeading('Nothing allocated')
            ->emptyStateDescription('Record an allocation when an agreement for this space is signed.')
            ->headerActions([
                Action::make('recordAllocation')
                    ->label('Record allocation')
                    ->icon(Heroicon::OutlinedPlus)
                    ->visible(fn (): bool => auth()->user()?->hasCapabilityForProperty(Capability::ManageAllocations, $this->ownerRecord->property) ?? false)
                    ->schema([
                        TextInput::make('agreement_reference')->label('Agreement reference')->required()->maxLength(60)
                            ->helperText('SmartGro\'s internal reference, for example AG-2027-014. No personal details.'),
                        Select::make('space_seat_id')->label('Seat')
                            ->options(fn (): array => $this->ownerRecord->seats()->where('is_active', true)->pluck('label', 'id')->map(fn (string $label): string => 'Seat '.$label)->all())
                            ->required(fn (): bool => $this->ownerRecord->inventory_mode === InventoryMode::PerSeat)
                            ->visible(fn (): bool => $this->ownerRecord->inventory_mode->hasSeats())
                            ->helperText(fn (): ?string => $this->ownerRecord->inventory_mode === InventoryMode::Hybrid ? 'Choose a seat to assign it, or leave empty and give a number of seats from the shared pool.' : null),
                        TextInput::make('seats_allocated')->label('Seats')->integer()->minValue(1)->maxValue(2000)
                            ->visible(fn (): bool => in_array($this->ownerRecord->inventory_mode, [InventoryMode::Pooled, InventoryMode::Hybrid], true)),
                        DatePicker::make('starts_on')->label('From')->required(),
                        DatePicker::make('ends_on')->label('Until (empty if open-ended)'),
                        Textarea::make('reason')->label('Reason')->required()->maxLength(300)->rows(2),
                    ])
                    ->action(fn (array $data) => $this->runInventoryStep(fn (User $user): SpaceAllocation => app(RecordAllocation::class)->handle($user, $this->ownerRecord, $data), 'Allocation recorded')),
            ])
            ->recordActions([
                Action::make('notice')
                    ->label('Record notice')
                    ->color('gray')
                    ->visible($canChange)
                    ->schema([
                        DatePicker::make('notice_given_on')->label('Notice given on')->required()->maxDate($today),
                        DatePicker::make('ends_on')->label('Ends on')->required(),
                        Textarea::make('reason')->label('Reason')->required()->maxLength(300)->rows(2),
                    ])
                    ->action(fn (SpaceAllocation $record, array $data) => $this->runInventoryStep(fn (User $user): SpaceAllocation => app(ChangeAllocation::class)->handle($user, $record, 'notice', $data), 'Notice recorded')),
                Action::make('endDate')
                    ->label('Change end date')
                    ->color('gray')
                    ->visible($canChange)
                    ->fillForm(fn (SpaceAllocation $record): array => ['ends_on' => $record->ends_on?->toDateString()])
                    ->schema([
                        DatePicker::make('ends_on')->label('Ends on (empty if open-ended)'),
                        Textarea::make('reason')->label('Reason')->required()->maxLength(300)->rows(2),
                    ])
                    ->action(fn (SpaceAllocation $record, array $data) => $this->runInventoryStep(fn (User $user): SpaceAllocation => app(ChangeAllocation::class)->handle($user, $record, 'end_date', $data), 'End date changed')),
                Action::make('cancel')
                    ->label('Cancel')
                    ->color('danger')
                    ->visible($canChange)
                    ->modalDescription('Frees the space at once. The allocation stays in the history as cancelled.')
                    ->schema([Textarea::make('reason')->label('Reason')->required()->maxLength(300)->rows(2)])
                    ->action(fn (SpaceAllocation $record, array $data) => $this->runInventoryStep(fn (User $user): SpaceAllocation => app(ChangeAllocation::class)->handle($user, $record, 'cancel', $data), 'Allocation cancelled')),
            ]);
    }
}
