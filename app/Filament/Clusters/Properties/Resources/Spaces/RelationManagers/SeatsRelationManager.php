<?php

namespace App\Filament\Clusters\Properties\Resources\Spaces\RelationManagers;

use App\Actions\Inventory\ChangeSeats;
use App\Filament\Clusters\Properties\Concerns\RunsInventorySteps;
use App\Models\Space;
use App\Models\SpaceSeat;
use App\Models\User;
use App\Services\Inventory\SpaceAvailability;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * The seats of a per-seat or hybrid zone (FR-PROP-2). Seats are added or taken out of use with a
 * reason, under the zone's lock; one an allocation needs is never taken out (BR-INV-4).
 *
 * @property Space $ownerRecord
 */
class SeatsRelationManager extends RelationManager
{
    use RunsInventorySteps;

    protected static string $relationship = 'seats';

    protected static ?string $title = 'Seats';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Space && $ownerRecord->inventory_mode->hasSeats() && Gate::allows('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->label('Seat')->weight('semibold'),
                TextColumn::make('is_active')->label('In use')->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'In use' : 'Out of use')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('today')->label('Today')
                    ->state(fn (SpaceSeat $record): string => match (true) {
                        ! $record->is_active => 'Not in use',
                        in_array($record->label, $this->freeSeatsToday(), true) => 'Free',
                        default => 'Allocated',
                    }),
            ])
            ->headerActions([
                Action::make('addSeats')
                    ->label('Add seats')
                    ->icon(Heroicon::OutlinedPlus)
                    ->visible(fn (): bool => Gate::allows('update', $this->ownerRecord))
                    ->schema([
                        TextInput::make('count')->label('How many')->integer()->required()->minValue(1)->maxValue(ChangeSeats::MAX_NEW_SEATS),
                        Textarea::make('reason')->label('Reason')->required()->maxLength(300)->rows(2),
                    ])
                    ->action(fn (array $data) => $this->runInventoryStep(fn (User $user): Space => app(ChangeSeats::class)->handle($user, $this->ownerRecord, 'add', $data), 'Seats added')),
            ])
            ->recordActions([
                Action::make('deactivate')
                    ->label('Take out of use')
                    ->color('gray')
                    ->visible(fn (SpaceSeat $record): bool => $record->is_active && Gate::allows('update', $this->ownerRecord))
                    ->schema([Textarea::make('reason')->label('Reason')->required()->maxLength(300)->rows(2)])
                    ->action(fn (SpaceSeat $record, array $data) => $this->runInventoryStep(fn (User $user): Space => app(ChangeSeats::class)->handle($user, $this->ownerRecord, 'deactivate', ['seat_id' => $record->getKey(), 'reason' => $data['reason']]), 'Seat taken out of use')),
                Action::make('activate')
                    ->label('Put back in use')
                    ->visible(fn (SpaceSeat $record): bool => ! $record->is_active && Gate::allows('update', $this->ownerRecord))
                    ->schema([Textarea::make('reason')->label('Reason')->required()->maxLength(300)->rows(2)])
                    ->action(fn (SpaceSeat $record, array $data) => $this->runInventoryStep(fn (User $user): Space => app(ChangeSeats::class)->handle($user, $this->ownerRecord, 'activate', ['seat_id' => $record->getKey(), 'reason' => $data['reason']]), 'Seat back in use')),
            ]);
    }

    /**
     * The zone's free seats today, from the availability service (FR-INV-6), once per request.
     *
     * @return list<string>
     */
    private function freeSeatsToday(): array
    {
        return once(fn (): array => app(SpaceAvailability::class)->on($this->ownerRecord)->freeSeats);
    }
}
