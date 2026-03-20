@extends('layouts.app')

@section('title', 'تفاصيل مشترك UserManager')

@section('content')
@php
    $umData = $subscriber->um_data ?? [];
    if (is_string($umData)) {
        $umData = json_decode($umData, true) ?? [];
    }
    
    $downloadUsedGB = ($umData['download_used'] ?? 0) / 1073741824;
    $uploadUsedGB = ($umData['upload_used'] ?? 0) / 1073741824;
    $totalUsedGB = $downloadUsedGB + $uploadUsedGB;
    $downloadLimitGB = ($umData['download_limit'] ?? 0) / 1073741824;
    $uploadLimitGB = ($umData['upload_limit'] ?? 0) / 1073741824;
    
    // حد البيانات المحدد من قاعدة البيانات
    $dataLimitGB = $subscriber->data_limit_gb ?? 0;
    $usagePercent = $dataLimitGB > 0 ? min(100, ($totalUsedGB / $dataLimitGB) * 100) : 0;
@endphp

<div class="mb-4 md:mb-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
            <h1 class="text-xl md:text-2xl font-bold text-gray-800">{{ $subscriber->username }}</h1>
            <p class="text-sm md:text-base text-gray-600">تفاصيل مشترك UserManager</p>
        </div>
        <a href="{{ route('usermanager.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">
            <i class="fas fa-arrow-right ml-1"></i> رجوع
        </a>
    </div>
</div>

@if($subscriber->is_throttled)
<!-- تنبيه التقييد -->
<div class="mb-4 bg-orange-100 border-l-4 border-orange-500 text-orange-700 p-4 rounded-lg">
    <div class="flex items-center">
        <i class="fas fa-exclamation-triangle text-2xl ml-3"></i>
        <div>
            <p class="font-bold">تم تقييد سرعة هذا المشترك</p>
            <p class="text-sm">تجاوز حد الاستهلاك المحدد ({{ $dataLimitGB }} GB). قم بتجديد الاشتراك لاستعادة السرعة العادية.</p>
            @if($subscriber->throttled_at)
            <p class="text-xs text-orange-600 mt-1">تاريخ التقييد: {{ $subscriber->throttled_at->format('Y-m-d H:i') }}</p>
            @endif
        </div>
    </div>
</div>
@endif

<!-- الإجراءات السريعة للموبايل -->
<div class="md:hidden mb-4">
    <div class="bg-white rounded-xl shadow-sm p-3">
        <div class="grid grid-cols-2 gap-2">
            <button onclick="syncUser()" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg text-xs">
                <i class="fas fa-sync-alt ml-1"></i> مزامنة
            </button>
            <button onclick="showEditModal()" class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg text-xs">
                <i class="fas fa-edit ml-1"></i> تعديل
            </button>
            <button onclick="showDataLimitModal()" class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-2 rounded-lg text-xs">
                <i class="fas fa-database ml-1"></i> تحديد الجيجات
            </button>
            <button onclick="renewSubscription()" class="bg-teal-600 hover:bg-teal-700 text-white px-3 py-2 rounded-lg text-xs">
                <i class="fas fa-redo-alt ml-1"></i> تجديد
            </button>
        </div>
        <div class="mt-2 grid grid-cols-2 gap-2">
            <button onclick="toggleIptv()" class="{{ $subscriber->iptv_enabled ? 'bg-purple-500 hover:bg-purple-600' : 'bg-gray-500 hover:bg-gray-600' }} text-white px-3 py-2 rounded-lg text-xs transition-colors" id="iptvToggleBtnMobile">
                <i class="fas fa-tv ml-1"></i> <span id="iptvBtnTextMobile">{{ $subscriber->iptv_enabled ? 'إيقاف IPTV' : 'تفعيل IPTV' }}</span>
            </button>
            <button onclick="resetUsage()" class="bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-2 rounded-lg text-xs">
                <i class="fas fa-redo ml-1"></i> تصفير
            </button>
        </div>
        <div class="mt-2">
            <button onclick="deleteUser()" class="w-full bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg text-xs">
                <i class="fas fa-trash ml-1"></i> حذف
            </button>
        </div>
    </div>
