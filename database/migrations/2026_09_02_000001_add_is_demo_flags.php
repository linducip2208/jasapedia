<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demo-data tagging (ADR demo-seed 2026-09).
 * is_demo columns let `jasapedia:seed-demo --fresh-demo` delete ONLY demo rows,
 * never production/customer data. Nullable keeps existing rows untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->after('status')->index();
            }
        });

        Schema::table('partners', function (Blueprint $table) {
            if (! Schema::hasColumn('partners', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->after('user_id')->index();
            }
        });

        Schema::table('services', function (Blueprint $table) {
            if (! Schema::hasColumn('services', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->after('status')->index();
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->after('status')->index();
            }
        });

        Schema::table('reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('reviews', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->after('status')->index();
            }
        });

        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->after('status')->index();
            }
        });

        Schema::table('rfqs', function (Blueprint $table) {
            if (! Schema::hasColumn('rfqs', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->after('status')->index();
            }
        });

        Schema::table('proposals', function (Blueprint $table) {
            if (! Schema::hasColumn('proposals', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->after('status')->index();
            }
        });

        Schema::table('contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('contracts', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->after('status')->index();
            }
        });

        Schema::table('milestones', function (Blueprint $table) {
            if (! Schema::hasColumn('milestones', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->after('status')->index();
            }
        });

        Schema::table('quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('quotations', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->after('status')->index();
            }
        });

        Schema::table('corporate_organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('corporate_organizations', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->after('name')->index();
            }
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('blog_posts', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->after('status')->index();
            }
        });

        Schema::table('cms_blocks', function (Blueprint $table) {
            if (! Schema::hasColumn('cms_blocks', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->after('key');
            }
        });
    }

    public function down(): void
    {
        foreach ([
            ['users', 'is_demo'],
            ['partners', 'is_demo'],
            ['services', 'is_demo'],
            ['orders', 'is_demo'],
            ['reviews', 'is_demo'],
            ['projects', 'is_demo'],
            ['rfqs', 'is_demo'],
            ['proposals', 'is_demo'],
            ['contracts', 'is_demo'],
            ['milestones', 'is_demo'],
            ['quotations', 'is_demo'],
            ['corporate_organizations', 'is_demo'],
            ['blog_posts', 'is_demo'],
            ['cms_blocks', 'is_demo'],
        ] as [$table, $column]) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};
