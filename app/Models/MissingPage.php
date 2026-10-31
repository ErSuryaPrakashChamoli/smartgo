<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * An address on the website that answered "not found" (FR-SEO-4's 404 monitor), so staff can see what
 * visitors and links ask for and add a redirect where one belongs. Only the path, without its query, the
 * number of times and when: nothing about who asked. Recorded by RecordMissingPage.
 */
class MissingPage extends Model
{
    public $timestamps = false;

    /**
     * Distinct addresses kept at most; once reached, only those already listed are counted, so scanners
     * cannot fill the table.
     */
    public const int MAX_PATHS = 5000;

    /**
     * Whether this database has the table; the owner's server does not (OD-120).
     */
    public static function isAvailable(): bool
    {
        return once(fn (): bool => Schema::hasTable('missing_pages'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['hits' => 'integer', 'first_seen_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }
}