</div>

<div class="grid md:grid-cols-3 gap-4 md:gap-6">
    <div class="md:col-span-2 space-y-4 md:space-y-6">
        
        <!-- معلومات أساسية -->
        <div class="bg-white rounded-xl shadow-sm p-4 md:p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-base md:text-lg font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-user text-blue-600"></i> معلومات أساسية
                </h2>
                <button onclick="showEditModal()" class="text-blue-600 hover:text-blue-800 text-sm hidden md:inline-flex">
                    <i class="fas fa-edit ml-1"></i> تعديل
                </button>
            </div>
            <div class="grid grid-cols-2 gap-3 md:gap-4">
                <div>
                    <label class="text-xs md:text-sm text-gray-500">اسم المستخدم</label>
                    <p class="font-medium text-gray-800 text-sm md:text-base truncate">{{ $subscriber->username }}</p>
                </div>
                <div>
                    <label class="text-xs md:text-sm text-gray-500">الاسم الثلاثي</label>
                    <p class="font-medium text-gray-800 text-sm md:text-base truncate">{{ $subscriber->full_name ?? '-' }}</p>
                </div>
                <div>
                    <label class="text-xs md:text-sm text-gray-500">الهاتف</label>
                    <p class="font-medium text-gray-800 text-sm md:text-base">{{ $subscriber->phone ?? '-' }}</p>
                </div>
                <div>
                    <label class="text-xs md:text-sm text-gray-500">العنوان</label>
                    <p class="font-medium text-gray-800 text-sm md:text-base truncate">{{ $subscriber->address ?? '-' }}</p>
                </div>
                <div>
                    <label class="text-xs md:text-sm text-gray-500">المجموعة</label>
                    <p class="font-medium">
                        <span class="px-2 py-0.5 md:px-3 md:py-1 rounded-full bg-purple-100 text-purple-800 text-xs md:text-sm">{{ $subscriber->profile }}</span>
                    </p>
                </div>
                <div>
                    <label class="text-xs md:text-sm text-gray-500">الراوتر</label>
                    <p class="font-medium text-gray-800 text-sm md:text-base truncate">{{ $subscriber->router->name ?? '-' }}</p>
                </div>
                <div>
                    <label class="text-xs md:text-sm text-gray-500">الحالة</label>
                    <p class="font-medium">
                        @if($subscriber->is_throttled)
                            <span class="px-2 py-0.5 md:px-3 md:py-1 rounded-full bg-orange-100 text-orange-800 text-xs md:text-sm">
                                <i class="fas fa-tachometer-alt-slow ml-1"></i> مقيد السرعة
                            </span>
                        @elseif($subscriber->status === 'active')
                            <span class="px-2 py-0.5 md:px-3 md:py-1 rounded-full bg-green-100 text-green-800 text-xs md:text-sm">نشط</span>
                        @elseif($subscriber->status === 'expired')
                            <span class="px-2 py-0.5 md:px-3 md:py-1 rounded-full bg-red-100 text-red-800 text-xs md:text-sm">منتهي</span>
                        @else
                            <span class="px-2 py-0.5 md:px-3 md:py-1 rounded-full bg-yellow-100 text-yellow-800 text-xs md:text-sm">معطل</span>
                        @endif
                    </p>
                </div>
                <div>
                    <label class="text-xs md:text-sm text-gray-500">تاريخ الانتهاء</label>
                    <p class="font-medium text-gray-800 text-sm md:text-base">
                        {{ $subscriber->expiration_date ? $subscriber->expiration_date->format('Y-m-d') : 'غير محدد' }}
                    </p>
                </div>
            </div>
        </div>
        
        <!-- إحصائيات الاستخدام -->
        <div class="bg-white rounded-xl shadow-sm p-4 md:p-6">
            <h2 class="text-base md:text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-chart-bar text-green-600"></i> إحصائيات الاستخدام
            </h2>
            
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-3 md:p-4 mb-4 md:mb-6">
                <div class="text-center">
                    <p class="text-xs md:text-sm text-gray-500 mb-1">إجمالي الاستهلاك</p>
                    <p class="text-2xl md:text-3xl font-bold text-blue-600">{{ number_format($totalUsedGB, 2) }} GB</p>
                    <div class="flex justify-center gap-4 md:gap-6 mt-2 md:mt-3 text-xs md:text-sm">
                        <span class="text-green-600">
                            <i class="fas fa-download ml-1"></i>
                            {{ number_format($downloadUsedGB, 2) }} GB
                        </span>
                        <span class="text-orange-600">
                            <i class="fas fa-upload ml-1"></i>
                            {{ number_format($uploadUsedGB, 2) }} GB
                        </span>
                    </div>
                </div>
            </div>
            
            @if($dataLimitGB > 0)
            <!-- حد البيانات المحدد -->
            <div class="bg-purple-50 rounded-xl p-4 mb-4">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-purple-800">
                        <i class="fas fa-database ml-1"></i> حد الاستهلاك
                    </span>
                    <span class="text-sm font-bold {{ $usagePercent >= 100 ? 'text-red-600' : ($usagePercent >= 80 ? 'text-orange-600' : 'text-purple-600') }}">
                        {{ number_format($totalUsedGB, 2) }} / {{ number_format($dataLimitGB, 2) }} GB
                    </span>
                </div>
                <div class="w-full bg-purple-200 rounded-full h-3">
                    <div class="h-3 rounded-full transition-all duration-300 {{ $usagePercent >= 100 ? 'bg-red-500' : ($usagePercent >= 80 ? 'bg-orange-500' : 'bg-purple-500') }}" 
                         style="width: {{ min($usagePercent, 100) }}%"></div>
                </div>
                <div class="flex justify-between mt-2 text-xs text-gray-500">
                    <span>{{ number_format($usagePercent, 1) }}% مستخدم</span>
                    <span>متبقي: {{ number_format(max(0, $dataLimitGB - $totalUsedGB), 2) }} GB</span>
                </div>
                @if($subscriber->is_throttled)
                <div class="mt-3 p-2 bg-orange-100 rounded-lg text-center">
                    <span class="text-xs text-orange-700">
                        <i class="fas fa-exclamation-circle ml-1"></i>
                        تم تقييد السرعة - قم بالتجديد لاستعادة السرعة
                    </span>
                </div>
                @endif
            </div>
            @endif
            
            @if($downloadLimitGB > 0 || $uploadLimitGB > 0)
            <div class="space-y-4">
                @if($downloadLimitGB > 0)
                <div>
                    <div class="flex justify-between mb-1 md:mb-2">
                        <span class="text-xs md:text-sm text-gray-600">التحميل</span>
                        <span class="text-xs md:text-sm font-medium text-green-600">
                            {{ number_format($downloadUsedGB, 2) }} / {{ number_format($downloadLimitGB, 2) }} GB
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 md:h-3">
                        <div class="bg-green-500 h-2 md:h-3 rounded-full" style="width: {{ min(($downloadUsedGB / $downloadLimitGB) * 100, 100) }}%"></div>
                    </div>
                </div>
                @endif
                
                @if($uploadLimitGB > 0)
                <div>
                    <div class="flex justify-between mb-1 md:mb-2">
                        <span class="text-xs md:text-sm text-gray-600">الرفع</span>
                        <span class="text-xs md:text-sm font-medium text-blue-600">
                            {{ number_format($uploadUsedGB, 2) }} / {{ number_format($uploadLimitGB, 2) }} GB
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 md:h-3">
                        <div class="bg-blue-500 h-2 md:h-3 rounded-full" style="width: {{ min(($uploadUsedGB / $uploadLimitGB) * 100, 100) }}%"></div>
                    </div>
                </div>
                @endif
            </div>
            @endif
        </div>
        
        <!-- إعدادات UserManager -->
        @if(isset($umData['shared_users']) || isset($umData['validity']) || (isset($umData['price']) && $umData['price'] > 0))
        <div class="bg-white rounded-xl shadow-sm p-4 md:p-6">
            <h2 class="text-base md:text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-cog text-purple-600"></i> إعدادات UserManager
            </h2>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 md:gap-4">
                @if(isset($umData['shared_users']))
                <div>
                    <label class="text-xs md:text-sm text-gray-500">المستخدمين المتزامنين</label>
                    <p class="font-medium text-gray-800 text-sm md:text-base">{{ $umData['shared_users'] }}</p>
                </div>
                @endif
                
                @if(isset($umData['validity']))
                <div>
                    <label class="text-xs md:text-sm text-gray-500">فترة الصلاحية</label>
                    <p class="font-medium text-gray-800 text-sm md:text-base">{{ $umData['validity'] }}</p>
                </div>
                @endif
                
                @if(isset($umData['price']) && $umData['price'] > 0)
                <div>
                    <label class="text-xs md:text-sm text-gray-500">السعر</label>
                    <p class="font-medium text-gray-800 text-sm md:text-base">{{ number_format($umData['price']) }} ل.س</p>
                </div>
                @endif
            </div>
        </div>
        @endif
    </div>
    
    <!-- Sidebar - مخفي على الموبايل -->
    <div class="hidden md:block space-y-4">
        <!-- الإجراءات -->
        <div class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-medium text-gray-800 mb-3">الإجراءات</h3>
            <div class="space-y-2">
                <button onclick="syncUser()" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-sync-alt ml-1"></i> مزامنة البيانات
                </button>
                <button onclick="renewSubscription()" class="w-full bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-redo-alt ml-1"></i> تجديد الاشتراك
                </button>
                <button onclick="showDataLimitModal()" class="w-full bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-database ml-1"></i> تحديد الجيجات
                </button>
                <button onclick="toggleIptv()" class="w-full {{ $subscriber->iptv_enabled ? 'bg-purple-500 hover:bg-purple-600' : 'bg-gray-500 hover:bg-gray-600' }} text-white px-4 py-2 rounded-lg text-sm transition-colors" id="iptvToggleBtn">
                    <i class="fas fa-tv ml-1"></i>
                    <span id="iptvBtnText">{{ $subscriber->iptv_enabled ? 'إيقاف IPTV' : 'تفعيل IPTV' }}</span>
                </button>
                <button onclick="resetUsage()" class="w-full bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-redo ml-1"></i> إعادة تعيين الاستخدام
                </button>
                <button onclick="showEditModal()" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-edit ml-1"></i> تعديل البيانات
                </button>
                <button onclick="deleteUser()" class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-trash ml-1"></i> حذف المستخدم
                </button>
            </div>
        </div>
        
        <!-- IPTV Settings (if enabled) -->
        @if($subscriber->iptv_enabled)
        <div class="bg-gradient-to-br from-purple-50 to-indigo-50 rounded-xl shadow-sm p-4 border border-purple-200">
            <h3 class="font-medium text-purple-800 mb-3 flex items-center gap-2">
                <i class="fas fa-tv"></i>
                إعدادات IPTV
            </h3>
            
            @if($subscriber->iptvSubscription)
            <div class="space-y-3">
                <div class="bg-white/70 rounded-lg p-3">
                    <p class="text-xs text-gray-600 mb-1">اسم المستخدم</p>
                    <p class="font-mono text-sm font-medium text-gray-900 break-all">{{ $subscriber->iptvSubscription->username }}</p>
                </div>
                
                <div class="bg-white/70 rounded-lg p-3">
                    <p class="text-xs text-gray-600 mb-1">كلمة المرور</p>
                    <div class="flex items-center gap-2">
                        <p class="font-mono text-sm font-medium text-gray-900 flex-1" id="iptvPassword">{{ str_repeat('•', 8) }}</p>
                        <button type="button" onclick="toggleIptvPassword()" class="text-purple-600 hover:text-purple-700">
                            <i class="fas fa-eye" id="iptvPasswordIcon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="bg-white/70 rounded-lg p-3">
                    <p class="text-xs text-gray-600 mb-1">حصر الـIP (اختياري)</p>
                    <input type="text" 
                           value="{{ $subscriber->iptv_allowed_ips ?? '' }}" 
                           placeholder="192.168.1.*, 10.0.0.0/24"
                           class="w-full px-3 py-2 bg-white border border-purple-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                           id="iptvAllowedIps">
                    <p class="text-xs text-gray-500 mt-1">افصل بين عدة IPs بفاصلة</p>
                </div>
                
                <button type="button" onclick="updateIptvIps()" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 rounded-lg text-sm font-medium">
                    <i class="fas fa-save ml-1"></i> حفظ إعدادات IP
                </button>
                
                <div class="bg-purple-100 rounded-lg p-3 mt-3">
                    <p class="text-xs text-purple-800 font-medium mb-2">رابط M3U:</p>
                    <button type="button" onclick="copyM3UUrl()" class="w-full text-left">
                        <p class="font-mono text-xs text-purple-900 break-all bg-white/70 p-2 rounded hover:bg-white/90 cursor-pointer" id="m3uUrl">
                            {{ url('/get_playlist.php') }}?username={{ $subscriber->iptvSubscription->username }}&password={{ $subscriber->iptvSubscription->password }}
                        </p>
                    </button>
                    <p class="text-xs text-purple-700 mt-1 text-center">اضغط للنسخ</p>
                </div>
            </div>
            @else
            <div class="bg-yellow-100 border border-yellow-300 rounded-lg p-3">
                <p class="text-sm text-yellow-800">
                    <i class="fas fa-exclamation-triangle ml-1"></i>
                    IPTV مفعل ولكن لم يتم إنشاء الاشتراك. قم بتحديث الصفحة.
                </p>
            </div>
            @endif
        </div>
        @endif
        
        <!-- التواريخ -->
        <div class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-medium text-gray-800 mb-3">التواريخ</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">تاريخ الإنشاء:</span>
                    <span class="font-medium">{{ $subscriber->created_at->format('Y-m-d') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">آخر تحديث:</span>
                    <span class="font-medium">{{ $subscriber->updated_at->format('Y-m-d') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal تعديل البيانات -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl p-4 md:p-6 w-full max-w-md max-h-[90vh] overflow-y-auto">
        <h3 class="text-base md:text-lg font-bold text-gray-800 mb-4">تعديل بيانات المشترك</h3>
        <form id="editForm" onsubmit="updateUser(event)">
            <div class="space-y-3 md:space-y-4">
                <div>
                    <label class="block text-xs md:text-sm font-medium text-gray-700 mb-1">الاسم الثلاثي</label>
                    <input type="text" name="full_name" value="{{ $subscriber->full_name }}" 
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="أدخل الاسم الثلاثي">
                </div>
                <div>
                    <label class="block text-xs md:text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                    <input type="text" name="phone" value="{{ $subscriber->phone }}" 
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="أدخل رقم الهاتف">
                </div>
                <div>
                    <label class="block text-xs md:text-sm font-medium text-gray-700 mb-1">العنوان</label>
                    <input type="text" name="address" value="{{ $subscriber->address }}" 
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="أدخل العنوان">
                </div>
                <div>
                    <label class="block text-xs md:text-sm font-medium text-gray-700 mb-1">كلمة المرور الجديدة</label>
                    <input type="password" name="password" 
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="اتركه فارغاً للإبقاء">
                </div>
            </div>
            <div class="flex gap-2 md:gap-3 mt-4 md:mt-6">
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                    حفظ
                </button>
                <button type="button" onclick="closeEditModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg text-sm">
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal تحديد الجيجات -->
<div id="dataLimitModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl p-4 md:p-6 w-full max-w-md">
        <h3 class="text-base md:text-lg font-bold text-gray-800 mb-4">
            <i class="fas fa-database text-purple-600 ml-2"></i>
            تحديد حد الاستهلاك
        </h3>
        <form id="dataLimitForm" onsubmit="setDataLimit(event)">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">حد الاستهلاك (جيجابايت)</label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="data_limit" id="dataLimitInput"
                               class="flex-1 px-4 py-3 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-center"
                               placeholder="0"
                               min="0"
                               step="1"
                               value="{{ $subscriber->data_limit_gb ?? 0 }}">
                        <span class="text-gray-500 font-medium">GB</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">أدخل 0 لإزالة الحد (غير محدود)</p>
                </div>
                
                <div class="bg-purple-50 rounded-lg p-3">
                    <p class="text-xs text-purple-700">
                        <i class="fas fa-info-circle ml-1"></i>
                        عند الوصول للحد سيتم تقييد السرعة تلقائياً. قم بالتجديد لاستعادة السرعة.
                    </p>
                </div>
                
                <!-- اختصارات سريعة -->
                <div>
                    <label class="block text-xs text-gray-500 mb-2">اختصارات سريعة:</label>
                    <div class="grid grid-cols-4 gap-2">
                        <button type="button" onclick="setQuickLimit(50)" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium">50 GB</button>
                        <button type="button" onclick="setQuickLimit(100)" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium">100 GB</button>
                        <button type="button" onclick="setQuickLimit(200)" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium">200 GB</button>
                        <button type="button" onclick="setQuickLimit(500)" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium">500 GB</button>
                    </div>
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="submit" id="dataLimitBtn" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-check ml-1"></i> تطبيق
                </button>
                <button type="button" onclick="closeDataLimitModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg text-sm">
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showEditModal() {
    document.getElementById('editModal').classList.remove('hidden');
    document.getElementById('editModal').classList.add('flex');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
    document.getElementById('editModal').classList.remove('flex');
}

function updateUser(e) {
    e.preventDefault();
    const form = document.getElementById('editForm');
    const formData = new FormData(form);
    
    fetch('/usermanager/{{ $subscriber->id }}', {
        method: 'PUT',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            full_name: formData.get('full_name'),
            phone: formData.get('phone'),
            address: formData.get('address'),
            password: formData.get('password'),
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success || !data.error) {
            alert('تم تحديث البيانات بنجاح');
            location.reload();
        } else {
            alert(data.message || 'حدث خطأ');
        }
    })
    .catch(() => alert('حدث خطأ في الاتصال'));
}

function syncUser() {
    if (!confirm('هل تريد مزامنة بيانات المستخدم؟')) return;
    
    fetch('/usermanager/{{ $subscriber->router_id }}/sync', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) location.reload();
    })
    .catch(() => alert('حدث خطأ'));
}

function resetUsage() {
    if (!confirm('هل تريد إعادة تعيين استهلاك هذا المستخدم؟ سيتم تصفير العدادات على الراوتر وقاعدة البيانات.')) return;
    
    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i> جاري التصفير...';
    
    fetch('/usermanager/{{ $subscriber->id }}/reset-usage', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) location.reload();
        else {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-redo ml-1"></i> تصفير';
        }
    })
    .catch(() => {
        alert('حدث خطأ في الاتصال');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-redo ml-1"></i> تصفير';
    });
}

