<?php

namespace App\Support\Settings\Contracts;

use App\Support\Settings\SettingType;

interface SettingContributor
{
    /**
     * Registry definitions this contributor owns, keyed by registry key.
     *
     * @return array<string, array{group: string, type: SettingType, config: string, options?: list<string>, rules?: list<mixed>}>
     */
    public function definitions(): array;
}
