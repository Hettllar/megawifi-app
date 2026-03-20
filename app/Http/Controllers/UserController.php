<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Router;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $query = User::with('routers');

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')->paginate(20);

        return view('users.index', compact('users'));
    }

    /**
     * Show the form for creating a new user
     */
    public function create()
    {
        $this->authorize('create', User::class);

        $routers = Router::orderBy('name')->get();

        return view('users.create', compact('routers'));
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => 'required|in:super_admin,admin,reseller,operator,viewer',
            'phone' => 'nullable|string|max:20',
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'max_subscribers' => 'nullable|integer|min:1',
            'routers' => 'array',
            'routers.*' => 'exists:routers,id',
            'expiration_days' => 'nullable|integer|min:1',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'phone' => $validated['phone'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
            'address' => $validated['address'] ?? null,
            'commission_rate' => $validated['role'] === 'reseller' ? ($validated['commission_rate'] ?? 0) : 0,
            'max_subscribers' => $validated['role'] === 'reseller' ? ($validated['max_subscribers'] ?? null) : null,
            'parent_id' => $validated['role'] === 'reseller' ? Auth::id() : null,
            'expires_at' => !empty($validated['expiration_days']) ? \Carbon\Carbon::now()->addDays((int)$validated['expiration_days']) : null,
        ]);

        // Assign routers for non-super-admins
        if ($validated['role'] !== 'super_admin' && !empty($validated['routers'])) {
            foreach ($validated['routers'] as $routerId) {
                $user->routers()->attach($routerId, [
                    'can_manage' => $validated['role'] === 'admin',
                    'can_add_users' => in_array($validated['role'], ['admin', 'operator']),
                    'can_delete_users' => $validated['role'] === 'admin',
                ]);
            }
        }

        ActivityLog::log('user.created', "إنشاء مستخدم جديد: {$user->name}", null, null, User::class, $user->id);

        return redirect()->route('users.index')
            ->with('success', 'تم إنشاء المستخدم بنجاح');
    }

    /**
     * Display the specified user
     */
    public function show(User $user)
    {
        $this->authorize('view', $user);

        $user->load('routers');

        $activityLogs = ActivityLog::where('user_id', $user->id)
            ->latest()
            ->take(20)
            ->get();

        return view('users.show', compact('user', 'activityLogs'));
    }

    /**
     * Show the form for editing the user
     */
    public function edit(User $user)
    {
        $this->authorize('update', $user);

        $routers = Router::orderBy('name')->get();
        $userRouterIds = $user->routers()->pluck('routers.id')->toArray();

        return view('users.edit', compact('user', 'routers', 'userRouterIds'));
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => 'required|in:super_admin,admin,reseller,operator,viewer',
            'phone' => 'nullable|string|max:20',
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'max_subscribers' => 'nullable|integer|min:1',
            'balance' => 'nullable|numeric|min:0',
            'routers' => 'array',
            'routers.*' => 'exists:routers,id',
            'expiration_days' => 'nullable|integer|min:0',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'phone' => $validated['phone'] ?? $user->phone,
            'company_name' => $validated['company_name'] ?? $user->company_name,
            'address' => $validated['address'] ?? $user->address,
            'commission_rate' => $validated['role'] === 'reseller' ? ($validated['commission_rate'] ?? $user->commission_rate) : 0,
            'max_subscribers' => $validated['role'] === 'reseller' ? ($validated['max_subscribers'] ?? $user->max_subscribers) : null,
            'expires_at' => isset($validated['expiration_days']) ? ($validated['expiration_days'] > 0 ? \Carbon\Carbon::now()->addDays((int)$validated['expiration_days']) : null) : $user->expires_at,
        ]);

        // Update balance if provided (admin only)
        if (Auth::user()->isSuperAdmin() && isset($validated['balance'])) {
            $user->update(['balance' => $validated['balance']]);
        }

        if (!empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        // Update router assignments
        $user->routers()->detach();
        
        if ($validated['role'] !== 'super_admin' && !empty($validated['routers'])) {
            foreach ($validated['routers'] as $routerId) {
                $user->routers()->attach($routerId, [
                    'role' => $validated['role'],
                    'can_add_users' => in_array($validated['role'], ['admin', 'operator']),
                    'can_delete_users' => $validated['role'] === 'admin',
                    'can_edit_users' => in_array($validated['role'], ['admin', 'operator']),
                    'can_view_reports' => true,
                    'can_manage_hotspot' => in_array($validated['role'], ['admin', 'operator']),
                    'can_manage_ppp' => in_array($validated['role'], ['admin', 'operator']),
                ]);
            }
        }

        ActivityLog::log('user.updated', "تحديث المستخدم: {$user->name}", null, null, User::class, $user->id);

        return redirect()->route('users.show', $user)
            ->with('success', 'تم تحديث بيانات المستخدم بنجاح');
    }

    /**
     * Remove the specified user
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        // Prevent self-deletion
        if ($user->id === Auth::id()) {
            return back()->withErrors(['error' => 'لا يمكنك حذف حسابك الخاص']);
        }

        $userName = $user->name;
        $user->delete();

        ActivityLog::log('user.deleted', "حذف المستخدم: {$userName}");

        return redirect()->route('users.index')
            ->with('success', 'تم حذف المستخدم بنجاح');
    }

    /**
     * Show profile edit form
     */
    public function profile()
    {
        return view('users.profile', ['user' => Auth::user()]);
    }

    /**
     * Update profile
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'current_password' => 'required_with:new_password|current_password',
            'new_password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if (!empty($validated['new_password'])) {
            $user->update(['password' => Hash::make($validated['new_password'])]);
        }

        return redirect()->route('profile')
            ->with('success', 'تم تحديث الملف الشخصي بنجاح');
    }

    /**
     * Toggle user active status
     */
    public function toggleStatus(User $user)
    {
        $this->authorize('update', $user);

        // Prevent self-deactivation
        if ($user->id === Auth::id()) {
            return back()->with('error', 'لا يمكنك تعطيل حسابك الخاص');
        }

        $user->update(['is_active' => !$user->is_active]);

        $status = $user->is_active ? 'تفعيل' : 'تعطيل';
        ActivityLog::log('user.status_changed', "تم {$status} المستخدم: {$user->name}", null, null, User::class, $user->id);

        return back()->with('success', "تم {$status} المستخدم بنجاح");
    }

    /**
     * Show router management page for user
     */
    public function manageRouters(User $user)
    {
        $this->authorize('update', $user);

        $routers = Router::orderBy('name')->get();
        $assignedRouterIds = $user->routers()->pluck('routers.id')->toArray();

        return view('users.routers', compact('user', 'routers', 'assignedRouterIds'));
    }

    /**
     * Update router assignments for user
     */
    public function updateRouters(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'routers' => 'nullable|array',
            'routers.*' => 'exists:routers,id',
        ]);

        // Detach all current routers
        $user->routers()->detach();

        // Attach new routers
        if (!empty($validated['routers'])) {
            foreach ($validated['routers'] as $routerId) {
                $user->routers()->attach($routerId, [
                    'role' => $user->role,
                    'can_add_users' => in_array($user->role, ['admin', 'operator']),
                    'can_delete_users' => $user->role === 'admin',
                    'can_edit_users' => in_array($user->role, ['admin', 'operator']),
                    'can_view_reports' => true,
                    'can_manage_hotspot' => in_array($user->role, ['admin', 'operator']),
                    'can_manage_ppp' => in_array($user->role, ['admin', 'operator']),
                ]);
            }
        }

        ActivityLog::log('user.routers_updated', "تحديث راوترات المستخدم: {$user->name}", null, null, User::class, $user->id);

        return redirect()->route('users.show', $user)
            ->with('success', 'تم تحديث الراوترات بنجاح');
    }

    public function resetDevice(User $user)
    {
        if (auth()->user()->role !== 'super_admin') {
            abort(403, 'غير مصرح');
        }

        $user->device_id = null;
        $user->device_locked_at = null;
        $user->save();

        return redirect()->back()->with('success', 'تم تصفير الهاتف بنجاح - يمكن للمستخدم الآن تسجيل الدخول من أي جهاز');
    }

}
