<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * إضافة حقول تتبع الاستهلاك والتقييد للمشتركين
     */
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            // حد البيانات بالجيجابايت
            if (!Schema::hasColumn('subscribers', 'data_limit_gb')) {
                $table->decimal('data_limit_gb', 10, 2)->nullable()->after('data_limit');
            }
            
            // البروفايل الأصلي قبل التقييد
            if (!Schema::hasColumn('subscribers', 'original_profile')) {
                $table->string('original_profile')->nullable()->after('profile');
            }
            
            // هل المشترك مقيد؟
            if (!Schema::hasColumn('subscribers', 'is_throttled')) {
                $table->boolean('is_throttled')->default(false)->after('status');
            }
            
            // تاريخ التقييد
            if (!Schema::hasColumn('subscribers', 'throttled_at')) {
                $table->timestamp('throttled_at')->nullable()->after('is_throttled');
            }
            
            // تاريخ آخر تجديد للاستهلاك
            if (!Schema::hasColumn('subscribers', 'usage_reset_at')) {
                $table->timestamp('usage_reset_at')->nullable()->after('throttled_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $columns = ['data_limit_gb', 'original_profile', 'is_throttled', 'throttled_at', 'usage_reset_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('subscribers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
