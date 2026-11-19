<?php

namespace App\Support\DomainAccess\Enums;

enum DomainVisibility: string
{
    case Public = 'public';
    case Private = 'private';

    public function isPublic(): bool
    {
        return $this === self::Public;
    }

    public function isPrivate(): bool
    {
        return $this === self::Private;
    }
}
