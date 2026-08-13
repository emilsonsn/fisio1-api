<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccessGroup\StoreAccessGroupRequest;
use App\Http\Requests\AccessGroup\UpdateAccessGroupRequest;
use App\Http\Resources\AccessGroupResource;
use App\Models\AccessGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AccessGroupController extends Controller
{
    public function index(Request $request)
    {
        return AccessGroupResource::collection(AccessGroup::query()->with('permissions')->withCount('users')->orderBy('name')->paginate($request->integer('per_page', 30))->withQueryString());
    }

    public function store(StoreAccessGroupRequest $request): AccessGroupResource
    {
        $group = DB::transaction(function () use ($request): AccessGroup {
            $group = AccessGroup::create($request->safe()->except('permission_ids'));
            $group->permissions()->sync($request->validated('permission_ids'));

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
        DB::transaction(function () use ($request, $accessGroup): void {
            $accessGroup->update($request->safe()->except('permission_ids'));
            if ($request->has('permission_ids')) {
                $accessGroup->permissions()->sync($request->validated('permission_ids'));
            }
        });

        return new AccessGroupResource($accessGroup->fresh()->load('permissions'));
    }

    public function destroy(AccessGroup $accessGroup): Response
    {
        abort_if($accessGroup->is_system, 422, 'Grupos de sistema não podem ser removidos.');
        $accessGroup->delete();

        return response()->noContent();
    }
}
