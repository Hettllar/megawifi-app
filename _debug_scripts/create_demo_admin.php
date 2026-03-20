<?php
require_once 'vendor/autoload.php';
\ = require_once 'bootstrap/app.php';
\ = \->make('Illuminate\\Contracts\\Console\\Kernel');
\->bootstrap();

use App\\Models\\User;
use App\\Models\\Router;
use Illuminate\\Support\\Facades\\Hash;

echo " إنشاء مستخدم مدير تجريبي...\\n\;

// Create or update demo admin
\ = User::updateOrCreate(
 ['email' => 'demo@megawifi.com'],
 [
 'name' => 'مدير تجريبي',
 'password' => Hash::make('demo123'),
 'role' => 'admin',
 'is_active' => true
 ]
);

echo " تم إنشاء المستخدم: \ . \->name . \\\n\;

// Get all routers
\ = Router::all();

if(\->count() > 0) {
    // Remove existing permissions
    \->routers()->detach();
    
    // Add full permissions to all routers
    foreach(\ as \) {
        \->routers()->attach(\->id, [
            'role' => 'admin',
            'can_add_users' => 1,
            'can_edit_users' => 1,
            'can_delete_users' => 1,
            'can_view_reports' => 1,
            'can_manage_hotspot' => 1,
            'can_manage_ppp' => 1
        ]);
        echo " إضافة صلاحيات للراوتر: \ . \->name . \\\n\;
 }
 
 echo \\\n تم إعطاء المستخدم صلاحيات كاملة لجميع الراوترات!\\n\;
} else {
 echo " لا توجد راوترات في النظام\\n\;
}

echo \\\n بيانات الدخول:\\n\;
echo \Email: demo@megawifi.com\\n\;
echo \Password: demo123\\n\;
