<?php

namespace App\Support\Localization\Concerns;

use App\Support\Localization\LocalePreference;
use Illuminate\Validation\Validator;

trait ValidatesTranslatableInput
{
    /**
     * Validate a field that accepts either a plain string (current locale) or
     * a locale-keyed array for Spatie translatable models.
     *
     * Locale keys are validated through `LocalePreference::normalize()` so SPA
     * aliases (`zh-CN`) are accepted alongside canonical backend codes
     * (`zh_CN`); the request map is then rewritten in place with canonical
     * keys, so `validated()` data and persistence never see alias keys.
     *
     * @param  list<string>  $supportedLocales
     */
    protected function validateTranslatableString(
        Validator $validator,
        string $field,
        array $supportedLocales,
        int $maxLength = 255,
    ): void {
        $value = $this->input($field);

        if ($value === null) {
            return;
        }

        if (is_string($value)) {
            if (mb_strlen($value) > $maxLength) {
                $validator->errors()->add($field, __('validation.max.string', [
                    'attribute' => $field,
                    'max' => $maxLength,
                ]));
            }

            return;
        }

        if (! is_array($value)) {
            $validator->errors()->add($field, __('validation.array', ['attribute' => $field]));

            return;
        }

        $changed = false;

        foreach ($value as $locale => $text) {
            if (! is_string($locale) || LocalePreference::normalize($locale) === null) {
                $validator->errors()->add($field, __('validation.in', ['attribute' => "{$field}.{$locale}"]));

                continue;
            }

            if (! is_string($text)) {
                $validator->errors()->add($field, __('validation.string', ['attribute' => "{$field}.{$locale}"]));

                continue;
            }

            if (mb_strlen($text) > $maxLength) {
                $validator->errors()->add($field, __('validation.max.string', [
                    'attribute' => "{$field}.{$locale}",
                    'max' => $maxLength,
                ]));
            }

            if (LocalePreference::normalize($locale) !== $locale) {
                $changed = true;
            }
        }

        // Rewrite the request payload with canonical keys so validated() data
        // (and persistence) never see alias keys. Iterate in original order so
        // the LAST occurrence wins the merge — an SPA edit sent as the `zh-CN`
        // alias overrides a stale canonical `zh_CN` prefill value. The
        // validator's data snapshot and the request input source are both
        // updated: the former feeds `validated()`, the latter feeds
        // `validateTranslatableAllLocales()` and the controller's request.
        if ($changed) {
            $canonicalized = [];

            foreach ($value as $locale => $text) {
                if (! is_string($locale) || ! is_string($text)) {
                    continue;
                }

                $canonical = LocalePreference::normalize($locale);

                if ($canonical !== null) {
                    $canonicalized[$canonical] = $text;
                }
            }

            $this->getInputSource()->set($field, $canonicalized);
            $validator->setData(array_merge($validator->getData(), [$field => $canonicalized]));
        }
    }

    /**
     * When the client sends a locale map, require a non-empty value for every
     * supported locale. Plain strings (API shorthand for the active locale) and
     * empty maps (an optional field left unset) skip this check.
     *
     * @param  list<string>  $supportedLocales
     */
    protected function validateTranslatableAllLocales(
        Validator $validator,
        string $field,
        array $supportedLocales,
    ): void {
        $value = $this->input($field);

        // An empty map means "clear / leave unset" for an optional field; it
        // is not a request to fill every locale.
        if ($value === []) {
            return;
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($supportedLocales as $locale) {
            $text = $value[$locale] ?? null;

            if (! is_string($text) || trim($text) === '') {
                $validator->errors()->add(
                    "{$field}.{$locale}",
                    __('validation.required', ['attribute' => "{$field}.{$locale}"]),
                );
            }
        }
    }
}
