<?php

namespace App\Support\Product\Enums;

enum ProductActivationState: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Expired = 'expired';

    public function isUsable(): bool
    {
        return $this === self::Active;
    }

    public function canSuspend(): bool
    {
        return $this === self::Active;
    }

    public function canReactivate(): bool
    {
        return $this === self::Suspended;
    }

    public function canApprove(): bool
    {
        return $this === self::Pending;
    }

    public function canExpire(): bool
    {
        return in_array($this, [self::Pending, self::Active, self::Suspended], true);
    }
}
