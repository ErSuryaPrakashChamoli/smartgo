<?php

namespace App\Filament\Clusters\Website\Concerns;

use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\RejectRevision;
use App\Actions\Content\ScheduleRevision;
use App\Actions\Content\SubmitRevision;
use App\Actions\Content\UnscheduleRevision;
use App\Actions\Content\UpdateDraft;
use App\Enums\Content\RevisionState;
use App\Http\Controllers\Backoffice\RevisionPreviewController;
use App\Models\Revision;
use App\Models\User;
use Carbon\CarbonInterface;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Spatie\Activitylog\Models\Activity;

/**
 * The revision workflow on an edit page of publishable content (pages, menus, posts, settings,
 * redirects). The form shows the version being worked on, or the live content when there is none,
 * and can be changed only while that version is a draft the user may edit. Every button runs a
 * workflow action that checks permission and state again on the server under a lock, so hiding or
 * disabling a button is only a convenience.
 *
 * The page using it implements formDataFromPayload() and may override contentNoun(), approvalLabel()
 * and workflowExtraNotice().
 */
trait HasRevisionWorkflow
{
    use MapsPayloadErrorsToForm;

    protected ?Revision $workingRevisionCache = null;

    protected bool $isWorkingRevisionCached = false;

    /**
     * The form fields for a payload; only these reach the browser.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    abstract protected function formDataFromPayload(array $payload): array;

    /**
     * What the content is called in messages ("page", "menu").
     */
    protected function contentNoun(): string
    {
        return 'page';
    }

    /**
     * The approval button's label ("Verify" for legal details, OD-55).
     */
    protected function approvalLabel(): string
    {
        return 'Approve';
    }

    /**
     * A sentence added to the workflow notice and the publish confirmation, such as a redirect warning.
     */
    protected function workflowExtraNotice(): string
    {
        return '';
    }

