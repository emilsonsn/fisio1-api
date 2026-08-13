<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;

class PermissionController extends Controller
{
    public function __invoke()
    {
        return PermissionResource::collection(Permission::query()->orderBy('module')->orderBy('key')->get());
    }
}
