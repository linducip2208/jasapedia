<?php

use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Catalog\CatalogController;
use App\Http\Controllers\Api\V1\Catalog\PublicCatalogController;
use App\Http\Controllers\Api\V1\Customer\AddressController;
use App\Http\Controllers\Api\V1\Customer\OrderController;
use App\Http\Controllers\Api\V1\Partner\PartnerController;
use App\Http\Controllers\Api\V1\Partner\FieldServiceController;
use Illuminate\Support\Facades\Route;

    Route::prefix('v1')->group(function (): void {
        Route::get('/health', [HealthController::class, 'index'])->name('api.health');

        // Public catalog
        Route::prefix('catalog')->name('catalog.')->group(function (): void {
            Route::get('/categories', [PublicCatalogController::class, 'categories'])->name('categories');
            Route::get('/categories/{slug}', [PublicCatalogController::class, 'category'])->name('category');
            Route::get('/services', [PublicCatalogController::class, 'services'])->name('services');
            Route::get('/services/{slug}', [PublicCatalogController::class, 'service'])->name('service');
        });

        Route::get('/locations', [PublicCatalogController::class, 'locations'])->name('locations');

        // Customer addresses
        Route::middleware(['auth:sanctum', 'permission:customer.address.manage'])->prefix('addresses')->group(function (): void {
            Route::get('/', [AddressController::class, 'index'])->name('addresses.index');
            Route::post('/', [AddressController::class, 'store'])->name('addresses.store');
            Route::put('/{id}', [AddressController::class, 'update'])->name('addresses.update');
            Route::delete('/{id}', [AddressController::class, 'destroy'])->name('addresses.destroy');
        });

        // Payments
        Route::middleware('throttle:webhook')->post('/payments/webhook/{gateway}', [\App\Http\Controllers\Api\V1\Payment\PaymentController::class, 'webhook'])->name('payments.webhook');
        Route::post('/payments/sandbox/pay', [\App\Http\Controllers\Api\V1\Payment\PaymentController::class, 'sandboxPay'])->name('payments.sandbox-pay');
        Route::middleware(['auth:sanctum'])->post('/payments/intent', [\App\Http\Controllers\Api\V1\Payment\PaymentController::class, 'createIntent'])->name('payments.intent');

        // Orders (customer)
        Route::middleware(['auth:sanctum', 'permission:customer.order.create'])->prefix('orders')->group(function (): void {
            Route::post('/quote', [OrderController::class, 'quote'])->name('orders.quote');
            Route::post('/', [OrderController::class, 'store'])->name('orders.store');
            Route::get('/', [OrderController::class, 'index'])->name('orders.index');
            Route::get('/{id}', [OrderController::class, 'show'])->name('orders.show');
            Route::post('/{id}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
            Route::post('/{id}/confirm', [OrderController::class, 'confirm'])->name('orders.confirm');
        });

        // Field service & dispatch (partner)
        Route::middleware(['auth:sanctum', 'permission:partner.order.accept'])->prefix('field')->group(function (): void {
            Route::get('/offers', [FieldServiceController::class, 'myOffers'])->name('field.offers');
            Route::post('/assignments/{assignmentId}/accept', [FieldServiceController::class, 'accept'])->name('field.accept');
            Route::post('/assignments/{assignmentId}/reject', [FieldServiceController::class, 'reject'])->name('field.reject');
            Route::post('/orders/{orderId}/on-the-way', [FieldServiceController::class, 'onTheWay'])->name('field.on-the-way');
            Route::post('/orders/{orderId}/arrived', [FieldServiceController::class, 'arrived'])->name('field.arrived');
            Route::post('/orders/{orderId}/checkin', [FieldServiceController::class, 'verifyCheckin'])->name('field.checkin');
            Route::post('/orders/{orderId}/start-work', [FieldServiceController::class, 'startWork'])->name('field.start-work');
            Route::post('/orders/{orderId}/evidence', [FieldServiceController::class, 'uploadEvidence'])->name('field.evidence');
            Route::post('/orders/{orderId}/materials', [FieldServiceController::class, 'addMaterial'])->name('field.materials');
            Route::post('/orders/{orderId}/additional-charges', [FieldServiceController::class, 'requestAdditionalCharge'])->name('field.acr');
            Route::post('/orders/{orderId}/submit-completion', [FieldServiceController::class, 'submitCompletion'])->name('field.submit');
        });

        // Customer: additional charge decision + OTP confirm
        Route::middleware(['auth:sanctum', 'permission:customer.order.confirm'])->prefix('orders')->group(function (): void {
            Route::post('/{id}/checkin', [\App\Http\Controllers\Api\V1\Customer\OrderController::class, 'confirmCheckin'])->name('orders.customer-checkin');
            Route::post('/{acrId}/additional-charges/decide', [\App\Http\Controllers\Api\V1\Customer\OrderController::class, 'decideAdditionalCharge'])->name('orders.acr-decide');
        });

        // Notifications
        Route::middleware('auth:sanctum')->prefix('notifications')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Api\V1\NotificationController::class, 'index'])->name('notifications.index');
            Route::get('/unread-count', [\App\Http\Controllers\Api\V1\NotificationController::class, 'unreadCount'])->name('notifications.unread');
            Route::post('/read', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markRead'])->name('notifications.read');
            Route::get('/preferences', [\App\Http\Controllers\Api\V1\NotificationController::class, 'preferences'])->name('notifications.prefs');
            Route::put('/preferences', [\App\Http\Controllers\Api\V1\NotificationController::class, 'updatePreferences'])->name('notifications.prefs-update');
        });

        // Chat
        Route::middleware('auth:sanctum')->prefix('chat')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Api\V1\Chat\ChatController::class, 'index'])->name('chat.index');
            Route::post('/direct', [\App\Http\Controllers\Api\V1\Chat\ChatController::class, 'direct'])->name('chat.direct');
            Route::get('/orders/{orderId}/conversation', [\App\Http\Controllers\Api\V1\Chat\ChatController::class, 'forOrder'])->name('chat.for-order');
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\Chat\ChatController::class, 'show'])->name('chat.show');
            Route::get('/{id}/messages', [\App\Http\Controllers\Api\V1\Chat\ChatController::class, 'messages'])->name('chat.messages');
            Route::post('/{id}/messages', [\App\Http\Controllers\Api\V1\Chat\ChatController::class, 'send'])->name('chat.send');
            Route::post('/{id}/read', [\App\Http\Controllers\Api\V1\Chat\ChatController::class, 'markRead'])->name('chat.read');
            Route::post('/messages/{messageId}/report', [\App\Http\Controllers\Api\V1\Chat\ChatController::class, 'reportMessage'])->name('chat.report');
        });

        // Projects & deal flow (customer)
        Route::middleware(['auth:sanctum', 'permission:customer.project.manage'])->prefix('projects')->group(function (): void {
            Route::post('/', [\App\Http\Controllers\Api\V1\Customer\ProjectController::class, 'store'])->name('projects.store');
            Route::get('/', [\App\Http\Controllers\Api\V1\Customer\ProjectController::class, 'index'])->name('projects.index');
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\Customer\ProjectController::class, 'show'])->name('projects.show');
            Route::post('/proposals/{proposalId}/decide', [\App\Http\Controllers\Api\V1\Customer\ProjectController::class, 'decideProposal'])->name('projects.proposal-decide');
            Route::post('/proposals/{proposalId}/contract', [\App\Http\Controllers\Api\V1\Customer\ProjectController::class, 'createContract'])->name('projects.contract');
            Route::post('/contracts/{contractId}/accept', [\App\Http\Controllers\Api\V1\Customer\ProjectController::class, 'acceptContract'])->name('projects.contract-accept');
            Route::post('/milestones/{milestoneId}/fund', [\App\Http\Controllers\Api\V1\Customer\ProjectController::class, 'fundMilestone'])->name('projects.milestone-fund');
            Route::post('/milestones/{milestoneId}/approve', [\App\Http\Controllers\Api\V1\Customer\ProjectController::class, 'approveMilestone'])->name('projects.milestone-approve');
            Route::post('/milestones/{milestoneId}/revision', [\App\Http\Controllers\Api\V1\Customer\ProjectController::class, 'requestMilestoneRevision'])->name('projects.milestone-revision');
            Route::post('/milestones/{milestoneId}/release', [\App\Http\Controllers\Api\V1\Customer\ProjectController::class, 'releaseMilestone'])->name('projects.milestone-release');
        });

        // Deal flow (partner)
        Route::middleware(['auth:sanctum', 'permission:vendor.proposal.submit'])->prefix('partner/deals')->group(function (): void {
            Route::get('/projects', [\App\Http\Controllers\Api\V1\Partner\DealController::class, 'openProjects'])->name('partner.deals.projects');
            Route::post('/proposals', [\App\Http\Controllers\Api\V1\Partner\DealController::class, 'submitProposal'])->name('partner.deals.proposal-submit');
            Route::get('/proposals', [\App\Http\Controllers\Api\V1\Partner\DealController::class, 'myProposals'])->name('partner.deals.my-proposals');
            Route::post('/proposals/{proposalId}/withdraw', [\App\Http\Controllers\Api\V1\Partner\DealController::class, 'withdrawProposal'])->name('partner.deals.proposal-withdraw');
            Route::get('/contracts', [\App\Http\Controllers\Api\V1\Partner\DealController::class, 'myContracts'])->name('partner.deals.contracts');
            Route::post('/milestones/{milestoneId}/start', [\App\Http\Controllers\Api\V1\Partner\DealController::class, 'startMilestone'])->name('partner.deals.milestone-start');
            Route::post('/milestones/{milestoneId}/submit', [\App\Http\Controllers\Api\V1\Partner\DealController::class, 'submitMilestone'])->name('partner.deals.milestone-submit');
            Route::post('/worklogs', [\App\Http\Controllers\Api\V1\Partner\DealController::class, 'storeWorkLog'])->name('partner.deals.worklogs');
        });

        // Reviews (customer)
        Route::middleware(['auth:sanctum', 'permission:customer.order.review'])->prefix('orders')->group(function (): void {
            Route::post('/{id}/review', [\App\Http\Controllers\Api\V1\Customer\ReviewController::class, 'store'])->name('orders.review');
        });
        Route::get('/partners/{partnerId}/reviews', [\App\Http\Controllers\Api\V1\Customer\ReviewController::class, 'partnerReviews'])->name('partners.reviews');

        // Partner review response
        Route::middleware(['auth:sanctum', 'permission:partner.review.respond'])->post('/reviews/{id}/respond', [\App\Http\Controllers\Api\V1\Customer\ReviewController::class, 'respond'])->name('reviews.respond');

        // Warranty (customer)
        Route::middleware(['auth:sanctum', 'permission:customer.warranty.claim'])->post('/orders/{id}/warranty-claims', [\App\Http\Controllers\Api\V1\Customer\ReviewController::class, 'warrantyClaim'])->name('orders.warranty-claim');

        // Disputes (customer/partner)
        Route::middleware('auth:sanctum')->prefix('disputes')->group(function (): void {
            Route::post('/orders/{orderId}', [\App\Http\Controllers\Api\V1\Customer\ReviewController::class, 'openDispute'])->name('disputes.open');
            Route::post('/{id}/evidence', [\App\Http\Controllers\Api\V1\Customer\ReviewController::class, 'addEvidence'])->name('disputes.evidence');
        });

        // KYC submission (partner)
        Route::middleware('auth:sanctum')->post('/partner/kyc-submit', [\App\Http\Controllers\Api\V1\Customer\ReviewController::class, 'kycSubmit'])->name('partner.kyc-submit');

        // Admin dispute resolution (DisputeOfficer)
        Route::middleware(['auth:sanctum', 'permission:dispute.resolve'])->post('/admin/disputes', [\App\Http\Controllers\Api\V1\Admin\DisputeAdminController::class, 'resolve'])->name('admin.disputes.resolve');

        // Partner services management
        Route::middleware(['auth:sanctum', 'permission:partner.service.manage'])->prefix('partner/services')->group(function (): void {
            Route::get('/', [CatalogController::class, 'myServices'])->name('partner.services.index');
            Route::post('/', [CatalogController::class, 'storeService'])->name('partner.services.store');
            Route::put('/{id}', [CatalogController::class, 'updateService'])->name('partner.services.update');
            Route::post('/{id}/toggle-pause', [CatalogController::class, 'pauseService'])->name('partner.services.pause');
        });

    Route::middleware('throttle:auth')->group(function (): void {
        Route::post('/auth/register', [AuthController::class, 'register'])->name('api.auth.register');
        Route::post('/auth/login', [AuthController::class, 'login'])->name('api.auth.login');
        Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->name('api.auth.forgot');
        Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->name('api.auth.reset');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.auth.me');
        Route::put('/auth/me', [AuthController::class, 'updateMe'])->name('api.auth.update-me');
        Route::put('/auth/password', [AuthController::class, 'changePassword'])->name('api.auth.change-password');
        Route::get('/auth/sessions', [AuthController::class, 'sessions'])->name('api.auth.sessions');
        Route::delete('/auth/sessions/{id}', [AuthController::class, 'revokeSession'])->name('api.auth.revoke-session');

        // Two-factor
        Route::post('/auth/2fa/enable', [AuthController::class, 'startTwoFactor'])->name('api.auth.2fa-start');
        Route::post('/auth/2fa/confirm', [AuthController::class, 'confirmTwoFactor'])->name('api.auth.2fa-confirm');
        Route::post('/auth/2fa/disable', [AuthController::class, 'disableTwoFactor'])->name('api.auth.2fa-disable');

        // Partner
        Route::prefix('partner')->group(function (): void {
            Route::post('/', [PartnerController::class, 'register'])->name('api.partner.register');

            Route::middleware('permission:partner.profile.manage')->group(function (): void {
                Route::get('/me', [PartnerController::class, 'me'])->name('api.partner.me');
                Route::put('/me', [PartnerController::class, 'update'])->name('api.partner.update');
                Route::post('/online-status', [PartnerController::class, 'setOnlineStatus'])->name('api.partner.online-status');
                Route::post('/submit-verification', [PartnerController::class, 'submitVerification'])->name('api.partner.submit-verification');
                Route::post('/skills', [PartnerController::class, 'addSkill'])->name('api.partner.skills.add');
                Route::delete('/skills/{skillId}', [PartnerController::class, 'removeSkill'])->name('api.partner.skills.remove');
                Route::post('/documents', [PartnerController::class, 'addDocument'])->name('api.partner.documents.add');
                Route::post('/service-areas', [PartnerController::class, 'addServiceArea'])->name('api.partner.service-areas.add');
                Route::post('/payout-destinations', [PartnerController::class, 'addPayoutDestination'])->name('api.partner.payout.add');
                Route::post('/members', [PartnerController::class, 'addMember'])->name('api.partner.members.add');
                Route::delete('/members/{memberId}', [PartnerController::class, 'removeMember'])->name('api.partner.members.remove');
            });
        });
    });
});
