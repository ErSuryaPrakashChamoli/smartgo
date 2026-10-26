<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\Content\MediaUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves an image's WebP rendition, never the original (which may carry camera and location data).
 * Visitors get it only while published content uses the asset and its licence has not expired
 * (BR-MED-3); staff who may see the media library get it for editing and previews.
 */
class MediaController extends Controller
{
    public function __invoke(Request $request, MediaAsset $mediaAsset, string $rendition, MediaUsage $mediaUsage): BinaryFileResponse
    {
        $isStaff = $request->user() !== null && Gate::forUser($request->user())->allows('view', $mediaAsset);
        $isPublic = ! $mediaAsset->isLicenceExpired() && $mediaUsage->isPubliclyUsed($mediaAsset);

        abort_unless($isStaff || $isPublic, 404);

        $path = $mediaAsset->file()?->getPath($rendition);

        abort_unless($path !== null && is_file($path), 404);

        $response = response()->file($path, ['Content-Type' => 'image/webp', 'X-Content-Type-Options' => 'nosniff']);

        if ($isPublic) {
            $response->setPublic()->setMaxAge(86400);
        } else {
            $response->setPrivate();
            $response->headers->addCacheControlDirective('no-store');
        }

        return $response;
    }
}
