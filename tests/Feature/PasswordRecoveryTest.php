<?php

namespace Tests\Feature;

use App\Enums\AuditEventCategory;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Notifications\PasswordRecoveryCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_reset_the_password_with_the_code_received_by_email(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'fisioterapeuta@example.com',
            'password' => 'senha-antiga',
            'is_active' => true,
        ]);
        $oldAccessTokenId = $user->createToken('old-session')->accessToken->id;
        $code = null;

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'FISIOTERAPEUTA@example.com',
            'website' => '',
        ])->assertOk()->assertJsonPath(
            'message',
            'Se o e-mail estiver cadastrado, enviaremos um código de recuperação.',
        );

        Notification::assertSentTo(
            $user,
            PasswordRecoveryCodeNotification::class,
            function (PasswordRecoveryCodeNotification $notification) use (&$code): bool {
                $code = $notification->code;

                return $notification->expiresInMinutes === 10;
            },
        );

        $storedCode = PasswordResetCode::query()->where('email', $user->email)->firstOrFail();
        $this->assertNotSame($code, $storedCode->code_hash);
        $this->assertTrue(Hash::check($code, $storedCode->code_hash));

        $this->postJson('/api/v1/auth/forgot-password/verify', [
            'email' => $user->email,
            'code' => $code === '000000' ? '000001' : '000000',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertSame(1, $storedCode->fresh()->attempts);

        $resetToken = $this->postJson('/api/v1/auth/forgot-password/verify', [
            'email' => $user->email,
            'code' => $code,
        ])->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->json('data.reset_token');

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $resetToken,
            'password' => 'nova-senha-segura',
            'password_confirmation' => 'nova-senha-segura',
        ])->assertOk()->assertJsonPath('message', 'Senha redefinida com sucesso.');

        $this->assertTrue(Hash::check('nova-senha-segura', $user->fresh()->password));
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldAccessTokenId]);
        $this->assertDatabaseMissing('password_reset_codes', ['email' => $user->email]);
        $this->assertDatabaseHas('audit_logs', ['event' => AuditEventCategory::PasswordResetRequested->value]);
        $this->assertDatabaseHas('audit_logs', ['event' => AuditEventCategory::PasswordResetVerificationFailed->value]);
        $this->assertDatabaseHas('audit_logs', ['event' => AuditEventCategory::PasswordResetCodeVerified->value]);
        $this->assertDatabaseHas('audit_logs', ['event' => AuditEventCategory::PasswordReset->value]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'nova-senha-segura',
        ])->assertOk();
    }

    public function test_honeypot_and_unknown_email_do_not_disclose_accounts_or_send_codes(): void
    {
        Notification::fake();
        $message = 'Se o e-mail estiver cadastrado, enviaremos um código de recuperação.';

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'robo@example.com',
            'website' => 'https://spam.example.com',
        ])->assertOk()->assertJsonPath('message', $message);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'inexistente@example.com',
            'website' => '',
        ])->assertOk()->assertJsonPath('message', $message);

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_codes', 0);
    }

    public function test_recovery_email_contains_the_branded_code_and_expiration(): void
    {
        $user = User::factory()->make(['name' => 'Carla Nogueira']);
        $html = (string) (new PasswordRecoveryCodeNotification('482731', 10))
            ->toMail($user)
            ->render();

        $this->assertStringContainsString('FISIO1', $html);
        $this->assertStringContainsString('Carla Nogueira', $html);
        $this->assertStringContainsString('482731', $html);
        $this->assertStringContainsString('10 minutos', $html);
    }
}
