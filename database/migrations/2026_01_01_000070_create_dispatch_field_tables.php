<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('partner_members')->nullOnDelete();
            $table->foreignId('worker_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mode', 24); // auto_direct|broadcast|sequential|manual|vendor_internal
            $table->string('status', 24)->default('offered'); // offered|accepted|rejected|expired|reassigned
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->json('score_breakdown')->nullable(); // dispatch scoring transparency
            $table->timestamps();
            $table->index(['order_id', 'status']);
            $table->index(['partner_id', 'status']);
        });

        Schema::create('checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16); // checkin|checkout
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('otp_code', 8)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'type']);
        });

        Schema::create('service_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('stage', 16); // before|after|rework
            $table->string('file_path', 512);
            $table->string('kind', 16)->default('photo'); // photo|video|file
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'stage']);
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('name', 190);
            $table->string('sku', 64)->nullable();
            $table->unsignedInteger('qty')->default(1);
            $table->string('unit', 24)->nullable();
            $table->unsignedBigInteger('cost')->default(0);
            $table->unsignedBigInteger('sell_price')->default(0);
            $table->unsignedBigInteger('tax')->default(0);
            $table->timestamps();
            $table->index(['partner_id', 'sku']);
        });

        Schema::create('additional_charge_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('item', 190);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('evidence_path', 512)->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 16)->default('pending')->index(); // pending|approved|rejected|expired|cancelled
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('additional_charge_requests');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('service_evidences');
        Schema::dropIfExists('checkins');
        Schema::dropIfExists('assignments');
    }
};
