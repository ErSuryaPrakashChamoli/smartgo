<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\TwinModel;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sends a 3D model's GLB file to the back-office viewer, and only when the viewer asks for it (BR-3D-6).
 * The route sits inside the back office, so it also needs a signed-in staff member with two-step login,
 * back-office access and permission to see models. The file is never cached or sniffed as another type.
 */
class TwinModelFileController extends Controller
{
    public const string ROUTE = 'filament.admin.twin-models.file';

    public function __invoke(TwinModel $twinModel): BinaryFileResponse
    {
        Gate::authorize('view', $twinModel);

        $file = $twinModel->file();

        abort_if($file === null || ! is_file($file->getPath()), 404);

        // Not response()->file(): that marks the response public, which would override "private".
        return new BinaryFileResponse($file->getPath(), 200, [
            'Content-Type' => 'model/gltf-binary',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], public: false);
    }
}
