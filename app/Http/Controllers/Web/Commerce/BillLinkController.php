<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Sale;
use App\Services\Commerce\InvoiceSheet;
use App\Support\CompanyContext;
use Illuminate\View\View;

class BillLinkController extends Controller
{
    /**
     * The bill a customer opens by scanning the QR code printed on it.
     */
    public function show(string $sale, InvoiceSheet $invoice, CompanyContext $context): View
    {
        $bill = $context->bypassing(fn () => Sale::query()->where('uuid', $sale)->first());
        $company = $bill ? Company::query()->find($bill->company_id) : null;

        abort_if($company === null || ! $company->isOperational(), 404);

        $context->set($company);

        return view('commerce.sales.public', $invoice->data($bill, $company));
    }
}
