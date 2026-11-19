<?php

namespace App\Support\Product\Http\Requests;

use App\Support\Product\Http\Requests\Concerns\ProductActivationRequestRules;
use Illuminate\Foundation\Http\FormRequest;

class SuspendProductActivationRequest extends FormRequest
{
    use ProductActivationRequestRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->optionalReasonRules();
    }
}
