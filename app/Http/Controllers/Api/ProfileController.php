<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditEventCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $oldName = $user->name;
        $payload = $request->safe()->only(['name']);

        if ($request->hasFile('photo')) {
            if ($user->photo_path) {
                Storage::disk('local')->delete($user->photo_path);
            }
            $payload['photo_path'] = $request->file('photo')->store('users/photos', 'local');
        }

        $user->update($payload);

        if (array_key_exists('name', $payload) && $oldName !== $user->name) {
            $this->audit->record(
                AuditEventCategory::ProfileUpdated,
                $user,
                ['name' => $oldName],
                ['name' => $user->name],
            );
        }

        return new UserResource($user->fresh()->load('accessGroups.permissions'));
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['password' => $request->validated('password')])->save();
        $user->tokens()->where('id', '!=', $user->currentAccessToken()?->id)->delete();

        $this->audit->record(
            AuditEventCategory::ProfilePasswordChanged,
            $user,
            newValues: ['password' => '[updated]'],
        );

        return response()->json(['message' => 'Senha atualizada com sucesso.']);
    }

    public function photo(Request $request)
    {
        $user = $request->user();
        abort_unless($user->photo_path && Storage::disk('local')->exists($user->photo_path), 404);

        return Storage::disk('local')->response($user->photo_path);
    }
}
