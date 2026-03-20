@extends('layouts.app')

@section('title', 'إضافة مستخدم جديد')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">إضافة مستخدم جديد</h1>
        <p class="text-gray-600">إنشاء حساب مستخدم جديد في النظام</p>
    </div>
    <a href="{{ route('users.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
        <i class="fas fa-arrow-right ml-1"></i> رجوع
    </a>
</div>

<form action="{{ route('users.store') }}" method="POST">
    @csrf
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- معلومات المستخدم -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">معلومات المستخدم</h2>
            
            <div class="space-y-4">
                <div>
                    <label for="name" class="block text-gray-700 mb-2">الاسم <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" required
                           value="{{ old('name') }}"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    @error('name')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="block text-gray-700 mb-2">البريد الإلكتروني <span class="text-red-500">*</span></label>
                    <input type="email" name="email" id="email" required
                           value="{{ old('email') }}"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    @error('email')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-gray-700 mb-2">كلمة المرور <span class="text-red-500">*</span></label>
                    <input type="password" name="password" id="password" required
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    @error('password')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-gray-700 mb-2">تأكيد كلمة المرور <span class="text-red-500">*</span></label>
                    <input type="password" name="password_confirmation" id="password_confirmation" required
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label for="role" class="block text-gray-700 mb-2">الدور <span class="text-red-500">*</span></label>
                    <select name="role" id="role" required
                            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="viewer" {{ old('role') == 'viewer' ? 'selected' : '' }}>مشاهد</option>
                        <option value="operator" {{ old('role') == 'operator' ? 'selected' : '' }}>مشغّل</option>
                        <option value="reseller" {{ old('role') == 'reseller' ? 'selected' : '' }}>وكيل / بائع</option>
                        <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>مدير</option>
                        @if(auth()->user()->role === 'super_admin')
                        <option value="super_admin" {{ old('role') == 'super_admin' ? 'selected' : '' }}>مدير عام</option>
                        @endif
                    </select>
                    @error('role')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="phone" class="block text-gray-700 mb-2">رقم الهاتف</label>
                    <input type="text" name="phone" id="phone"
                           value="{{ old('phone') }}"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    @error('phone')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="expiration_days" class="block text-gray-700 mb-2">مدة الصلاحية (بالأيام)</label>
                    <input type="number" name="expiration_days" id="expiration_days"
                           value="{{ old('expiration_days') }}" min="1"
                           placeholder="غير محدود"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">اتركه فارغاً لصلاحية غير محددة</p>
                    @error('expiration_days')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <!-- معلومات الوكيل/البائع -->
        <div id="reseller-fields" class="bg-white rounded-xl shadow-sm p-6 hidden">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">
                <i class="fas fa-store text-green-600 ml-2"></i>
                معلومات الوكيل / البائع
            </h2>
            
            <div class="space-y-4">
                <div>
                    <label for="company_name" class="block text-gray-700 mb-2">اسم الشركة / المحل</label>
                    <input type="text" name="company_name" id="company_name"
                           value="{{ old('company_name') }}"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label for="address" class="block text-gray-700 mb-2">العنوان</label>
                    <textarea name="address" id="address" rows="2"
                              class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">{{ old('address') }}</textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="commission_rate" class="block text-gray-700 mb-2">نسبة العمولة %</label>
                        <input type="number" name="commission_rate" id="commission_rate"
                               value="{{ old('commission_rate', 0) }}" min="0" max="100" step="0.01"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-1">النسبة من كل اشتراك يبيعه الوكيل</p>
                    </div>

                    <div>
                        <label for="max_subscribers" class="block text-gray-700 mb-2">الحد الأقصى للمشتركين</label>
                        <input type="number" name="max_subscribers" id="max_subscribers"
                               value="{{ old('max_subscribers') }}" min="1"
                               placeholder="غير محدود"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-1">اتركه فارغاً لعدد غير محدود</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- تخصيص الراوترات -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">تخصيص الراوترات</h2>
            <p class="text-gray-500 text-sm mb-4">اختر الراوترات التي يمكن للمستخدم الوصول إليها</p>
            
            <div class="space-y-3 max-h-96 overflow-y-auto">
                @foreach($routers as $router)
                <label class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer">
                    <input type="checkbox" name="routers[]" value="{{ $router->id }}"
                           {{ in_array($router->id, old('routers', [])) ? 'checked' : '' }}
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
    </div>

    <div class="mt-6 flex gap-4">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium">
            <i class="fas fa-save ml-1"></i> إنشاء المستخدم
        </button>
        <a href="{{ route('users.index') }}" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-lg font-medium">
            إلغاء
        </a>
    </div>
</form>

<script>
document.getElementById('select-all').addEventListener('change', function() {
    document.querySelectorAll('input[name="routers[]"]').forEach(cb => {
        cb.checked = this.checked;
    });
});

const roleSelect = document.getElementById('role');
const resellerFields = document.getElementById('reseller-fields');

function updateRoleUI() {
    const role = roleSelect.value;
    const routersSection = document.querySelector('input[name="routers[]"]')?.closest('.bg-white');
    
    // Show/hide reseller fields
    if (role === 'reseller') {
        resellerFields.classList.remove('hidden');
    } else {
        resellerFields.classList.add('hidden');
    }
    
    // Update routers section
    if (routersSection) {
        if (role === 'super_admin') {
            routersSection.style.opacity = '0.5';
            routersSection.querySelector('p').textContent = 'المدير العام لديه صلاحية الوصول لجميع الراوترات تلقائياً';
        } else {
            routersSection.style.opacity = '1';
            routersSection.querySelector('p').textContent = 'اختر الراوترات التي يمكن للمستخدم الوصول إليها';
        }
    }
}

roleSelect.addEventListener('change', updateRoleUI);
updateRoleUI(); // Run on page load
</script>
@endsection
