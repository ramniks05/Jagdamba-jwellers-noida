<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Overview') — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
</head>
<body>
    <div class="app-shell">
        <aside class="offcanvas-lg offcanvas-start app-sidebar" tabindex="-1" id="sidebar">
            <div class="offcanvas-header d-lg-none">
                <div class="brand">{{ config('app.name') }}</div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body d-flex flex-column p-0">
                <div class="sidebar-brand">
                    <span class="brand-mark">JJ</span>
                    <div>
                        <div class="brand">{{ config('app.name') }}</div>
                        <div class="shop-name">{{ $currentCompany->name ?? 'Jewellery shop' }}</div>
                    </div>
                </div>
                <nav class="sidebar-nav nav flex-column">
                    <a class="nav-link {{ request()->routeIs('overview') ? 'active' : '' }}" href="{{ route('overview') }}"><i class="bi bi-grid-1x2"></i><span>Overview</span></a>
                    @can('create', App\Models\Sale::class)
                        <a class="nav-bill" href="{{ route('sales.create') }}"><i class="bi bi-plus-lg"></i><span>New bill</span></a>
                    @endcan
                    @if (auth()->user()->can('items.view') || auth()->user()->can('sales.view') || auth()->user()->can('customers.view'))
                        <div class="menu-label">Counter</div>
                    @endif
                    @can('sales.view')
                        <a class="nav-link {{ request()->routeIs('sales.*') && ! request()->routeIs('sales.create') ? 'active' : '' }}" href="{{ route('sales.index') }}"><i class="bi bi-receipt"></i><span>Sales</span></a>
                    @endcan
                    @can('customers.view')
                        <a class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}" href="{{ route('customers.index') }}"><i class="bi bi-people"></i><span>Customers</span></a>
                    @endcan
                    @can('items.view')
                        <a class="nav-link {{ request()->routeIs('items.*') ? 'active' : '' }}" href="{{ route('items.index') }}"><i class="bi bi-box-seam"></i><span>Pieces</span></a>
                    @endcan
                    @can('rates.view')
                        <a class="nav-link {{ request()->routeIs('rates.*') ? 'active' : '' }}" href="{{ route('rates.index') }}"><i class="bi bi-graph-up-arrow"></i><span>Metal rates</span></a>
                    @endcan
                    @can('purchase.view')
                        <a class="nav-link {{ request()->routeIs('purchases.*') ? 'active' : '' }}" href="{{ route('purchases.index') }}"><i class="bi bi-bag"></i><span>Purchases</span></a>
                    @endcan
                    @can('sales.view')
                        <a class="nav-link {{ request()->routeIs('old-gold.*') ? 'active' : '' }}" href="{{ route('old-gold.index') }}"><i class="bi bi-arrow-repeat"></i><span>Old gold</span></a>
                    @endcan
                    @can('repairs.view')
                        <a class="nav-link {{ request()->routeIs('repairs.*') ? 'active' : '' }}" href="{{ route('repairs.index') }}"><i class="bi bi-tools"></i><span>Repairs</span></a>
                    @endcan
                    @can('schemes.view')
                        <a class="nav-link {{ request()->routeIs('schemes.*') || request()->routeIs('enrollments.*') ? 'active' : '' }}" href="{{ route('schemes.index') }}"><i class="bi bi-piggy-bank"></i><span>Schemes</span></a>
                    @endcan
                    @can('sales.view')
                        <a class="nav-link {{ request()->routeIs('girvi.*') ? 'active' : '' }}" href="{{ route('girvi.index') }}"><i class="bi bi-safe"></i><span>Girvi</span></a>
                    @endcan
                    @can('suppliers.view')
                        <a class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}" href="{{ route('suppliers.index') }}"><i class="bi bi-truck"></i><span>Suppliers</span></a>
                    @endcan
                    @can('inventory.view')
                        <a class="nav-link {{ request()->routeIs('locations.*') ? 'active' : '' }}" href="{{ route('locations.index') }}"><i class="bi bi-geo-alt"></i><span>Locations</span></a>
                    @endcan
                    @can('reports.view')
                        <div class="menu-label">Reports</div>
                        <a class="nav-link {{ request()->routeIs('reports.stock') ? 'active' : '' }}" href="{{ route('reports.stock') }}"><i class="bi bi-clipboard-data"></i><span>Stock</span></a>
                        <a class="nav-link {{ request()->routeIs('reports.sales') ? 'active' : '' }}" href="{{ route('reports.sales') }}"><i class="bi bi-bar-chart"></i><span>Sales</span></a>
                        <a class="nav-link {{ request()->routeIs('reports.outstanding') ? 'active' : '' }}" href="{{ route('reports.outstanding') }}"><i class="bi bi-cash-coin"></i><span>Outstanding</span></a>
                    @endcan
                    @can('masters.view')
                        <div class="menu-label">Masters</div>
                        <a class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}" href="{{ route('categories.index') }}"><i class="bi bi-tags"></i><span>Categories</span></a>
                        <a class="nav-link {{ request()->routeIs('brands.*') ? 'active' : '' }}" href="{{ route('brands.index') }}"><i class="bi bi-award"></i><span>Brands</span></a>
                        <a class="nav-link {{ request()->routeIs('collections.*') ? 'active' : '' }}" href="{{ route('collections.index') }}"><i class="bi bi-collection"></i><span>Collections</span></a>
                        <a class="nav-link {{ request()->routeIs('designs.*') ? 'active' : '' }}" href="{{ route('designs.index') }}"><i class="bi bi-gem"></i><span>Designs</span></a>
                        <a class="nav-link {{ request()->routeIs('metals.*') ? 'active' : '' }}" href="{{ route('metals.index') }}"><i class="bi bi-circle"></i><span>Metals</span></a>
                        <a class="nav-link {{ request()->routeIs('stones.*', 'stone-types.*', 'stone-grades.*') ? 'active' : '' }}" href="{{ route('stones.index') }}"><i class="bi bi-diamond"></i><span>Stones</span></a>
                        <a class="nav-link {{ request()->routeIs('charge-methods.*') ? 'active' : '' }}" href="{{ route('charge-methods.index') }}"><i class="bi bi-percent"></i><span>Making and wastage</span></a>
                    @endcan
                    <div class="menu-label">Setup</div>
                    @can('update', $currentCompany)
                        <a class="nav-link {{ request()->routeIs('company.*') ? 'active' : '' }}" href="{{ route('company.edit') }}"><i class="bi bi-shop"></i><span>Shop profile</span></a>
                    @endcan
                    @can('viewAny', App\Models\Branch::class)
                        <a class="nav-link {{ request()->routeIs('branches.*') ? 'active' : '' }}" href="{{ route('branches.index') }}"><i class="bi bi-buildings"></i><span>Branches</span></a>
                    @endcan
                    @can('viewAny', App\Models\Setting::class)
                        <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.edit') }}"><i class="bi bi-sliders"></i><span>Settings</span></a>
                    @endcan
                    @can('viewAny', App\Models\FinancialYear::class)
                        <a class="nav-link {{ request()->routeIs('financial-years.*') ? 'active' : '' }}" href="{{ route('financial-years.index') }}"><i class="bi bi-calendar3"></i><span>Financial years</span></a>
                    @endcan
                    @can('viewAny', App\Models\DocumentSequence::class)
                        <a class="nav-link {{ request()->routeIs('document-sequences.*') ? 'active' : '' }}" href="{{ route('document-sequences.index') }}"><i class="bi bi-hash"></i><span>Document numbers</span></a>
                    @endcan
                    @can('users.view')
                        <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}"><i class="bi bi-person-badge"></i><span>Users</span></a>
                    @endcan
                    @can('roles.view')
                        <a class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}" href="{{ route('roles.index') }}"><i class="bi bi-shield-lock"></i><span>Roles</span></a>
                    @endcan
                    <a class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.edit') }}"><i class="bi bi-person"></i><span>Profile</span></a>
                </nav>
                <form class="sidebar-foot" method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-outline-light btn-sm w-100" type="submit"><i class="bi bi-box-arrow-right"></i> Log out</button>
                </form>
            </div>
        </aside>
        <main class="app-main">
            <header class="app-topbar">
                <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar"><i class="bi bi-list"></i> Menu</button>
                <div class="topbar-date"><i class="bi bi-calendar-event"></i> {{ now()->timezone(config('app.timezone'))->format('D, d M Y') }}</div>
                @php
                    $initials = collect(preg_split('/\s+/', trim((string) auth()->user()->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('');
                @endphp
                <a class="user-chip" href="{{ route('profile.edit') }}"><span class="user-avatar">{{ $initials }}</span>{{ auth()->user()->name }}</a>
            </header>
            <div class="app-content container-fluid">
                @include('partials.alerts')
                @yield('content')
            </div>
        </main>
    </div>
    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    @stack('scripts')
</body>
</html>
