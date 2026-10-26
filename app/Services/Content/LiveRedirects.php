<?php

namespace App\Services\Content;

use App\Models\Redirect;
use Illuminate\Http\RedirectResponse;

/**
 * Answers a public path that has no published content: a live redirect (301/302 to a path on this
 * site, or 410), otherwise 404.
 */
class LiveRedirects
{
    public function respond(string $path): RedirectResponse
    {
        $redirect = Redirect::findFor($path);

        abort_if($redirect === null || ! $redirect->isServable(), 404);

        $redirect->recordHit();

        abort_if($redirect->status_code === 410, 410);

        return redirect()->to((string) $redirect->to_path, $redirect->status_code);
    }
}
