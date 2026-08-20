<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\UserAccountCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserCreationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_user_generates_a_temporary_password_and_sends_the_access_email(): void
    {
        Notification::fake();
        config(['app.frontend_url' => 'https://app.fisio1.test']);
        $this->seed();
        $admin = User::query()->where('email', 'andre@fisio1.com.br')->firstOrFail();
        $groupId = $admin->accessGroups()->firstOrFail()->id;

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/users', [
            'name' => 'Carla Nogueira',
            'email' => 'carla@example.com',
            'access_group_ids' => [$groupId],
        ])->assertCreated();

        $user = User::query()->findOrFail($response->json('data.id'));

        Notification::assertSentTo(
            $user,
            UserAccountCreatedNotification::class,
            function (UserAccountCreatedNotification $notification) use ($user): bool {
                $this->assertSame('https://app.fisio1.test/login', $notification->applicationUrl);
                $this->assertTrue(Hash::check($notification->temporaryPassword, $user->password));
                $this->assertNotSame($notification->temporaryPassword, $user->password);

                $html = (string) $notification->toMail($user)->render();
                $this->assertStringContainsString('FISIO1', $html);
                $this->assertStringContainsString('carla@example.com', $html);
                $this->assertStringContainsString($notification->temporaryPassword, $html);
                $this->assertStringContainsString('https://app.fisio1.test/login', $html);

                return true;
            },
        );
    }

    public function test_an_explicit_temporary_password_remains_supported(): void
    {
        Notification::fake();
        $this->seed();
        $admin = User::query()->where('email', 'andre@fisio1.com.br')->firstOrFail();
        $groupId = $admin->accessGroups()->firstOrFail()->id;

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/users', [
            'name' => 'Ricardo Mendes',
            'email' => 'ricardo@example.com',
            'password' => 'senha-temporaria',
            'access_group_ids' => [$groupId],
        ])->assertCreated();

        $user = User::query()->where('email', 'ricardo@example.com')->firstOrFail();

        Notification::assertSentTo(
            $user,
            UserAccountCreatedNotification::class,
            fn (UserAccountCreatedNotification $notification): bool => $notification->temporaryPassword === 'senha-temporaria'
                && Hash::check('senha-temporaria', $user->password),
        );
    }
}
