<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyContext
{
    public function __construct(private readonly CompanyContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->deny($request, 'Authentication is required.', 401);
        }

        if (! $user->is_active) {
            $this->revoke($request);

            return $this->deny($request, 'This account is inactive.', 403);
        }

        $company = $user->company;

        if (! $company instanceof Company) {
            return $this->deny($request, 'This account is not linked to a shop.', 403);
        }

        if (! $company->isOperational()) {
            return $this->deny($request, 'This shop cannot be used right now.', 403);
        }

        $this->context->set($company);

        if (in_array($company->timezone, timezone_identifiers_list(), true)) {
            config(['app.timezone' => $company->timezone]);
            date_default_timezone_set($company->timezone);
        }

        view()->share('currentCompany', $company);

        return $next($request);
    }

    private function revoke(Request $request): void
    {
        $user = $request->user();
        $token = $user && method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }
    }

    private function deny(Request $request, string $message, int $status): Response
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
