<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Show all users
echo "=== All Users ===\n";
foreach (App\Models\User::all() as $u) {
    echo "{$u->id} | {$u->name} | {$u->email} | {$u->role}\n";
}

// Show router_admins
echo "\n=== Router Admins Pivot ===\n";
$pivots = DB::table('router_admins')->get();
foreach ($pivots as $p) {
    echo "user_id: {$p->user_id} | router_id: {$p->router_id} | role: {$p->role}\n";
}

// Fix: Set wesammiga@gmail.com to super_admin
$u = App\Models\User::where('email', 'wesammiga@gmail.com')->first();
if ($u) {
    $u->role = 'super_admin';
    $u->save();
    echo "\n=== FIXED: {$u->email} role set to super_admin ===\n";
    echo "Accessible routers now: " . $u->getAccessibleRouters()->count() . "\n";
}
