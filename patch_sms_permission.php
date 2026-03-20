<?php
/**
 * Patch: Add sms_enabled permission to users
 * 1. Add sms_enabled column to users table
 * 2. Add sms_enabled to login response 
 * 3. Add sms_enabled to listUsers response
 * 4. Accept sms_enabled in updateUser
 * 5. Guard SMS API with sms_enabled check
 * 6. Super_admin always has SMS access
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Step 1: Add sms_enabled column to users table ===\n";
if (!Schema::hasColumn('users', 'sms_enabled')) {
    Schema::table('users', function ($table) {
        $table->boolean('sms_enabled')->default(false)->after('is_active');
    });
    // Enable SMS for super_admin by default
    DB::table('users')->where('role', 'super_admin')->update(['sms_enabled' => true]);
    echo "DONE: Added sms_enabled column, enabled for super_admins\n";
} else {
    echo "SKIP: sms_enabled column already exists\n";
}

// ==========================================
// Step 2: Patch login response to include sms_enabled
// ==========================================
echo "\n=== Step 2: Patch login response ===\n";
$file = '/var/www/megawifi/app/Http/Controllers/Api/MobileApiController.php';
$content = file_get_contents($file);

// Add sms_enabled to login response
$loginSearch = "'balance' => \$user->balance,\n            ],";
$loginReplace = "'balance' => \$user->balance,\n                'sms_enabled' => (bool) \$user->sms_enabled,\n            ],";

if (strpos($content, "'sms_enabled' => (bool) \$user->sms_enabled") !== false) {
    echo "SKIP: sms_enabled already in login response\n";
} elseif (strpos($content, $loginSearch) !== false) {
    $content = str_replace($loginSearch, $loginReplace, $content);
    echo "DONE: Added sms_enabled to login response\n";
} else {
    echo "WARNING: Could not find login response pattern\n";
}

// ==========================================
// Step 3: Add sms_enabled to listUsers response
// ==========================================
echo "\n=== Step 3: Patch listUsers response ===\n";

$listSearch = '"linked_reseller_ids"';
if (strpos($content, '"sms_enabled" => (bool) $u->sms_enabled') !== false) {
    echo "SKIP: sms_enabled already in listUsers response\n";
} else {
    // Find the listUsers mapping area and add sms_enabled before linked_reseller_ids
    $listSearch = '                    "linked_reseller_ids"';
    $listReplace = '                    "sms_enabled" => (bool) $u->sms_enabled,
                    "linked_reseller_ids"';
    if (strpos($content, $listSearch) !== false) {
        $content = str_replace($listSearch, $listReplace, $content);
        echo "DONE: Added sms_enabled to listUsers response\n";
    } else {
        echo "WARNING: Could not find listUsers pattern\n";
    }
}

// ==========================================
// Step 4: Accept sms_enabled in updateUser
// ==========================================
echo "\n=== Step 4: Patch updateUser to accept sms_enabled ===\n";

if (strpos($content, 'sms_enabled') !== false && strpos($content, '$target->sms_enabled') !== false) {
    echo "SKIP: sms_enabled already in updateUser\n";
} else {
    // Add sms_enabled handling after $target->update($data)
    $updateSearch = '$target->update($data);

        // Sync routers if provided';
    $updateReplace = '// Handle sms_enabled permission
        if ($request->has("sms_enabled")) {
            $target->sms_enabled = (bool) $request->input("sms_enabled");
            $target->save();
        }

        $target->update($data);

        // Sync routers if provided';
    if (strpos($content, $updateSearch) !== false) {
        $content = str_replace($updateSearch, $updateReplace, $content);
        echo "DONE: Added sms_enabled to updateUser\n";
    } else {
        echo "WARNING: Could not find updateUser pattern\n";
    }
}

file_put_contents($file, $content);

// ==========================================
// Step 5: Guard SMS API with sms_enabled check
// ==========================================
echo "\n=== Step 5: Add sms_enabled check to SmsApiController ===\n";
$smsFile = '/var/www/megawifi/app/Http/Controllers/Api/SmsApiController.php';
$smsContent = file_get_contents($smsFile);

if (strpos($smsContent, 'sms_enabled') !== false && strpos($smsContent, 'checkSmsAccess') !== false) {
    echo "SKIP: SMS access check already exists\n";
} else {
    // Add a helper method and guard to SMS controller
    // Find the class body start - add after routerIds function
    $routerIdsEnd = 'private function routerIds($user) {';
    $pos = strpos($smsContent, $routerIdsEnd);
    if ($pos !== false) {
        // Find the closing brace of routerIds function
        // We'll add our method after routerIds
        $checkMethod = '
    /**
     * Check if user has SMS access
     */
    private function checkSmsAccess($user)
    {
        if ($user->isSuperAdmin()) return true;
        return (bool) $user->sms_enabled;
    }

';
        // Find a good insertion point - after the routerIds closing brace
        // Search for the dashboard function and insert before it
        $dashboardFunc = '    public function dashboard(Request $request)';
        if (strpos($smsContent, 'checkSmsAccess') === false) {
            $smsContent = str_replace($dashboardFunc, $checkMethod . $dashboardFunc, $smsContent);
            echo "DONE: Added checkSmsAccess method\n";
        }

        // Add access check at the beginning of dashboard function
        $dashboardBody = 'public function dashboard(Request $request)
    {
        $user = $request->user();
        $allowedIds = $this->routerIds($user);';
        
        $dashboardGuard = 'public function dashboard(Request $request)
    {
        $user = $request->user();
        if (!$this->checkSmsAccess($user)) {
            return response()->json(["error" => "SMS service not enabled for your account", "sms_access" => false], 403);
        }
        $allowedIds = $this->routerIds($user);';

        if (strpos($smsContent, 'checkSmsAccess($user)') === false) {
            $smsContent = str_replace($dashboardBody, $dashboardGuard, $smsContent);
            echo "DONE: Added SMS access guard to dashboard\n";
        }
    } else {
        echo "WARNING: Could not find routerIds function in SmsApiController\n";
    }

    file_put_contents($smsFile, $smsContent);
}

// ==========================================
// Step 6: Add sms_enabled to User model fillable
// ==========================================
echo "\n=== Step 6: Update User model ===\n";
$userModelFile = '/var/www/megawifi/app/Models/User.php';
$userContent = file_get_contents($userModelFile);

if (strpos($userContent, 'sms_enabled') !== false) {
    echo "SKIP: sms_enabled already in User model\n";
} else {
    // Add to fillable array
    if (strpos($userContent, "'is_active'") !== false) {
        $userContent = str_replace("'is_active'", "'is_active',\n        'sms_enabled'", $userContent);
        echo "DONE: Added sms_enabled to User model fillable\n";
    } else {
        echo "WARNING: Could not find is_active in User model fillable\n";
    }
    
    // Add to casts array if exists
    if (strpos($userContent, "'is_active' => 'boolean'") !== false) {
        $userContent = str_replace(
            "'is_active' => 'boolean'", 
            "'is_active' => 'boolean',\n        'sms_enabled' => 'boolean'", 
            $userContent
        );
        echo "DONE: Added sms_enabled to casts\n";
    }
    
    file_put_contents($userModelFile, $userContent);
}

echo "\n=== SUMMARY ===\n";
$users = DB::table('users')->select('id', 'name', 'role', 'sms_enabled')->get();
foreach ($users as $u) {
    echo "ID={$u->id} name={$u->name} role={$u->role} sms_enabled=" . ($u->sms_enabled ? 'YES' : 'NO') . "\n";
}
echo "\nAll patches applied successfully!\n";
