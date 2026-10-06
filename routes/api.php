<?php

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\Commerce\ItemController as ApiItemController;
use App\Http\Controllers\Api\V1\Commerce\SaleController as ApiSaleController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\DocumentSequenceController;
use App\Http\Controllers\Api\V1\FinancialYearController;
use App\Http\Controllers\Api\V1\Masters\BrandController as MasterBrandController;
use App\Http\Controllers\Api\V1\Masters\CategoryController as MasterCategoryController;
use App\Http\Controllers\Api\V1\Masters\ChargeMethodController as MasterChargeMethodController;
use App\Http\Controllers\Api\V1\Masters\CollectionController as MasterCollectionController;
use App\Http\Controllers\Api\V1\Masters\DesignController as MasterDesignController;
use App\Http\Controllers\Api\V1\Masters\MetalController as MasterMetalController;
use App\Http\Controllers\Api\V1\Masters\PurityController as MasterPurityController;
use App\Http\Controllers\Api\V1\Masters\StoneGradeController as MasterStoneGradeController;
use App\Http\Controllers\Api\V1\Masters\StoneTypeController as MasterStoneTypeController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.')->group(function () {
    Route::post('auth/token', [AuthTokenController::class, 'store'])->middleware('throttle:login');
    Route::post('auth/forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:password-reset');
    Route::post('auth/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:password-reset');

    Route::middleware(['auth:sanctum', 'company.context'])->group(function () {
        Route::delete('auth/token', [AuthTokenController::class, 'destroy']);

        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('profile/password', [ProfileController::class, 'password']);

        Route::apiResource('users', UserController::class);
        Route::apiResource('roles', RoleController::class);

        Route::get('company', [CompanyController::class, 'show']);
        Route::match(['put', 'post'], 'company', [CompanyController::class, 'update']);
        Route::delete('company/logo', [CompanyController::class, 'destroyLogo']);

        Route::apiResource('branches', BranchController::class);

        Route::get('settings', [SettingController::class, 'index']);
        Route::put('settings', [SettingController::class, 'update']);

        Route::apiResource('financial-years', FinancialYearController::class)
            ->parameters(['financial-years' => 'financialYear']);
        Route::post('financial-years/{financialYear}/current', [FinancialYearController::class, 'current']);
        Route::post('financial-years/{financialYear}/close', [FinancialYearController::class, 'close']);

        Route::get('document-sequences', [DocumentSequenceController::class, 'index']);
        Route::get('document-sequences/{documentSequence}', [DocumentSequenceController::class, 'show']);
        Route::put('document-sequences/{documentSequence}', [DocumentSequenceController::class, 'update']);
        Route::get('document-sequences/{documentSequence}/preview', [DocumentSequenceController::class, 'preview']);

        Route::apiResource('categories', MasterCategoryController::class);
        Route::apiResource('brands', MasterBrandController::class);
        Route::apiResource('collections', MasterCollectionController::class);
        Route::apiResource('designs', MasterDesignController::class);
        Route::apiResource('metals', MasterMetalController::class);
        Route::apiResource('metals.purities', MasterPurityController::class);
        Route::apiResource('stone-types', MasterStoneTypeController::class);
        Route::apiResource('stone-grades', MasterStoneGradeController::class)
            ->parameters(['stone-grades' => 'stoneGrade']);
        Route::get('charge-methods', [MasterChargeMethodController::class, 'index']);
        Route::get('charge-methods/{chargeMethod}', [MasterChargeMethodController::class, 'show']);
        Route::put('charge-methods/{chargeMethod}', [MasterChargeMethodController::class, 'update']);
        Route::delete('charge-methods/{chargeMethod}', [MasterChargeMethodController::class, 'destroy']);

        Route::apiResource('items', ApiItemController::class)->only(['index', 'store', 'show']);
        Route::apiResource('sales', ApiSaleController::class)->only(['index', 'store', 'show']);
    });
});