function deleteUser() {
    if (!confirm('هل تريد حذف هذا المستخدم نهائياً؟')) return;
    
    fetch('/usermanager/{{ $subscriber->id }}', {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) window.location.href = '/usermanager';
    })
    .catch(() => alert('حدث خطأ'));
}

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

document.getElementById('dataLimitModal').addEventListener('click', function(e) {
    if (e.target === this) closeDataLimitModal();
});

// Data Limit Modal Functions
function showDataLimitModal() {
    document.getElementById('dataLimitModal').classList.remove('hidden');
    document.getElementById('dataLimitModal').classList.add('flex');
}

function closeDataLimitModal() {
    document.getElementById('dataLimitModal').classList.add('hidden');
    document.getElementById('dataLimitModal').classList.remove('flex');
}

function setQuickLimit(gb) {
    document.getElementById('dataLimitInput').value = gb;
}

function setDataLimit(e) {
    e.preventDefault();
    const limitGB = document.getElementById('dataLimitInput').value;
    const btn = document.getElementById('dataLimitBtn');
    
    if (limitGB === '' || limitGB < 0) {
        alert('الرجاء إدخال قيمة صحيحة');
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i> جاري التطبيق...';
    
    fetch('/usermanager/{{ $subscriber->id }}/set-data-limit', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            data_limit_gb: parseFloat(limitGB)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message || 'حدث خطأ');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check ml-1"></i> تطبيق';
        }
    })
    .catch(() => {
        alert('حدث خطأ في الاتصال');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check ml-1"></i> تطبيق';
    });
}

