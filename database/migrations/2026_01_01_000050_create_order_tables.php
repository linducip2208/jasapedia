<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // customer
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('type', 24)->default('service')->index(); // service|project|milestone_funding|additional_charge|manual
            $table->string('status', 32)->default('draft')->index();
            $table->string('active_status_snapshot', 32)->nullable(); // for DISPUTED restore
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('service_packages')->nullOnDelete();
            $table->string('fulfillment_type', 24)->nullable();
            $table->string('delivery_mode', 16)->nullable();
            $table->dateTime('scheduled_at')->nullable()->index();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->foreignId('address_id')->nullable();
            $table->json('address_snapshot')->nullable();
            $table->unsignedBigInteger('slot_id')->nullable();
            $table->text('customer_note')->nullable();
            $table->json('attachments')->nullable();
            $table->json('pricing_snapshot')->nullable(); // frozen PriceQuote
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('emergency_surcharge')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->boolean('is_emergency')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 255)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['partner_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24); // base|package|addon|material|additional_charge|adjustment
            $table->string('name', 190);
            $table->unsignedInteger('qty')->default(1);
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->unsignedBigInteger('amount')->default(0);
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->string('unit_label', 32)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'type']);
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 24)->default('user'); // user|system
            $table->string('reason', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
