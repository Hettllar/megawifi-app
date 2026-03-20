@extends('layouts.app')

@section('title', 'توليد أكواد UserManager')

@section('content')
<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">توليد أكواد UserManager</h1>
            <p class="text-gray-600">إنشاء أكواد مشتركين بالجملة</p>
        </div>
        <a href="{{ route('usermanager.index') }}" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-right ml-1"></i> رجوع
        </a>
    </div>
</div>

<div class="grid md:grid-cols-3 gap-6">
    <!-- Form -->
    <div class="md:col-span-2">
        <form method="POST" action="{{ route('usermanager.vouchers.generate', $router) }}" class="bg-white rounded-xl shadow-sm p-6">
            @csrf
            
            <div class="grid md:grid-cols-2 gap-6">
                <!-- Number of Vouchers -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">عدد الأكواد</label>
                    <input type="number" 
                           name="count" 
                           min="1" 
                           max="100"
                           value="10"
                           required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">الحد الأقصى: 100 كود</p>
                </div>
                
                <!-- Username Prefix -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">بادئة اسم المستخدم</label>
                    <input type="text" 
                           name="prefix" 
                           placeholder="user"
                           value="voucher"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">مثال: voucher1, voucher2...</p>
                </div>
                
                <!-- Password Length -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">طول كلمة المرور</label>
                    <input type="number" 
                           name="password_length" 
                           min="4" 
                           max="20"
                           value="8"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                
                <!-- Group/Profile -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">المجموعة</label>
                    <select name="group" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">اختر المجموعة</option>
                        @foreach($groups as $group)
                        <option value="{{ $group['name'] }}">{{ $group['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                
                <!-- Validity Period -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">فترة الصلاحية</label>
                    <div class="flex gap-2">
                        <input type="number" 
                               name="validity_value" 
                               min="1"
                               placeholder="30"
                               class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <select name="validity_unit" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="d">يوم</option>
                            <option value="h">ساعة</option>
                            <option value="m">دقيقة</option>
                        </select>
                    </div>
                </div>
                
                <!-- Download Limit -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">حد التحميل</label>
                    <div class="flex gap-2">
                        <input type="number" 
                               name="download_limit_value"
                               min="0"
                               step="0.1"
                               placeholder="غير محدود"
                               class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <select name="download_limit_unit" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="GB">GB</option>
                            <option value="MB">MB</option>
                            <option value="KB">KB</option>
                        </select>
                    </div>
                </div>
                
                <!-- Upload Limit -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">حد الرفع</label>
                    <div class="flex gap-2">
                        <input type="number" 
                               name="upload_limit_value"
                               min="0"
                               step="0.1"
                               placeholder="غير محدود"
                               class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <select name="upload_limit_unit" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="GB">GB</option>
                            <option value="MB">MB</option>
                            <option value="KB">KB</option>
                        </select>
                    </div>
                </div>
                
                <!-- Shared Users -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">عدد المستخدمين المتزامنين</label>
                    <input type="number" 
                           name="shared_users" 
                           min="1"
                           value="1"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                
                <!-- Price -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">السعر (ل.س)</label>
                    <input type="number" 
                           name="price" 
                           min="0"
                           step="1000"
                           placeholder="0"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            
            <div class="mt-6 flex gap-3">
                <button type="submit" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg font-medium">
                    <i class="fas fa-ticket-alt ml-2"></i> توليد الأكواد
                </button>
                <a href="{{ route('usermanager.index') }}" class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50">
                    إلغاء
                </a>
            </div>
        </form>
    </div>
    
    <!-- Info Panel -->
    <div class="space-y-4">
        <div class="bg-blue-50 border-r-4 border-blue-500 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <i class="fas fa-info-circle text-blue-500 text-xl mt-1"></i>
                <div class="text-sm text-blue-800">
                    <p class="font-medium mb-2">معلومات مهمة:</p>
                    <ul class="list-disc list-inside space-y-1">
                        <li>يتم توليد أسماء مستخدمين وكلمات مرور عشوائية</li>
                        <li>المجموعة تحدد السرعات والقيود</li>
                        <li>الأكواد تُنشأ مباشرة على الراوتر</li>
                        <li>يمكن طباعة الأكواد أو تصديرها</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="bg-yellow-50 border-r-4 border-yellow-500 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <i class="fas fa-exclamation-triangle text-yellow-500 text-xl mt-1"></i>
                <div class="text-sm text-yellow-800">
                    <p class="font-medium mb-2">تنبيه:</p>
                    <p>تأكد من اختيار المجموعة المناسبة قبل التوليد. لا يمكن التراجع عن العملية بعد التنفيذ.</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm p-4">
            <h3 class="font-medium text-gray-800 mb-3">الراوتر المحدد</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">الاسم:</span>
                    <span class="font-medium">{{ $router->name }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">IP:</span>
                    <span class="font-medium">{{ $router->wg_enabled ? $router->wg_client_ip : $router->ip_address }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">الحالة:</span>
                    <span class="px-2 py-1 text-xs rounded-full {{ $router->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ $router->status === 'active' ? 'نشط' : 'معطل' }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
