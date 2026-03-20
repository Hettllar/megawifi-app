<?php

namespace App\Console\Commands;

use App\Models\Subscriber;
use App\Models\IptvSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateIptvSubscriptionsForSubscribers extends Command
{
    protected $signature = 'iptv:create-subscriber-subscriptions {--limit=100 : Maximum number of subscriptions to create}';
    
    protected $description = 'إنشاء اشتراكات IPTV مجانية للمشتركين النشطين';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        
        $this->info("🚀 بدء إنشاء اشتراكات IPTV للمشتركين النشطين (الحد الأقصى: $limit)...");
        
        // جلب المشتركين النشطين الذين ليس لديهم اشتراك IPTV
        $subscribers = Subscriber::where('status', 'active')
            ->whereDoesntHave('iptvSubscription')
            ->limit($limit)
            ->get();
        
        if ($subscribers->count() == 0) {
            $this->warn('⚠️ لا يوجد مشتركين نشطين بحاجة لاشتراكات IPTV');
            
            // Show stats
            $totalActive = Subscriber::where('status', 'active')->count();
            $withIptv = IptvSubscription::whereNotNull('subscriber_id')->count();
            $this->info("📊 إحصائيات:");
            $this->line("   - المشتركين النشطين: $totalActive");
            $this->line("   - لديهم IPTV: $withIptv");
            
            return 0;
        }
        
        $created = 0;
        $bar = $this->output->createProgressBar($subscribers->count());
        $bar->start();
        
        foreach ($subscribers as $subscriber) {
            // إنشاء username وpassword فريدين
            $username = 'iptv_sub_' . $subscriber->id;
            $password = Str::random(12);
            
            try {
                // إنشاء اشتراك IPTV
                IptvSubscription::create([
                    'subscriber_id' => $subscriber->id,
                    'user_id' => null, // NULL since this is for subscriber not admin
                    'username' => $username,
                    'password' => $password,
                    'expires_at' => now()->addYear(), // صلاحية سنة
                    'is_active' => 1,
                    'max_connections' => 2, // يمكن الاتصال من جهازين
                    'notes' => 'اشتراك IPTV مجاني - ميزة إضافية للمشتركين'
                ]);
                
                $created++;
            } catch (\Exception $e) {
                $this->error("\n❌ خطأ في إنشاء اشتراك لـ {$subscriber->username}: " . $e->getMessage());
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        $this->info("🎉 تم إنشاء {$created} اشتراك IPTV بنجاح!");
        
        // Check total remaining
        $remaining = Subscriber::where('status', 'active')
            ->whereDoesntHave('iptvSubscription')
            ->count();
        
        if ($remaining > 0) {
            $this->warn("⚠️ يوجد $remaining مشترك نشط آخر بدون IPTV");
            $this->line("   قم بتشغيل الأمر مرة أخرى لإنشاء المزيد:");
            $this->line("   php artisan iptv:create-subscriber-subscriptions --limit=$limit");
        }
        
        return 0;
    }
}
