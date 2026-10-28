<?php

namespace App\Filament\Clusters\Website\Concerns;

use App\Actions\Content\UnpublishContent;
use App\Models\Contracts\RequiresConsent;
use App\Models\Contracts\Unpublishable;
use App\Models\Revision;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

/**
 * The "Take off the website" button of a testimonial, customer story, property profile or workspace
 * offering editor (OD-98), and what the editor says once the item is taken down. The consent question
 * is asked only for content that needs consent. Used with HasRevisionWorkflow; the server action checks
 * permission and state again under a lock, so hiding or disabling the button is only a convenience.
 *
 * @property Unpublishable $record
 */
trait TakesContentDown
{
    protected function takeDownAction(): Action
    {
        return Action::make('takeDown')
            ->label('Take off the website')
            ->icon(Heroicon::OutlinedEyeSlash)
            ->color('danger')
            ->visible(fn (): bool => $this->record->getAttribute('status') === 'published')
            ->disabled(fn (): bool => $this->liveRevision() === null || Gate::denies('unpublish', $this->liveRevision()))
            ->tooltip(fn (): ?string => $this->liveRevision() === null ? null : Gate::inspect('unpublish', $this->liveRevision())->message())
            ->modalHeading(fn (): string => 'Take the '.$this->record->contentLabel().' off the website')
            ->modalDescription('It disappears from every page at once. Its content and history are kept. Any version waiting for approval or publication is sent back to its authors, and any scheduled publication is cancelled. Putting it back needs a new version, approved and published as usual.')
            ->schema([
                Radio::make('consent_withdrawn')
                    ->label('Was consent withdrawn?')
                    ->options([
                        '1' => 'Yes: the person or customer withdrew consent',
                        '0' => 'No: it is taken down for another reason',
                    ])
                    ->required()
                    ->visible(fn (): bool => $this->record instanceof RequiresConsent)
                    ->helperText('If yes, a draft in progress is sent back too, and it can only go back on the website with new consent, recorded under a new reference and confirmed.'),
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->maxLength(UnpublishContent::MAX_REASON_LENGTH)
                    ->rows(3)
                    ->helperText('Kept in the audit log, for example why it must not be shown any more.'),
            ])
            ->modalSubmitActionLabel('Take it off the website')
            ->action(fn (array $data) => $this->runStep(
                fn (User $user) => app(UnpublishContent::class)->handle($this->record, $user, (string) $data['reason'], (bool) ($data['consent_withdrawn'] ?? false)),
                ucfirst($this->record->contentLabel()).' taken off the website',
            ));
    }

    /**
     * When, by whom and why the item was taken down, and what withdrawn consent means for putting it back.
     */
    protected function takeDownNotice(): string
    {
        $consent = $this->record instanceof RequiresConsent && $this->record->consentWithdrawn()
            ? 'Consent was withdrawn on '.$this->record->consent_withdrawn_at->copy()->timezone(config('backoffice.timezone'))->format('j M Y, H:i T')
                .' by '.$this->record->consentWithdrawnBy?->name.'. A new version can only be submitted with new consent: tick consent, enter the new reference and confirm that consent was given again.'
            : '';

        if ($this->record->getAttribute('status') !== 'unpublished') {
            return $consent;
        }

        $entry = Activity::query()->inLog('content')->where('event', 'content_unpublished')
            ->where('subject_type', (new Revision)->getMorphClass())
            ->whereIn('subject_id', $this->record->revisions()->select('id'))
            ->with('causer')->latest('id')->first();
        $when = $entry?->created_at?->copy()->timezone(config('backoffice.timezone'))->format('j M Y, H:i T');

        return trim(($entry === null ? '' : "Taken off the website on {$when} by {$entry->causer?->name}. Reason: \u{201C}{$entry->properties->get('reason')}\u{201D} ").$consent);
    }

    private function liveRevision(): ?Revision
    {
        return $this->record->getAttribute('status') === 'published' ? $this->record->publishedRevision : null;
    }
}
