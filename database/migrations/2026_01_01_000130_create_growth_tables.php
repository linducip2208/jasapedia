<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- Corporate (Phase 33) ----
        Schema::create('corporate_organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('npwp', 32)->nullable();
            $table->string('billing_email')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('corporate_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('corporate_organizations')->cascadeOnDelete();
            $table->string('name', 120);
            $table->foreignId('city_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('address', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('corporate_cost_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('corporate_organizations')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('code', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('corporate_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('corporate_organizations')->cascadeOnDelete();
            $table->string('name', 120);
            $table->foreignId('cost_center_id')->nullable()->constrained('corporate_cost_centers')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('corporate_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('corporate_organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('corporate_branches')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('corporate_departments')->nullOnDelete();
            $table->string('role', 24)->default('employee'); // employee|manager|finance|admin
            $table->decimal('spend_limit', 14)->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });

        Schema::create('corporate_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('corporate_organizations')->cascadeOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('corporate_cost_centers')->nullOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('used')->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'cost_center_id', 'period'], 'corp_budget_unique');
        });

        Schema::create('corporate_approval_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('corporate_organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('threshold')->default(0); // require manager approval above
            $table->unsignedBigInteger('finance_threshold')->nullable(); // require finance approval above
            $table->boolean('require_category_approval')->default(false);
            $table->json('allowed_categories')->nullable();
            $table->timestamps();
        });

        Schema::create('corporate_service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->foreignId('organization_id')->constrained('corporate_organizations')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('corporate_branches')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('corporate_departments')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('corporate_cost_centers')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('title', 190);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('estimated_amount')->nullable();
            $table->string('status', 24)->default('pending_manager')->index(); // pending_manager|pending_finance|approved|rejected|converted|cancelled
            $table->foreignId('manager_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('manager_approved_at')->nullable();
            $table->foreignId('finance_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finance_approved_at')->nullable();
            $table->unsignedBigInteger('order_id')->nullable(); // converted order
            $table->string('po_reference', 64)->nullable();
            $table->timestamps();
        });

        // ---- Recurring (Phase 34) ----
        Schema::create('recurring_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('frequency', 16); // weekly|monthly|quarterly|custom
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->time('preferred_time')->nullable();
            $table->foreignId('address_id')->nullable();
            $table->unsignedInteger('occurrences_left')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 16)->default('active')->index(); // active|paused|cancelled
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('recurring_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('recurring_schedules')->cascadeOnDelete();
            $table->date('scheduled_on');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('status', 16)->default('pending'); // pending|ordered|skipped|failed
            $table->timestamps();
            $table->unique(['schedule_id', 'scheduled_on'], 'recurring_occurrence_unique');
        });

        // ---- Promotions (Phase 35) ----
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 32)->unique();
            $table->string('type', 16); // discount|cashback
            $table->unsignedBigInteger('value'); // percent for discount, idr for cashback
            $table->string('value_unit', 8)->default('percent'); // percent|idr
            $table->unsignedBigInteger('max_discount')->nullable();
            $table->unsignedBigInteger('min_spend')->default(0);
            $table->string('funding', 8)->default('platform'); // platform|vendor|shared
            $table->unsignedTinyInteger('vendor_share_percent')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('city', 64)->nullable();
            $table->json('customer_segments')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->boolean('first_order_only')->default(false);
            $table->boolean('stackable')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 16)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('discount_amount');
            $table->timestamps();
            $table->index(['promotion_id', 'user_id']);
        });

        // ---- Referral (Phase 36) ----
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invitee_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('referral_code', 32);
            $table->string('audience', 8)->default('customer'); // customer|partner
            $table->string('status', 16)->default('invited'); // invited|qualified|rewarded|flagged
            $table->unsignedBigInteger('reward_amount')->default(0);
            $table->timestamp('qualified_at')->nullable();
            $table->timestamps();
        });

        // ---- Membership (Phase 37) ----
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique(); // jasapedia_plus|partner_pro
            $table->string('audience', 8); // customer|partner
            $table->unsignedBigInteger('price_monthly');
            $table->unsignedBigInteger('price_yearly')->nullable();
            $table->json('benefits');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('membership_plans')->cascadeOnDelete();
            $table->morphs('member'); // user or partner
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 16)->default('active')->index(); // active|expired|cancelled
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('membership_plans');
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('recurring_occurrences');
        Schema::dropIfExists('recurring_schedules');
        Schema::dropIfExists('corporate_service_requests');
        Schema::dropIfExists('corporate_approval_policies');
        Schema::dropIfExists('corporate_budgets');
        Schema::dropIfExists('corporate_employees');
        Schema::dropIfExists('corporate_cost_centers');
        Schema::dropIfExists('corporate_departments');
        Schema::dropIfExists('corporate_branches');
        Schema::dropIfExists('corporate_organizations');
    }
};
