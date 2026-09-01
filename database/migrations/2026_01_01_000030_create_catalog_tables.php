<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name', 120);
            $table->string('slug', 160)->unique();
            $table->string('icon', 48)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable(); // review_dimensions, warranty, cancellation, sla_defaults, fulfillment_defaults
            $table->timestamps();
            $table->index(['parent_id', 'is_active']);
        });

        Schema::create('category_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('label', 120);
            $table->string('type', 16); // text|number|select|multi|boolean|file
            $table->json('options')->nullable();
            $table->boolean('required')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['category_id', 'key']);
        });

        Schema::create('service_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->string('fulfillment_type', 24); // instant_booking|appointment|fixed_package|hourly|daily|per_unit|survey_required|request_quotation|rfq|project|milestone_project
            $table->string('delivery_mode', 16); // remote|onsite|hybrid|provider_location
            $table->unsignedInteger('default_duration_minutes')->nullable();
            $table->text('description')->nullable();
            $table->json('config')->nullable(); // required_fields, required_documents, addon hints
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['category_id', 'is_active']);
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('service_templates')->nullOnDelete();
            $table->string('title', 190);
            $table->string('slug', 210)->unique();
            $table->text('description')->nullable();
            $table->text('inclusions')->nullable();
            $table->text('exclusions')->nullable();
            $table->string('fulfillment_type', 24);
            $table->string('delivery_mode', 16);
            $table->string('price_model', 24); // fixed|per_unit|hourly|daily|starting_from|package|quotation|milestone
            $table->unsignedBigInteger('base_price')->default(0);
            $table->string('unit_label', 32)->nullable(); // unit, jam, m2, AC
            $table->unsignedInteger('min_quantity')->default(1);
            $table->unsignedInteger('max_quantity')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->boolean('emergency_capable')->default(false);
            $table->unsignedBigInteger('emergency_surcharge')->default(0);
            $table->unsignedInteger('warranty_days')->default(0);
            $table->string('status', 16)->default('draft')->index(); // draft|pending_review|active|paused|rejected
            $table->json('media')->nullable();
            $table->json('attributes')->nullable(); // filled category attributes
            $table->timestamps();
            $table->index(['status', 'category_id']);
        });

        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price');
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->json('inclusions')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('service_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price');
            $table->string('unit', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_addons');
        Schema::dropIfExists('service_packages');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_templates');
        Schema::dropIfExists('category_attributes');
        Schema::dropIfExists('categories');
    }
};
