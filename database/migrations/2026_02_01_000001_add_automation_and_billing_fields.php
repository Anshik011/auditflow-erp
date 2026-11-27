<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('is_recurring_retainer')->default(false)->after('is_tds_applicable');
            $table->decimal('retainer_amount', 14, 2)->default(0)->after('is_recurring_retainer');
            $table->string('billing_cycle', 20)->default('none')->after('retainer_amount');
        });

        Schema::table('compliance_deadlines', function (Blueprint $table) {
            $table->timestamp('last_reminded_at')->nullable()->after('completed_at');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->timestamp('last_reminded_at')->nullable()->after('is_shared_with_client');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('last_reminded_at')->nullable()->after('notes');
            $table->string('razorpay_payment_link_id', 80)->nullable()->after('last_reminded_at');
            $table->string('razorpay_payment_link_url', 255)->nullable()->after('razorpay_payment_link_id');
            $table->string('razorpay_payment_id', 80)->nullable()->after('razorpay_payment_link_url');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('tds_deducted', 14, 2)->default(0)->after('amount');
            $table->string('tds_section', 20)->nullable()->after('tds_deducted');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_enabled')->default(false)->after('is_active');
            $table->string('two_factor_code')->nullable()->after('two_factor_enabled');
            $table->timestamp('two_factor_expires_at')->nullable()->after('two_factor_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_enabled', 'two_factor_code', 'two_factor_expires_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['tds_deducted', 'tds_section']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['last_reminded_at', 'razorpay_payment_link_id', 'razorpay_payment_link_url', 'razorpay_payment_id']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('last_reminded_at');
        });

        Schema::table('compliance_deadlines', function (Blueprint $table) {
            $table->dropColumn('last_reminded_at');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['is_recurring_retainer', 'retainer_amount', 'billing_cycle']);
        });
    }
};
