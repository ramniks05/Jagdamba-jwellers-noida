<?php

namespace Tests\Unit\Support;

use App\Support\InvoiceTermsHtml;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceTermsHtmlTest extends TestCase
{
    #[Test]
    public function it_formats_numbered_lines(): void
    {
        $html = InvoiceTermsHtml::format("1. Goods once sold will not be taken back.\n2. Subject to local jurisdiction.");

        $this->assertStringContainsString('<ol class="invoice-terms-list">', $html);
        $this->assertStringContainsString('<li>Goods once sold will not be taken back.</li>', $html);
        $this->assertStringContainsString('<li>Subject to local jurisdiction.</li>', $html);
    }

    #[Test]
    public function it_formats_bullet_lines(): void
    {
        $html = InvoiceTermsHtml::format("- Exchange within 7 days\n- Bill must be presented");

        $this->assertStringContainsString('<ul class="invoice-terms-list">', $html);
        $this->assertStringContainsString('<li>Exchange within 7 days</li>', $html);
    }

    #[Test]
    public function it_keeps_a_single_paragraph_plain(): void
    {
        $html = InvoiceTermsHtml::format('Thank you for shopping with us.');

        $this->assertSame('<p>Thank you for shopping with us.</p>', $html);
    }
}
