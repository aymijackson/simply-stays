<?php

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Guest', 'Host', 'Agent', 'ServiceProvider', 'Admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_admin_can_change_a_users_role(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guest');

        $this->actingAs($this->admin)
            ->patch(route('admin.users.role', $user), ['role' => 'Host'])
            ->assertRedirect();

        $this->assertSame('Host', $user->fresh()->primaryRole());
    }

    public function test_admin_can_suspend_and_reactivate_a_user(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($this->admin)
            ->patch(route('admin.users.status', $user))
            ->assertRedirect();
        $this->assertSame('suspended', $user->fresh()->status);

        $this->actingAs($this->admin)
            ->patch(route('admin.users.status', $user))
            ->assertRedirect();
        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_admin_cannot_suspend_their_own_account(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('admin.users.status', $this->admin))
            ->assertStatus(422);

        $this->assertSame('active', $this->admin->fresh()->status);
    }

    public function test_a_suspended_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['status' => 'suspended', 'password' => bcrypt('password')]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_non_admin_cannot_access_user_management(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guest');

        $this->actingAs($user)->get(route('admin.users.index'))->assertStatus(403);
    }
}
