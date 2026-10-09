<?php

use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\PasswordResetController;
use App\Http\Controllers\Web\BranchController;
use App\Http\Controllers\Web\Commerce\AdvanceOrderController;
use App\Http\Controllers\Web\Commerce\BillLinkController;
use App\Http\Controllers\Web\Commerce\CustomerController as ShopCustomerController;
use App\Http\Controllers\Web\Commerce\CustomerFormController;
use App\Http\Controllers\Web\Commerce\CustomerIntakeController;
use App\Http\Controllers\Web\Commerce\GirviController;
use App\Http\Controllers\Web\Commerce\ItemController;
use App\Http\Controllers\Web\Commerce\LocationController;
use App\Http\Controllers\Web\Commerce\OldGoldController;
use App\Http\Controllers\Web\Commerce\OldGoldStockController;
use App\Http\Controllers\Web\Commerce\PurchaseController;
use App\Http\Controllers\Web\Commerce\RateController;
use App\Http\Controllers\Web\Commerce\RepairController;
use App\Http\Controllers\Web\Commerce\ReportController;
use App\Http\Controllers\Web\Commerce\SaleController;
use App\Http\Controllers\Web\Commerce\SchemeController;
use App\Http\Controllers\Web\Commerce\SupplierController;
use App\Http\Controllers\Web\CompanyProfileController;
use App\Http\Controllers\Web\DocumentSequenceController;
use App\Http\Controllers\Web\FinancialYearController;
use App\Http\Controllers\Web\Masters\BrandController;
use App\Http\Controllers\Web\Masters\CategoryController;
use App\Http\Controllers\Web\Masters\ChargeMethodController;
use App\Http\Controllers\Web\Masters\CollectionController;
use App\Http\Controllers\Web\Masters\DesignController;
use App\Http\Controllers\Web\Masters\MetalController;
use App\Http\Controllers\Web\Masters\PurityController;
use App\Http\Controllers\Web\Masters\StoneDirectoryController;
use App\Http\Controllers\Web\Masters\StoneGradeController;
use App\Http\Controllers\Web\Masters\StoneTypeController;
use App\Http\Controllers\Web\OverviewController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\SettingController;
use App\Http\Controllers\Web\UserController;
use Illuminate\Support\Facades\Route;

Route::get('customer-form/{company}', [CustomerFormController::class, 'create'])->name('customer-form.create');
Route::get('bill/{sale}', [BillLinkController::class, 'show'])->middleware(['signed', 'throttle:60,1'])->name('bills.show');
Route::post('customer-form/{company}', [CustomerFormController::class, 'store'])->middleware('throttle:customer-form')->name('customer-form.store');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:password-reset')->name('password.update');
});

