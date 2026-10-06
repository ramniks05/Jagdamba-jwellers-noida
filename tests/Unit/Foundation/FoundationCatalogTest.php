<?php

namespace Tests\Unit\Foundation;

use App\Enums\DocumentType;
use App\Models\Company;
use App\Services\Foundation\FinancialYearService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FoundationCatalogTest extends TestCase
{
    public function test_document_types_match_the_numbering_catalog(): void
    {
        $configured = array_keys(config('foundation.document_types'));
        $cases = array_map(fn (DocumentType $type) => $type->value, DocumentType::cases());

        $this->assertEqualsCanonicalizing($configured, $cases);

        foreach (DocumentType::cases() as $type) {
            $this->assertNotSame('', $type->defaultPrefix());
            $this->assertNotSame('', $type->label());
        }
    }

    public function test_every_setting_has_a_group_type_default_and_rules(): void
    {
        foreach (config('foundation.settings') as $key => $meta) {
            $this->assertArrayHasKey('group', $meta, $key);
            $this->assertArrayHasKey('type', $meta, $key);
            $this->assertArrayHasKey('default', $meta, $key);
            $this->assertArrayHasKey('rules', $meta, $key);
            $this->assertArrayHasKey($meta['group'], config('foundation.groups'), $key);
        }
    }

    public function test_financial_year_suggestion_follows_the_start_month(): void
    {
        $service = new FinancialYearService;
        $company = new Company(['fy_start_month' => 4]);

        $february = $service->suggestRange($company, Carbon::parse('2026-02-06'));
        $october = $service->suggestRange($company, Carbon::parse('2026-10-06'));

        $this->assertSame('2025-26', $february['name']);
        $this->assertSame('2025-04-01', $february['start']);
        $this->assertSame('2026-03-31', $february['end']);
        $this->assertSame('2026-27', $october['name']);

        $calendar = new Company(['fy_start_month' => 1]);
        $calendarYear = $service->suggestRange($calendar, Carbon::parse('2026-10-06'));

        $this->assertSame('2026', $calendarYear['name']);
        $this->assertSame('2026-01-01', $calendarYear['start']);
        $this->assertSame('2026-12-31', $calendarYear['end']);
    }
}
