<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Overview') — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <div class="app-shell">
        <aside class="offcanvas-lg offcanvas-start app-sidebar" tabindex="-1" id="sidebar">
            <div class="offcanvas-header d-lg-none">
                <div class="brand">{{ config('app.name') }}</div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body d-flex flex-column p-3">
                <div class="brand d-none d-lg-block mb-1">{{ config('app.name') }}</div>
                <div class="shop-name small mb-3">{{ $currentCompany->name ?? 'Jewellery shop' }}</div>
                <nav class="nav flex-column gap-1">
                    <a class="nav-link {{ request()->routeIs('overview') ? 'active' : '' }}" href="{{ route('overview') }}">Overview</a>
                    @can('update', $currentCompany)
                        <a class="nav-link {{ request()->routeIs('company.*') ? 'active' : '' }}" href="{{ route('company.edit') }}">Shop profile</a>
                    @endcan
                    @can('viewAny', App\Models\Branch::class)
                        <a class="nav-link {{ request()->routeIs('branches.*') ? 'active' : '' }}" href="{{ route('branches.index') }}">Branches</a>
                    @endcan
                    @can('viewAny', App\Models\Setting::class)
                        <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.edit') }}">Settings</a>
                    @endcan
                    @can('viewAny', App\Models\FinancialYear::class)
                        <a class="nav-link {{ request()->routeIs('financial-years.*') ? 'active' : '' }}" href="{{ route('financial-years.index') }}">Financial years</a>
                    @endcan
                    @can('viewAny', App\Models\DocumentSequence::class)
                        <a class="nav-link {{ request()->routeIs('document-sequences.*') ? 'active' : '' }}" href="{{ route('document-sequences.index') }}">Document numbers</a>
                    @endcan
                    @can('masters.view')
                        <div class="menu-label mt-3 mb-1 px-2">Masters</div>
                        <a class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}" href="{{ route('categories.index') }}">Categories</a>
                        <a class="nav-link {{ request()->routeIs('brands.*') ? 'active' : '' }}" href="{{ route('brands.index') }}">Brands</a>
                        <a class="nav-link {{ request()->routeIs('collections.*') ? 'active' : '' }}" href="{{ route('collections.index') }}">Collections</a>
                        <a class="nav-link {{ request()->routeIs('designs.*') ? 'active' : '' }}" href="{{ route('designs.index') }}">Designs</a>
                        <a class="nav-link {{ request()->routeIs('metals.*') ? 'active' : '' }}" href="{{ route('metals.index') }}">Metals</a>
                        <a class="nav-link {{ request()->routeIs('stones.*', 'stone-types.*', 'stone-grades.*') ? 'active' : '' }}" href="{{ route('stones.index') }}">Stones</a>
                        <a class="nav-link {{ request()->routeIs('charge-methods.*') ? 'active' : '' }}" href="{{ route('charge-methods.index') }}">Making and wastage</a>
                    @endcan
                    @if (auth()->user()->can('items.view') || auth()->user()->can('sales.view') || auth()->user()->can('customers.view') || auth()->user()->can('reports.view'))
                        <div class="menu-label mt-3 mb-1 px-2">Shop</div>
                    @endif
                    @can('items.view')
                        <a class="nav-link {{ request()->routeIs('items.*') ? 'active' : '' }}" href="{{ route('items.index') }}">Pieces</a>
                    @endcan
                    @can('rates.view')
                        <a class="nav-link {{ request()->routeIs('rates.*') ? 'active' : '' }}" href="{{ route('rates.index') }}">Metal rates</a>
                    @endcan
                    @can('inventory.view')
                        <a class="nav-link {{ request()->routeIs('locations.*') ? 'active' : '' }}" href="{{ route('locations.index') }}">Locations</a>
                    @endcan
                    @can('customers.view')
                        <a class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}" href="{{ route('customers.index') }}">Customers</a>
                    @endcan
                    @can('suppliers.view')
                        <a class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}" href="{{ route('suppliers.index') }}">Suppliers</a>
                    @endcan
                    @can('sales.view')
                        <a class="nav-link {{ request()->routeIs('sales.*') ? 'active' : '' }}" href="{{ route('sales.index') }}">Sales</a>
                        <a class="nav-link {{ request()->routeIs('old-gold.*') ? 'active' : '' }}" href="{{ route('old-gold.index') }}">Old gold</a>
                    @endcan
                    @can('purchase.view')
                        <a class="nav-link {{ request()->routeIs('purchases.*') ? 'active' : '' }}" href="{{ route('purchases.index') }}">Purchases</a>
                    @endcan
                    @can('repairs.view')
                        <a class="nav-link {{ request()->routeIs('repairs.*') ? 'active' : '' }}" href="{{ route('repairs.index') }}">Repairs</a>
                    @endcan
                    @can('schemes.view')
                        <a class="nav-link {{ request()->routeIs('schemes.*') || request()->routeIs('enrollments.*') ? 'active' : '' }}" href="{{ route('schemes.index') }}">Schemes</a>
                    @endcan
                    @can('reports.view')
                        <a class="nav-link {{ request()->routeIs('reports.stock') ? 'active' : '' }}" href="{{ route('reports.stock') }}">Stock report</a>
                        <a class="nav-link {{ request()->routeIs('reports.sales') ? 'active' : '' }}" href="{{ route('reports.sales') }}">Sales report</a>
                        <a class="nav-link {{ request()->routeIs('reports.outstanding') ? 'active' : '' }}" href="{{ route('reports.outstanding') }}">Outstanding</a>
                    @endcan
                    @if (auth()->user()->can('users.view') || auth()->user()->can('roles.view'))
                        <div class="menu-label mt-3 mb-1 px-2">Access</div>
                    @endif
                    @can('users.view')
                        <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">Users</a>
                    @endcan
                    @can('roles.view')
                        <a class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}" href="{{ route('roles.index') }}">Roles</a>
                    @endcan
                    <a class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.edit') }}">Profile</a>
                </nav>
                <form class="mt-auto pt-4" method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-outline-light btn-sm w-100" type="submit">Log out</button>
                </form>
            </div>
        </aside>
        <main class="app-main">
            <header class="border-bottom bg-white">
                <div class="container-fluid py-3 d-flex justify-content-between align-items-center">
                    <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar">Menu</button>
                    <a class="ms-auto small text-secondary" href="{{ route('profile.edit') }}">{{ auth()->user()->name }}</a>
                </div>
            </header>
            <div class="container-fluid py-4">
                @include('partials.alerts')
                @yield('content')
            </div>
        </main>
    </div>
    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
