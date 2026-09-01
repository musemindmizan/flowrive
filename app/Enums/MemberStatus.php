<?php

namespace App\Enums;

/**
 * Lives on the membership, not the user — someone can be Active in one
 * workspace and Invited to another.
 */
enum MemberStatus: string
{
    case Active = 'active';
    case Invited = 'invited';
    case Deactivated = 'deactivated';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Invited => 'Invited',
            self::Deactivated => 'Deactivated',
        };
    }

    /** Badge tone used by the Team page. */
    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Invited => 'warning',
            self::Deactivated => 'neutral',
        };
    }
}