function renewSubscription() {
    if (!confirm('هل تريد تجديد الاشتراك؟\n\nسيتم:\n- تصفير الاستهلاك\n- رفع التقييد (إذا كان مقيد)\n- استعادة السرعة الأصلية')) return;
    
    const btn = event.target;
    btn.disabled = true;
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i> جاري التجديد...';
    
    fetch('/usermanager/{{ $subscriber->id }}/renew', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) location.reload();
        else {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    })
    .catch(() => {
        alert('حدث خطأ في الاتصال');
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
}

// IPTV Functions
function toggleIptv() {
    const btn = document.getElementById('iptvToggleBtn');
    const btnMobile = document.getElementById('iptvToggleBtnMobile');
    const btnText = document.getElementById('iptvBtnText');
    const btnTextMobile = document.getElementById('iptvBtnTextMobile');
    const originalText = btnText.textContent;
    
    if (btn) btn.disabled = true;
    if (btnMobile) btnMobile.disabled = true;
    if (btnText) btnText.textContent = 'جاري التحديث...';
    if (btnTextMobile) btnTextMobile.textContent = 'جاري...';
    
    fetch('/subscribers/{{ $subscriber->id }}/toggle-iptv', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.reload();
        } else {
            alert(data.message || 'حدث خطأ');
            if (btnText) btnText.textContent = originalText;
            if (btnTextMobile) btnTextMobile.textContent = originalText;
            if (btn) btn.disabled = false;
            if (btnMobile) btnMobile.disabled = false;
        }
    })
    .catch(() => {
        alert('حدث خطأ في الاتصال');
        if (btnText) btnText.textContent = originalText;
        if (btnTextMobile) btnTextMobile.textContent = originalText;
        if (btn) btn.disabled = false;
        if (btnMobile) btnMobile.disabled = false;
    });
}

