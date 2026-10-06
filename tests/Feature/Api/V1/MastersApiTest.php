<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MastersApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_can_list_categories_and_create_a_brand(): void
    {
        $owner = $this->shopUser();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonFragment(['code' => 'RING', 'name' => 'Ring']);

        $this->postJson('/api/v1/brands', [
            'name' => 'House brand',
            'code' => 'HOUSE',
            'sort_order' => 0,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.code', 'HOUSE')
            ->assertJsonPath('data.name', 'House brand');
    }

    public function test_another_shops_category_is_not_found(): void
    {
        $first = $this->shopUser();
        app(CompanyContext::class)->set($first->company);
        $ring = Category::query()->where('code', 'RING')->firstOrFail();

        $other = $this->shopUser([
            'name' => 'Other Jewellers',
            'code' => 'OTHER',
            'gstin' => '29ABCDE1234F1Z5',
            'pan' => 'ABCDE1234F',
            'email' => 'other@jagdamba.test',
        ], [
            'email' => 'owner-other@jagdamba.test',
        ]);

        Sanctum::actingAs($other);

        $this->getJson('/api/v1/categories/'.$ring->uuid)->assertNotFound();
    }
}
