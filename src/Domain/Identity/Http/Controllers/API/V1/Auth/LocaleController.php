<?php

namespace App\Domain\Identity\Http\Controllers\API\V1\Auth;

use App\Domain\Identity\Http\Requests\Auth\UpdateLocaleRequest;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Support\Localization\LocalePreference;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LocaleController extends Controller
{
    use ApiResponse;

    public function update(UpdateLocaleRequest $request): JsonResponse
    {
        $locale = LocalePreference::normalize((string) $request->validated('locale'));
        if ($locale === null) {
            abort(422, __('validation.in', ['attribute' => 'locale']));
        }
        $user = Auth::guard('api')->user();
        LocalePreference::apply($locale, $user instanceof User ? $user : null);

        return $this->successResponse(['locale' => $locale], __('api/common.updated'));
    }
}