Route::middleware(['auth', 'company.context'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/', [OverviewController::class, 'index'])->name('overview');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');

    Route::resource('users', UserController::class)->except(['show']);
    Route::resource('roles', RoleController::class)->except(['show']);

    Route::get('company', [CompanyProfileController::class, 'edit'])->name('company.edit');
    Route::put('company', [CompanyProfileController::class, 'update'])->name('company.update');

    Route::resource('branches', BranchController::class)->except(['show']);

    Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

    Route::resource('financial-years', FinancialYearController::class)
        ->parameters(['financial-years' => 'financialYear'])
        ->except(['show']);
    Route::post('financial-years/{financialYear}/current', [FinancialYearController::class, 'current'])->name('financial-years.current');
    Route::post('financial-years/{financialYear}/close', [FinancialYearController::class, 'close'])->name('financial-years.close');

    Route::get('document-sequences', [DocumentSequenceController::class, 'index'])->name('document-sequences.index');
    Route::get('document-sequences/{documentSequence}/edit', [DocumentSequenceController::class, 'edit'])->name('document-sequences.edit');
    Route::put('document-sequences/{documentSequence}', [DocumentSequenceController::class, 'update'])->name('document-sequences.update');

    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::resource('brands', BrandController::class)->except(['show']);
    Route::resource('collections', CollectionController::class)->except(['show']);
    Route::resource('designs', DesignController::class)->except(['show']);
    Route::resource('metals', MetalController::class)->except(['show']);
    Route::resource('metals.purities', PurityController::class)->except(['show']);

    Route::get('stones', [StoneDirectoryController::class, 'index'])->name('stones.index');
    Route::resource('stone-types', StoneTypeController::class)->except(['index', 'show']);
    Route::resource('stone-grades', StoneGradeController::class)
        ->parameters(['stone-grades' => 'stoneGrade'])
        ->except(['index', 'show']);

    Route::get('charge-methods', [ChargeMethodController::class, 'index'])->name('charge-methods.index');
    Route::get('charge-methods/{chargeMethod}/edit', [ChargeMethodController::class, 'edit'])->name('charge-methods.edit');
    Route::put('charge-methods/{chargeMethod}', [ChargeMethodController::class, 'update'])->name('charge-methods.update');
    Route::delete('charge-methods/{chargeMethod}', [ChargeMethodController::class, 'destroy'])->name('charge-methods.destroy');

    Route::resource('items', ItemController::class)->except(['destroy']);
    Route::post('items/{item}/reserve', [ItemController::class, 'reserve'])->name('items.reserve');
    Route::post('items/{item}/release', [ItemController::class, 'release'])->name('items.release');
    Route::post('items/{item}/damage', [ItemController::class, 'damage'])->name('items.damage');
    Route::post('items/{item}/lost', [ItemController::class, 'lost'])->name('items.lost');
    Route::post('items/{item}/restore', [ItemController::class, 'restore'])->name('items.restore');
    Route::get('items/{item}/photo', [ItemController::class, 'photo'])->name('items.photo');

    Route::get('rates', [RateController::class, 'index'])->name('rates.index');
    Route::post('rates/market', [RateController::class, 'refresh'])->name('rates.market');
    Route::post('rates', [RateController::class, 'store'])->name('rates.store');
    Route::post('rates/from-market', [RateController::class, 'storeMarket'])->name('rates.market.store');

    Route::resource('locations', LocationController::class)->except(['show', 'destroy']);
    Route::get('customers/qr', [CustomerIntakeController::class, 'qr'])->name('customers.qr');
    Route::get('customer-intakes', [CustomerIntakeController::class, 'index'])->name('customer-intakes.index');
    Route::post('customer-intakes/{intake}/approve', [CustomerIntakeController::class, 'approve'])->name('customer-intakes.approve');
    Route::post('customer-intakes/{intake}/reject', [CustomerIntakeController::class, 'reject'])->name('customer-intakes.reject');
    Route::resource('customers', ShopCustomerController::class);
    Route::post('customers/{customer}/payments', [ShopCustomerController::class, 'payment'])->name('customers.payments.store');
    Route::resource('suppliers', SupplierController::class);
    Route::post('suppliers/{supplier}/payments', [SupplierController::class, 'payment'])->name('suppliers.payments.store');
    Route::resource('sales', SaleController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('sales/{sale}/returns', [SaleController::class, 'returnSale'])->name('sales.returns.store');
    Route::post('sales/{sale}/payments', [SaleController::class, 'payment'])->name('sales.payments.store');
    Route::post('sales/{sale}/whatsapp', [SaleController::class, 'whatsapp'])->middleware('throttle:10,1')->name('sales.whatsapp.store');
    Route::resource('purchases', PurchaseController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('purchases/{purchase}/return', [PurchaseController::class, 'returnToSupplier'])->name('purchases.return');
    Route::post('purchases/{purchase}/payments', [PurchaseController::class, 'pay'])->name('purchases.payments.store');
    Route::get('old-gold', [OldGoldController::class, 'index'])->name('old-gold.index');
    Route::get('old-gold/create', [OldGoldController::class, 'create'])->name('old-gold.create');
    Route::get('old-gold/stock', [OldGoldStockController::class, 'index'])->name('old-gold.stock');
    Route::post('old-gold/stock', [OldGoldStockController::class, 'store'])->name('old-gold.stock.send');
    Route::post('old-gold', [OldGoldController::class, 'store'])->name('old-gold.store');
    Route::get('old-gold/{exchange}', [OldGoldController::class, 'show'])->name('old-gold.show');
    Route::resource('repairs', RepairController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('repairs/{repair}/status', [RepairController::class, 'status'])->name('repairs.status');
    Route::post('repairs/{repair}/deliver', [RepairController::class, 'deliver'])->name('repairs.deliver');
    Route::resource('schemes', SchemeController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('girvi', [GirviController::class, 'index'])->name('girvi.index');
    Route::get('girvi/create', [GirviController::class, 'create'])->name('girvi.create');
    Route::post('girvi', [GirviController::class, 'store'])->name('girvi.store');
    Route::get('girvi/{pledge}', [GirviController::class, 'show'])->name('girvi.show');
    Route::post('girvi/{pledge}/settle', [GirviController::class, 'settle'])->name('girvi.settle');
    Route::get('orders', [AdvanceOrderController::class, 'index'])->name('orders.index');
    Route::get('orders/create', [AdvanceOrderController::class, 'create'])->name('orders.create');
    Route::post('orders', [AdvanceOrderController::class, 'store'])->name('orders.store');
    Route::get('orders/{order}', [AdvanceOrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/advance', [AdvanceOrderController::class, 'advance'])->name('orders.advance');
    Route::post('orders/{order}/ready', [AdvanceOrderController::class, 'ready'])->name('orders.ready');
    Route::post('orders/{order}/cancel', [AdvanceOrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('schemes/{scheme}/enroll', [SchemeController::class, 'enroll'])->name('schemes.enroll');
    Route::get('enrollments/{enrollment}', [SchemeController::class, 'enrollment'])->name('enrollments.show');
    Route::post('enrollments/{enrollment}/installments', [SchemeController::class, 'installment'])->name('enrollments.installments.store');
    Route::post('enrollments/{enrollment}/mature', [SchemeController::class, 'mature'])->name('enrollments.mature');

    Route::get('reports/stock', [ReportController::class, 'stock'])->name('reports.stock');
    Route::get('reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
    Route::get('reports/outstanding', [ReportController::class, 'outstanding'])->name('reports.outstanding');
});
