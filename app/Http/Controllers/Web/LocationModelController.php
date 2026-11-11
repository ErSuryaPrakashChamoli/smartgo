<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TwinModel;
use App\Services\Website\LocationExplore;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sends the GLB file of a location's 3D walkthrough to its page's viewer, which asks for it only when the visitor
 * presses the button (BR-3D-6). A model is public only while a published website profile of a listed location uses
 * it (OD-181); any other model, including one only in a draft or a staff preview, answers 404 here. The file is
 * never sniffed as another type.
 */
class LocationModelController extends Controller
{
    public function __invoke(TwinModel $twinModel, LocationExplore $explore): BinaryFileResponse
    {
        abort_unless($explore->isPubliclyShown($twinModel), 404);

        $path = $twinModel->file()?->getPath();

        abort_unless($path !== null && is_file($path), 404);

        $response = new BinaryFileResponse($path, 200, ['Content-Type' => 'model/gltf-binary', 'X-Content-Type-Options' => 'nosniff'], public: true, autoEtag: true, autoLastModified: true);

        return $response->setMaxAge(3600);
    }
}
