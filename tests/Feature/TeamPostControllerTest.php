<?php

namespace Tests\Feature;

use App\Models\TeamPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamPostControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_see_team_post_listing(): void
    {
        $user = User::factory()->create();
        $posts = TeamPost::factory()->count(2)->create();

        $response = $this->actingAs($user)->get(route('team-posts.index'));

        $response->assertOk();
        $posts->each(fn ($post) => $response->assertSee($post->title));
    }

    public function test_user_can_create_team_post(): void
    {
        $user = User::factory()->create();

        $payload = [
            'title' => '徵團隊夥伴',
            'content' => '正在尋找混雙戰友。',
            'contact' => 'Line: archery',
        ];

        $response = $this->actingAs($user)->post(route('team-posts.store'), $payload);

        $response->assertRedirect(route('team-posts.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('team_posts', $payload + ['user_id' => $user->id]);
    }

    public function test_store_requires_mandatory_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('team-posts.store'), []);

        $response->assertSessionHasErrors(['title', 'content', 'contact']);
    }
}
