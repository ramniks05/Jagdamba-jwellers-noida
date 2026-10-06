<?php

namespace App\Providers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\ChargeMethod;
use App\Models\Collection;
use App\Models\Customer;
use App\Models\Design;
use App\Models\GoldScheme;
use App\Models\Item;
use App\Models\MetalRate;
use App\Models\MetalType;
use App\Models\OldGoldExchange;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Purity;
use App\Models\RepairOrder;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SchemeEnrollment;
use App\Models\StockLocation;
use App\Models\StoneGrade;
use App\Models\StoneType;
use App\Models\Supplier;
use App\Models\User;
use App\Policies\CustomerPolicy;
use App\Policies\ItemPolicy;
use App\Policies\MasterDataPolicy;
use App\Policies\MetalRatePolicy;
use App\Policies\OldGoldPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PurchasePolicy;
use App\Policies\RepairPolicy;
use App\Policies\SalePolicy;
use App\Policies\SaleReturnPolicy;
use App\Policies\SchemePolicy;
use App\Policies\StockLocationPolicy;
use App\Policies\SupplierPolicy;
use App\Services\Foundation\DocumentNumberService;
use App\Services\Foundation\NumberFormatService;
use App\Services\Foundation\SettingService;
use App\Support\CompanyContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CompanyContext::class);
        $this->app->scoped(SettingService::class);
        $this->app->scoped(DocumentNumberService::class);
        $this->app->scoped(NumberFormatService::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        PasswordRule::defaults(fn () => PasswordRule::min(10)->mixedCase()->numbers()->symbols());

        ResetPassword::createUrlUsing(function (User $user, string $token) {
            return route('password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });

        foreach ([
            Category::class,
            Brand::class,
            Collection::class,
            Design::class,
            MetalType::class,
            Purity::class,
            StoneType::class,
            StoneGrade::class,
            ChargeMethod::class,
        ] as $model) {
            Gate::policy($model, MasterDataPolicy::class);
        }

        Gate::policy(Item::class, ItemPolicy::class);
        Gate::policy(StockLocation::class, StockLocationPolicy::class);
        Gate::policy(MetalRate::class, MetalRatePolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(Sale::class, SalePolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Purchase::class, PurchasePolicy::class);
        Gate::policy(SaleReturn::class, SaleReturnPolicy::class);
        Gate::policy(OldGoldExchange::class, OldGoldPolicy::class);
        Gate::policy(RepairOrder::class, RepairPolicy::class);
        Gate::policy(GoldScheme::class, SchemePolicy::class);
        Gate::policy(SchemeEnrollment::class, SchemePolicy::class);

        Gate::before(function (User $user, string $ability) {
            if (! str_contains($ability, '.')) {
                return null;
            }

            $user->loadMissing('company');

            return $user->is_active
                && (bool) $user->company?->isOperational()
                && $user->hasPermission($ability);
        });

        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by((string) ($request->user()?->id ?: $request->ip()));
        });
    }
}
