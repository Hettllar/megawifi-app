<?php

namespace App\Console\Commands;

use App\Jobs\CheckUsageLimit;
use App\Jobs\RefreshUserManagerUsage;
use Illuminate\Console\Command;

class CheckUsageLimitCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'usage:check {--refresh : تحديث بيانات الاستهلاك أولاً}';

    /**
     * The console command description.
     */
    protected $description = 'فحص حدود الاستهلاك لجميع المشتركين واتخاذ الإجراء المناسب';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('جاري فحص حدود الاستهلاك...');
        
        // إذا تم طلب التحديث أولاً
        if ($this->option('refresh')) {
            $this->info('جاري تحديث بيانات الاستهلاك من الراوترات...');
            
            try {
                $job = new RefreshUserManagerUsage();
                $job->handle();
                $this->info('✓ تم تحديث بيانات الاستهلاك');
            } catch (\Exception $e) {
                $this->error('✗ فشل تحديث البيانات: ' . $e->getMessage());
            }
        }
        
        try {
            $job = new CheckUsageLimit();
            $job->handle();
            
            $this->info('✓ تم فحص حدود الاستهلاك بنجاح');
            $this->info('راجع السجلات للتفاصيل: storage/logs/laravel.log');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('✗ حدث خطأ: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
