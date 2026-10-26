<?php

namespace App\Actions\Media;

use App\Models\MediaAsset;
use App\Models\User;
use App\Services\Content\MediaUsage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Deletes an asset and its files, but never one that live content or any version uses.
 */
class DeleteMediaAsset
{
    public function __construct(private MediaUsage $mediaUsage, private RecordMediaActivity $recordMediaActivity) {}

    /**
     * @throws ValidationException when the asset is in use
     */
    public function handle(MediaAsset $asset, User $actor): void
    {
        Gate::forUser($actor)->authorize('delete', $asset);

        if ($this->mediaUsage->isReferenced($asset)) {
            throw ValidationException::withMessages(['media' => 'This image is used by a page or a page version, so it cannot be deleted.']);
        }

        $this->recordMediaActivity->handle($asset, $actor, 'media_deleted', 'Image deleted from the media library');

        $asset->delete();
    }
}
