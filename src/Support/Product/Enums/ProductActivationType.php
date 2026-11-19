<?php

namespace App\Support\Product\Enums;

enum ProductActivationType: string
{
    case Implicit = 'implicit';
    case Explicit = 'explicit';
}
