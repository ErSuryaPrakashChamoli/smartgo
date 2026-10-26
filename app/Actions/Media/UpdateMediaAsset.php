<?php

namespace App\Actions\Media;

use App\Models\MediaAsset;
use App\Models\User;
use App\Services\Content\MediaAssetRules;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Changes an asset's description and licence details. The file and its origin never change; a new
 * licence expiry date allows a new reminder.
 */
class UpdateMediaAsset
{
    public function __construct(private MediaAssetRules $rules, private RecordMediaActivity $recordMediaActivity) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function handle(MediaAsset $asset, User $editor, array $attributes): MediaAsset
    {
        Gate::forUser($editor)->authorize('update', $asset);

        $validated = Validator::make($attributes, $this->rules->details($asset->origin), $this->rules->messages())->validate();

        $asset->fill($validated);
        $asset->is_decorative = (bool) ($validated['is_decorative'] ?? false);

        if ($asset->isDirty('licence_expires_at')) {
            $asset->licence_reminder_sent_at = null;
        }

        $changed = array_keys($asset->getDirty());
        $asset->save();

        $this->recordMediaActivity->handle($asset, $editor, 'media_updated', 'Media details changed', ['changed' => $changed]);

        return $asset;
    }
}
