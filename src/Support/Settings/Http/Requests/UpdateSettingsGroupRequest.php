<?php

namespace App\Support\Settings\Http\Requests;

use App\Support\Settings\SettingRegistry;
use App\Support\Settings\SettingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage settings') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $group = (string) $this->route('group');
        $definitions = SettingRegistry::forGroup($group);
        $all = $this->all();
        $aliased = [];

        foreach (array_keys($definitions) as $key) {
            $field = str_replace('.', '_', $key);

            if (! array_key_exists($field, $all) && array_key_exists($key, $all)) {
                $aliased[$field] = $all[$key];
            }
        }

        if ($aliased !== []) {
            $this->merge($aliased);
        }

        foreach ($definitions as $key => $definition) {
            if ($definition['type'] !== SettingType::Array) {
                continue;
            }

            $field = str_replace('.', '_', $key);
            $raw = $this->input($field);

            if (is_string($raw)) {
                $this->merge([
                    $field => array_values(array_filter(array_map('trim', explode(',', $raw)))),
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $group = (string) $this->route('group');
        $definitions = SettingRegistry::forGroup($group);
        $rules = [];

        foreach ($definitions as $key => $definition) {
            $field = str_replace('.', '_', $key);
            $rules[$field] = $this->ruleFor($definition['type'], $key);
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsPayload(): array
    {
        $group = (string) $this->route('group');
        $definitions = SettingRegistry::forGroup($group);
        $payload = [];

        foreach ($definitions as $key => $definition) {
            $field = str_replace('.', '_', $key);

            if (! $this->has($field)) {
                continue;
            }

            $payload[$key] = $this->input($field);
        }

        return $payload;
    }

    private function ruleFor(SettingType $type, string $key): array
    {
        return match ($type) {
            SettingType::Bool => ['sometimes', 'boolean'],
            SettingType::Int => ['sometimes', 'integer'],
            SettingType::Float => ['sometimes', 'numeric'],
            SettingType::String => match ($key) {
                'platform.notifications.delivery' => ['sometimes', 'string', Rule::in(['polling', 'realtime'])],
                'platform.payment.channel_fee_bearer' => ['sometimes', 'string', Rule::in(['buyer', 'merchant'])],
                'microstock.ai.vision_image.delivery' => ['sometimes', 'string', Rule::in(['base64', 'url'])],
                default => ['sometimes', 'string', 'max:255'],
            },
            SettingType::Array => ['sometimes', 'array'],
        };
    }
}
