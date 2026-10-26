<?php

namespace App\Actions\Content;

use App\Enums\Content\RedirectMatch;
use App\Models\Redirect;
use App\Models\Revision;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Keeps the redirects right when a page, post or case study version goes live with a new address
 * (BR-CMS-6, FR-SEO-4). The redirect is part
 * of the approved and published page version, so it needs no separate approval (interpretation of
 * OD-66). Runs inside the publishing transaction:
 *
 * 1. no redirect may start at the page's new address, because the page answers there;
 * 2. redirects that pointed at the old address now point at the new one, so every old link reaches
 *    the page in one hop and no chain or loop forms;
 * 3. the old address redirects permanently (301) to the new one.
 */
class RedirectMovedPage
{
    public function handle(Model $page, ?string $previousPath, string $newPath, Revision $revision): void
    {
        Redirect::query()->where('from_path', $newPath)->where('match_type', RedirectMatch::Exact)->lockForUpdate()->get()
            ->each(function (Redirect $redirect) use ($page, $revision): void {
                $redirect->delete();
                $this->record($redirect, $page, $revision, 'redirect_removed', 'Redirect removed: the page now uses this address');
            });

        if ($previousPath === null || $previousPath === $newPath) {
            return;
        }

        Redirect::query()->where('to_path', $previousPath)->lockForUpdate()->get()
            ->each(function (Redirect $redirect) use ($newPath, $page, $revision): void {
                $previousTarget = $redirect->to_path;
                $redirect->forceFill(['to_path' => $newPath])->save();
                $this->record($redirect, $page, $revision, 'redirect_retargeted', 'Redirect pointed at the page\'s new address', ['previous_to' => $previousTarget]);
            });

        if (! Redirect::isSafePath($previousPath) || ! Redirect::isSafePath($newPath)) {
            throw new LogicException('Page addresses must be paths on this website.');
        }

        $redirect = Redirect::query()->where('from_path', $previousPath)->where('match_type', RedirectMatch::Exact)->lockForUpdate()->first()
            ?? new Redirect(['from_path' => $previousPath, 'match_type' => RedirectMatch::Exact]);
        $replacedTarget = $redirect->exists ? $redirect->to_path : null;

        $redirect->forceFill(['to_path' => $newPath, 'status_code' => 301, 'created_by' => $revision->published_by])->save();

        $this->record($redirect, $page, $revision, 'redirect_created', 'Redirect created for the page\'s old address', $replacedTarget === null ? [] : ['replaced_to' => $replacedTarget]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function record(Redirect $redirect, Model $page, Revision $revision, string $event, string $description, array $extra = []): void
    {
        activity('content')
            ->performedOn($redirect)
            ->causedBy($revision->publisher)
            ->event($event)
            ->withProperties([
                'from' => $redirect->from_path,
                'to' => $redirect->to_path,
                'status_code' => $redirect->status_code,
                'page' => class_basename($page).' #'.$page->getKey(),
                'revision' => $revision->number,
                ...$extra,
            ])
            ->log($description);
    }
}
