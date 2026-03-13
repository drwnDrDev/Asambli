<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('onboarded_at')->nullable()->after('email_verified_at');
            $table->char('quick_pin', 6)->nullable()->after('onboarded_at');
            $table->timestamp('pin_expires_at')->nullable()->after('quick_pin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('onboarded_at');
            $table->dropColumn('quick_pin');
            $table->dropColumn('pin_expires_at');
        });
    }
};
