<?php

namespace Tests\Feature;

use App\Models\ArcherySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_users_with_last_practice_date(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $userWithPractice = User::factory()->create([
            'name' => 'Alice Archer',
            'email' => 'alice@example.com',
        ]);

        $userWithoutPractice = User::factory()->create([
            'name' => 'Bob Archer',
            'email' => 'bob@example.com',
        ]);

        ArcherySession::query()->create([
            'user_id' => $userWithPractice->id,
            'bow_type' => 'recurve',
            'venue' => 'indoor',
            'distance_m' => 18,
            'arrows_total' => 30,
            'arrows_per_end' => 6,
            'target_face' => 'ten-ring',
            'score_total' => 280,
            'x_count' => 5,
            'm_count' => 0,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('Alice Archer');
        $response->assertSee('alice@example.com');
        $response->assertSee('Bob Archer');
        $response->assertSee('尚無練習紀錄');
    }

    public function test_non_admin_cannot_access_admin_users_page(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get(route('admin.users.index'));

        $response->assertForbidden();
    }
}
