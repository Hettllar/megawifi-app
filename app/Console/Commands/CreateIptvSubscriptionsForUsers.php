<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\IptvSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateIptvSubscriptionsForUsers extends Command
{
    protected $signature = 'iptv:create-subscriptions';
    
    protected $description = 'إنشاء اشتراكات IPTV مجانية لجميع المشتركين';

    public function handle()
    {
        $this->info('🚀 بدء إنشاء اشتراكات IPTV للمشتركين...');
        
        // جلب جميع المستخدمين النشطين الذين ليس لديهم اشتراك IPTV
        $users = User::where('is_active', 1)
            ->whereDoesntHave('iptvSubscription')
            ->get();
        
        if ($users->count() == 0) {
            $this->warn('⚠️ جميع المشتركين لديهم اشتراكات IPTV بالفعل');
            return 0;
        }
        
        $created = 0;
        
        foreach ($users as $user) {
            // إنشاء username وpassword فريدين
            $username = 'iptv_' . $user->id;
            $password = Str::random(10);
            
            // إنشاء اشتراك IPTV
            IptvSubscription::create([
                'user_id' => $user->id,
                'username' => $username,
                'password' => $password,
                'expires_at' => now()->addYear(), // صلاحية سنة
                'is_active' => 1,
                'max_connections' => 2, // يمكن الاتصال من جهازين
                'notes' => 'اشتراك IPTV مجاني - ميزة إضافية للمشتركين'
            ]);
            
            $this->line("✅ تم إنشاء اشتراك لـ: {$user->name} (Username: {$username})");
            $created++;
        }
        
        $this->info("🎉 تم إنشاء {$created} اشتراك IPTV بنجاح!");
        
        return 0;
    }
}
