<?php

namespace App\Domain\Identity\Http\Controllers\API\V1\Auth;

use App\Domain\Identity\Http\Requests\Auth\DiscordAuthorizeUrlRequest;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DiscordOAuthService;
use App\Domain\Identity\Support\DiscordOAuthState;
use App\Domain\Identity\Support\OAuthReturnUrl;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DiscordOAuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly DiscordOAuthService $discordOAuth,
    ) {}

    public function authorizeUrl(DiscordAuthorizeUrlRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();
        $validated = $request->validated();
        $returnTo = OAuthReturnUrl::fromSpaPath((string) $validated['return_path']);

        $state = DiscordOAuthState::encode(
            (string) $user->id,
            (string) $validated['intent'],
            $returnTo,
        );

        return $this->successResponse(
            ['url' => $this->discordOAuth->authorizeUrl($state)],
            __('api/identity.oauth.authorize_url'),
        );
    }
}
