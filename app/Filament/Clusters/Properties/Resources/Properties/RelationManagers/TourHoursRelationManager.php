<?php

namespace App\Filament\Clusters\Properties\Resources\Properties\RelationManagers;

use App\Actions\Booking\SaveTourRule;
use App\Enums\Access\Capability;
use App\Filament\Clusters\Properties\Concerns\RunsInventorySteps;
use App\Models\Property;
use App\Models\TourAvailabilityRule;
use App\Models\TourRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * When tours can happen at the property (BR-TR-1), in India time. None is assumed: staff enter them from
 * SmartGro's tour rules (D-28). Visitors see tour times only once the privacy notice is published
 * (TourForm). Hidden on a database without the tour tables.
 *
 * @property Property $ownerRecord
 */
class TourHoursRelationManager extends RelationManager
{
    use RunsInventorySteps;

    protected static string $relationship = 'tourRules';

    protected static ?string $title = 'Tour hours';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return TourRequest::isAvailable() && $user !== null && $ownerRecord instanceof Property && $user->hasCapabilityForProperty(Capability::ViewTours, $ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Weekly windows in India time. Each window is split into tours of the length given; a time is full once as many tours as allowed overlap it.')
            ->columns([
                TextColumn::make('weekday')->label('Day')->formatStateUsing(fn (int $state): string => TourAvailabilityRule::WEEKDAYS[$state]),
                TextColumn::make('start_time')->label('First tour')->formatStateUsing(fn (TourAvailabilityRule $record): string => $record->startsAt()),
                TextColumn::make('end_time')->label('Last tour ends')->formatStateUsing(fn (TourAvailabilityRule $record): string => $record->endsAt()),
                TextColumn::make('slot_minutes')->label('Tour length')->suffix(' min'),
                TextColumn::make('max_concurrent_tours')->label('Tours at a time'),
            ])
            ->emptyStateHeading('No tour hours')
            ->emptyStateDescription('Enter the hours SmartGro offers tours here, from its tour rules. Until then visitors cannot pick a tour time for this location.')
            ->headerActions([
                Action::make('addTourHours')
                    ->label('Add tour hours')
                    ->icon(Heroicon::OutlinedPlus)
                    ->visible(fn (): bool => $this->canManage())
                    ->schema($this->fields())
                    ->action(fn (array $data) => $this->runInventoryStep(fn (User $user): TourAvailabilityRule => app(SaveTourRule::class)->handle($user, $this->ownerRecord, null, $data), 'Tour hours added')),
            ])
            ->recordActions([
                Action::make('editTourHours')
                    ->label('Edit')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (TourAvailabilityRule $record): bool => Gate::allows('update', $record))
                    ->fillForm(fn (TourAvailabilityRule $record): array => ['weekday' => $record->weekday, 'start_time' => $record->startsAt(), 'end_time' => $record->endsAt(), 'slot_minutes' => $record->slot_minutes, 'max_concurrent_tours' => $record->max_concurrent_tours])
                    ->schema($this->fields())
                    ->action(fn (TourAvailabilityRule $record, array $data) => $this->runInventoryStep(fn (User $user): TourAvailabilityRule => app(SaveTourRule::class)->handle($user, $this->ownerRecord, $record, $data), 'Tour hours saved')),
                Action::make('removeTourHours')
                    ->label('Remove')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Tours already arranged in these hours stay as they are.')
                    ->visible(fn (TourAvailabilityRule $record): bool => Gate::allows('delete', $record))
                    ->action(fn (TourAvailabilityRule $record) => $this->runInventoryStep(fn (User $user) => app(SaveTourRule::class)->remove($user, $record), 'Tour hours removed')),
            ]);
    }

    /**
     * @return list<mixed>
     */
    private function fields(): array
    {
        return [
            Select::make('weekday')->label('Day')->options(TourAvailabilityRule::WEEKDAYS)->required(),
            TimePicker::make('start_time')->label('First tour starts')->seconds(false)->required(),
            TimePicker::make('end_time')->label('Last tour ends by')->seconds(false)->required(),
            TextInput::make('slot_minutes')->label('Tour length (minutes)')->integer()->minValue(10)->maxValue(240)->required(),
            TextInput::make('max_concurrent_tours')->label('Tours at the same time')->integer()->minValue(1)->maxValue(20)->required()
                ->helperText('How many tours may overlap, for example with several hosts.'),
        ];
    }

    private function canManage(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->hasCapabilityForProperty(Capability::ManageTours, $this->ownerRecord);
    }
}
