<?php

namespace App\Support\Settings;

enum SettingType: string
{
    case Bool = 'bool';
    case Int = 'int';
    case Float = 'float';
    case String = 'string';
    case Array = 'array';

    public function cast(mixed $value): mixed
    {
        return match ($this) {
            self::Bool => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $value,
            self::Int => (int) $value,
            self::Float => (float) $value,
            self::String => (string) $value,
            self::Array => is_array($value) ? array_values($value) : [],
        };
    }
}
