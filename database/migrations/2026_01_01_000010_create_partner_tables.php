<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('type', 24)->default('freelancer')->index(); // freelancer|individual|vendor_company
            $table->string('display_name');
            $table->string('slug', 160)->unique();
            $table->text('about')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('verification_state', 24)->default('unverified')->index();
            $table->string('online_status', 16)->default('offline');
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('completed_jobs')->default(0);
            $table->decimal('acceptance_rate', 5, 2)->default(100);
            $table->unsignedInteger('response_minutes')->default(60);
            $table->string('city', 64)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name', 96);
            $table->unsignedTinyInteger('level')->default(3); // 1-5
            $table->timestamps();
            $table->unique(['partner_id', 'name']);
        });

        Schema::create('partner_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32); // ktp|sim|npwp|nib|certificate|portfolio|other
            $table->string('file_path', 512);
            $table->string('number', 96)->nullable();
            $table->string('status', 24)->default('pending'); // pending|approved|rejected
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_service_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('coverage_type', 16); // city|district|radius|polygon
            $table->unsignedBigInteger('location_id')->nullable(); // id on locations table
            $table->decimal('radius_km', 6, 2)->nullable();
            $table->json('polygon')->nullable();
            $table->timestamps();
            $table->index(['partner_id', 'coverage_type']);
        });

        Schema::create('partner_organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('npwp', 32)->nullable();
            $table->string('nib', 48)->nullable();
            $table->text('address')->nullable();
            $table->string('pic_name', 120)->nullable();
            $table->string('pic_phone', 32)->nullable();
            $table->unsignedInteger('worker_count')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('partner_organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 24); // owner|manager|dispatcher|finance|pm|worker
            $table->string('status', 16)->default('active'); // active|inactive|invited
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });

        Schema::create('payout_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16); // bank|ewallet
            $table->string('bank_code', 16)->nullable();
            $table->string('account_number', 64);
            $table->string('account_name', 120);
            $table->boolean('is_default')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['partner_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_destinations');
        Schema::dropIfExists('partner_members');
        Schema::dropIfExists('partner_organizations');
        Schema::dropIfExists('partner_service_areas');
        Schema::dropIfExists('partner_documents');
        Schema::dropIfExists('partner_skills');
        Schema::dropIfExists('partners');
    }
};
