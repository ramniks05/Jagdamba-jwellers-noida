<?php

namespace Tests\Feature\Commerce;

use App\Enums\ChargeAppliesTo;
use App\Enums\DocumentType;
use App\Enums\ItemStatus;
use App\Models\Category;
use App\Models\ChargeMethod;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\GirviPledge;
use App\Models\Item;
use App\Models\MetalRate;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\StockLocation;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationGuardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_mobile_must_be_a_real_number_and_belong_to_one_customer(): void
    {
        $owner = $this->shopUser();

        $this->actingAs($owner)->post(route('customers.store'), $this->customerData(['mobile' => 'hello world']))
            ->assertSessionHasErrors(['mobile' => 'Enter a 10-digit mobile number, like 98765 43210.']);

        $this->actingAs($owner)->post(route('customers.store'), $this->customerData(['mobile' => '098765 43210']))
            ->assertSessionHasNoErrors();
        $this->seeShop($owner);
        $first = Customer::query()->where('name', 'Meera Shah')->firstOrFail();
        $this->assertSame('09876543210', $first->mobile);

        $this->actingAs($owner)->post(route('customers.store'), $this->customerData(['name' => 'Other', 'mobile' => '+91 98765-43210']))
            ->assertSessionHasErrors(['mobile' => 'This mobile already belongs to Meera Shah ('.$first->code.').']);

        $this->actingAs($owner)->put(route('customers.update', $first), $this->customerData(['mobile' => '9876543210']))
            ->assertSessionHasNoErrors();

        $this->actingAs($owner)->post(route('customers.store'), $this->customerData(['name' => 'Baby', 'mobile' => '', 'dob' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors(['dob' => 'Date of birth cannot be in the future.']);
    }

    public function test_supplier_mobile_and_bank_details_get_plain_messages(): void
    {
        $owner = $this->shopUser();

        $this->actingAs($owner)->post(route('suppliers.store'), [
            'code' => 'SUP01',
            'name' => 'Mumbai Bullion',
            'mobile' => 'hello',
            'pan' => '123',
            'ifsc' => 'xx',
            'kyc_status' => 'pending',
            'is_active' => '1',
        ])->assertSessionHasErrors([
            'mobile' => 'Enter a 10-digit mobile number, like 98765 43210.',
            'pan' => 'Enter a valid 10-character PAN, like ABCDE1234F.',
            'ifsc' => 'Enter a valid 11-character IFSC, like SBIN0001234.',
        ]);
    }

    public function test_metal_rate_cannot_be_absurd_or_far_ahead(): void
    {
        $owner = $this->shopUser();
        [$gold, $purity] = $this->basics($owner);
        $rate = fn (array $overrides) => array_merge([
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '7000',
        ], $overrides);

        $this->actingAs($owner)->post(route('rates.store'), $rate(['rate_per_gram' => '999999999999']))
            ->assertSessionHasErrors(['rate_per_gram' => 'That rate looks too high. Check for an extra zero.']);
        $this->actingAs($owner)->post(route('rates.store'), $rate(['effective_at' => now()->addDays(2)->toDateTimeString()]))
            ->assertSessionHasErrors(['effective_at' => 'A rate can be saved for today or tomorrow, not further ahead.']);
        $this->actingAs($owner)->post(route('rates.store'), $rate(['effective_at' => now()->addDay()->setTime(9, 0)->toDateTimeString()]))
            ->assertSessionHasNoErrors();
        $this->seeShop($owner);
        $this->assertSame(0, MetalRate::query()->inForce()->where('rate_per_gram', 7000)->count());
    }

    public function test_master_names_are_not_repeated(): void
    {
        $owner = $this->shopUser();

        $this->actingAs($owner)->post(route('brands.store'), ['name' => 'Tanishq Style', 'code' => 'TS1', 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('brands.store'), ['name' => 'Tanishq Style', 'code' => 'TS2', 'is_active' => '1'])
            ->assertSessionHasErrors(['name' => 'This name is already in the list.']);

        $this->actingAs($owner)->post(route('categories.store'), ['name' => 'Gold', 'code' => 'GLD', 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('categories.store'), ['name' => 'Silver', 'code' => 'SLV', 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->seeShop($owner);
        $goldParent = Category::query()->where('code', 'GLD')->firstOrFail();
        $silverParent = Category::query()->where('code', 'SLV')->firstOrFail();

        $this->actingAs($owner)->post(route('categories.store'), ['name' => 'Kada', 'code' => 'KADA1', 'parent_uuid' => $goldParent->uuid, 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('categories.store'), ['name' => 'Kada', 'code' => 'KADA2', 'parent_uuid' => $silverParent->uuid, 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('categories.store'), ['name' => 'Kada', 'code' => 'KADA3', 'parent_uuid' => $goldParent->uuid, 'is_active' => '1'])
            ->assertSessionHasErrors(['name' => 'This name is already in the list.']);
    }

    public function test_girvi_interest_cannot_skip_months_that_are_owed(): void
    {
        $owner = $this->shopUser();
        [$gold, $purity] = $this->basics($owner);
        $this->actingAs($owner)->post(route('customers.store'), $this->customerData([]))->assertRedirect();
        $this->seeShop($owner);
        $customer = Customer::query()->where('name', 'Meera Shah')->firstOrFail();

        $this->actingAs($owner)->post(route('girvi.store'), [
            'customer_uuid' => $customer->uuid,
            'pieces' => [[
                'description' => 'Chain',
                'metal_uuid' => $gold->uuid,
                'purity_uuid' => $purity->uuid,
                'gross_weight' => '10',
                'stone_weight' => '0',
                'rate_per_gram' => '6000',
            ]],
            'loan_mode' => 'amount',
            'loan_amount' => '40000',
            'interest_percent' => '2',
        ])->assertRedirect();
        $this->seeShop($owner);
        $pledge = GirviPledge::query()->firstOrFail();

        $this->travel(40)->days();

        $this->actingAs($owner)->post(route('girvi.settle', $pledge), [
            'action' => 'release',
            'months' => '0',
            'payment' => '40000',
            'method' => 'cash',
        ])->assertSessionHasErrors(['months' => 'Interest is due for 2 months since 06-10-2026.']);

        $this->actingAs($owner)->post(route('girvi.settle', $pledge), [
            'action' => 'release',
            'months' => '2',
            'payment' => '41600',
            'method' => 'cash',
        ])->assertSessionHasNoErrors();
        $this->assertSame('released', $pledge->refresh()->status);
    }

    public function test_a_damaged_or_lost_piece_can_come_back_into_stock(): void
    {
        $owner = $this->shopUser();
        [$gold, $purity, $location] = $this->basics($owner);
        $making = ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Making)->where('code', 'fixed')->firstOrFail();

        $this->actingAs($owner)->post(route('items.store'), [
            'name' => 'Plain chain',
            'item_code' => 'PC0001',
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'location_uuid' => $location->uuid,
            'gross_weight' => '10',
            'making_method_uuid' => $making->uuid,
        ])->assertSessionHasNoErrors();
        $this->seeShop($owner);
        $item = Item::query()->where('item_code', 'PC0001')->firstOrFail();

        $this->actingAs($owner)->post(route('items.restore', $item))->assertSessionHasErrors(['item' => 'Only a damaged or lost piece can come back into stock.']);
        $this->actingAs($owner)->post(route('items.damage', $item))->assertRedirect();
        $this->actingAs($owner)->get(route('items.show', $item))->assertSee('Mended · back in stock');
        $this->actingAs($owner)->post(route('items.restore', $item))->assertSessionHasNoErrors();
        $this->assertSame(ItemStatus::Available, $item->refresh()->status);

        $this->actingAs($owner)->post(route('items.lost', $item))->assertRedirect();
        $this->actingAs($owner)->post(route('items.restore', $item))->assertSessionHasNoErrors();
        $this->assertSame(ItemStatus::Available, $item->refresh()->status);
    }

    public function test_dates_and_bonuses_stay_in_sensible_ranges(): void
    {
        $owner = $this->shopUser();
        $this->actingAs($owner)->post(route('customers.store'), $this->customerData([]))->assertRedirect();
        $this->seeShop($owner);
        $customer = Customer::query()->where('name', 'Meera Shah')->firstOrFail();

        $this->actingAs($owner)->post(route('repairs.store'), [
            'customer_uuid' => $customer->uuid,
            'description' => 'Ring',
            'problem' => 'Resize',
            'gross_weight' => '4',
            'expected_on' => now()->subDay()->toDateString(),
        ])->assertSessionHasErrors(['expected_on' => 'The ready-by date cannot be in the past.']);

        $this->actingAs($owner)->post(route('schemes.store'), [
            'code' => 'GOLD11',
            'name' => 'Gold plan',
            'installment_mode' => 'fixed',
            'monthly_amount' => '5000',
            'duration_months' => '11',
            'bonus_type' => 'percent',
            'bonus_value' => '150',
            'is_active' => '1',
        ])->assertSessionHasErrors(['bonus_value' => 'A percent bonus cannot be more than 100%.']);

        $this->actingAs($owner)->post(route('financial-years.store'), [
            'name' => '2027-29',
            'start_date' => '2027-04-01',
            'end_date' => '2029-03-31',
        ])->assertSessionHasErrors(['end_date' => 'A financial year can be at most 12 months long.']);
    }

    public function test_two_number_series_cannot_share_a_prefix(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $orders = DocumentSequence::query()->where('document_type', DocumentType::AdvanceOrder->value)->firstOrFail();
        $data = fn (string $prefix) => [
            'prefix' => $prefix,
            'separator' => '-',
            'padding' => '4',
            'next_number' => '1',
            'reset_policy' => $orders->reset_policy->value,
            'is_active' => '1',
        ];

        $this->actingAs($owner)->put(route('document-sequences.update', $orders), $data('inv'))
            ->assertSessionHasErrors('prefix');
        $this->actingAs($owner)->put(route('document-sequences.update', $orders), $data('ORDR'))
            ->assertSessionHasNoErrors();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function customerData(array $overrides): array
    {
        return array_merge([
            'name' => 'Meera Shah',
            'customer_type' => 'retail',
            'kyc_status' => 'pending',
            'is_active' => '1',
        ], $overrides);
    }

    /**
     * @return array{0: MetalType, 1: Purity, 2: StockLocation}
     */
    private function basics(User $owner): array
    {
        $this->seeShop($owner);

        return [
            MetalType::query()->where('code', 'GOLD')->firstOrFail(),
            Purity::query()->where('code', '22K')->firstOrFail(),
            StockLocation::query()->where('code', 'MAIN')->firstOrFail(),
        ];
    }

    private function seeShop(User $user): void
    {
        app(CompanyContext::class)->set($user->company);
    }
}
