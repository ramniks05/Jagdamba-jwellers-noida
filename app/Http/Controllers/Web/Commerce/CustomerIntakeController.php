<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Enums\IntakeStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerIntake;
use App\Services\Commerce\CustomerIntakeService;
use App\Support\CompanyContext;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerIntakeController extends Controller
{
    public function qr(CompanyContext $context): View
    {
        $this->authorize('viewAny', Customer::class);
        $company = $context->company();
        $url = route('customer-form.create', $company);
        $options = new QROptions([
            'outputBase64' => true,
            'scale' => 8,
        ]);

        return view('commerce.customers.qr', [
            'company' => $company,
            'url' => $url,
            'qr' => (new QRCode($options))->render($url),
        ]);
    }

    public function index(): View
    {
        $this->authorize('viewAny', Customer::class);

        return view('commerce.customers.intakes', [
            'intakes' => CustomerIntake::query()->where('status', IntakeStatus::Pending)->orderBy('id')->paginate(20),
        ]);
    }

    public function approve(CustomerIntake $intake, CustomerIntakeService $intakes): RedirectResponse
    {
        $this->authorize('create', Customer::class);
        $customer = $intakes->approve($intake, request()->user()?->id);

        return redirect()->route('customers.show', $customer)->with('status', $customer->name.' added as a customer.');
    }

    public function reject(CustomerIntake $intake, CustomerIntakeService $intakes): RedirectResponse
    {
        $this->authorize('create', Customer::class);
        $intakes->reject($intake, request()->user()?->id);

        return redirect()->route('customer-intakes.index')->with('status', 'Form set aside.');
    }
}
