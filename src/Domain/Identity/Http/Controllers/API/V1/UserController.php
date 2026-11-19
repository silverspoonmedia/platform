<?php

namespace App\Domain\Identity\Http\Controllers\API\V1;

use App\Domain\Identity\Exceptions\CannotDeleteSelfException;
use App\Domain\Identity\Http\Requests\IndexUserRequest;
use App\Domain\Identity\Http\Requests\StoreUserRequest;
use App\Domain\Identity\Http\Requests\UpdateUserRequest;
use App\Domain\Identity\Http\Resources\UserManagementResource;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\Rbac\RoleService;
use App\Domain\Identity\Services\User\UserManagementService;
use App\Http\Controllers\Concerns\RespondsWithListDisplayMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\ListActions\Builders\UserQueryRowActions;
use App\Support\ListActions\QueryRowActionAttacher;
use App\Support\ListQueryPaginator;
use App\Support\User\UserManagementPresenter;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;
    use RespondsWithListDisplayMode;

    public function __construct(
        private readonly UserManagementService $userManagement,
        private readonly RoleService $roleService,
        private readonly UserQueryRowActions $queryRowActions,
    ) {}

    public function index(IndexUserRequest $request): JsonResponse
    {
        $actor = $this->actor();
        $this->authorize('viewAny', User::class);

        if ($request->wantsQueryPaging() && ! $request->filled('search')) {
            $paginator = $this->userManagement->paginate(
                $actor,
                ListQueryPaginator::perPage($request),
                max(1, (int) $request->input('page', 1)),
            );
            $page = $paginator->getCollection();

            return $this->successResponse([
                'items' => QueryRowActionAttacher::attach(
                    $this->resolveResource(UserManagementResource::collection($page)),
                    $page,
                    $this->queryRowActions,
                    $actor,
                ),
                'pagination' => ListQueryPaginator::meta($paginator),
            ], __('api/users.list_success'));
        }

        $users = $this->userManagement->list($actor);

        return $this->respondToQueryList(
            $request,
            $users,
            fn ($list) => QueryRowActionAttacher::attach(
                $this->resolveResource(UserManagementResource::collection($list)),
                $list,
                $this->queryRowActions,
                $actor,
            ),
            __('api/users.list_success'),
        );
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $user->load('roles');

        return $this->successResponse(
            $this->resolveResource(new UserManagementResource($user)),
            __('api/users.show_success'),
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $actor = $this->actor();
        $this->authorize('create', User::class);

        $user = $this->userManagement->create($actor, $request->validated());

        return $this->successResponse(
            UserManagementPresenter::present($user),
            __('api/users.created'),
            201,
        );
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $updated = $this->userManagement->update($this->actor(), $user->id, $request->validated());

        return $this->successResponse(
            UserManagementPresenter::present($updated),
            __('api/users.updated'),
        );
    }

    public function destroy(User $user): JsonResponse
    {
        if ($this->actor()->id === $user->id) {
            throw new CannotDeleteSelfException;
        }

        $this->authorize('delete', $user);

        $this->userManagement->delete($this->actor(), $user->id);

        return $this->successResponse(null, __('api/users.deleted'));
    }

    public function assignableRoles(): JsonResponse
    {
        $actor = $this->actor();
        $this->authorize('assignableRoles', User::class);

        $roles = $this->roleService->getAssignableRoles($actor);

        return $this->successResponse(
            UserManagementPresenter::presentAssignableRoles($roles),
            __('api/users.assignable_roles_success'),
        );
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        return $user;
    }
}
