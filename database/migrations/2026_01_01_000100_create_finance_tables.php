<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name', 120);
            $table->string('type', 16); // asset|liability|revenue|expense|equity
            $table->string('owner_type', 32)->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->timestamps();
        });

        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('group', 32)->index(); // order_payment|settlement|withdrawal|refund|adjustment|promotion
            $table->string('reference_type', 32)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->unsignedBigInteger('reversal_of')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained();
            $table->unsignedBigInteger('debit')->default(0);
            $table->unsignedBigInteger('credit')->default(0);
            $table->string('memo', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['ledger_account_id', 'created_at']);
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('basis_amount');
            $table->decimal('rate_percent', 5, 2);
            $table->unsignedBigInteger('amount');
            $table->json('snapshot');
            $table->timestamp('created_at')->useCurrent();
            $table->unique('order_id'); // one commission snapshot per order
        });

        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('gross');
            $table->unsignedBigInteger('commission');
            $table->unsignedBigInteger('additional_amount')->default(0);
            $table->unsignedBigInteger('vendor_net');
            $table->string('status', 16)->default('pending')->index(); // pending|eligible|processing|completed|failed|held
            $table->timestamp('eligible_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('type', 16); // full|partial|service_credit
            $table->string('status', 16)->default('requested')->index(); // requested|approved|rejected|processing|completed|failed
            $table->string('reason', 255);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider_ref', 96)->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payout_destination_id')->constrained();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('fee')->default(0);
            $table->unsignedBigInteger('net');
            $table->string('status', 16)->default('requested')->index(); // requested|under_review|approved|processing|completed|failed|rejected|cancelled
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider_ref', 96)->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('ledger_accounts');
    }
};
