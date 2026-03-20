@extends('layouts.app')

@section('title', 'إضافة باقة جديدة')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">إضافة باقة جديدة</h1>
        <p class="text-gray-600">إنشاء خطة اشتراك جديدة</p>
    </div>
    <a href="{{ route('plans.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
        <i class="fas fa-arrow-right ml-1"></i> رجوع
    </a>
</div>

<form action="{{ route('plans.store') }}" method="POST">
    @csrf
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- معلومات الباقة -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">معلومات الباقة</h2>
            
            <div class="space-y-4">
                <div>
                    <label for="name" class="block text-gray-700 mb-2">اسم الباقة <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" required
                           value="{{ old('name') }}"
                           placeholder="مثال: باقة 10 ميجا"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    @error('name')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="name_en" class="block text-gray-700 mb-2">الاسم بالإنجليزية</label>
                    <input type="text" name="name_en" id="name_en"
                           value="{{ old('name_en') }}"
                           placeholder="10 Mbps Package"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    @error('name_en')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="description" class="block text-gray-700 mb-2">الوصف</label>
                    <textarea name="description" id="description" rows="3"
                              class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="وصف مختصر للباقة...">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="service_type" class="block text-gray-700 mb-2">نوع الخدمة</label>
                    <select name="service_type" id="service_type"
                            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">جميع الأنواع</option>
                        <option value="ppp" {{ old('service_type') == 'ppp' ? 'selected' : '' }}>PPP</option>
                        <option value="hotspot" {{ old('service_type') == 'hotspot' ? 'selected' : '' }}>Hotspot</option>
                        <option value="usermanager" {{ old('service_type') == 'usermanager' ? 'selected' : '' }}>UserManager</option>
                    </select>
                    @error('service_type')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1" checked
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="mr-2 text-gray-700">الباقة نشطة</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- إعدادات السرعة -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">إعدادات السرعة</h2>
            
            <div class="space-y-4">
                <div>
                    <label for="download_speed" class="block text-gray-700 mb-2">سرعة التحميل (Mbps) <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="number" name="download_speed" id="download_speed" required
                               value="{{ old('download_speed') }}"
                               min="1" max="1000"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <span class="absolute left-3 top-2.5 text-gray-500">Mbps</span>
                    </div>
                    @error('download_speed')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="upload_speed" class="block text-gray-700 mb-2">سرعة الرفع (Mbps) <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="number" name="upload_speed" id="upload_speed" required
                               value="{{ old('upload_speed') }}"
                               min="1" max="1000"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <span class="absolute left-3 top-2.5 text-gray-500">Mbps</span>
                    </div>
                    @error('upload_speed')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="burst_limit" class="block text-gray-700 mb-2">Burst Limit (اختياري)</label>
                    <input type="text" name="burst_limit" id="burst_limit"
                           value="{{ old('burst_limit') }}"
                           placeholder="مثال: 20M/20M"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    @error('burst_limit')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="priority" class="block text-gray-700 mb-2">الأولوية (1-8)</label>
                    <input type="number" name="priority" id="priority"
                           value="{{ old('priority', 8) }}"
                           min="1" max="8"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <p class="text-gray-500 text-sm mt-1">1 = أعلى أولوية, 8 = أقل أولوية</p>
                    @error('priority')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <!-- إعدادات الاشتراك -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">إعدادات الاشتراك</h2>
            
            <div class="space-y-4">
                <div>
                    <label for="price" class="block text-gray-700 mb-2">السعر <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="number" name="price" id="price" required
                               value="{{ old('price') }}"
                               min="0" step="100"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <span class="absolute left-3 top-2.5 text-gray-500">ر.ي</span>
                    </div>
                    @error('price')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="validity_days" class="block text-gray-700 mb-2">مدة الصلاحية (أيام) <span class="text-red-500">*</span></label>
                    <div class="flex gap-2">
                        <input type="number" name="validity_days" id="validity_days" required
                               value="{{ old('validity_days', 30) }}"
                               min="1"
                               class="flex-1 border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <button type="button" onclick="document.getElementById('validity_days').value=7" 
                                class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm">أسبوع</button>
                        <button type="button" onclick="document.getElementById('validity_days').value=30" 
                                class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm">شهر</button>
                        <button type="button" onclick="document.getElementById('validity_days').value=365" 
                                class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm">سنة</button>
                    </div>
                    @error('validity_days')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="data_limit_gb" class="block text-gray-700 mb-2">حد البيانات (GB)</label>
                    <div class="relative">
                        <input type="number" name="data_limit_gb" id="data_limit_gb"
                               value="{{ old('data_limit_gb') }}"
                               min="0" step="1"
                               placeholder="اتركه فارغاً لغير محدود"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <span class="absolute left-3 top-2.5 text-gray-500">GB</span>
                    </div>
                    @error('data_limit_gb')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="session_timeout" class="block text-gray-700 mb-2">Session Timeout (دقائق)</label>
                    <input type="number" name="session_timeout" id="session_timeout"
                           value="{{ old('session_timeout') }}"
                           min="0"
                           placeholder="اتركه فارغاً لبدون timeout"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    @error('session_timeout')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="idle_timeout" class="block text-gray-700 mb-2">Idle Timeout (دقائق)</label>
                    <input type="number" name="idle_timeout" id="idle_timeout"
                           value="{{ old('idle_timeout') }}"
                           min="0"
                           placeholder="اتركه فارغاً لبدون timeout"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    @error('idle_timeout')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <!-- إعدادات MikroTik -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">إعدادات MikroTik</h2>
            
            <div class="space-y-4">
                <div>
                    <label for="mikrotik_profile" class="block text-gray-700 mb-2">اسم Profile في MikroTik</label>
                    <input type="text" name="mikrotik_profile" id="mikrotik_profile"
                           value="{{ old('mikrotik_profile') }}"
                           placeholder="سيتم إنشاؤه تلقائياً إذا تركته فارغاً"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono">
                    @error('mikrotik_profile')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="address_pool" class="block text-gray-700 mb-2">Address Pool</label>
                    <input type="text" name="address_pool" id="address_pool"
                           value="{{ old('address_pool') }}"
                           placeholder="اسم pool في MikroTik"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono">
                    @error('address_pool')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="shared_users" class="block text-gray-700 mb-2">عدد الأجهزة المسموحة</label>
                    <input type="number" name="shared_users" id="shared_users"
                           value="{{ old('shared_users', 1) }}"
                           min="1" max="10"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <p class="text-gray-500 text-sm mt-1">عدد الأجهزة التي يمكنها استخدام نفس الحساب</p>
                    @error('shared_users')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="sync_to_routers" value="1" checked
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="mr-2 text-gray-700">مزامنة الباقة مع جميع الراوترات</span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 flex gap-4">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium">
            <i class="fas fa-save ml-1"></i> حفظ الباقة
        </button>
        <a href="{{ route('plans.index') }}" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-lg font-medium">
            إلغاء
        </a>
    </div>
</form>
@endsection
