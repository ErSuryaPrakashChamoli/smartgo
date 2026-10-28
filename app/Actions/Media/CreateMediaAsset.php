<?php

namespace App\Actions\Media;

use App\Enums\Access\Capability;
use App\Enums\Content\MediaOrigin;
use App\Models\MediaAsset;
use App\Models\Property;
use App\Models\User;
use App\Services\Content\MediaAssetRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Adds an image to the media library with its origin, licence, description and, for a photo of a
 * property, that property. The original goes to the private disk; renditions are made at once. A user
 * who manages media only for assigned properties must say which of those the photo shows.
 */
class CreateMediaAsset
{
    public function __construct(private MediaAssetRules $rules, private RecordMediaActivity $recordMediaActivity) {}

    /**
     * @param  array<string, mixed>  $attributes  details, `origin`, `depicts_property_id` and the `not_from_old_website` confirmation
     *
     * @throws ValidationException
     */
    public function handle(User $uploader, UploadedFile $file, array $attributes): MediaAsset
    {
        Gate::forUser($uploader)->authorize('create', MediaAsset::class);

        $origin = MediaOrigin::tryFrom((string) ($attributes['origin'] ?? ''));
        $isLimited = ! $uploader->hasCapabilityForEveryProperty(Capability::ManageMedia);
        $validated = Validator::make(
            [...$attributes, 'file' => $file],
            [...$this->rules->file(), ...$this->rules->details($origin), ...$this->rules->property($isLimited)],
            $this->rules->messages(),
        )->validate();

        $property = isset($validated['depicts_property_id']) ? Property::query()->find($validated['depicts_property_id']) : null;

        if ($property !== null && ! $uploader->hasCapabilityForProperty(Capability::ManageMedia, $property)) {
            throw ValidationException::withMessages(['depicts_property_id' => 'You can only add photos of the properties assigned to you.']);
        }

        $asset = new MediaAsset(collect($validated)->except(['file', 'origin', 'not_from_old_website', 'depicts_property_id'])->all());
        $asset->forceFill(['origin' => $origin, 'uploaded_by' => $uploader->getKey(), 'depicts_property_id' => $property?->getKey()]);
        $asset->is_decorative = (bool) ($validated['is_decorative'] ?? false);
        $asset->save();

        [$width, $height] = getimagesize($file->getRealPath()) ?: [null, null];
        $mimeType = $file->getMimeType();
        $sizeBytes = $file->getSize();

        try {
            $asset->addMedia($file)
                ->usingFileName(str($asset->title)->slug()->limit(60, '')->append('.', $file->guessExtension() ?? 'jpg')->toString())
                ->withCustomProperties(['width' => $width, 'height' => $height])
                ->toMediaCollection(MediaAsset::COLLECTION);
        } catch (Throwable $exception) {
            $asset->delete();

            throw ValidationException::withMessages(['file' => 'The image could not be stored. Try another file.']);
        }

        $this->recordMediaActivity->handle($asset, $uploader, 'media_uploaded', 'Image added to the media library', [
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'confirmed_not_from_old_website' => true,
            'property' => $property?->code,
        ]);

        return $asset;
    }
}
