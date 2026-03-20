@extends('layouts.app')

@section('title', 'تعديل المستخدم: ' . $user->name)

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">تعديل المستخدم</h1>
        <p class="text-gray-600">{{ $user->name }}</p>
    </div>
    <a href="{{ route('users.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
        <i class="fas fa-arrow-right ml-1"></i> رجوع
    </a>
</div>

<form action="{{ route('users.update', $user) }}" method="POST">
    @csrf
    @method('PUT')
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- معلومات المستخدم -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">معلومات المستخدم</h2>
            
            <div class="space-y-4">
                <div>
                    <label for="name" class="block text-gray-700 mb-2">الاسم <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" required
                           value="{{ old('name', $user->name) }}"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    @error('name')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="block text-gray-700 mb-2">البريد الإلكتروني <span class="text-red-500">*</span></label>
                    <input type="email" name="email" id="email" required
                           value="{{ old('email', $user->email) }}"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    @error('email')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-gray-700 mb-2">كلمة المرور الجديدة</label>
                    <input type="password" name="password" id="password"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <p class="text-gray-500 text-sm mt-1">اتركها فارغة للإبقاء على كلمة المرور الحالية</p>
                    @error('password')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-gray-700 mb-2">تأكيد كلمة المرور</label>
                    <input type="password" name="password_confirmation" id="password_confirmation"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                @if(auth()->user()->role === 'super_admin' && $user->id !== auth()->id())
                <div>
                    <label for="role" class="block text-gray-700 mb-2">الدور <span class="text-red-500">*</span></label>
                    <select name="role" id="role" required
                            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="viewer" {{ old('role', $user->role) == 'viewer' ? 'selected' : '' }}>مشاهد</option>
                        <option value="operator" {{ old('role', $user->role) == 'operator' ? 'selected' : '' }}>مشغّل</option>
                        <option value="reseller" {{ old('role', $user->role) == 'reseller' ? 'selected' : '' }}>وكيل / بائع</option>
                        <option value="admin" {{ old('role', $user->role) == 'admin' ? 'selected' : '' }}>مدير</option>
                        <option value="super_admin" {{ old('role', $user->role) == 'super_admin' ? 'selected' : '' }}>مدير عام</option>
                    </select>
                    @error('role')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="phone" class="block text-gray-700 mb-2">رقم الهاتف</label>
                    <input type="text" name="phone" id="phone"
                           value="{{ old('phone', $user->phone) }}"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label for="expiration_days" class="block text-gray-700 mb-2">مدة الصلاحية (بالأيام)</label>
                    <input type="number" name="expiration_days" id="expiration_days"
                           value="{{ old('expiration_days', $user->expires_at ? (int)now()->diffInDays($user->expires_at, false) : '') }}" min="0"
                           placeholder="غير محدود"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">
                        @if($user->expires_at)
                            تنتهي الصلاحية: {{ \Carbon\Carbon::parse($user->expires_at)->format('Y-m-d H:i') }}
                            @if(\Carbon\Carbon::parse($user->expires_at)->isPast())
                                <span class="text-red-600 font-bold">- منتهية!</span>
                            @else
                                <span class="text-green-600">(متبقي {{ (int)now()->diffInDays($user->expires_at, false) }} يوم)</span>
                            @endif
                            <br>أدخل 0 لإزالة الصلاحية
                        @else
                            اتركه فارغاً لصلاحية غير محددة
                        @endif
                    </p>
                    @error('expiration_days')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                @else
                <div>
                    <label class="block text-gray-700 mb-2">الدور</label>
                    <p class="px-4 py-2 bg-gray-100 rounded-lg">{{ $user->role_label }}</p>
                    <input type="hidden" name="role" value="{{ $user->role }}">
                </div>
                @endif
            </div>
        </div>

        <!-- معلومات الوكيل/البائع -->
        <div id="reseller-fields" class="bg-white rounded-xl shadow-sm p-6 {{ $user->role !== 'reseller' ? 'hidden' : '' }}">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">
                <i class="fas fa-store text-green-600 ml-2"></i>
                معلومات الوكيل / البائع
            </h2>
            
            <div class="space-y-4">
                <div>
                    <label for="company_name" class="block text-gray-700 mb-2">اسم الشركة / المحل</label>
                    <input type="text" name="company_name" id="company_name"
                           value="{{ old('company_name', $user->company_name) }}"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label for="address" class="block text-gray-700 mb-2">العنوان</label>
                    <textarea name="address" id="address" rows="2"
                              class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">{{ old('address', $user->address) }}</textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="commission_rate" class="block text-gray-700 mb-2">نسبة العمولة %</label>
                        <input type="number" name="commission_rate" id="commission_rate"
                               value="{{ old('commission_rate', $user->commission_rate) }}" min="0" max="100" step="0.01"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <div>
                        <label for="max_subscribers" class="block text-gray-700 mb-2">الحد الأقصى للمشتركين</label>
                        <input type="number" name="max_subscribers" id="max_subscribers"
                               value="{{ old('max_subscribers', $user->max_subscribers) }}" min="1"
                               placeholder="غير محدود"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>

                @if(auth()->user()->isSuperAdmin())
                <div>
                    <label for="balance" class="block text-gray-700 mb-2">الرصيد الحالي</label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="balance" id="balance"
                               value="{{ old('balance', $user->balance) }}" min="0" step="0.01"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <span class="text-gray-500">د.ع</span>
                    </div>
                </div>

                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-blue-800">عدد المشتركين الحالي:</span>
                        <span class="font-bold text-blue-600">{{ $user->resellerSubscribers()->count() }}</span>
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- تخصيص الراوترات -->
        @if($user->role !== 'super_admin')
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">تخصيص الراوترات</h2>
            <p class="text-gray-500 text-sm mb-4">اختر الراوترات التي يمكن للمستخدم الوصول إليها</p>
            
            @php
                $assignedRouterIds = $user->assignedRouters ? $user->assignedRouters->pluck('id')->toArray() : [];
            @endphp
            <div class="space-y-3 max-h-96 overflow-y-auto">
                @foreach($routers as $router)
                <label class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer">
                    <input type="checkbox" name="routers[]" value="{{ $router->id }}"
                           {{ in_array($router->id, old('routers', $assignedRouterIds)) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <div class="mr-3">
                        <p class="font-medium text-gray-800">{{ $router->name }}</p>
                        <p class="text-sm text-gray-500">{{ $router->ip_address }}</p>
                    </div>
                    <span class="mr-auto px-2 py-1 rounded text-xs
                        {{ $router->is_online ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ $router->is_online ? 'متصل' : 'غير متصل' }}
                    </span>
                </label>
                @endforeach
            </div>

            @if($routers->isEmpty())
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-server text-4xl mb-2"></i>
                <p>لا توجد راوترات مضافة</p>
            </div>
            @endif

            <div class="mt-4 pt-4 border-t">
                <label class="flex items-center">
                    <input type="checkbox" id="select-all" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="mr-2 text-gray-700">تحديد الكل</span>
                </label>
            </div>
        </div>
        @else
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">الصلاحيات</h2>
            <div class="bg-green-50 p-4 rounded-lg">
                <i class="fas fa-shield-alt text-green-600 text-2xl mb-2"></i>
                <p class="text-green-800">المدير العام لديه صلاحية الوصول الكاملة لجميع الراوترات والإعدادات</p>
            </div>
        </div>
        @endif
    </div>

    
    
    <div class="mt-6 flex gap-4">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium">
            <i class="fas fa-save ml-1"></i> حفظ التغييرات
        </button>
        <a href="{{ route('users.index') }}" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-lg font-medium">
            إلغاء
        </a>
    </div>
</form>

    @if(auth()->user()->role === 'super_admin' && $user->device_id)
    <div class="bg-white rounded-xl shadow-sm p-6 mt-6 lg:col-span-2">
        <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">
            <i class="fas fa-mobile-alt text-orange-600 ml-2"></i>
            قفل الجهاز
        </h2>
        <div class="flex items-center justify-between bg-orange-50 p-4 rounded-lg">
            <div>
                <p class="text-orange-800 font-medium">
                    <i class="fas fa-lock ml-1"></i>
                    هذا المستخدم مقفل على جهاز معين
                </p>
                <p class="text-orange-600 text-sm mt-1">
                    معرّف الجهاز: <span class="font-mono text-xs bg-orange-100 px-2 py-1 rounded">{{ $user->device_id }}</span>
                </p>
                @if($user->device_locked_at)
                <p class="text-orange-600 text-sm mt-1">
                    تاريخ القفل: {{ \Carbon\Carbon::parse($user->device_locked_at)->format('Y-m-d H:i') }}
                </p>
                @endif
            </div>
            <form action="{{ route('users.reset-device', $user) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من تصفير الهاتف؟ سيتمكن المستخدم من تسجيل الدخول من أي جهاز آخر.')">
                @csrf
                <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                    <i class="fas fa-mobile-alt ml-1"></i>
                    تصفير الهاتف
                </button>
            </form>
        </div>
    </div>
    @elseif(auth()->user()->role === 'super_admin' && !$user->device_id)
    <div class="bg-white rounded-xl shadow-sm p-6 mt-6 lg:col-span-2">
        <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">
            <i class="fas fa-mobile-alt text-gray-400 ml-2"></i>
            قفل الجهاز
        </h2>
        <div class="bg-gray-50 p-4 rounded-lg text-center">
            <i class="fas fa-unlock text-gray-400 text-2xl mb-2"></i>
            <p class="text-gray-500">لم يتم تسجيل أي جهاز بعد - المستخدم يمكنه الدخول من أي جهاز</p>
        </div>
    </div>
    @endif


<script>
document.getElementById('select-all')?.addEventListener('change', function() {
    document.querySelectorAll('input[name="routers[]"]').forEach(cb => {
        cb.checked = this.checked;
    });
});

const roleSelect = document.getElementById('role');
const resellerFields = document.getElementById('reseller-fields');

if (roleSelect) {
    roleSelect.addEventListener('change', function() {
        if (this.value === 'reseller') {
            resellerFields?.classList.remove('hidden');
        } else {
            resellerFields?.classList.add('hidden');
        }
    });
}
</script>
@endsection
