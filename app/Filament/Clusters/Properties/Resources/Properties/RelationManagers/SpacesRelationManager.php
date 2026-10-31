<?php

namespace App\Filament\Clusters\Properties\Resources\Properties\RelationManagers;

use App\Actions\Inventory\AddSpaces;
use App\Actions\Inventory\SaveSpace;
use App\Enums\Access\Capability;
use App\Enums\Inventory\InventoryMode;
use App\Enums\Inventory\SpaceStatus;
use App\Filament\Clusters\Properties\Concerns\RunsInventorySteps;
use App\Filament\Clusters\Properties\Resources\Spaces\Schemas\SpaceForm;
use App\Filament\Clusters\Properties\Resources\Spaces\SpaceResource;
use App\Models\Property;
use App\Models\Space;
use App\Models\User;
use App\Services\Inventory\DayAvailability;
use App\Services\Inventory\SpaceAvailability;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The property's spaces with today's availability (FR-INV-1, FR-INV-6), and adding them one at a time
 * or as a run of similar spaces. Each space opens on its own page for seats, status and allocations.
 * Hidden on a database without the inventory tables.
 *
 * @property Property $ownerRecord
 */
class SpacesRelationManager extends RelationManager
{
    use RunsInventorySteps;

    protected static string $relationship = 'spaces';

    protected static ?string $title = 'Spaces';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Space::isAvailable() && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['floor', 'spaceType', 'seats', 'offerings.product']))
            ->defaultSort('code')
            ->description('Cabins, rooms, suites and desk zones, entered from real inventory. Availability is worked out from status, seats and allocations; it is never typed in.')
            ->columns([
                TextColumn::make('code')->weight('semibold')->searchable()->description(fn (Space $record): ?string => $record->name),
                TextColumn::make('floor.label')->label('Floor')->description(fn (Space $record): string => 'Level '.$record->floor->building_level),
                TextColumn::make('spaceType.name')->label('Type')->description(fn (Space $record): string => $record->inventory_mode->label()),
                TextColumn::make('operational_status')->label('Status')->badge()
                    ->formatStateUsing(fn (SpaceStatus $state): string => $state->label())
                    ->color(fn (SpaceStatus $state): string => $state->color()),
                TextColumn::make('today')->label('Today')
                    ->state(fn (Space $record): string => $this->availabilityToday()->get($record->getKey())?->summary($record->inventory_mode === InventoryMode::Exclusive) ?? ''),
                TextColumn::make('sold_as')->label('Sold as')
                    ->state(fn (Space $record): string => $record->offerings->map(fn ($offering): string => $offering->product->name)->implode(', ') ?: 'Not yet'),
            ])
            ->emptyStateHeading('No spaces recorded')
            ->emptyStateDescription($this->ownerRecord->floors()->exists() ? 'Add the spaces on each floor from the real inventory.' : 'Add a floor first, then its spaces.')
            ->headerActions([
                Action::make('addSpace')
                    ->label('Add space')
                    ->icon(Heroicon::OutlinedPlus)
                    ->visible(fn (): bool => $this->canAddSpaces())
                    ->schema(fn (): array => SpaceForm::fields($this->ownerRecord, SpaceForm::ONE))
                    ->action(fn (array $data) => $this->runInventoryStep(fn (User $user): Space => app(SaveSpace::class)->handle($user, $this->ownerRecord, null, $data), 'Space added')),
                Action::make('addSpaces')
                    ->label('Add several spaces')
                    ->icon(Heroicon::OutlinedSquaresPlus)
                    ->color('gray')
                    ->visible(fn (): bool => $this->canAddSpaces())
                    ->modalDescription('Adds a numbered run of similar spaces to one floor, all or none.')
                    ->schema(fn (): array => SpaceForm::fields($this->ownerRecord, SpaceForm::MANY))
                    ->action(fn (array $data) => $this->runInventoryStep(fn (User $user): Collection => app(AddSpaces::class)->handle($user, $this->ownerRecord, $data), 'Spaces added')),
            ])
            ->recordUrl(fn (Space $record): string => SpaceResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                Action::make('open')->label('Open')->icon(Heroicon::OutlinedArrowRight)->url(fn (Space $record): string => SpaceResource::getUrl('view', ['record' => $record])),
            ]);
    }

    /**
     * Today's availability of every space on the property, worked out once per request.
     *
     * @return Collection<int, DayAvailability>
     */
    private function availabilityToday(): Collection
    {
        return once(fn (): Collection => app(SpaceAvailability::class)->onForMany($this->ownerRecord->spaces()->with('seats')->get()));
    }

    private function canAddSpaces(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->hasCapabilityForProperty(Capability::ManageInventory, $this->ownerRecord) && $this->ownerRecord->floors()->exists();
    }
}
