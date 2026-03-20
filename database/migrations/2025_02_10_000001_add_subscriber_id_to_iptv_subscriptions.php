<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iptv_subscriptions', function (Blueprint $table) {
            // Add subscriber_id column (nullable to keep existing admin subscriptions)
            $table->unsignedBigInteger('subscriber_id')->nullable()->after('user_id');
            
            // Add index for faster lookups
            $table->index('subscriber_id');
            
            // Optional: Add foreign key
            $table->foreign('subscriber_id')
                  ->references('id')
                  ->on('subscribers')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('iptv_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['subscriber_id']);
            $table->dropIndex(['subscriber_id']);
            $table->dropColumn('subscriber_id');
        });
    }
};
