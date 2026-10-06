<?php

namespace App\Http\Controllers\Web;

use App\Enums\DocumentType;
use App\Enums\ItemStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DocumentSequence;
use App\Models\FinancialYear;
use App\Models\Item;
use App\Models\Sale;
use App\Services\Foundation\DocumentNumberService;
use App\Services\Foundation\NumberFormatService;
use App\Services\Foundation\SettingService;
use App\Support\CompanyContext;
use App\Support\TenantScopeKey;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OverviewController extends Controller
{
    public function index(
        CompanyContext $context,
        DocumentNumberService $numbers,
        NumberFormatService $format,
        SettingService $settings,
    ): View {
        $company = $context->company();
        $this->authorize('dashboard', $company);

        $branches = Branch::query()->visibleTo(auth()->user())->orderByDesc('is_head_office')->orderBy('name')->get();
        $year = FinancialYear::query()->where('is_current', true)->first();
        $invoice = DocumentSequence::query()
            ->where('document_type', DocumentType::Invoice)
            ->where('scope_key', TenantScopeKey::COMPANY)
            ->first();

        $invoicePreview = null;
        $invoicePreviewError = null;

        if ($invoice) {
            try {
                $invoicePreview = $numbers->preview($invoice);
            } catch (ValidationException $exception) {
                $invoicePreviewError = collect($exception->errors())->flatten()->first();
            }
        }

        $timezone = (string) $settings->get('datetime.timezone', $company);
        $moment = now()->timezone($timezone);

        return view('foundation.overview', [
            'company' => $company,
            'branches' => $branches,
            'year' => $year,
            'invoicePreview' => $invoicePreview,
            'invoicePreviewError' => $invoicePreviewError,
            'currencyPreview' => $format->money('1234567.5', $company),
            'weightPreview' => $format->weight('12.346', $company),
            'datePreview' => $moment->format((string) $settings->get('datetime.date_format', $company)),
            'timePreview' => $moment->format((string) $settings->get('datetime.time_format', $company)),
            'availablePieces' => auth()->user()->can('inventory.view')
                ? Item::query()->where('status', ItemStatus::Available)->count()
                : null,
            'todaySales' => auth()->user()->can('sales.view')
                ? $format->money((string) (Sale::query()->whereDate('sold_at', $moment->toDateString())->sum('total') ?: 0), $company)
                : null,
        ]);
    }
}
