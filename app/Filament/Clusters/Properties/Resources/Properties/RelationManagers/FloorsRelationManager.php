<?php

namespace App\Filament\Clusters\Properties\Resources\Properties\RelationManagers;

use App\Actions\Inventory\RemoveFloor;
use App\Actions\Inventory\SaveFloor;
use App\Filament\Clusters\Properties\Concerns\RunsInventorySteps;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Property;
use App\Models\User;
use Filament\Actions\Action;
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
 * The floors SmartGro operates in the property's buildings (FR-PROP-1, BR-PROP-6). None is assumed:
 * staff add them from the owner's facts (OD-30, OD-32). Hidden on a database without the inventory
 * tables.
 *
 * @property Property $ownerRecord
 */
class FloorsRelationManager extends RelationManager
{
    use RunsInventorySteps;

    protected static string $relationship = 'floors';

    protected static ?string $title = 'Floors';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Floor::isAvailable() && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('building_level')
            ->description('Only the floors SmartGro operates, as the owner has confirmed them.')
            ->columns([
                TextColumn::make('building_level')->label('Level'),
                TextColumn::make('label')->weight('semibold'),
                TextColumn::make('building.code')->label('Building')->visible(fn (): bool => $this->hasSeveralBuildings()),
                TextColumn::make('spaces_count')->counts('spaces')->label('Spaces'),
            ])
            ->emptyStateHeading('No floors recorded')
            ->emptyStateDescription('Add the floors SmartGro operates here from the owner\'s facts. Spaces are added to a floor.')
            ->headerActions([
                Action::make('addFloor')
                    ->label('Add floor')
                    ->icon(Heroicon::OutlinedPlus)
                    ->visible(fn (): bool => Gate::allows('create', Floor::class))
                    ->schema($this->floorFields())
                    ->action(fn (array $data) => $this->runInventoryStep(fn (User $user): Floor => app(SaveFloor::class)->handle($user, $this->ownerRecord, null, $this->withBuilding($data)), 'Floor added')),
            ])
            ->recordActions([
                Action::make('editFloor')
                    ->label('Edit')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (Floor $record): bool => Gate::allows('update', $record))
                    ->fillForm(fn (Floor $record): array => $record->only(['building_id', 'building_level', 'label']))
                    ->schema($this->floorFields())
                    ->action(fn (Floor $record, array $data) => $this->runInventoryStep(fn (User $user): Floor => app(SaveFloor::class)->handle($user, $this->ownerRecord, $record, $this->withBuilding($data)), 'Floor saved')),
                Action::make('removeFloor')
                    ->label('Remove')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->visible(fn (Floor $record): bool => Gate::allows('delete', $record) && $record->spaces_count === 0)
                    ->modalDescription('Only a floor recorded by mistake, with no spaces, can be removed.')
                    ->schema([Textarea::make('reason')->label('Reason')->required()->maxLength(300)->rows(2)])
                    ->action(fn (Floor $record, array $data) => $this->runInventoryStep(fn (User $user) => app(RemoveFloor::class)->handle($user, $record, (string) $data['reason']), 'Floor removed')),
            ]);
    }

    /**
     * @return list<mixed>
     */
    private function floorFields(): array
    {
        return [
            Select::make('building_id')
                ->label('Building')
                ->options(fn (): array => $this->ownerRecord->buildings()->get()->mapWithKeys(fn (Building $building): array => [$building->getKey() => $building->displayName()])->all())
                ->required()
                ->visible(fn (): bool => $this->hasSeveralBuildings()),
            TextInput::make('building_level')->label('Level')->integer()->required()->minValue(-5)->maxValue(200)
                ->helperText('0 is the ground floor, 1 the first floor, -1 a basement.'),
            TextInput::make('label')->label('Name')->required()->maxLength(120)->placeholder('First floor'),
            Textarea::make('reason')->label('Reason for the change')->required()->maxLength(300)->rows(2)
                ->helperText('Recorded in the audit log, for example "Floor list confirmed by the owner".'),
        ];
    }

    /**
     * The primary building when the property has only one, so the building level stays hidden.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withBuilding(array $data): array
    {
        return [...$data, 'building_id' => $data['building_id'] ?? $this->ownerRecord->primaryBuilding()->value('id')];
    }

    private function hasSeveralBuildings(): bool
    {
        return $this->ownerRecord->buildings()->count() > 1;
    }
}
