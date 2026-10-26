<?php

namespace App\Filament\Clusters\Website\Concerns;

use App\Actions\Content\UnpublishContent;
use App\Models\Contracts\Unpublishable;
use App\Models\Revision;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

/**
 * The "Take off the website" button of a testimonial or customer story editor (OD-98), and what the
 * editor says once the item is taken down. Used with HasRevisionWorkflow; the server action checks
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
            ->modalDescription('It disappears from every page at once. Its content and history are kept. Any approved version waiting to be published is sent back to its authors, and any scheduled publication is cancelled. Putting it back needs a new version, approved and published as usual.')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->maxLength(UnpublishContent::MAX_REASON_LENGTH)
                    ->rows(3)
                    ->helperText('Kept in the audit log. For example: consent withdrawn by email on 26 October 2026.'),
            ])
            ->modalSubmitActionLabel('Take it off the website')
            ->action(fn (array $data) => $this->runStep(
                fn (User $user) => app(UnpublishContent::class)->handle($this->record, $user, (string) $data['reason']),
                ucfirst($this->record->contentLabel()).' taken off the website',
            ));
    }

    /**
     * When, by whom and why the item was taken down, with the warning about withdrawn consent.
     */
    protected function takeDownNotice(): string
    {
        if ($this->record->getAttribute('status') !== 'unpublished') {
            return '';
        }

        $entry = Activity::query()->inLog('content')->where('event', 'content_unpublished')
            ->where('subject_type', (new Revision)->getMorphClass())
            ->whereIn('subject_id', $this->record->revisions()->select('id'))
            ->with('causer')->latest('id')->first();
        $when = $entry?->created_at?->copy()->timezone(config('backoffice.timezone'))->format('j M Y, H:i T');

        return trim(($entry === null ? '' : "Taken off the website on {$when} by {$entry->causer?->name}. Reason: \u{201C}{$entry->properties->get('reason')}\u{201D} ")
            .'If consent was withdrawn, do not put it back until consent has been given again and recorded.');
    }

    private function liveRevision(): ?Revision
    {
        return $this->record->getAttribute('status') === 'published' ? $this->record->publishedRevision : null;
    }
}
