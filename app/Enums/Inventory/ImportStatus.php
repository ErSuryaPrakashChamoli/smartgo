<?php

namespace App\Enums\Inventory;

/**
 * Where an inventory import stands (INVENTORY_IMPORT_SPEC §5). Validation runs as the file is uploaded
 * (and again on request), so `uploaded` and `validating` last only within that request; `approved` and
 * `committing` only within the commit's transaction.
 */
enum ImportStatus: string
{
    case Uploaded = 'uploaded';
    case Validating = 'validating';
    case Invalid = 'invalid';
    case Valid = 'valid';
    case AwaitingApproval = 'awaiting_approval';
    case Approved = 'approved';
    case Committing = 'committing';
    case Committed = 'committed';
    case Rejected = 'rejected';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Uploaded',
            self::Validating => 'Being checked',
            self::Invalid => 'Has errors',
            self::Valid => 'Checked, not submitted',
            self::AwaitingApproval => 'Awaiting approval',
            self::Approved => 'Approved',
            self::Committing => 'Being imported',
            self::Committed => 'Imported',
            self::Rejected => 'Rejected',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Committed => 'success',
            self::Invalid, self::Failed, self::Rejected => 'danger',
            self::AwaitingApproval => 'warning',
            default => 'gray',
        };
    }
}
