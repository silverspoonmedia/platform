<?php

namespace App\Domain\Identity\Enums;

enum DomainKind: string
{
    case Core = 'core';
    case Product = 'product';
}
