<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_their_own_name(): void
    {
        $user = User::factory()->create(['name' => 'Original Name'])->fresh();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/profile', ['name' => 'Updated Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertSame('Updated Name', $user->fresh()->name);
    }

    public function test_email_cannot_be_changed_through_the_profile_endpoint(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com'])->fresh();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/profile', [
                'name' => 'Updated Name',
                'email' => 'changed@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('data.email', 'original@example.com');

        $this->assertSame('original@example.com', $user->fresh()->email);
    }

    public function test_user_can_upload_a_profile_photo(): void
    {
        Storage::fake('local');
        $user = User::factory()->create()->fresh();

        $this->actingAs($user, 'sanctum')
            ->post('/api/v1/profile', [
                '_method' => 'PATCH',
                'photo' => UploadedFile::fake()->image('avatar.jpg'),
            ])
            ->assertOk()
            ->assertJsonPath('data.has_photo', true);

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->photo_path);
        Storage::disk('local')->assertExists($fresh->photo_path);
    }

    public function test_user_can_change_their_password_with_the_correct_current_password(): void
    {
        $user = User::factory()->create()->fresh();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile/password', [
                'current_password' => 'password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
    }

    public function test_password_change_is_rejected_when_current_password_is_wrong(): void
    {
        $user = User::factory()->create()->fresh();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
