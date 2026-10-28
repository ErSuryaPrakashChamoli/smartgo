<?php

namespace App\Models;

use App\Enums\Twin\AnchorType;
use App\Enums\Twin\CaptureType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A 3D model of a property, prepared by the 3D-modelling supplier and uploaded as a GLB file that passed
 * the automated checks (FR-3D-1, BR-3D-4, ADR-0009). The file stays on the private disk and reaches only
 * staff who may see models, through TwinModelFileController. The file, the property and the capture type
 * never change after upload, so the notice an architectural or conceptual model carries cannot be lost
 * (BR-3D-2); a corrected model is uploaded as a new one.
 */
class TwinModel extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const string COLLECTION = 'glb';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capture_type' => CaptureType::class,
            'captured_on' => 'date',
            'file_bytes' => 'integer',
            'triangle_count' => 'integer',
            'validation_report' => 'array',
            'uploaded_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return HasMany<TwinAnchor, $this>
     */
    public function anchors(): HasMany
    {
        return $this->hasMany(TwinAnchor::class)->orderBy('anchor_type')->orderBy('node_name');
    }

    /**
     * The guided-tour camera positions, in the order of their numbers.
     *
     * @return HasMany<TwinAnchor, $this>
     */
    public function cameraAnchors(): HasMany
    {
        return $this->hasMany(TwinAnchor::class)->where('anchor_type', AnchorType::Camera)->orderByRaw('CAST(reference AS UNSIGNED)');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COLLECTION)
            ->singleFile()
            ->useDisk('local');
    }

    public function file(): ?Media
    {
        return $this->getFirstMedia(self::COLLECTION);
    }
}
