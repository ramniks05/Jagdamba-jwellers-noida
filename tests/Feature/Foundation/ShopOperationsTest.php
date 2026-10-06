<?php

namespace Tests\Feature\Foundation;

use App\Enums\BranchStatus;
use App\Enums\DocumentType;
use App\Enums\SequenceResetPolicy;
use App\Events\Foundation\DocumentNumberIssued;
use App\Models\Branch;
use App\Models\DocumentNumberAllocation;
use App\Models\DocumentSequence;
use App\Models\FinancialYear;
use App\Services\Foundation\BranchService;
use App\Services\Foundation\DocumentNumberService;
use App\Services\Foundation\SettingService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class ShopOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_branches_financial_years_and_settings_follow_shop_rules(): void
    {
        $user = $this->shopUser();

        $this->actingAs($user)->post(route('branches.store'), [
            'name' => 'Andheri',
            'code' => 'AND',
            'status' => 'active',
            'phone' => '02240000000',
            'email' => 'andheri@jagdamba.test',
            'address_line1' => '1 Link Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'postal_code' => '400053',
            'country' => 'India',
            'is_head_office' => '0',
        ])->assertRedirect(route('branches.index'));

        app(CompanyContext::class)->set($user->company);

        $headOffice = Branch::query()->where('is_head_office', true)->firstOrFail();
        $andheri = Branch::query()->where('code', 'AND')->firstOrFail();

        $this->actingAs($user)
            ->delete(route('branches.destroy', $headOffice))
            ->assertSessionHasErrors('branch');

        $this->actingAs($user)->put(route('branches.update', $andheri), [
            'name' => 'Andheri',
            'code' => 'AND',
            'status' => 'active',
            'address_line1' => '1 Link Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'postal_code' => '400053',
            'country' => 'India',
            'is_head_office' => '1',
        ])->assertRedirect(route('branches.index'));

        app(CompanyContext::class)->set($user->company);

        $this->assertTrue($andheri->fresh()->is_head_office);
        $this->assertFalse($headOffice->fresh()->is_head_office);
        $this->assertSame(BranchStatus::Active, $andheri->fresh()->status);

        $this->actingAs($user)->post(route('financial-years.store'), [
            'name' => 'Overlap',
            'start_date' => '2026-06-01',
            'end_date' => '2027-03-31',
            'is_current' => '0',
        ])->assertSessionHasErrors('start_date');

        $this->actingAs($user)->post(route('financial-years.store'), [
            'name' => '2027-28',
            'start_date' => '2027-04-01',
            'end_date' => '2028-03-31',
            'is_current' => '0',
        ])->assertRedirect(route('financial-years.index'));

        app(CompanyContext::class)->set($user->company);

        $nextYear = FinancialYear::query()->where('name', '2027-28')->firstOrFail();
        $current = FinancialYear::query()->where('is_current', true)->firstOrFail();

        $this->actingAs($user)
            ->post(route('financial-years.close', $current))
            ->assertSessionHasErrors('year');

        $this->actingAs($user)
            ->post(route('financial-years.current', $nextYear))
            ->assertRedirect(route('financial-years.index'));

        $this->actingAs($user)
            ->post(route('financial-years.close', $current))
            ->assertRedirect(route('financial-years.index'));

        app(CompanyContext::class)->set($user->company);

        $this->assertTrue($current->fresh()->is_closed);
        $this->assertTrue($nextYear->fresh()->is_current);

        $this->actingAs($user)
            ->delete(route('financial-years.destroy', $current))
            ->assertSessionHasErrors('year');

        $settings = app(SettingService::class)->formFields($user->company);
        $payload = [];

        foreach ($settings as $index => $field) {
            $value = $field['key'] === 'currency.symbol' ? 'Rs' : $field['value'];
            $payload[] = [
                'key' => $field['key'],
                'value' => is_bool($value) ? ($value ? '1' : '0') : $value,
            ];
        }

        $this->actingAs($user)
            ->put(route('settings.update'), ['settings' => $payload])
            ->assertRedirect(route('settings.edit'));

        $this->assertSame('Rs', app(SettingService::class)->get('currency.symbol', $user->company->fresh()));
    }

    public function test_document_numbers_are_issued_once_and_cannot_be_rewritten(): void
    {
        Event::fake([DocumentNumberIssued::class]);
        $user = $this->shopUser();
        $this->actingAs($user);

        $sequence = DocumentSequence::query()
            ->where('document_type', DocumentType::Invoice)
            ->firstOrFail();

        $numbers = app(DocumentNumberService::class);
        $this->assertSame('INV-2026-27-0001', $numbers->preview($sequence));
        $this->assertSame(1, $sequence->fresh()->next_number);

        $first = $numbers->issue($sequence->fresh());
        $second = $numbers->issue($sequence->fresh());

        $this->assertSame('INV-2026-27-0001', $first->number);
        $this->assertSame('INV-2026-27-0002', $second->number);
        $this->assertSame(2, DocumentNumberAllocation::query()->count());
        Event::assertDispatched(DocumentNumberIssued::class);

        $this->expectException(RuntimeException::class);
        $first->number = 'INV-CHANGED';
        $first->save();
    }

    public function test_a_new_period_restarts_the_series_and_the_counter_cannot_move_backward(): void
    {
        $user = $this->shopUser();
        $this->actingAs($user);

        $sequence = DocumentSequence::query()->where('document_type', DocumentType::Invoice)->firstOrFail();
        $sequence->forceFill([
            'last_period_key' => 'fy:0',
            'last_issued_at' => now(),
            'next_number' => 9,
        ])->save();

        $issued = app(DocumentNumberService::class)->issue($sequence->fresh());
        $this->assertSame('INV-2026-27-0001', $issued->number);

        $this->put(route('document-sequences.update', $sequence), [
            'prefix' => 'INV',
            'suffix' => '',
            'separator' => '-',
            'padding' => 4,
            'next_number' => 1,
            'reset_policy' => SequenceResetPolicy::FinancialYear->value,
            'is_active' => '1',
        ])->assertSessionHasErrors('next_number');
    }

    public function test_branch_series_overrides_the_company_series(): void
    {
        $user = $this->shopUser();
        $branch = app(BranchService::class)->create($user->company, [
            'name' => 'Pune',
            'code' => 'PUNE',
            'status' => 'active',
            'address_line1' => '2 Camp',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'postal_code' => '411001',
            'country' => 'India',
            'is_head_office' => false,
        ]);

        DocumentSequence::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $branch->id,
            'document_type' => DocumentType::Invoice,
            'prefix' => 'PN',
            'separator' => '-',
            'padding' => 3,
            'next_number' => 1,
            'reset_policy' => SequenceResetPolicy::Never,
            'is_active' => true,
            'is_system' => false,
        ]);

        $resolved = app(DocumentNumberService::class)->for(DocumentType::Invoice, $branch);

        $this->assertSame('PN', $resolved->prefix);
        $this->assertSame('PN-001', app(DocumentNumberService::class)->preview($resolved));
    }

    public function test_records_from_another_shop_are_not_visible(): void
    {
        $first = $this->shopUser();
        $branch = Branch::query()->where('company_id', $first->company_id)->firstOrFail();

        $second = $this->shopUser([
            'name' => 'Other Jewellers',
            'code' => 'OTHER',
            'gstin' => '29ABCDE1234F1Z5',
            'pan' => 'ABCDE1234F',
            'email' => 'other@jagdamba.test',
        ], [
            'email' => 'owner-other@jagdamba.test',
        ]);

        $this->assertFalse($second->can('update', $branch));

        $this->actingAs($second)
            ->get(route('branches.edit', $branch))
            ->assertNotFound();

        app(CompanyContext::class)->forget();
        $this->assertSame(0, Branch::query()->count());

        $this->artisan('shop:status')->assertSuccessful();
    }
}
