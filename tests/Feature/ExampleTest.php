<?php

namespace Tests\Feature;

use App\Domain\Access\Role as RoleName;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_root_path_points_to_the_dashboard()
    {
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_the_dashboard_is_shown_to_an_authenticated_user()
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(RoleName::VIEWER);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard'));
    }
}
