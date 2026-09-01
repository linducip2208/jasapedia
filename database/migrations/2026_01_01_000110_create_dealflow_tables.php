<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // owner
            $table->foreignId('category_id')->constrained();
            $table->string('title', 190);
            $table->text('description');
            $table->json('requirements')->nullable();
            $table->json('skills')->nullable();
            $table->string('budget_type', 16); // fixed|hourly|range
            $table->unsignedBigInteger('budget_min')->nullable();
            $table->unsignedBigInteger('budget_max')->nullable();
            $table->date('deadline')->nullable();
            $table->json('attachments')->nullable();
            $table->string('visibility', 16)->default('public'); // public|invited
            $table->string('status', 24)->default('draft')->index();
            $table->string('active_status_snapshot', 24)->nullable();
            $table->foreignId('awarded_partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('rfqs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained();
            $table->string('title', 190);
            $table->text('description')->nullable();
            $table->json('requirements')->nullable(); // quantities, technical, commercial
            $table->json('attachments')->nullable();
            $table->timestamp('deadline')->nullable();
            $table->json('invited_partner_ids')->nullable();
            $table->string('visibility', 16)->default('public');
            $table->string('status', 24)->default('open')->index(); // open|closed|awarded|cancelled
            $table->timestamps();
        });

        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('rfq_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->text('cover_letter')->nullable();
            $table->text('technical_approach')->nullable();
            $table->unsignedBigInteger('price');
            $table->unsignedInteger('timeline_days')->nullable();
            $table->json('deliverables')->nullable();
            $table->json('milestone_plan')->nullable();
            $table->unsignedInteger('warranty_days')->default(0);
            $table->timestamp('valid_until')->nullable();
            $table->json('attachments')->nullable();
            $table->string('status', 16)->default('draft')->index(); // draft|submitted|shortlisted|rejected|withdrawn|accepted
            $table->timestamps();
            $table->index(['project_id', 'partner_id']);
            $table->index(['rfq_id', 'partner_id']);
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->foreignId('rfq_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete(); // survey→quotation flow
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('version')->default(1);
            $table->json('line_items'); // [{name, qty, unit_price, amount}]
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('tax')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('total');
            $table->text('terms')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->json('attachments')->nullable();
            $table->string('status', 16)->default('draft')->index(); // draft|sent|approved|rejected|expired|superseded
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('proposal_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('version')->default(1);
            $table->json('scope');
            $table->json('deliverables')->nullable();
            $table->unsignedBigInteger('price');
            $table->string('payment_terms', 255)->nullable();
            $table->json('milestone_plan')->nullable();
            $table->unsignedInteger('revision_limit')->default(2);
            $table->unsignedInteger('warranty_days')->default(0);
            $table->text('ip_terms')->nullable();
            $table->text('cancellation_terms')->nullable();
            $table->text('dispute_terms')->nullable();
            $table->string('status', 16)->default('draft')->index(); // draft|sent|accepted|amended|terminated
            $table->timestamp('customer_accepted_at')->nullable();
            $table->timestamp('partner_accepted_at')->nullable();
            $table->unsignedBigInteger('amends')->nullable();
            $table->timestamps();
        });

        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete(); // funding order
            $table->string('title', 190);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('amount');
            $table->date('deadline')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->string('status', 24)->default('draft')->index(); // draft|ready|funded|in_progress|submitted|revision_requested|resubmitted|approved|release_pending|released|disputed|cancelled
            $table->string('active_status_snapshot', 24)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });

        Schema::create('milestone_deliverables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milestone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('file_path', 512);
            $table->string('kind', 16)->default('file');
            $table->text('note')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
        });

        Schema::create('work_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('duration_minutes');
            $table->text('description')->nullable();
            $table->json('proof')->nullable();
            $table->string('source', 16)->default('manual'); // manual|automatic
            $table->string('status', 16)->default('pending'); // pending|approved|rejected
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_logs');
        Schema::dropIfExists('milestone_deliverables');
        Schema::dropIfExists('milestones');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('proposals');
        Schema::dropIfExists('rfqs');
        Schema::dropIfExists('projects');
    }
};
