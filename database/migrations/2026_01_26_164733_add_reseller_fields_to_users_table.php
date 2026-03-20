<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update the role enum to include reseller
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'admin', 'reseller', 'operator', 'viewer') DEFAULT 'viewer'");
        
        Schema::table('users', function (Blueprint $table) {
            // Reseller specific fields
            $table->unsignedBigInteger('parent_id')->nullable()->after('role'); // Parent reseller/admin
            $table->decimal('commission_rate', 5, 2)->default(0)->after('parent_id'); // Commission percentage
            $table->decimal('balance', 10, 2)->default(0)->after('commission_rate'); // Account balance
            $table->integer('max_subscribers')->nullable()->after('balance'); // Max subscribers limit
            $table->string('company_name')->nullable()->after('max_subscribers'); // Company name
            $table->text('address')->nullable()->after('company_name'); // Address
            
            // Foreign key
            $table->foreign('parent_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'commission_rate', 'balance', 'max_subscribers', 'company_name', 'address']);
        });
        
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'admin', 'operator', 'viewer') DEFAULT 'viewer'");
    }
};
