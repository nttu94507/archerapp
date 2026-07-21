<?php

namespace Tests\Feature;

use App\Models\SecondHandItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecondHandItemViewCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_an_item_detail_page_increments_its_view_count(): void
    {
        $item = SecondHandItem::create([
            'title' => 'Test bow',
            'price' => 1000,
            'photo_path' => 'second-hand/test.jpg',
            'description' => null,
            'contact_type' => 'phone',
            'contact_value' => '0900000000',
        ]);

        $this->get(route('second-hand.show', $item))
            ->assertOk()
            ->assertSee('瀏覽次數：1');

        $this->get(route('second-hand.show', $item))->assertOk();

        $this->assertDatabaseHas('second_hand_items', [
            'id' => $item->id,
            'view_count' => 2,
        ]);
    }
}
