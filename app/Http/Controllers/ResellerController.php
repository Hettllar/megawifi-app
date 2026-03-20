<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Router;
use App\Models\ResellerRouterPermission;
use App\Models\ResellerPricing;
use App\Models\ResellerTransaction;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ResellerController extends Controller
{
    /**
     * التحقق من صلاحيات المدير العام
     */
    private function requireSuperAdmin()
    {
        if (!Auth::user()->isSuperAdmin()) {
            abort(403, 'هذا الإجراء مخصص للمدير العام فقط');
        }
    }

    /**
     * عرض قائمة الوكلاء
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $selectedRouterId = $request->get('router_id');
        
        \Log::info('ResellerController index', [
            'user_id' => $user->id,
            'user_role' => $user->role,
            'is_super_admin' => $user->isSuperAdmin(),
        ]);
        
        // Get routers based on user role
        if ($user->isSuperAdmin()) {
            // المدير العام يرى جميع الراوترات
            $routers = Router::active()->orderBy('name')->get();
        } else {
            // المدير العادي يرى فقط الراوترات التي يديرها
            $routers = Router::active()
                ->whereHas('admins', function($q) use ($user) {
                    $q->where('users.id', $user->id);
                })
                ->orderBy('name')
                ->get();
        }
        
        \Log::info('ResellerController routers', [
            'routers_count' => $routers->count(),
            'router_ids' => $routers->pluck('id')->toArray(),
        ]);
        
        // Build resellers query
        $query = User::where('role', 'reseller')
            ->withCount('resellerSubscribers')
            ->with('resellerPermissions.router');
        
        // Filter by router if selected
        if ($selectedRouterId) {
            $query->whereHas('resellerPermissions', function($q) use ($selectedRouterId) {
                $q->where('router_id', $selectedRouterId);
            });
        } elseif (!$user->isSuperAdmin()) {
            // المدير العادي يرى فقط الوكلاء الذين لديهم صلاحيات على راوتراته
            $routerIds = $routers->pluck('id')->toArray();
            $query->whereHas('resellerPermissions', function($q) use ($routerIds) {
                $q->whereIn('router_id', $routerIds);
            });
        }
        
        $resellers = $query->orderBy('name')->paginate(20);
        
        \Log::info('ResellerController resellers', [
            'resellers_count' => $resellers->count(),
            'reseller_ids' => $resellers->pluck('id')->toArray(),
        ]);

        return view('resellers.index', compact('resellers', 'routers', 'selectedRouterId'));
    }

    /**
     * صفحة إنشاء وكيل جديد (للمدير العام فقط)
     */
    public function create()
    {
        $this->requireSuperAdmin();
        $routers = Router::active()->orderBy('name')->get();
        return view('resellers.create', compact('routers'));
    }

    /**
     * حفظ وكيل جديد (للمدير العام فقط)
     */
    public function store(Request $request)
    {
        $this->requireSuperAdmin();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'phone' => 'nullable|string|max:20',
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'max_subscribers' => 'nullable|integer|min:0',
            'balance' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $reseller = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'reseller',
            'phone' => $validated['phone'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
            'address' => $validated['address'] ?? null,
            'commission_rate' => $validated['commission_rate'] ?? 0,
            'max_subscribers' => $validated['max_subscribers'] ?? null,
            'balance' => $validated['balance'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'parent_id' => Auth::id(),
        ]);

        ActivityLog::log('reseller.created', "إنشاء وكيل جديد: {$reseller->name}");

        return redirect()->route('resellers.show', $reseller)
            ->with('success', 'تم إنشاء الوكيل بنجاح');
    }

    /**
     * صفحة تعديل وكيل (للمدير العام فقط)
     */
    public function edit(User $reseller)
    {
        $this->requireSuperAdmin();

        if ($reseller->role !== 'reseller') {
            abort(404);
        }

        return view('resellers.edit', compact('reseller'));
    }

    /**
     * تحديث بيانات وكيل (للمدير العام فقط)
     */
    public function update(Request $request, User $reseller)
    {
        $this->requireSuperAdmin();

        if ($reseller->role !== 'reseller') {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $reseller->id,
            'password' => 'nullable|string|min:6|confirmed',
            'phone' => 'nullable|string|max:20',
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'max_subscribers' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'can_view_hotspot_password' => 'boolean',
        ]);

        $reseller->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
            'address' => $validated['address'] ?? null,
            'commission_rate' => $validated['commission_rate'] ?? 0,
            'max_subscribers' => $validated['max_subscribers'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'can_view_hotspot_password' => $validated['can_view_hotspot_password'] ?? false,
        ]);

        if (!empty($validated['password'])) {
            $reseller->update(['password' => Hash::make($validated['password'])]);
        }

        ActivityLog::log('reseller.updated', "تحديث بيانات الوكيل: {$reseller->name}");

        return redirect()->route('resellers.show', $reseller)
            ->with('success', 'تم تحديث بيانات الوكيل بنجاح');
    }

    /**
     * حذف وكيل (للمدير العام فقط)
     */
    public function destroy(User $reseller)
    {
        $this->requireSuperAdmin();

        if ($reseller->role !== 'reseller') {
            abort(404);
        }

        $name = $reseller->name;
        
        // حذف الصلاحيات المرتبطة
        $reseller->resellerPermissions()->delete();
        $reseller->resellerTransactions()->delete();
        
        $reseller->delete();

        ActivityLog::log('reseller.deleted', "حذف الوكيل: {$name}");

        return redirect()->route('resellers.index')
            ->with('success', "تم حذف الوكيل {$name} بنجاح");
    }

    /**
     * عرض تفاصيل وكيل معين
     */
    public function show(User $reseller)
    {
        if ($reseller->role !== 'reseller') {
            abort(404);
        }

        $reseller->load(['resellerPermissions.router', 'resellerSubscribers' => function($q) {
            $q->latest()->take(10);
        }]);

        $transactions = $reseller->resellerTransactions()
            ->with(['admin', 'subscriber'])
            ->latest()
            ->take(20)
            ->get();

        $stats = [
            'total_subscribers' => $reseller->resellerSubscribers()->count(),
            'active_subscribers' => $reseller->resellerSubscribers()->where('status', 'active')->count(),
            'total_purchases' => $reseller->resellerTransactions()->where('type', 'purchase')->sum('amount'),
            'total_deposits' => $reseller->resellerTransactions()->where('type', 'deposit')->sum('amount'),
        ];

        return view('resellers.show', compact('reseller', 'transactions', 'stats'));
    }

    /**
     * صفحة إدارة صلاحيات الوكيل (للمدير العام فقط)
     */
    public function permissions(User $reseller)
    {
        $this->requireSuperAdmin();

        if ($reseller->role !== 'reseller') {
            abort(404);
        }

        $routers = Router::active()->orderBy('name')->get();
        $permissions = $reseller->resellerPermissions()->with('router')->get()->keyBy('router_id');

        return view('resellers.permissions', compact('reseller', 'routers', 'permissions'));
    }

    /**
     * حفظ صلاحيات الوكيل (للمدير العام فقط)
     */
    public function updatePermissions(Request $request, User $reseller)
    {
        $this->requireSuperAdmin();

        if ($reseller->role !== 'reseller') {
            abort(404);
        }

        $validated = $request->validate([
            'routers' => 'array',
            'routers.*.router_id' => 'required|exists:routers,id',
            'routers.*.enabled' => 'boolean',
            'routers.*.can_create_hotspot' => 'boolean',
            'routers.*.can_edit_hotspot' => 'boolean',
            'routers.*.can_delete_hotspot' => 'boolean',
            'routers.*.can_enable_disable_hotspot' => 'boolean',
            'routers.*.can_create_ppp' => 'boolean',
            'routers.*.can_edit_ppp' => 'boolean',
            'routers.*.can_delete_ppp' => 'boolean',
            'routers.*.can_enable_disable_ppp' => 'boolean',
            'routers.*.can_create_usermanager' => 'boolean',
            'routers.*.can_edit_usermanager' => 'boolean',
            'routers.*.can_delete_usermanager' => 'boolean',
            'routers.*.can_renew_usermanager' => 'boolean',
            'routers.*.can_enable_disable_usermanager' => 'boolean',
            'routers.*.can_view_reports' => 'boolean',
            'routers.*.can_generate_vouchers' => 'boolean',
        ]);

        DB::transaction(function () use ($reseller, $validated) {
            foreach ($validated['routers'] ?? [] as $routerData) {
                $routerId = $routerData['router_id'];
                $enabled = $routerData['enabled'] ?? false;

                if (!$enabled) {
                    // حذف الصلاحية إذا تم إلغاء التفعيل
                    $reseller->resellerPermissions()->where('router_id', $routerId)->delete();
                    continue;
                }

                // تحديث أو إنشاء الصلاحية
                ResellerRouterPermission::updateOrCreate(
                    ['reseller_id' => $reseller->id, 'router_id' => $routerId],
                    [
                        'can_create_hotspot' => $routerData['can_create_hotspot'] ?? false,
                        'can_edit_hotspot' => $routerData['can_edit_hotspot'] ?? false,
                        'can_delete_hotspot' => $routerData['can_delete_hotspot'] ?? false,
                        'can_enable_disable_hotspot' => $routerData['can_enable_disable_hotspot'] ?? false,
                        'can_create_ppp' => $routerData['can_create_ppp'] ?? false,
                        'can_edit_ppp' => $routerData['can_edit_ppp'] ?? false,
                        'can_delete_ppp' => $routerData['can_delete_ppp'] ?? false,
                        'can_enable_disable_ppp' => $routerData['can_enable_disable_ppp'] ?? false,
                        'can_create_usermanager' => $routerData['can_create_usermanager'] ?? false,
                        'can_edit_usermanager' => $routerData['can_edit_usermanager'] ?? false,
                        'can_delete_usermanager' => $routerData['can_delete_usermanager'] ?? false,
                        'can_renew_usermanager' => $routerData['can_renew_usermanager'] ?? false,
                        'can_enable_disable_usermanager' => $routerData['can_enable_disable_usermanager'] ?? false,
                        'can_view_reports' => $routerData['can_view_reports'] ?? false,
                        'can_generate_vouchers' => $routerData['can_generate_vouchers'] ?? false,
                    ]
                );
            }
        });

        ActivityLog::log('reseller.permissions.updated', "تحديث صلاحيات الوكيل: {$reseller->name}");

        return redirect()->route('resellers.permissions', $reseller)
            ->with('success', 'تم تحديث الصلاحيات بنجاح');
    }

    /**
     * صفحة شحن الرصيد
     */
    public function deposit(User $reseller)
    {
        if ($reseller->role !== 'reseller') {
            abort(404);
        }

        $recentTransactions = $reseller->resellerTransactions()
            ->where('type', 'deposit')
            ->latest()
            ->take(10)
            ->get();

        return view('resellers.deposit', compact('reseller', 'recentTransactions'));
    }

    /**
     * تنفيذ شحن الرصيد
     */
    public function processDeposit(Request $request, User $reseller)
    {
        if ($reseller->role !== 'reseller') {
            abort(404);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string|max:255',
        ]);

        $description = $validated['description'] ?? 'شحن رصيد من المدير';

        ResellerTransaction::createTransaction(
            $reseller,
            'deposit',
            $validated['amount'],
            $description,
            Auth::user()
        );

        ActivityLog::log('reseller.deposit', "شحن رصيد للوكيل {$reseller->name}: {$validated['amount']}");

        return redirect()->route('resellers.show', $reseller)
            ->with('success', "تم شحن {$validated['amount']} بنجاح. الرصيد الجديد: {$reseller->fresh()->balance}");
    }

    /**
     * صفحة التسعير للراوتر (للمدير العام فقط)
     */
    public function pricing(Router $router)
    {
        $this->requireSuperAdmin();
        $pricing = ResellerPricing::where('router_id', $router->id)->get();
        $serviceTypes = ResellerPricing::getServiceTypes();
        $pricingTypes = ResellerPricing::getPricingTypes();
        $currencies = ResellerPricing::getCurrencies();

        return view('resellers.pricing', compact('router', 'pricing', 'serviceTypes', 'pricingTypes', 'currencies'));
    }

    /**
     * حفظ التسعير (للمدير العام فقط)
     */
    public function updatePricing(Request $request, Router $router)
    {
        $this->requireSuperAdmin();
        $validated = $request->validate([
            'pricing' => 'array',
            'pricing.*.service_type' => 'required|in:hotspot,ppp,usermanager',
            'pricing.*.pricing_type' => 'required|in:per_gb,per_day,per_week,per_month,fixed',
            'pricing.*.price_per_unit' => 'required|numeric|min:0',
            'pricing.*.currency' => 'required|in:SYP,IQD,USD,TRY',
            'pricing.*.is_active' => 'boolean',
            'pricing.*.description' => 'nullable|string|max:255',
        ]);

        // حذف التسعير القديم
        ResellerPricing::where('router_id', $router->id)->delete();

        // إنشاء التسعير الجديد
        foreach ($validated['pricing'] ?? [] as $priceData) {
            if (($priceData['price_per_unit'] ?? 0) > 0) {
                ResellerPricing::create([
                    'router_id' => $router->id,
                    'service_type' => $priceData['service_type'],
                    'pricing_type' => $priceData['pricing_type'],
                    'price_per_unit' => $priceData['price_per_unit'],
                    'currency' => $priceData['currency'],
                    'is_active' => $priceData['is_active'] ?? true,
                    'description' => $priceData['description'] ?? null,
                ]);
            }
        }

        ActivityLog::log('reseller.pricing.updated', "تحديث تسعير الوكلاء للراوتر: {$router->name}");

        return redirect()->route('resellers.pricing', $router)
            ->with('success', 'تم تحديث التسعير بنجاح');
    }

    /**
     * سجل المعاملات
     */
    public function transactions(User $reseller)
    {
        if ($reseller->role !== 'reseller') {
            abort(404);
        }

        $transactions = $reseller->resellerTransactions()
            ->with(['admin', 'subscriber'])
            ->latest()
            ->paginate(30);

        return view('resellers.transactions', compact('reseller', 'transactions'));
    }

    /**
     * حساب سعر الخدمة للوكيل (API)
     */
    public function calculatePrice(Request $request)
    {
        $validated = $request->validate([
            'router_id' => 'required|exists:routers,id',
            'service_type' => 'required|in:hotspot,ppp,usermanager',
            'data_gb' => 'nullable|numeric|min:0',
            'days' => 'nullable|integer|min:0',
        ]);

        $pricing = ResellerPricing::where('router_id', $validated['router_id'])
            ->where('service_type', $validated['service_type'])
            ->where('is_active', true)
            ->first();

        if (!$pricing) {
            return response()->json(['error' => 'لا يوجد تسعير لهذه الخدمة'], 404);
        }

        $price = $pricing->calculatePrice(
            $validated['data_gb'] ?? 0,
            $validated['days'] ?? 0
        );

        return response()->json([
            'price' => $price,
            'currency' => $pricing->currency,
            'pricing_type' => $pricing->pricing_type,
            'formatted' => number_format($price, 0) . ' ' . $pricing->currency,
        ]);
    }
}
