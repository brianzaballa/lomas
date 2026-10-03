<?php

namespace App\Http\Controllers\Administration;

use App\Actions\Users\CreateUser;
use App\Actions\Users\UpdateUser;
use App\Domain\Access\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\StoreUserRequest;
use App\Http\Requests\Administration\UpdateUserRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * List the users of the system with their assigned roles.
     */
    public function index(): Response
    {
        $this->authorize(Permission::USERS_MANAGE);

        $users = User::query()
            ->with('roles')
            ->when(request('search'), function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->values()->all(),
                'created_at' => $user->created_at?->toDateString(),
            ]);

        return Inertia::render('administration/Users/Index', [
            'users' => $users,
            'filters' => ['search' => request('search')],
        ]);
    }

    /**
     * Show the form for creating a user.
     */
    public function create(): Response
    {
        $this->authorize(Permission::USERS_MANAGE);

        return Inertia::render('administration/Users/Create', [
            'roles' => $this->roles(),
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request, CreateUser $createUser): RedirectResponse
    {
        $user = $createUser->handle([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
        ], $request->validated('roles'));

        $this->success("User [{$user->name}] created.");

        return to_route('users.index');
    }

    /**
     * Show the form for editing a user.
     */
    public function edit(User $user): Response
    {
        $this->authorize(Permission::USERS_MANAGE);

        $user->load('roles');

        return Inertia::render('administration/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->values()->all(),
                'created_at' => $user->created_at?->toDateString(),
            ],
            'roles' => $this->roles(),
        ]);
    }

    /**
     * Update an existing user.
     */
    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): RedirectResponse
    {
        $updateUser->handle($user, [
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
        ], $request->validated('roles'));

        $this->success("User [{$user->name}] updated.");

        return to_route('users.index');
    }

    /**
     * @return list<array{name: string}>
     */
    protected function roles(): array
    {
        return array_values(
            Role::query()
                ->orderBy('name')
                ->pluck('name')
                ->map(fn (string $name): array => ['name' => $name])
                ->all(),
        );
    }
}
