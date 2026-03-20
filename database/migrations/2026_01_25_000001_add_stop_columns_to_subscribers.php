<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            if (!Schema::hasColumn('subscribers', 'stopped_at')) {
                $table->timestamp('stopped_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('subscribers', 'stop_reason')) {
                $table->string('stop_reason')->nullable()->after('stopped_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropColumn(['stopped_at', 'stop_reason']);
        });
    }
};
