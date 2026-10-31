<?php

namespace App\Filament\Clusters\Sales\Resources\Tours\Pages;

use App\Actions\Booking\ChangeTour;
use App\Enums\Access\Capability;
use App\Enums\Booking\TourStatus;
use App\Filament\Clusters\Sales\Resources\Tours\TourResource;
use App\Models\TourRequest;
use App\Models\User;
use App\Services\Booking\TourSlots;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * One tour with its actions. Each runs ChangeTour, which locks the property and the tour and checks
 * permission, scope and the status the user saw again, so hiding a button is only a convenience. No
 * message is sent to the visitor from here yet (BR-TR-3 waits for a verified channel, input O-1).
 *
 * @property TourRequest $record
 */
class ViewTour extends ViewRecord
{
    protected static string $resource = TourResource::class;

    /**
     * The status the user is looking at; a change is refused if the tour moved on meanwhile.
     */
    public string $seenStatus = '';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->seenStatus = $this->record->status->value;
    }

    public function getSubheading(): ?string
    {
        return 'A tour is a visit, not a booking. SmartGro tells the visitor about any change itself: no message is sent from here yet.';
    }

    protected function getHeaderActions(): array
    {
        $upcoming = fn (): bool => Gate::allows('update', $this->record) && $this->record->status->isUpcoming() && $this->record->scheduled_start_at->isFuture();
        $started = fn (): bool => Gate::allows('update', $this->record) && $this->record->status === TourStatus::Confirmed && ! $this->record->scheduled_start_at->isFuture();

        return [
            Action::make('confirm')
                ->label('Confirm')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->visible(fn (): bool => $upcoming() && $this->record->status === TourStatus::Requested)
                ->requiresConfirmation()
                ->modalDescription('Confirm only once the time is agreed with the visitor.')
                ->action(fn () => $this->change('confirm', [], 'Tour confirmed')),
            Action::make('reschedule')
                ->label('Move')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible($upcoming)
                ->schema([
                    Select::make('starts_at')
                        ->label('New time (India time)')
                        ->options(fn (TourSlots $slots): array => $slots->options($this->record->property, $this->record))
                        ->required()
                        ->helperText('Only free times in the next '.config('tours.window_days').' days are listed. The time is checked again when you save.'),
                ])
                ->action(fn (array $data) => $this->change('reschedule', $data, 'Tour moved')),
            Action::make('assignHost')
                ->label(fn (): string => $this->record->host_user_id === null ? 'Name host' : 'Change host')
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('gray')
                ->visible(fn (): bool => Gate::allows('update', $this->record) && $this->record->status->isUpcoming())
                ->fillForm(fn (): array => ['host_user_id' => $this->record->host_user_id])
                ->schema([
                    Select::make('host_user_id')
                        ->label('Host')
                        ->placeholder('Not named')
                        ->options(fn (): array => User::query()->where('is_active', true)->orderBy('name')->get()
                            ->filter(fn (User $user): bool => $user->hasCapabilityForProperty(Capability::ViewTours, $this->record->property))
                            ->pluck('name', 'id')->all())
                        ->helperText('Active staff who work on this location\'s tours.'),
                ])
                ->action(fn (array $data) => $this->change('assign_host', ['host_user_id' => $data['host_user_id'] ?? null], 'Host updated')),
            Action::make('complete')
                ->label('Completed')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->visible($started)
                ->requiresConfirmation()
                ->action(fn () => $this->change('complete', [], 'Tour marked completed')),
            Action::make('noShow')
                ->label('Did not come')
                ->icon(Heroicon::OutlinedUserMinus)
                ->color('gray')
                ->visible($started)
                ->requiresConfirmation()
                ->action(fn () => $this->change('no_show', [], 'Tour marked as a no-show')),
            Action::make('cancel')
                ->label('Cancel tour')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible($upcoming)
                ->schema([
                    Textarea::make('reason')->label('Reason')->required()->maxLength(300)->rows(2)
                        ->helperText('Recorded in the tour\'s history.'),
                ])
                ->action(fn (array $data) => $this->change('cancel', ['reason' => $data['reason']], 'Tour cancelled')),
        ];
    }

    /**
     * Runs the change for the signed-in user against the status shown; refusals become notifications.
     *
     * @param  array<string, mixed>  $data
     */
    private function change(string $change, array $data, string $success): void
    {
        $this->runStep(fn (User $user) => app(ChangeTour::class)->handle($user, $this->record, $change, TourStatus::from($this->seenStatus), $data), $success);
    }

    /**
     * @param  Closure(User): mixed  $step
     */
    private function runStep(Closure $step, string $success): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $step($user);
        } catch (AuthorizationException $exception) {
            Notification::make()->danger()->title('Not allowed')->body($exception->getMessage())->send();

            throw new Halt;
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title('Not saved')->body(collect($exception->errors())->flatten()->first())->persistent()->send();

            throw new Halt;
        }

        Notification::make()->success()->title($success)->send();
        $this->record->refresh();
        $this->seenStatus = $this->record->status->value;
    }
}
