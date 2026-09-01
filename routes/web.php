<?php

use App\Http\Controllers\Web\WebController;
use Illuminate\Support\Facades\Route;

// Customer web storefront (PWA-ready)
Route::name('web.')->group(function (): void {
    Route::get('/', [WebController::class, 'home'])->name('home');
    Route::get('/explore', [WebController::class, 'explore'])->name('explore');
    Route::get('/jasa/{service:slug}', [WebController::class, 'service'])->name('service');
    Route::get('/halaman/{slug}', [WebController::class, 'page'])->name('page');

    Route::middleware('auth')->group(function (): void {
        Route::post('/checkout', [WebController::class, 'checkout'])->name('checkout');
        Route::get('/orders', [WebController::class, 'orders'])->name('orders');
        Route::get('/orders/{id}', [WebController::class, 'orderShow'])->name('orders.show');
        Route::post('/orders/{id}/pay', [WebController::class, 'pay'])->name('orders.pay');
        Route::post('/orders/{id}/confirm', [WebController::class, 'confirm'])->name('orders.confirm');
        Route::post('/orders/{id}/checkin', [WebController::class, 'checkin'])->name('orders.checkin');
        Route::post('/orders/{id}/cancel', [WebController::class, 'cancel'])->name('orders.cancel');
        Route::post('/orders/{id}/review', [WebController::class, 'review'])->name('orders.review');
    });
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/auth.php';