    public function form(Schema $schema): Schema
    {
        return parent::form($schema)->disabled(fn (): bool => ! $this->canChangeDraft());
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make(fn (): string => $this->workflowNotice()['heading'])
                ->description(fn (): string => $this->workflowNotice()['description'])
                ->icon(fn (): Heroicon => $this->workflowNotice()['icon'])
                ->color(fn (): string => $this->workflowNotice()['color']),
            $this->getFormContentComponent(),
            $this->getRelationManagersContentComponent(),
        ]);
    }

    /**
     * Reloads the version being worked on after a step, here or in the version history.
     */
    #[On('revisions-changed')]
    public function refreshWorkflow(): void
    {
        $this->record->refresh();
        $this->isWorkingRevisionCached = false;
        $this->fillForm();
    }

    public function workingRevision(): ?Revision
    {
        if (! $this->isWorkingRevisionCached) {
            $this->workingRevisionCache = $this->record->openRevision()?->load(['author', 'reviewer', 'scheduler']);
            $this->isWorkingRevisionCached = true;
        }

        return $this->workingRevisionCache;
    }

    public function canChangeDraft(): bool
    {
        $revision = $this->workingRevision();

        return $revision !== null && Gate::allows('update', $revision);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->formDataFromPayload($this->workingRevision()?->payload ?? $this->record->toRevisionPayload());
    }

    /**
     * Saving changes the draft, never the live content.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $this->runStep(fn (User $user) => app(UpdateDraft::class)->handle($this->workingRevisionOrFail(), $user, $data), notify: false, haltOnRefusal: true);

        return $record;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Draft saved';
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Save draft')
                ->visible(fn (): bool => $this->canChangeDraft()),
        ];
    }

    /**
     * @param  list<Action>  $extraActions  shown after Preview
     * @return list<Action>
     */
    protected function workflowHeaderActions(array $extraActions = []): array
    {
        return [
            Action::make('startDraft')
                ->label(fn (): string => $this->latestRejectedRevision() !== null ? 'Revise the rejected version' : 'Start a new draft')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->visible(fn (): bool => $this->workingRevision() === null)
                ->disabled(fn (): bool => Gate::denies('create', [Revision::class, $this->record]))
                ->tooltip(fn (): ?string => Gate::inspect('create', [Revision::class, $this->record])->message())
                ->action(fn () => $this->runStep(
                    fn (User $user) => app(CreateDraft::class)->handle($this->record, $user, basedOn: $this->latestRejectedRevision()),
                    'Draft started',
                )),
            Action::make('preview')
                ->label('Preview')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->visible(fn (): bool => $this->workingRevision() !== null && RevisionPreviewController::isAvailableFor($this->workingRevision()) && Gate::allows('view', $this->workingRevision()))
                ->url(fn (): ?string => $this->workingRevision() === null ? null : RevisionPreviewController::url($this->workingRevision()), shouldOpenInNewTab: true),
            ...$extraActions,
            $this->workflowAction('submit', 'submit', RevisionState::Draft)
                ->label('Submit for approval')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->requiresConfirmation()
                ->modalDescription('Your latest changes are saved first. After that the version cannot be changed, and someone who did not work on it must approve it.')
                ->action(fn () => $this->runStep(function (User $user): void {
                    if ($this->canChangeDraft()) {
                        app(UpdateDraft::class)->handle($this->workingRevisionOrFail(), $user, $this->form->getState());
                    }

                    app(SubmitRevision::class)->handle($this->workingRevisionOrFail(), $user);
                }, 'Submitted for approval')),
            $this->workflowAction('approve', 'approve', RevisionState::Submitted)
                ->label(fn (): string => $this->approvalLabel())
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription(fn (): string => ($this->workingRevision() === null || RevisionPreviewController::isAvailableFor($this->workingRevision()) ? 'Check the preview first. ' : 'Check every value above first. ').'One approval is enough. You will not be able to publish or schedule this version yourself.')
                ->action(fn () => $this->runStep(fn (User $user) => app(ApproveRevision::class)->handle($this->workingRevisionOrFail(), $user), 'Version approved')),
            $this->workflowAction('reject', 'reject', RevisionState::Submitted)
                ->label('Reject')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->schema([
                    Textarea::make('review_note')
                        ->label('What needs to change')
                        ->helperText('The authors see this note.')
                        ->required()
                        ->maxLength(2000)
                        ->rows(4),
                ])
                ->action(fn (array $data) => $this->runStep(fn (User $user) => app(RejectRevision::class)->handle($this->workingRevisionOrFail(), $user, (string) $data['review_note']), 'Version rejected')),
            $this->workflowAction('publish', 'publish', RevisionState::Approved)
                ->label('Publish now')
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription(fn (): string => trim("This version replaces the live {$this->contentNoun()} now. ".$this->workflowExtraNotice()))
                ->action(fn () => $this->runStep(fn (User $user) => app(PublishRevision::class)->handle($this->workingRevisionOrFail(), $user), ucfirst($this->contentNoun()).' published')),
            $this->workflowAction('schedule', 'publish', RevisionState::Approved)
                ->label(fn (): string => $this->workingRevision()?->scheduled_for === null ? 'Schedule' : 'Reschedule')
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->schema([
                    DateTimePicker::make('publish_at')
                        ->label('Publish at')
                        ->timezone(config('backoffice.timezone'))
                        ->seconds(false)
                        ->minDate(fn (): CarbonInterface => now())
                        ->required()
                        ->helperText('India Standard Time. At that moment the version is published as you, after the rules are checked again.'),
                ])
                ->action(fn (array $data) => $this->runStep(fn (User $user) => app(ScheduleRevision::class)->handle($this->workingRevisionOrFail(), $user, Carbon::parse($data['publish_at'])), 'Publication scheduled')),
            Action::make('unschedule')
                ->label('Cancel schedule')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('gray')
                ->visible(fn (): bool => $this->workingRevision()?->state === RevisionState::Approved && $this->workingRevision()?->scheduled_for !== null)
                ->disabled(fn (): bool => Gate::denies('unschedule', $this->workingRevision()))
                ->tooltip(fn (): ?string => $this->workingRevision() === null ? null : Gate::inspect('unschedule', $this->workingRevision())->message())
                ->requiresConfirmation()
                ->modalDescription('The version stays approved and unpublished.')
                ->action(fn () => $this->runStep(fn (User $user) => app(UnscheduleRevision::class)->handle($this->workingRevisionOrFail(), $user), 'Schedule cancelled')),
        ];
    }

    /**
     * A button shown in one state. When the rules refuse the user it stays visible but disabled, with
     * the reason, so the separation of duties is visible.
     */
    private function workflowAction(string $name, string $ability, RevisionState $state): Action
    {
        return Action::make($name)
            ->visible(fn (): bool => $this->workingRevision()?->state === $state)
            ->disabled(fn (): bool => $this->workingRevision() === null || Gate::denies($ability, $this->workingRevision()))
            ->tooltip(fn (): ?string => $this->workingRevision() === null ? null : Gate::inspect($ability, $this->workingRevision())->message());
    }

    /**
     * Runs a workflow step for the signed-in user. Refusals become a notification; validation errors
     * are shown on the fields they belong to.
     *
     * @param  Closure(User): mixed  $step
     */
    private function runStep(Closure $step, string $success = '', bool $notify = true, bool $haltOnRefusal = false): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $step($user);
        } catch (AuthorizationException $exception) {
            Notification::make()->danger()->title('Not allowed')->body($exception->getMessage())->send();

            if ($haltOnRefusal) {
                throw new Halt;
            }

            return;
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title('Check the highlighted fields')->body(collect($exception->errors())->flatten()->first())->send();

            throw $this->payloadErrorsForForm($exception);
        }

        if ($notify) {
            Notification::make()->success()->title($success)->send();
            $this->refreshWorkflow();
            $this->dispatch('page-workflow-changed');
        }
    }

    private function workingRevisionOrFail(): Revision
    {
        return $this->workingRevision() ?? throw ValidationException::withMessages(['revision' => 'There is no version in progress.']);
    }

    private function latestRejectedRevision(): ?Revision
    {
        $latest = $this->record->revisions()->latest('number')->first();

        return $latest?->state === RevisionState::Rejected ? $latest : null;
    }

    /**
     * Where the content stands in the workflow, worked out on every render so it follows each step.
     *
     * @return array{heading: string, description: string, icon: Heroicon, color: string}
     */
    private function workflowNotice(): array
    {
        $notice = fn (string $heading, string $description, Heroicon $icon, string $color): array => [
            'heading' => $heading,
            'description' => trim($description),
            'icon' => $icon,
            'color' => $color,
        ];
        $noun = $this->contentNoun();
        $revision = $this->workingRevision();

        if ($revision === null) {
            $rejected = $this->latestRejectedRevision();

            return match (true) {
                $rejected !== null => $notice(
                    "Version {$rejected->number} was rejected",
                    "By {$rejected->reviewer?->name} on {$this->time($rejected->reviewed_at)}: \u{201C}{$rejected->review_note}\u{201D} Revise it to start a new draft from it.",
                    Heroicon::OutlinedXCircle,
                    'danger',
                ),
                $this->record->isPublished() => $notice(
                    'Live: version '.$this->record->publishedRevision?->number,
                    "Published on {$this->time($this->record->published_at)}. Start a new draft to change the {$noun}; the live {$noun} stays as it is until a new version is approved and published.",
                    Heroicon::OutlinedCheckCircle,
                    'success',
                ),
                default => $notice('No version yet', "Start a new draft to write this {$noun}.", Heroicon::OutlinedDocument, 'gray'),
            };
        }

        $extra = $this->workflowExtraNotice();

        if ($revision->state === RevisionState::Draft) {
            return $notice(
                "Draft: version {$revision->number}",
                ($this->canChangeDraft()
                    ? (RevisionPreviewController::isAvailableFor($revision) ? 'Save your changes, preview them, then submit the draft for approval.' : 'Save your changes, then submit the draft for approval.').' Fields marked * must be filled before you submit.'
                    : 'You cannot change this draft: '.Gate::inspect('update', $revision)->message()).' '.$extra,
                Heroicon::OutlinedPencilSquare,
                'gray',
            );
        }

        if ($revision->state === RevisionState::Submitted) {
            return $notice(
                "Version {$revision->number} is waiting for approval",
                "Submitted on {$this->time($revision->submitted_at)}. It can no longer be changed. Someone who did not work on it must approve or reject it. {$extra}",
                Heroicon::OutlinedClock,
                'warning',
            );
        }

        $lastScheduleEvent = Activity::query()->inLog('content')->whereMorphedTo('subject', $revision)
            ->whereIn('event', ['revision_scheduled', 'revision_schedule_failed', 'revision_unscheduled'])
            ->latest('id')->first();

        if ($revision->scheduled_for === null && $lastScheduleEvent?->event === 'revision_schedule_failed') {
            return $notice(
                'Scheduled publication failed',
                "Version {$revision->number} was not published at its scheduled time: {$lastScheduleEvent->properties->get('reason')} It is still approved: publish it now or schedule it again. {$extra}",
                Heroicon::OutlinedExclamationTriangle,
                'warning',
            );
        }

        $schedule = $revision->scheduled_for === null
            ? 'Anyone allowed to publish, except the approver, can publish or schedule it.'
            : "Scheduled for {$this->time($revision->scheduled_for)} by {$revision->scheduler?->name}. The live {$noun} does not change until then.";

        return $notice(
            "Version {$revision->number} is approved",
            "Approved by {$revision->reviewer?->name} on {$this->time($revision->reviewed_at)}. {$schedule} {$extra}",
            Heroicon::OutlinedCheckBadge,
            'info',
        );
    }

    private function time(?CarbonInterface $time): string
    {
        return $time?->copy()->timezone(config('backoffice.timezone'))->format('j M Y, H:i T') ?? '—';
    }
}
