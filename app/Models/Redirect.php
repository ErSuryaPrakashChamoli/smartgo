<?php

namespace App\Models;

use App\Enums\Content\RedirectMatch;
use Database\Factories\RedirectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A live redirect (DATABASE_ERD §7). Only approved changes reach this table: a published page's
 * change of address (BR-CMS-6, source `automatic`), and redirects made by hand after their own
 * approval and publication (OD-66, BR-CMS-9, source `manual`). Targets are always paths on this website.
 */
#[Fillable(['from_path', 'match_type', 'to_path', 'status_code'])]
class Redirect extends Model
{
    /** @use HasFactory<RedirectFactory> */
    use HasFactory;

    public const string SOURCE_AUTOMATIC = 'automatic';

    public const string SOURCE_MANUAL = 'manual';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'match_type' => RedirectMatch::class,
            'status_code' => 'integer',
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
            'created_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The redirect that answers a requested path: an exact match first, then the longest prefix.
     */
    public static function findFor(string $path): ?self
    {
        return static::query()->where('match_type', RedirectMatch::Exact)->where('from_path', $path)->first()
            ?? static::query()->where('match_type', RedirectMatch::Prefix)->get()
                ->filter(fn (self $redirect): bool => str_starts_with($path, $redirect->from_path))
                ->sortByDesc(fn (self $redirect): int => strlen($redirect->from_path))
                ->first();
    }

    /**
     * A path on this website: one leading slash, no scheme, host, backslash, whitespace or control
     * character, so a redirect can never send a visitor to another website.
     */
    public static function isSafePath(?string $path): bool
    {
        return is_string($path)
            && str_starts_with($path, '/')
            && ! str_starts_with($path, '//')
            && ! preg_match('/[\x00-\x20\x7F\\\\]/', $path)
            && ! str_contains($path, '://');
    }

    /**
     * Whether the stored row can be served: 410 needs no target, 301 and 302 need a safe one that
     * differs from where the redirect starts.
     */
    public function isServable(): bool
    {
        return match ($this->status_code) {
            410 => true,
            301, 302 => self::isSafePath($this->to_path) && $this->to_path !== $this->from_path,
            default => false,
        };
    }

    public function recordHit(): void
    {
        static::query()->whereKey($this->getKey())->toBase()->increment('hits', 1, ['last_hit_at' => now()]);
    }
}
