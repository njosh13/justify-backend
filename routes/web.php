<?php

use App\Http\Controllers\Admin\AroVersionController;
use App\Http\Controllers\Admin\PublishedAroVersionController;
use App\Http\Controllers\Admin\ReviewedAroVersionController;
use App\Http\Controllers\AroItemController;
use App\Http\Controllers\AroPreviewController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\BillDocumentController;
use App\Http\Controllers\BillInterestClaimController;
use App\Http\Controllers\BillPaymentController;
use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\ChargeableItemController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CurrentFirmController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveredBillController;
use App\Http\Controllers\FeeAgreementController;
use App\Http\Controllers\FirmController;
use App\Http\Controllers\IssuedBillController;
use App\Http\Controllers\MatterClassificationController;
use App\Http\Controllers\MatterController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('firms/create', [FirmController::class, 'create'])->name('firms.create');
    Route::post('firms', [FirmController::class, 'store'])->name('firms.store');
    Route::put('current-firm', [CurrentFirmController::class, 'update'])->name('current-firm.update');
});

Route::middleware(['auth', 'verified', 'firm'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('settings/firm', [FirmController::class, 'edit'])->name('firm.edit');
    Route::patch('settings/firm', [FirmController::class, 'update'])->name('firm.update');

    Route::get('calculator', [CalculatorController::class, 'index'])->name('calculator.index');
    Route::post('aro/preview', AroPreviewController::class)->name('aro.preview');
    Route::get('aro/items', [AroItemController::class, 'index'])->name('aro.items');

    Route::resource('clients', ClientController::class)->only(['index', 'create', 'store', 'edit', 'update']);

    Route::resource('matters', MatterController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::put('matters/{matter}/classification', [MatterClassificationController::class, 'update'])->name('matters.classification.update');
    Route::post('matters/{matter}/fee-agreements', [FeeAgreementController::class, 'store'])->name('matters.fee-agreements.store');
    Route::delete('matters/{matter}/fee-agreements/{feeAgreement}', [FeeAgreementController::class, 'destroy'])->name('matters.fee-agreements.destroy')->scopeBindings();
    Route::post('matters/{matter}/chargeable-items', [ChargeableItemController::class, 'store'])->name('matters.chargeable-items.store');
    Route::put('matters/{matter}/chargeable-items/{chargeableItem}', [ChargeableItemController::class, 'update'])->name('matters.chargeable-items.update')->scopeBindings();
    Route::delete('matters/{matter}/chargeable-items/{chargeableItem}', [ChargeableItemController::class, 'destroy'])->name('matters.chargeable-items.destroy')->scopeBindings();
    Route::post('matters/{matter}/bills', [BillController::class, 'store'])->name('matters.bills.store');

    Route::resource('bills', BillController::class)->only(['index', 'show', 'destroy']);
    Route::post('bills/{bill}/issue', [IssuedBillController::class, 'store'])->name('bills.issue');
    Route::post('bills/{bill}/deliver', [DeliveredBillController::class, 'store'])->name('bills.deliver');
    Route::post('bills/{bill}/claim-interest', [BillInterestClaimController::class, 'store'])->name('bills.claim-interest');
    Route::post('bills/{bill}/payments', [BillPaymentController::class, 'store'])->name('bills.payments.store');
    Route::get('bills/{bill}/preview', [BillDocumentController::class, 'preview'])->name('bills.preview');
    Route::get('bills/{bill}/pdf', [BillDocumentController::class, 'pdf'])->name('bills.pdf');

    Route::prefix('admin/aro')->name('admin.aro.')->group(function () {
        Route::get('/', [AroVersionController::class, 'index'])->name('index');
        Route::get('{aroVersion}', [AroVersionController::class, 'show'])->name('show');
        Route::post('{aroVersion}/review', [ReviewedAroVersionController::class, 'store'])->name('review');
        Route::post('{aroVersion}/publish', [PublishedAroVersionController::class, 'store'])->name('publish');
    });
});

require __DIR__.'/settings.php';
