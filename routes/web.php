<?php

use App\Http\Controllers\Web;
use Illuminate\Support\Facades\Route;

// ============ Customer Storefront ============
Route::name('web.')->group(function (): void {
    Route::get('/', [Web\WebController::class, 'home'])->name('home');
    Route::get('/explore', [Web\ExploreWebController::class, 'index'])->name('explore');
    Route::get('/jasa/{service:slug}', [Web\WebController::class, 'service'])->name('service');
    Route::get('/jasa/{category}/{city}', [Web\ExploreWebController::class, 'seoLanding'])->name('seo.landing-city');
    Route::get('/sitemap.xml', [Web\ExploreWebController::class, 'sitemap'])->name('seo.sitemap');

    // robots.txt rendered as a view so the Sitemap URL follows the current host
    Route::get('/robots.txt', fn () => response()
        ->view('web.seo.robots', [], 200)
        ->header('Content-Type', 'text/plain; charset=UTF-8'))
        ->name('seo.robots');
    Route::get('/halaman/{slug}', [Web\WebController::class, 'page'])->name('page');
    Route::get('/penyedia/{slug}', [Web\ProviderWebController::class, 'show'])->name('provider.show');
    Route::get('/blog', [Web\WebController::class, 'blogIndex'])->name('blog.index');
    Route::get('/blog/{slug}', [Web\WebController::class, 'blogShow'])->name('blog.show');
    Route::get('/search/suggest', [Web\ExploreWebController::class, 'suggest'])->name('search.suggest');

    // Business (public landing)
    Route::get('/business', [Web\BusinessWebController::class, 'landing'])->name('business.landing');

    Route::middleware('auth')->group(function (): void {
        // Checkout & orders
        Route::post('/checkout', [Web\WebController::class, 'checkout'])->name('checkout');
        Route::get('/orders', [Web\AccountWebController::class, 'orders'])->name('orders');
        Route::get('/orders/{id}', [Web\WebController::class, 'orderShow'])->name('orders.show');
        Route::post('/orders/{id}/pay', [Web\WebController::class, 'pay'])->name('orders.pay');
        Route::post('/orders/{id}/confirm', [Web\WebController::class, 'confirm'])->name('orders.confirm');
        Route::post('/orders/{id}/checkin', [Web\WebController::class, 'checkin'])->name('orders.checkin');
        Route::post('/orders/{id}/cancel', [Web\WebController::class, 'cancel'])->name('orders.cancel');
        Route::post('/orders/{id}/review', [Web\WebController::class, 'review'])->name('orders.review');

        // Favorites
        Route::get('/favorit', [Web\FavoriteWebController::class, 'index'])->name('favorites');
        Route::post('/favorit/toggle', [Web\FavoriteWebController::class, 'toggle'])->name('favorites.toggle');

        // Account center
        Route::prefix('akun')->name('account.')->group(function (): void {
            Route::get('/', [Web\AccountWebController::class, 'dashboard'])->name('dashboard');
            Route::get('/profil', [Web\AccountWebController::class, 'profile'])->name('profile');
            Route::get('/notifikasi', [Web\AccountWebController::class, 'notifications'])->name('notifications');
            Route::post('/alamat', [Web\AccountWebController::class, 'storeAddress'])->name('address.store');
            Route::delete('/alamat/{id}', [Web\AccountWebController::class, 'destroyAddress'])->name('address.destroy');
        });

        // Service requests (Posting Kebutuhan → RFQ)
        Route::prefix('kebutuhan')->name('requests.')->group(function (): void {
            Route::get('/', [Web\RequestWebController::class, 'index'])->name('index');
            Route::get('/buat', [Web\RequestWebController::class, 'create'])->name('create');
            Route::post('/', [Web\RequestWebController::class, 'store'])->name('store');
            Route::get('/{id}', [Web\RequestWebController::class, 'show'])->name('show');
            Route::post('/{id}/tutup', [Web\RequestWebController::class, 'close'])->name('close');
            Route::post('/{id}/penawaran/{quotationId}/terima', [Web\RequestWebController::class, 'acceptQuotation'])->name('quotations.accept');
Route::post('/{id}/penawaran/{quotationId}/pesan', [Web\RequestWebController::class, 'orderQuotation'])->name('quotations.order');
        });

        // Projects
        Route::prefix('proyek')->name('projects.')->group(function (): void {
            Route::get('/', [Web\ProjectWebController::class, 'index'])->name('index');
            Route::get('/buat', [Web\ProjectWebController::class, 'create'])->name('create');
            Route::post('/', [Web\ProjectWebController::class, 'store'])->name('store');
            Route::get('/{id}', [Web\ProjectWebController::class, 'show'])->name('show');
            Route::post('/{id}/proposal', [Web\ProjectWebController::class, 'submitProposal'])->name('proposal.submit');
            Route::post('/{id}/proposal/{proposalId}/putuskan', [Web\ProjectWebController::class, 'decideProposal'])->name('proposal.decide');
            Route::post('/{id}/proposal/{proposalId}/kontrak', [Web\ProjectWebController::class, 'createContract'])->name('proposal.contract');
        });

        // Chat
        Route::prefix('chat')->name('chat.')->group(function (): void {
            Route::get('/', [Web\ChatWebController::class, 'index'])->name('index');
            Route::get('/{id}', [Web\ChatWebController::class, 'show'])->name('show');
            Route::post('/{id}/kirim', [Web\ChatWebController::class, 'send'])->name('send');
            Route::get('/{id}/poll', [Web\ChatWebController::class, 'poll'])->name('poll');
        });

        // Partner Center
        Route::prefix('partner')->name('partner.')->group(function (): void {
            Route::get('/onboarding', [Web\PartnerWebController::class, 'onboarding'])->name('onboarding');
            Route::post('/onboarding', [Web\PartnerWebController::class, 'completeOnboarding'])->name('onboarding.complete');
            Route::post('/kyc', [Web\PartnerWebController::class, 'submitKyc'])->name('kyc.submit');
            Route::get('/', [Web\PartnerWebController::class, 'dashboard'])->name('dashboard');
            Route::get('/pesanan', [Web\PartnerWebController::class, 'orders'])->name('orders');
            Route::post('/pesanan/{id}/aksi', [Web\PartnerWebController::class, 'orderAction'])->name('orders.action');
            Route::get('/jasa', [Web\PartnerWebController::class, 'services'])->name('services');
            Route::get('/jasa/buat', [Web\PartnerWebController::class, 'createService'])->name('services.create');
            Route::post('/jasa', [Web\PartnerWebController::class, 'storeService'])->name('services.store');
            Route::post('/jasa/{id}/toggle', [Web\PartnerWebController::class, 'toggleService'])->name('services.toggle');
            Route::get('/kebutuhan', [Web\PartnerWebController::class, 'requests'])->name('rfqs');
            Route::post('/kebutuhan/{id}/penawaran', [Web\PartnerWebController::class, 'submitQuotation'])->name('rfqs.quote');
            Route::get('/penawaran', [Web\PartnerWebController::class, 'quotations'])->name('quotations');
            Route::get('/proyek', [Web\PartnerWebController::class, 'projects'])->name('projects');
            Route::post('/proyek/proposal', [Web\PartnerWebController::class, 'submitProposal'])->name('projects.proposal');
            Route::get('/keuangan', [Web\PartnerWebController::class, 'finance'])->name('finance');
            Route::post('/keuangan/penarikan', [Web\PartnerWebController::class, 'withdraw'])->name('finance.withdraw');
            Route::post('/keuangan/rekening', [Web\PartnerWebController::class, 'addPayout'])->name('finance.payout');
            Route::get('/ulasan', [Web\PartnerWebController::class, 'reviews'])->name('reviews');
            Route::post('/ulasan/{id}/tanggapi', [Web\PartnerWebController::class, 'respondReview'])->name('reviews.respond');
        });

        // Jasapedia Business (corporate)
        Route::prefix('business')->name('business.')->group(function (): void {
            Route::get('/dashboard', [Web\BusinessWebController::class, 'dashboard'])->name('dashboard');
            Route::post('/organisasi', [Web\BusinessWebController::class, 'createOrg'])->name('org.create');
            Route::post('/request', [Web\BusinessWebController::class, 'createRequest'])->name('request.create');
        });

        // Admin Command Center
        Route::prefix('admin')->name('admin.')->group(function (): void {
            Route::get('/', [Web\AdminWebController::class, 'dashboard'])->name('dashboard');
            Route::get('/pesanan', [Web\AdminWebController::class, 'orders'])->name('orders');
            Route::get('/penyedia', [Web\AdminWebController::class, 'partners'])->name('partners');
            Route::post('/penyedia/{id}/verifikasi', [Web\AdminWebController::class, 'verifyPartner'])->name('partners.verify');
            Route::get('/keuangan', [Web\AdminWebController::class, 'finance'])->name('finance');
            Route::post('/penarikan/{id}/aksi', [Web\AdminWebController::class, 'withdrawalAction'])->name('withdrawals.action');
            Route::get('/sengketa', [Web\AdminWebController::class, 'disputes'])->name('disputes');
            Route::post('/sengketa/{id}/selesaikan', [Web\AdminWebController::class, 'resolveDispute'])->name('disputes.resolve');
            Route::get('/pengguna', [Web\AdminWebController::class, 'users'])->name('users');
        });
    });
});

Route::get('/dashboard', function () {
    return redirect()->route('web.account.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/auth.php';
