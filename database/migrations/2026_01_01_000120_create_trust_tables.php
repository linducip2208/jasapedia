<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- KYC / KYB (Phase 28) ----
        Schema::create('kyc_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 8)->default('kyc'); // kyc|kyb
            $table->json('identity')->nullable(); // name, id number, dob...
            $table->json('company')->nullable(); // nib, npwp, address, pic...
            $table->json('documents')->nullable(); // [{type, path, number}]
            $table->string('bank_account', 64)->nullable();
            $table->string('status', 24)->default('draft')->index(); // draft|submitted|under_review|needs_revision|verified|rejected|suspended
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        // ---- Trust & Safety (Phase 29) ----
        Schema::create('risk_flags', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject'); // user/partner/order
            $table->string('flag', 48); // suspicious_contact|refund_abuse|voucher_abuse|location_anomaly|duplicate_account|collusion
            $table->string('risk_level', 8)->default('low'); // low|medium|high
            $table->text('detail')->nullable();
            $table->string('status', 16)->default('open'); // open|reviewing|cleared|actioned
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('user_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blocked_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'blocked_user_id']);
        });

        // ---- Dispute (Phase 30) ----
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 64);
            $table->text('description');
            $table->string('status', 24)->default('opened')->index(); // opened|evidence_collection|counter_response|mediation|decision|resolved|closed
            $table->string('resolution', 32)->nullable(); // release_payment|partial_refund|full_refund|rework|service_credit|claim_rejected
            $table->unsignedBigInteger('resolution_amount')->nullable();
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dispute_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 24); // message|photo|video|document|gps|work_log|quotation|contract|milestone|payment
            $table->string('file_path', 512)->nullable();
            $table->string('ref_type', 32)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // ---- Warranty (Phase 31) ----
        Schema::create('warranty_claims', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claimed_by')->constrained('users')->cascadeOnDelete();
            $table->text('issue');
            $table->json('evidence')->nullable();
            $table->string('status', 24)->default('submitted')->index(); // submitted|under_assessment|rework_scheduled|resolved|rejected|expired
            $table->string('outcome', 32)->nullable(); // rework|refund|service_credit|rejected
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        // ---- Reviews (Phase 32) ----
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete(); // one review per order
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('overall'); // 1-5
            $table->json('dimension_ratings'); // {quality: 5, punctuality: 4...}
            $table->text('comment')->nullable();
            $table->json('images')->nullable();
            $table->text('partner_response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('status', 16)->default('published')->index(); // published|hidden|flagged
            $table->timestamps();
        });

        Schema::create('review_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 64);
            $table->text('note')->nullable();
            $table->string('status', 16)->default('open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_reports');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('warranty_claims');
        Schema::dropIfExists('dispute_evidences');
        Schema::dropIfExists('disputes');
        Schema::dropIfExists('user_blocks');
        Schema::dropIfExists('risk_flags');
        Schema::dropIfExists('kyc_submissions');
    }
};
