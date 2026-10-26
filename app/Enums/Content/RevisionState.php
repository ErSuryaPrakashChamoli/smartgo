<?php

namespace App\Enums\Content;

/**
 * Where a revision is in the publishing workflow (BR-CMS-1, ADR-0005): Draft → Submitted → Approved →
 * Published, or Rejected at review. A published revision becomes Superseded when another one is published.
 */
enum RevisionState: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Published = 'published';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted for approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Published => 'Published',
            self::Superseded => 'Previously published',
        };
    }

    /**
     * The Filament colour used for the state's badge.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'warning',
            self::Approved => 'info',
            self::Rejected => 'danger',
            self::Published => 'success',
            self::Superseded => 'gray',
        };
    }

    /**
     * States in which the revision is still being worked on; an item has at most one such revision.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Draft, self::Submitted, self::Approved];
    }
}
