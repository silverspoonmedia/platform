<?php

namespace App\Support\Settings\Contracts;

interface SettingContributor
{
    /**
     * Registry definitions this contributor owns, keyed by registry key.
     *
     * @return array<string, array{group: string, type: \App\Support\Settings\SettingType, config: string, options?: list<string>, rules?: list<mixed>}>
     */
    public function definitions(): array;
}
