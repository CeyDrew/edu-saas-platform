<?php

namespace App\Http\Controllers\Api\Brand;

use App\Domain\Identity\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Brand\StoreMemberRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Members (staff and students) of the current brand.
 */
class MemberController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $request->validate([
            'role' => ['nullable', Rule::in(Role::values())],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        // Instructors only ever see students.
        $role = $actor->hasRole(Role::BrandAdmin->value) ? $request->string('role')->toString() : Role::Student->value;

        $members = User::query()
            ->with('roles')
            ->when($role !== '', fn ($query) => $query->role($role))
            ->when($request->filled('search'), fn ($query) => $query->where(function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';
                $query->where('name', 'like', $term)->orWhere('email', 'like', $term);
            }))
            ->latest('id')
            ->paginate(25);

        return UserResource::collection($members);
    }

    public function store(StoreMemberRequest $request): JsonResponse
    {
        Gate::authorize('create', User::class);

        $user = User::query()->create($request->safe()->except('role'));
        $user->assignRole($request->string('role')->toString());

        return (new UserResource($user))->response()->setStatusCode(201);
    }
}
