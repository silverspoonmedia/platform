<?php

namespace App\Domain\Identity\Http\Controllers\API\V1;

use App\Domain\Identity\Http\Requests\Lookup\LookupRequest;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\Lookup\LookupService;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Select2 lookups owned by Identity: users and roles.
 *
 * The menu lookup lives with the navigation package
 * (`/api/v1/navigation/lookups/menus`) — the kernel does not import its model.
 */
class LookupController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly LookupService $lookups,
    ) {}

    public function users(LookupRequest $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $payload = $this->lookups->users(
            $this->actor(),
            $request->searchTerm(),
            $request->page(),
            $request->perPage(),
            $request->ids(),
        );

        return $this->successResponse($payload, __('api/lookups.users_success'));
    }

    public function roles(LookupRequest $request): JsonResponse
    {
        Gate::authorize('view menu');

        $payload = $this->lookups->roles(
            $request->searchTerm(),
            $request->page(),
            $request->perPage(),
            $request->ids(),
        );

        return $this->successResponse($payload, __('api/lookups.roles_success'));
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        return $user;
    }
}
