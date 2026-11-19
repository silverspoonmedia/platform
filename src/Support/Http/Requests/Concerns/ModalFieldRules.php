<?php

namespace App\Support\Http\Requests\Concerns;

/**
 * Build store vs update validation rules from a shared field map.
 *
 * Each field value is the rule list *after* the modality keyword:
 * store prepends {@code required}, update prepends {@code sometimes}.
 * Fields that must stay nullable-only (no required/sometimes) use a leading
 * {@code null} modality sentinel — pass {@see optional()} for those.
 */
trait ModalFieldRules
{
    /**
     * @param  array<string, list<mixed>>  $fields  field => rules after modality
     * @return array<string, list<mixed>>
     */
    protected function storeRules(array $fields): array
    {
        return $this->withModality($fields, 'required');
    }

    /**
     * @param  array<string, list<mixed>>  $fields  field => rules after modality
     * @return array<string, list<mixed>>
     */
    protected function updateRules(array $fields): array
    {
        return $this->withModality($fields, 'sometimes');
    }

    /**
     * Rules with no store/update modality (nullable, sometimes, etc. as given).
     *
     * @param  list<mixed>  $rules
     * @return list<mixed>
     */
    protected function optional(array $rules): array
    {
        return $rules;
    }

    /**
     * @param  array<string, list<mixed>>  $fields
     * @return array<string, list<mixed>>
     */
    private function withModality(array $fields, string $modality): array
    {
        $out = [];

        foreach ($fields as $field => $rules) {
            // Sentinel: first element null means "use rules as-is"
            if ($rules !== [] && $rules[0] === null) {
                $out[$field] = array_values(array_slice($rules, 1));

                continue;
            }

            $out[$field] = array_merge([$modality], $rules);
        }

        return $out;
    }
}
