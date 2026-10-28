<?php

namespace App\Filament\Clusters\Sales\Resources\Leads\Pages;

use App\Actions\Crm\AssignLead;
use App\Actions\Crm\ChangeLeadStatus;
use App\Enums\Crm\LeadStatus;
use App\Filament\Clusters\Sales\Resources\Leads\LeadResource;
use App\Models\Lead;
use App\Models\LeadCloseReason;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * One enquiry with its actions. Each action runs a server-side step that locks the enquiry and checks
 * permission, scope and the state the user saw again, so hiding a button is only a convenience.
 *
 * @property Lead $record
 */
class ViewLead extends ViewRecord
{
    protected static string $resource = LeadResource::class;

    public function getSubheading(): ?string
    {
        return 'An enquiry is not a booking: nothing is reserved, priced or charged until confirmed in writing.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assign')
                ->label(fn (): string => $this->record->owner_user_id === null ? 'Assign' : 'Reassign')
                ->icon(Heroicon::OutlinedUserPlus)
                ->visible(fn (): bool => Gate::allows('assign', $this->record))
                ->fillForm(fn (): array => ['owner' => $this->record->owner_user_id, 'expected_owner' => $this->record->owner_user_id])
                ->schema([
                    Select::make('owner')
                        ->label('Owner')
                        ->options(fn (AssignLead $assignLead): array => $assignLead->candidates($this->record))
                        ->placeholder('Unassigned')
                        ->helperText('Only active staff who may work on enquiries about this location are listed.'),
                    Hidden::make('expected_owner'),
                ])
                ->action(fn (array $data) => $this->runStep(function (User $user) use ($data): void {
                    $owner = filled($data['owner'] ?? null) ? User::query()->find($data['owner']) : null;
                    app(AssignLead::class)->handle($this->record, $user, $owner, filled($data['expected_owner'] ?? null) ? (int) $data['expected_owner'] : null);
                }, 'Owner updated')),
            Action::make('changeStatus')
                ->label('Change status')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (): bool => Gate::allows('update', $this->record))
                ->fillForm(fn (): array => ['expected_status' => $this->record->status->value])
                ->schema([
                    Select::make('status')
                        ->label('New status')
                        ->options(fn (): array => collect($this->record->status->nextStatuses())->mapWithKeys(fn (LeadStatus $status): array => [$status->value => $status->label()])->all())
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('close_reason_id', null)),
                    Select::make('close_reason_id')
                        ->label('Reason')
                        ->options(fn (Get $get): array => ($status = LeadStatus::tryFrom((string) $get('status'))) !== null ? LeadCloseReason::query()->offeredFor($status)->pluck('label', 'id')->all() : [])
                        ->required(fn (Get $get): bool => LeadStatus::tryFrom((string) $get('status'))?->needsReason() ?? false)
                        ->visible(fn (Get $get): bool => LeadStatus::tryFrom((string) $get('status'))?->needsReason() ?? false)
                        ->helperText(fn (Get $get): string => ($status = LeadStatus::tryFrom((string) $get('status'))) !== null && ! LeadCloseReason::query()->offeredFor($status)->exists()
                            ? 'No reasons for marking an enquiry '.strtolower($status->label()).' are set up yet, so it cannot be given this status. The Super Admin adds them in Sales → Closing reasons.'
                            : 'The Super Admin keeps this list in Sales → Closing reasons.'),
                    Textarea::make('details')
                        ->label('Details (optional)')
                        ->maxLength(ChangeLeadStatus::MAX_DETAILS_LENGTH)
                        ->rows(3)
                        ->visible(fn (Get $get): bool => LeadStatus::tryFrom((string) $get('status'))?->needsReason() ?? false),
                    Hidden::make('expected_status'),
                ])
                ->action(fn (array $data) => $this->runStep(fn (User $user) => app(ChangeLeadStatus::class)->handle(
                    $this->record,
                    $user,
                    LeadStatus::from((string) $data['status']),
                    LeadStatus::from((string) $data['expected_status']),
                    filled($data['close_reason_id'] ?? null) ? (int) $data['close_reason_id'] : null,
                    $data['details'] ?? null,
                ), 'Status updated')),
        ];
    }

    /**
     * Runs a step for the signed-in user; refusals and validation errors become notifications.
     *
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
            Notification::make()->danger()->title('Not saved')->body(collect($exception->errors())->flatten()->first())->send();

            throw new Halt;
        }

        Notification::make()->success()->title($success)->send();
        $this->record->refresh();
        $this->dispatch('lead-changed');
    }
}
