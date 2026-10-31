<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Back-office security settings: one row (OD-124). Changed only through ChangeTwoStepLoginRequirement,
 * which audits every change.
 */
class SecuritySetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'two_step_login_required' => 'boolean',
        ];
    }

    /**
     * Whether staff must set up two-step login before using the back office. Outside local development
     * and the tests it is always required, whatever the switch says, so a site never goes live without
     * it (OD-124). A local database that does not have the setting yet keeps the default: optional.
     */
    public static function requiresTwoStepLogin(): bool
    {
        if (self::isTwoStepLoginAlwaysRequired()) {
            return true;
        }

        if (! self::isAvailable()) {
            return false;
        }

        return (bool) self::query()->value('two_step_login_required');
    }

    /**
     * Whether this database has the setting: a database not migrated for it keeps the default (OD-120, OD-150).
     */
    public static function isAvailable(): bool
    {
        return Schema::hasTable((new self)->getTable());
    }

    /**
     * Only local development and the tests may make two-step login optional (OD-124).
     */
    public static function isTwoStepLoginAlwaysRequired(): bool
    {
        return ! app()->environment('local', 'testing');
    }
}
