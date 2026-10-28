<?php

namespace App\Enums\Crm;

/**
 * What staff record on an enquiry (DATABASE_ERD §8 `activities`). A follow-up has a due date and is
 * completed once; the others record something that happened.
 */
enum LeadActivityType: string
{
    case Note = 'note';
    case Call = 'call';
    case Email = 'email';
    case WhatsApp = 'whatsapp';
    case Meeting = 'meeting';
    case FollowUp = 'follow_up';

    public function label(): string
    {
        return match ($this) {
            self::Note => 'Note',
            self::Call => 'Call',
            self::Email => 'Email',
            self::WhatsApp => 'WhatsApp',
            self::Meeting => 'Meeting',
            self::FollowUp => 'Follow-up',
        };
    }

    /**
     * Whether recording it counts as responding to the visitor.
     */
    public function isContact(): bool
    {
        return in_array($this, [self::Call, self::Email, self::WhatsApp, self::Meeting], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])->all();
    }
}
