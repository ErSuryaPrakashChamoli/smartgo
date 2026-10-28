<?php

namespace App\Models\Contracts;

use App\Models\Property;

/**
 * Content that belongs to one property, so a user whose role reaches only assigned properties may work
 * on it only for those (SECURITY_MODEL §3.1 "Own properties"). RevisionPolicy checks it.
 */
interface BelongsToProperty
{
    public function owningProperty(): Property;
}
