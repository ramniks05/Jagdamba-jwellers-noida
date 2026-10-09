<?php

namespace App\Services\Commerce;

use App\Models\Company;
use App\Models\Sale;
use App\Services\Foundation\NumberFormatService;
use App\Services\Foundation\SettingService;
use App\Support\RupeesInWords;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\URL;

class InvoiceSheet
{
    public function __construct(
        private readonly NumberFormatService $format,
        private readonly SettingService $settings,
    ) {}

    /**
     * Everything the printed invoice needs, for the counter and for the customer link.
     *
     * @return array<string, mixed>
     */
    public function data(Sale $sale, Company $company): array
    {
        $sale->loadMissing(['lines.item', 'lines.stones', 'payments', 'customer', 'branch', 'advanceOrder']);
        $link = $this->link($sale);

        return [
            'sale' => $sale,
            'company' => $company,
            'terms' => (string) ($this->settings->get('invoice.terms', $company) ?? ''),
            'footer' => (string) ($this->settings->get('invoice.footer_note', $company) ?? ''),
            'showLogo' => (bool) $this->settings->get('invoice.show_logo', $company),
            'amountWords' => RupeesInWords::format((string) $sale->total),
            'taxes' => $this->taxRows($sale, $company),
            'makingLines' => (string) $sale->lines->reduce(fn (BigDecimal $sum, $line) => $sum->plus((string) $line->making_amount), BigDecimal::zero()),
            'money' => fn (string $amount) => $this->format->money($amount, $company),
            'weight' => fn (string $amount) => $this->format->weight($amount, $company),
            'billLink' => $link,
            'billQr' => (new QRCode(new QROptions(['outputBase64' => true, 'scale' => 3, 'addQuietzone' => false])))->render($link),
        ];
    }

    /**
     * A signed link printed on the bill, so only someone holding the bill can open it.
     */
    public function link(Sale $sale): string
    {
        return URL::signedRoute('bills.show', ['sale' => $sale->uuid]);
    }

    /**
     * @return list<array{label: string, amount: string}>
     */
    private function taxRows(Sale $sale, Company $company): array
    {
        $makingTax = BigDecimal::of((string) $sale->making_tax_amount);
        $split = $sale->making_mode !== 'inside';
        $rows = $this->taxPair(
            $sale,
            $company,
            BigDecimal::of((string) $sale->tax_amount)->minus($makingTax),
            (float) $sale->tax_percent,
            $split ? ' on jewellery' : '',
        );

        if ($makingTax->isPositive()) {
            $rows = array_merge($rows, $this->taxPair($sale, $company, $makingTax, (float) $sale->making_tax_percent, ' on making'));
        }

        return $rows;
    }

    /**
     * @return list<array{label: string, amount: string}>
     */
    private function taxPair(Sale $sale, Company $company, BigDecimal $tax, float $percent, string $suffix): array
    {
        $customerState = mb_strtolower(trim((string) $sale->customer?->state));
        $shopState = mb_strtolower(trim((string) $company->state));
        $interstate = $customerState !== '' && $shopState !== '' && $customerState !== $shopState;

        if ($interstate) {
            return [[
                'label' => 'IGST '.$this->percentLabel($percent).'%'.$suffix,
                'amount' => (string) $tax,
            ]];
        }

        $half = $tax->dividedBy(2, 2, RoundingMode::HalfUp);

        return [
            ['label' => 'CGST '.$this->percentLabel($percent / 2).'%'.$suffix, 'amount' => (string) $half],
            ['label' => 'SGST '.$this->percentLabel($percent / 2).'%'.$suffix, 'amount' => (string) $tax->minus($half)],
        ];
    }

    private function percentLabel(float $percent): string
    {
        $label = rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');

        return $label === '' ? '0' : $label;
    }
}
