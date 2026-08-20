<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditEventCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccessGroup\StoreAccessGroupRequest;
use App\Http\Requests\AccessGroup\UpdateAccessGroupRequest;
use App\Http\Resources\AccessGroupResource;
use App\Models\AccessGroup;
use App\Models\Permission;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AccessGroupController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request)
    {
        return AccessGroupResource::collection(AccessGroup::query()->with('permissions')->withCount('users')->orderBy('name')->paginate($request->integer('per_page', 30))->withQueryString());
    }

    public function store(StoreAccessGroupRequest $request): AccessGroupResource
    {
        $group = DB::transaction(function () use ($request): AccessGroup {
            $group = AccessGroup::create($request->safe()->except('permission_ids'));
            $permissionIds = $request->validated('permission_ids');
            $group->permissions()->sync($permissionIds);
            $this->audit->record(AuditEventCategory::AccessGroupPermissionsUpdated, $group, newValues: [
                'permissions' => $this->permissionSnapshots($permissionIds),
            ]);

            return $group;
        });

        return new AccessGroupResource($group->load('permissions'));
    }

    public function show(AccessGroup $accessGroup): AccessGroupResource
    {
        return new AccessGroupResource($accessGroup->load('permissions')->loadCount('users'));
    }

    public function update(UpdateAccessGroupRequest $request, AccessGroup $accessGroup): AccessGroupResource
    {
        abort_if($accessGroup->is_system, 422, 'Grupos de sistema não podem ser alterados.');

        DB::transaction(function () use ($request, $accessGroup): void {
            $accessGroup->update($request->safe()->except('permission_ids'));
            if ($request->has('permission_ids')) {
                $oldPermissions = $accessGroup->permissions()->get(['permissions.id', 'permissions.name']);
                $oldPermissionIds = $oldPermissions->pluck('id')->sort()->values()->all();
                $newPermissionIds = collect($request->validated('permission_ids'))->sort()->values()->all();
                $accessGroup->permissions()->sync($newPermissionIds);

                if ($oldPermissionIds !== $newPermissionIds) {
                    $this->audit->record(
                        AuditEventCategory::AccessGroupPermissionsUpdated,
                        $accessGroup,
                        ['permissions' => $oldPermissions->map->only(['id', 'name'])->values()->all()],
                        ['permissions' => $this->permissionSnapshots($newPermissionIds)],
                    );
                }
            }
        });

        return new AccessGroupResource($accessGroup->fresh()->load('permissions'));
    }

    public function destroy(AccessGroup $accessGroup): Response
    {
        abort_if($accessGroup->is_system, 422, 'Grupos de sistema não podem ser removidos.');
        abort_if($accessGroup->users()->exists(), 422, 'Remova os usuários vinculados antes de excluir o grupo.');
        $accessGroup->delete();

        return response()->noContent();
    }

    private function permissionSnapshots(array $ids): array
    {
        return Permission::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map->only(['id', 'name'])
            ->values()
            ->all();
    }
}
