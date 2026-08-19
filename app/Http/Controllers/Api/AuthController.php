<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditEventCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email'))->first();
        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            $this->audit->record(AuditEventCategory::LoginFailed, metadata: [
                'attempted_email' => $request->string('email')->toString(),
                'reason' => 'invalid_credentials',
            ]);
            abort(422, 'Credenciais inválidas.');
        }
        if (! $user->is_active) {
            $this->audit->record(AuditEventCategory::LoginFailed, $user, metadata: ['reason' => 'inactive_user']);
            abort(403, 'Usuário inativo.');
        }

        $this->audit->record(AuditEventCategory::Login, $user, user: $user);

        return response()->json(['data' => ['user' => new UserResource($user->load('accessGroups.permissions')), 'token' => $user->createToken($request->input('device_name', 'angular-web'))->plainTextToken]]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('accessGroups.permissions'));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->audit->record(AuditEventCategory::Logout, $request->user());
        $request->user()->currentAccessToken()?->delete();

        return response()->json(status: 204);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        $user = User::query()->where('email', $request->string('email'))->first();
        $this->audit->record(AuditEventCategory::PasswordResetRequested, $user, metadata: [
            'attempted_email' => $request->string('email')->toString(),
        ]);
        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => 'Se o e-mail existir, as instruções de recuperação foram enviadas.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'confirmed', 'min:8']]);
        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function (User $user, string $password): void {
            $user->forceFill(['password' => $password])->save();
            $this->audit->record(AuditEventCategory::PasswordReset, $user, newValues: ['password' => $password]);
        });
        abort_unless($status === Password::PASSWORD_RESET, 422, __($status));

        return response()->json(['message' => 'Senha redefinida com sucesso.']);
    }
}
