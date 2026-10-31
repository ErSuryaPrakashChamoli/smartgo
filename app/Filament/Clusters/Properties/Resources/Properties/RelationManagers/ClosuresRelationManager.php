<?php

namespace App\Filament\Clusters\Properties\Resources\Properties\RelationManagers;

use App\Actions\Booking\SaveClosure;
use App\Enums\Access\Capability;
use App\Filament\Clusters\Properties\Concerns\RunsInventorySteps;
use App\Models\Property;
use App\Models\PropertyClosure;
use App\Models\TourRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Holidays and other closures of the property, in India time. No tour is offered while one runs; nothing
 * already arranged is cancelled, and staff are told which tours fall inside. Past closures are hidden.
 * Hidden on a database without the tour tables.
 *
 * @property Property $ownerRecord
 */
class ClosuresRelationManager extends RelationManager
{
    use RunsInventorySteps;

    protected static string $relationship = 'closures';

    protected static ?string $title = 'Closures';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return TourRequest::isAvailable() && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function table(Table $table): Table
    {
        $timezone = (string) config('backoffice.timezone');

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('ends_at', '>', now()))
            ->description('Times in India time. Closures that have ended are not listed.')
            ->columns([
                TextColumn::make('starts_at')->label('From')->dateTime('D j M Y, H:i', $timezone),
                TextColumn::make('ends_at')->label('Until')->dateTime('D j M Y, H:i', $timezone),
                TextColumn::make('reason')->wrap(),
                IconColumn::make('is_public')->label('Shown to visitors')->boolean(),
            ])
            ->emptyStateHeading('No closures coming up')
            ->emptyStateDescription('Record holidays and other days the location is closed, so no tour is offered then.')
            ->headerActions([
                Action::make('addClosure')
                    ->label('Add closure')
                    ->icon(Heroicon::OutlinedPlus)
                    ->visible(fn (): bool => $this->canManage())
                    ->schema($this->fields())
                    ->action(fn (array $data) => $this->saved($this->runInventoryStep(fn (User $user): array => app(SaveClosure::class)->handle($user, $this->ownerRecord, null, $data), 'Closure recorded'))),
            ])
            ->recordActions([
                Action::make('editClosure')
                    ->label('Edit')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (PropertyClosure $record): bool => Gate::allows('update', $record))
                    ->fillForm(fn (PropertyClosure $record): array => [
                        'starts_at' => $record->starts_at->copy()->timezone($timezone)->format('Y-m-d H:i'),
                        'ends_at' => $record->ends_at->copy()->timezone($timezone)->format('Y-m-d H:i'),
                        'reason' => $record->reason,
                        'is_public' => $record->is_public,
                    ])
                    ->schema($this->fields())
                    ->action(fn (PropertyClosure $record, array $data) => $this->saved($this->runInventoryStep(fn (User $user): array => app(SaveClosure::class)->handle($user, $this->ownerRecord, $record, $data), 'Closure saved'))),
                Action::make('removeClosure')
                    ->label('Remove')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (PropertyClosure $record): bool => Gate::allows('delete', $record))
                    ->action(fn (PropertyClosure $record) => $this->runInventoryStep(fn (User $user) => app(SaveClosure::class)->remove($user, $record), 'Closure removed')),
            ]);
    }

    /**
     * Warns when tours already arranged fall inside the closure.
     *
     * @param  array{closure: PropertyClosure, tours_inside: int}  $result
     */
    private function saved(array $result): void
    {
        if ($result['tours_inside'] > 0) {
            Notification::make()->warning()->persistent()
                ->title($result['tours_inside'].' tour(s) fall inside this closure')
                ->body('They were not changed. Move or cancel them in Sales → Tours.')
                ->send();
        }
    }

    /**
     * @return list<mixed>
     */
    private function fields(): array
    {
        return [
            DateTimePicker::make('starts_at')->label('From (India time)')->seconds(false)->required(),
            DateTimePicker::make('ends_at')->label('Until (India time)')->seconds(false)->required(),
            TextInput::make('reason')->required()->maxLength(200)->placeholder('Public holiday'),
            Toggle::make('is_public')->label('May be shown to visitors')->helperText('Not shown on the website yet; kept for when closures are.'),
        ];
    }

    private function canManage(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->hasCapabilityForProperty(Capability::ManageProperties, $this->ownerRecord);
    }
}
