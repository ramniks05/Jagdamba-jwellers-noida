<?php

namespace Tests\Unit\Support;

use App\Support\DisplayText;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DisplayTextTest extends TestCase
{
    #[Test]
    public function it_title_cases_piece_names_for_the_invoice(): void
    {
        $this->assertSame('Gold Ring With Stone', DisplayText::titleCase('gold ring with stone'));
        $this->assertSame('Silver Chain', DisplayText::titleCase('SILVER CHAIN'));
    }
}
