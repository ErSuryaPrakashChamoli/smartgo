<?php

namespace App\Models;

use App\Enums\Content\MediaOrigin;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One image in the media library (ADR-0006). The original is kept on the private disk and is never
 * served; the website gets WebP renditions through MediaController, which hides an asset until
 * published content uses it and once its licence has expired (BR-MED-3). The file and the origin
 * cannot change after upload, so a "Concept visual" label can never silently disappear (BR-MED-2).
 */
#[Fillable(['title', 'alt_text', 'is_decorative', 'caption', 'focal_point_x', 'focal_point_y', 'licence_type', 'licence_holder', 'licence_reference', 'licence_expires_at', 'credit_line', 'usage_restrictions'])]
class MediaAsset extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const string COLLECTION = 'file';

    /**
     * Rendition name => width in pixels.
     *
     * @var array<string, int>
     */
    public const array RENDITIONS = ['w480' => 480, 'w960' => 960, 'w1600' => 1600];

    /**
     * @var list<string>
     */
    public const array ACCEPTED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin' => MediaOrigin::class,
            'is_decorative' => 'boolean',
            'focal_point_x' => 'float',
            'focal_point_y' => 'float',
            'licence_expires_at' => 'date',
            'licence_reminder_sent_at' => 'datetime',
            'uploaded_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COLLECTION)
            ->singleFile()
            ->useDisk('local')
            ->acceptsMimeTypes(self::ACCEPTED_MIME_TYPES);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        foreach (self::RENDITIONS as $name => $width) {
            $this->addMediaConversion($name)
                ->fit(Fit::Max, $width, $width * 2)
                ->format('webp')
                ->quality(80)
                ->nonQueued();
        }
    }

    public function file(): ?Media
    {
        return $this->getFirstMedia(self::COLLECTION);
    }

    public function isLicenceExpired(): bool
    {
        return $this->licence_expires_at !== null && $this->licence_expires_at->isPast() && ! $this->licence_expires_at->isToday();
    }

    /**
     * The address of a rendition, through the access-checked media route.
     */
    public function renditionPath(string $rendition = 'w960'): string
    {
        return route('media.show', ['mediaAsset' => $this, 'rendition' => $rendition], absolute: false);
    }

    /**
     * @return array{width: int, height: int}|null the original's size, recorded at upload
     */
    public function dimensions(): ?array
    {
        $file = $this->file();
        $width = $file?->getCustomProperty('width');
        $height = $file?->getCustomProperty('height');

        return is_int($width) && is_int($height) ? ['width' => $width, 'height' => $height] : null;
    }
}
