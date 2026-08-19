<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditEventCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\AccessGroup;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request)
    {
        return UserResource::collection(User::query()->with('accessGroups.permissions')->latest()->paginate($request->integer('per_page', 15))->withQueryString());
    }

    public function store(StoreUserRequest $request): UserResource
    {
        $user = User::create($this->payloadWithPhoto($request));
        $groupIds = $request->validated('access_group_ids');
        $user->accessGroups()->sync($groupIds);
        $this->audit->record(AuditEventCategory::UserGroupsUpdated, $user, newValues: [
            'access_groups' => $this->groupSnapshots($groupIds),
        ]);

        return new UserResource($user->load('accessGroups.permissions'));
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load('accessGroups.permissions'));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $user->update($this->payloadWithPhoto($request, $user));
        if ($request->has('access_group_ids')) {
            $oldGroups = $user->accessGroups()->get(['access_groups.id', 'access_groups.name']);
            $oldGroupIds = $oldGroups->pluck('id')->sort()->values()->all();
            $newGroupIds = collect($request->validated('access_group_ids'))->sort()->values()->all();
            $user->accessGroups()->sync($newGroupIds);

            if ($oldGroupIds !== $newGroupIds) {
                $this->audit->record(
                    AuditEventCategory::UserGroupsUpdated,
                    $user,
                    ['access_groups' => $oldGroups->map->only(['id', 'name'])->values()->all()],
                    ['access_groups' => $this->groupSnapshots($newGroupIds)],
                );
            }
        }

        return new UserResource($user->fresh()->load('accessGroups.permissions'));
    }

    public function photo(User $user)
    {
        abort_unless($user->photo_path && Storage::disk('local')->exists($user->photo_path), 404);

        return Storage::disk('local')->response($user->photo_path);
    }

    private function payloadWithPhoto(StoreUserRequest|UpdateUserRequest $request, ?User $user = null): array
    {
        $payload = $request->safe()->except(['access_group_ids', 'photo']);
        if (! $request->hasFile('photo')) {
            return $payload;
        }
        if ($user?->photo_path) {
            Storage::disk('local')->delete($user->photo_path);
        }
        $payload['photo_path'] = $request->file('photo')->store('users/photos', 'local');

        return $payload;
    }

    private function groupSnapshots(array $ids): array
    {
        return AccessGroup::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map->only(['id', 'name'])
            ->values()
            ->all();
    }
}
