<?php

namespace App\Models;

use App\Enums\Access\Capability;
use App\Models\Concerns\HasRevisions;
use App\Models\Contracts\Revisionable;
use App\Services\Content\RedirectMapPayload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * The redirects made by hand, including the launch map and the `/pin/` rules (FR-SEO-4, FR-SEO-6).
 * The whole list is one piece of content: a version is approved and published by different people
 * (OD-66, BR-CMS-9), and publishing makes the `manual` rows of `redirects` match it. A page's
 * automatic redirects (BR-CMS-6) are not part of it.
 */
class RedirectMap extends Model implements Revisionable
{
    use HasRevisions;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Revision, $this>
     */
    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(Revision::class, 'published_revision_id');
    }

    public function isPublished(): bool
    {
        return $this->published_revision_id !== null;
    }

    public function revisionCapabilities(): array
    {
        return ['view' => Capability::ManageRedirects, 'edit' => Capability::ManageRedirects, 'approve' => Capability::ApproveRedirects, 'publish' => Capability::PublishRedirects];
    }

    /**
     * The live manual redirects, including targets a page's change of address has since updated.
     */
    public function toRevisionPayload(): array
    {
        return ['redirects' => Redirect::query()->where('source', Redirect::SOURCE_MANUAL)->orderBy('from_path')->get()
            ->map(fn (Redirect $redirect): array => [
                'from_path' => $redirect->from_path,
                'match_type' => $redirect->match_type->value,
                'status_code' => $redirect->status_code,
                'to_path' => $redirect->to_path,
            ])->all()];
    }

    public function prepareRevisionPayload(array $payload, bool $forSubmission): array
    {
        return app(RedirectMapPayload::class)->validate($this, $payload, $forSubmission);
    }

    /**
     * Makes the live manual redirects match the version, inside the publishing transaction: unchanged
     * ones keep their hit counts; every change is audited.
     *
     * @throws ValidationException when a page took one of the addresses meanwhile
     */
    public function applyPublishedRevision(Revision $revision): void
    {
        $key = fn (string $matchType, string $fromPath): string => $matchType.' '.$fromPath;
        $live = Redirect::query()->where('source', Redirect::SOURCE_MANUAL)->lockForUpdate()->get()
            ->keyBy(fn (Redirect $redirect): string => $key($redirect->match_type->value, $redirect->from_path));
        $wanted = collect($revision->payload['redirects'] ?? [])->keyBy(fn (array $row): string => $key($row['match_type'], $row['from_path']));

        $live->diffKeys($wanted)->each(function (Redirect $redirect) use ($revision): void {
            $redirect->delete();
            $this->record($redirect, $revision, 'redirect_removed', 'Redirect removed from the manual list');
        });

        try {
            $wanted->each(function (array $row, string $rowKey) use ($live, $revision): void {
                $redirect = $live->get($rowKey) ?? new Redirect;
                $redirect->forceFill([...$row, 'source' => Redirect::SOURCE_MANUAL]);

                if (! $redirect->exists) {
                    $redirect->forceFill(['created_by' => $revision->published_by])->save();
                    $this->record($redirect, $revision, 'redirect_created', 'Redirect added from the manual list');
                } elseif ($redirect->isDirty()) {
                    $previous = $redirect->getOriginal();
                    $redirect->save();
                    $this->record($redirect, $revision, 'redirect_changed', 'Redirect changed from the manual list', ['previous_to' => $previous['to_path'], 'previous_status_code' => $previous['status_code']]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['redirects' => 'An automatic redirect now starts at one of these addresses. Check the list again.']);
        }

        $this->forceFill(['published_revision_id' => $revision->getKey(), 'published_at' => $revision->published_at])->save();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function record(Redirect $redirect, Revision $revision, string $event, string $description, array $extra = []): void
    {
        activity('content')
            ->performedOn($redirect)
            ->causedBy($revision->publisher)
            ->event($event)
            ->withProperties([
                'from' => $redirect->from_path,
                'match' => $redirect->match_type->value,
                'to' => $redirect->to_path,
                'status_code' => $redirect->status_code,
                'list_version' => $revision->number,
                ...$extra,
            ])
            ->log($description);
    }
}