let iptvPasswordVisible = false;
const actualPassword = '{{ $subscriber->iptvSubscription->password ?? '' }}';

function toggleIptvPassword() {
    const passwordEl = document.getElementById('iptvPassword');
    const iconEl = document.getElementById('iptvPasswordIcon');
    
    if (!passwordEl || !iconEl) return;
    
    iptvPasswordVisible = !iptvPasswordVisible;
    
    if (iptvPasswordVisible) {
        passwordEl.textContent = actualPassword;
        iconEl.className = 'fas fa-eye-slash';
    } else {
        passwordEl.textContent = '{{ str_repeat("•", 8) }}';
        iconEl.className = 'fas fa-eye';
    }
}

async function updateIptvIps() {
    const ipsInput = document.getElementById('iptvAllowedIps');
    if (!ipsInput) return;
    
    const ips = ipsInput.value.trim();
    
    try {
        const response = await fetch('/usermanager/{{ $subscriber->id }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                iptv_allowed_ips: ips,
                _method: 'PUT'
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            alert('✅ تم حفظ إعدادات IP بنجاح');
        } else {
            alert('❌ ' + (data.message || 'حدث خطأ'));
        }
    } catch (error) {
        alert('❌ حدث خطأ في الاتصال');
    }
}

function copyM3UUrl() {
    const urlEl = document.getElementById('m3uUrl');
    if (!urlEl) return;
    
    const url = urlEl.textContent.trim();
    
    navigator.clipboard.writeText(url).then(() => {
        const originalText = urlEl.textContent;
        urlEl.textContent = '✅ تم النسخ!';
        urlEl.classList.add('bg-green-100');
        
        setTimeout(() => {
            urlEl.textContent = originalText;
            urlEl.classList.remove('bg-green-100');
        }, 2000);
    }).catch(() => {
        alert('فشل النسخ. جرب يدوياً.');
    });
}
</script>
@endsection
