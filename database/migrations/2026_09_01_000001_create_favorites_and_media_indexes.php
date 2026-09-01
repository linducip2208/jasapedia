<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('favorites')) {
            Schema::create('favorites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('service_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('partner_id')->nullable()->constrained()->cascadeOnDelete();
                $table->timestamps();

                // No duplicate favorites per user per target.
                $table->unique(['user_id', 'service_id']);
                $table->unique(['user_id', 'partner_id']);
                $table->index('user_id');
            });
        }

        if (! Schema::hasIndex('services', 'services_status_category_id_index')) {
            Schema::table('services', function (Blueprint $table) {
                $table->index(['status', 'category_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('services', 'services_status_category_id_index')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropIndex(['status', 'category_id']);
            });
        }
        Schema::dropIfExists('favorites');
    }
};
