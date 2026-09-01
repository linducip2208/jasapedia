<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- Support (Phase 38) ----
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 24); // general|order|payment|project|withdrawal|kyc|dispute|warranty|technical
            $table->unsignedBigInteger('ref_id')->nullable(); // order/dispute/...
            $table->string('ref_type', 32)->nullable();
            $table->string('subject', 190);
            $table->string('priority', 8)->default('normal'); // low|normal|high|urgent
            $table->string('status', 24)->default('open')->index(); // open|pending_customer|pending_internal|resolved|closed
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('author_type', 12)->default('customer'); // customer|staff|system
            $table->text('body');
            $table->json('attachments')->nullable();
            $table->timestamps();
        });

        // ---- CMS (Phase 39) ----
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 190)->unique();
            $table->string('title', 190);
            $table->longText('content')->nullable();
            $table->string('status', 16)->default('published');
            $table->json('seo')->nullable();
            $table->timestamps();
        });

        Schema::create('cms_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique(); // home.hero, home.categories...
            $table->string('type', 24); // hero|banner|category_grid|richtext|promo_strip
            $table->json('data')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 210)->unique();
            $table->string('title', 190);
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('draft'); // draft|published
            $table->timestamp('published_at')->nullable();
            $table->json('seo')->nullable();
            $table->timestamps();
        });

        // ---- SEO (Phase 40) ----
        Schema::create('seo_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('page_type', 24); // category|city|category_city|service|static
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('city', 64)->nullable();
            $table->string('canonical_url', 500)->nullable();
            $table->string('meta_title', 190)->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('og_image', 500)->nullable();
            $table->boolean('noindex')->default(false);
            $table->string('h1', 190)->nullable();
            $table->text('intro_copy')->nullable();
            $table->timestamps();
            $table->unique(['page_type', 'category_id', 'city'], 'seo_page_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_metadata');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('cms_blocks');
        Schema::dropIfExists('cms_pages');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
    }
};
