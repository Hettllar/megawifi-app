@extends('layouts.app')

@section('title', 'UserManager 7')

@section('content')
<div class="mb-2 mb-3">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 sm:gap-4">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 lg:w-10 lg:h-10 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-lg flex items-center justify-center flex-shrink-0">
                <i class="fas fa-users-cog text-white text-sm sm:text-base"></i>
            </div>
            <div>
                <h1 class="text-lg lg:text-xl font-bold text-gray-800">UserManager 7</h1>
                <p class="text-gray-500 text-xs sm:text-sm">إدارة مشتركي UserManager</p>
            </div>
        </div>
        @if($routers->count() > 0)
        <div class="grid grid-cols-4 sm:flex gap-1.5 sm:gap-2 w-full sm:w-auto">
            <a href="{{ route('usermanager.packages.index') }}" 
               class="flex flex-col sm:flex-row items-center justify-center gap-1 bg-gradient-to-r from-orange-500 to-orange-600 text-white p-2 sm:px-3 sm:py-2 rounded-lg text-center shadow-md">
                <i class="fas fa-box text-sm"></i>
                <span class="text-xs sm:text-sm">الباقات</span>
            </a>
            <button onclick="openAssignProfileModal()" 
               class="flex flex-col sm:flex-row items-center justify-center gap-1 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white p-2 sm:px-3 sm:py-2 rounded-lg text-center shadow-md">
                <i class="fas fa-link text-sm"></i>
                <span class="text-xs sm:text-sm">ربط</span>
            </button>
            <a href="{{ route('usermanager.migrate.index') }}" 
               class="flex flex-col sm:flex-row items-center justify-center gap-1 bg-gradient-to-r from-emerald-500 to-green-600 text-white p-2 sm:px-3 sm:py-2 rounded-lg text-center shadow-md">
                <i class="fas fa-exchange-alt text-sm"></i>
                <span class="text-xs sm:text-sm">ترحيل</span>
            </a>
            <button onclick="openAddUserModal()" 
               class="flex flex-col sm:flex-row items-center justify-center gap-1 bg-gradient-to-r from-purple-500 to-purple-600 text-white p-2 sm:px-3 sm:py-2 rounded-lg text-center shadow-md">
                <i class="fas fa-user-plus text-sm"></i>
                <span class="text-xs sm:text-sm">إضافة</span>
            </button>
        </div>
        @endif
    </div>
</div>

<!-- Stats Cards - Ultra Compact for Mobile -->
<div class="grid grid-cols-4 gap-1.5 sm:gap-2 gap-2 mb-3 mb-3">
    <!-- الإجمالي -->
    <div class="bg-gradient-to-br from-blue-50 to-white rounded-lg sm:rounded-lg p-2 p-2 lg:p-2 shadow-sm border border-blue-100">
        <div class="text-center">
            <div class="w-7 h-7 sm:w-8 sm:h-8 lg:w-10 lg:h-10 bg-blue-500 rounded-full flex items-center justify-center mx-auto mb-1">
                <i class="fas fa-users text-white text-xs sm:text-xs lg:text-sm"></i>
            </div>
            <p class="text-sm sm:text-lg lg:text-2xl font-bold text-blue-600">{{ number_format(intval($stats['total'] ?? 0)) }}</p>
            <p class="text-xs sm:text-xs lg:text-xs text-gray-500 font-medium">الإجمالي</p>
        </div>
    </div>
    
    <!-- نشط -->
    <div class="bg-gradient-to-br from-green-50 to-white rounded-lg sm:rounded-lg p-2 p-2 lg:p-2 shadow-sm border border-green-100">
        <div class="text-center">
            <div class="w-7 h-7 sm:w-8 sm:h-8 lg:w-10 lg:h-10 bg-green-500 rounded-full flex items-center justify-center mx-auto mb-1">
                <i class="fas fa-user-check text-white text-xs sm:text-xs lg:text-sm"></i>
            </div>
            <p class="text-sm sm:text-lg lg:text-2xl font-bold text-green-600">{{ number_format(intval($stats['active'] ?? 0)) }}</p>
            <p class="text-xs sm:text-xs lg:text-xs text-gray-500 font-medium">نشط</p>
        </div>
    </div>
    
    <!-- متصل الآن -->
    <div class="bg-gradient-to-br from-emerald-50 to-white rounded-lg sm:rounded-lg p-2 p-2 lg:p-2 shadow-sm border border-emerald-100">
        <div class="text-center">
            <div class="w-7 h-7 sm:w-8 sm:h-8 lg:w-10 lg:h-10 bg-emerald-500 rounded-full flex items-center justify-center mx-auto mb-1 relative">
                <i class="fas fa-wifi text-white text-xs sm:text-xs lg:text-sm"></i>
                <span class="absolute -top-0.5 -right-0.5 flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-300"></span>
                </span>
            </div>
            <p class="text-sm sm:text-lg lg:text-2xl font-bold text-emerald-600">{{ number_format($stats['online'] ?? $subscribers->where('is_online', true)->count()) }}</p>
            <p class="text-xs sm:text-xs lg:text-xs text-gray-500 font-medium">متصل</p>
        </div>
    </div>
    
    <!-- غير مدفوع -->
    <div class="bg-gradient-to-br from-amber-50 to-white rounded-lg sm:rounded-lg p-2 p-2 lg:p-2 shadow-sm border border-amber-100 cursor-pointer hover:shadow-md transition-all" onclick="filterUnpaid()" title="اضغط لعرض غير المدفوعين فقط">
        <div class="text-center">
            <div class="w-7 h-7 sm:w-8 sm:h-8 lg:w-10 lg:h-10 bg-amber-500 rounded-full flex items-center justify-center mx-auto mb-1">
                <i class="fas fa-money-bill-wave text-white text-xs sm:text-xs lg:text-sm"></i>
            </div>
            <p class="text-sm sm:text-lg lg:text-2xl font-bold text-amber-600">{{ number_format(intval($stats['unpaid'] ?? 0)) }}</p>
            <p class="text-xs sm:text-xs lg:text-xs text-gray-500 font-medium">غير مدفوع</p>
        </div>
    </div>
</div>


<!-- Search & Filters - Compact -->
<div class="bg-white rounded-lg shadow-sm p-2.5 sm:p-2 mb-3 mb-3 border" x-data="{ showFilters: false }">
    <form method="GET" action="{{ route('usermanager.index') }}">
        <!-- Main Search Bar -->
        <div class="flex gap-2">
            <div class="flex-1 relative">
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                    <i class="fas fa-search text-sm"></i>
                </span>
                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="بحث..."
                       class="w-full pr-9 pl-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm bg-gray-50 focus:bg-white">
            </div>
            
            <!-- Filter Toggle Button -->
            <button type="button" 
                    @click="showFilters = !showFilters"
                    class="px-3 py-2.5 border rounded-lg transition flex items-center justify-center gap-1.5 text-sm"
                    :class="showFilters || '{{ request()->hasAny(['router_id', 'status', 'profile']) }}' ? 'bg-blue-50 border-blue-300 text-blue-600' : 'bg-gray-50 border-gray-200 text-gray-600'">
                <i class="fas fa-filter text-xs"></i>
                @if(request()->hasAny(['router_id', 'status', 'profile']))
                    <span class="w-4 h-4 bg-blue-500 text-white text-xs rounded-full flex items-center justify-center">
                        {{ collect([request('router_id'), request('status'), request('profile')])->filter()->count() }}
                    </span>
                @endif
            </button>
            
            <!-- Search Button -->
            <button type="submit" class="bg-blue-600 text-white px-4 py-2.5 rounded-lg transition flex items-center justify-center">
                <i class="fas fa-search text-sm"></i>
            </button>
        </div>
        
        <!-- Advanced Filters Panel -->
        <div x-show="showFilters" 
             x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="mt-3 pt-3 border-t border-gray-100">
            
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <!-- Router Filter -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">الراوتر</label>
                    <select name="router_id" class="w-full px-2 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm bg-white" onchange="this.form.submit()">
                        @if(isset($isSuperAdmin) && $isSuperAdmin)
                            <option value="">⬇ اختر راوتر</option>
                        @else
                            <option value="">الكل</option>
                        @endif
                        @foreach($routers as $router)
                            <option value="{{ $router->id }}" {{ request('router_id') == $router->id ? 'selected' : '' }}>
                                {{ $router->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <!-- Status Filter -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">الحالة</label>
                    <select name="status" class="w-full px-2 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm bg-white">
                        <option value="">الكل</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>نشط</option>
                        <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>منتهي</option>
                        <option value="disabled" {{ request('status') === 'disabled' ? 'selected' : '' }}>معطل</option>
                    </select>
                </div>
                
                <!-- Profile Filter -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">الباقة</label>
                    <select name="profile" class="w-full px-2 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm bg-white">
                        <option value="">الكل</option>
                        @php
                            $profiles = $subscribers->pluck('profile')->unique()->filter()->sort();
                        @endphp
                        @foreach($profiles as $profile)
                            <option value="{{ $profile }}" {{ request('profile') === $profile ? 'selected' : '' }}>
                                {{ $profile }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <!-- Per Page -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">العدد</label>
                    <select name="per_page" class="w-full px-2 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm bg-white">
                        <option value="15" {{ request('per_page', 15) == 15 ? 'selected' : '' }}>15</option>
                        <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                    </select>
                </div>
            </div>
            
            <!-- Filter Actions -->
            <div class="flex items-center justify-between gap-2 mt-3 pt-2 border-t border-gray-100">
                <div class="text-xs text-gray-500">
                    @if(request()->hasAny(['search', 'router_id', 'status', 'profile']))
                        <span class="text-blue-600 font-medium">{{ $subscribers->count() }} نتيجة</span>
                    @endif
                </div>
                <div class="flex gap-1.5">
                    @if(request()->hasAny(['search', 'router_id', 'status', 'profile', 'per_page']))
                    <a href="{{ route('usermanager.index') }}" 
                       class="px-2.5 py-1.5 bg-red-50 text-red-600 rounded-lg text-xs transition">
                        <i class="fas fa-times"></i>
                    </a>
                    @endif
                    <button type="submit" class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs transition">
                        تطبيق
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Quick Filters (Active filters chips) -->
        @if(request()->hasAny(['router_id', 'status', 'profile']))
        <div class="flex flex-wrap gap-1.5 mt-2 pt-2 border-t border-gray-100">
            @if(request('router_id'))
                @php $routerName = $routers->find(request('router_id'))?->name ?? 'غير معروف'; @endphp
                <a href="{{ request()->fullUrlWithoutQuery('router_id') }}" 
                   class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-50 text-blue-700 rounded-full text-xs font-medium">
                    {{ $routerName }}
                    <i class="fas fa-times text-[8px]"></i>
                </a>
            @endif
            @if(request('status'))
                <a href="{{ request()->fullUrlWithoutQuery('status') }}" 
                   class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-50 text-green-700 rounded-full text-xs font-medium">
                    {{ request('status') === 'active' ? 'نشط' : (request('status') === 'expired' ? 'منتهي' : 'معطل') }}
                    <i class="fas fa-times text-[8px]"></i>
                </a>
            @endif
            @if(request('profile'))
                <a href="{{ request()->fullUrlWithoutQuery('profile') }}" 
                   class="inline-flex items-center gap-1 px-2 py-0.5 bg-purple-50 text-purple-700 rounded-full text-xs font-medium">
                    {{ request('profile') }}
                    <i class="fas fa-times text-[8px]"></i>
                </a>
            @endif
        </div>
        @endif
    </form>
</div>

<!-- Users List - Mobile Cards / Desktop Table -->
@if(isset($noRouterSelected) && $noRouterSelected)
    <div class="bg-white rounded-xl shadow-sm p-6 sm:p-10 text-center border border-dashed border-blue-200">
        <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto mb-4 bg-gradient-to-br from-blue-100 to-indigo-100 rounded-full flex items-center justify-center">
            <i class="fas fa-hand-pointer text-blue-400 text-2xl sm:text-3xl"></i>
        </div>
        <h3 class="text-gray-700 text-base sm:text-lg font-bold mb-2">اختر راوتر لعرض المشتركين</h3>
        <p class="text-gray-400 text-sm mb-4">حدد راوتر من القائمة أعلاه لتحميل بيانات المشتركين</p>
        <div class="flex flex-wrap justify-center gap-2">
            @foreach($routers as $router)
                <a href="{{ route('usermanager.index', ['router_id' => $router->id]) }}" 
                   class="inline-flex items-center gap-1.5 px-3 py-2 bg-gradient-to-r from-blue-50 to-indigo-50 hover:from-blue-100 hover:to-indigo-100 text-blue-700 rounded-lg text-sm font-medium transition border border-blue-200 hover:border-blue-300 hover:shadow-sm">
                    <i class="fas fa-server text-xs"></i>
                    {{ $router->name }}
                </a>
            @endforeach
        </div>
    </div>
@elseif($subscribers->isEmpty())
    <div class="bg-white rounded-lg shadow-sm p-4 sm:p-8 text-center">
        <div class="w-12 h-12 sm:w-16 sm:h-16 mx-auto mb-3 bg-gray-100 rounded-full flex items-center justify-center">
            <i class="fas fa-users text-gray-300 text-lg lg:text-xl"></i>
        </div>
        <p class="text-gray-500 text-sm sm:text-lg">لا يوجد مستخدمين</p>
        @if($routers->isNotEmpty())
        <button onclick="syncAllRouters()" id="syncAllBtn" class="mt-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white px-4 py-2 rounded-lg text-sm shadow-md">
            <i class="fas fa-sync ml-1"></i> مزامنة
        </button>
        @endif
    </div>
@else
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <!-- Header -->
        <div class="p-2 sm:p-2 border-b flex justify-between items-center bg-gray-50">
            <span class="text-gray-600 text-xs sm:text-sm font-medium">
                {{ $subscribers->count() }} مستخدم
            </span>
            <div class="flex items-center gap-2">
                @if(isset($lastSync))
                <div class="hidden lg:block text-xs text-gray-500 text-left">
                    <span class="font-medium">آخر مزامنة:</span>
                    <span class="text-purple-600">{{ \Carbon\Carbon::parse($lastSync)->locale('ar')->diffForHumans() }}</span>
                    @if(isset($syncInterval))
                    <span class="text-gray-400">(كل {{ $syncInterval }} د)</span>
                    @endif
                </div>
                @endif
                <button onclick="syncAllRouters()" id="syncAllBtnHeader" class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-2.5 py-1.5 rounded-lg text-xs shadow-sm flex items-center gap-1 transition">
                    <i class="fas fa-sync-alt"></i>
                    <span class="hidden sm:inline">مزامنة يدوية</span>
                    <span class="sm:hidden">مزامنة</span>
                </button>
                <button onclick="refreshAllUsage()" id="refreshUsageBtn" class="bg-gradient-to-r from-purple-500 to-violet-600 hover:from-purple-600 hover:to-violet-700 text-white px-2.5 py-1.5 rounded-lg text-xs shadow-sm flex items-center gap-1 transition" title="تحديث استهلاك جميع المشتركين (فصل وتوصيل)">
                    <i class="fas fa-redo-alt"></i>
                    <span class="hidden sm:inline">تحديث الاستهلاك</span>
                    <span class="sm:hidden">استهلاك</span>
                </button>
                <button onclick="openBackupModal()" class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-2.5 py-1.5 rounded-lg text-xs shadow-sm flex items-center gap-1 transition">
                    <i class="fas fa-database"></i>
                    <span>نسخ</span>
                </button>
                <button onclick="openBulkExpiryModal()" class="bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white px-2 py-1.5 rounded-lg text-xs shadow-sm flex items-center gap-1 transition">
                    <i class="fas fa-calendar-alt text-xs"></i>
                    <span class="hidden sm:inline">تاريخ انتهاء جماعي</span>
                    <span class="sm:hidden">تاريخ</span>
                </button>
            </div>
        </div>
        
        <!-- Mobile Cards View -->
        <div class="block md:hidden divide-y">
            @foreach($subscribers as $index => $subscriber)
            @php
                $umInfo = is_string($subscriber->um_data) ? json_decode($subscriber->um_data, true) : ($subscriber->um_data ?? []);
                $downloadSpeed = $umInfo['download_speed'] ?? '';
                $downloadUsed = ($umInfo['download_used'] ?? 0) > 0 
                    ? $umInfo['download_used'] / 1073741824 
                    : ($subscriber->bytes_out ?? 0) / 1073741824;
                $uploadUsed = ($umInfo['upload_used'] ?? 0) > 0 
                    ? $umInfo['upload_used'] / 1073741824 
                    : ($subscriber->bytes_in ?? 0) / 1073741824;
                $totalUsed = $downloadUsed + $uploadUsed;
                
                // Check data limit
                $dataLimitBytes = floatval($subscriber->data_limit ?? ($subscriber->data_limit_gb ? $subscriber->data_limit_gb * 1073741824 : 0));
                $dataLimitGb = $dataLimitBytes > 0 ? $dataLimitBytes / 1073741824 : 0;
                $remainingGb = $dataLimitGb > 0 ? max(0, $dataLimitGb - $totalUsed) : 0;
                $usagePercent = $dataLimitGb > 0 ? min(100, ($totalUsed / $dataLimitGb) * 100) : 0;
            @endphp
            <div class="px-3 py-3 {{ $index % 2 === 0 ? 'bg-white' : 'bg-slate-50/50' }} border-b transition-all duration-300" data-subscriber-id="{{ $subscriber->id }}">
                {{-- Row 1: User info and status --}}
                <div class="flex items-center justify-between gap-2 mb-2">
                    <div class="flex items-center gap-2.5 flex-1 min-w-0">
                        <button onclick="refreshUserUsage({{ $subscriber->id }}, '{{ $subscriber->mikrotik_id }}', {{ $subscriber->router_id }}, '{{ $subscriber->username }}')" 
                                class="w-10 h-10 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-lg flex items-center justify-center flex-shrink-0 hover:from-blue-500 hover:to-cyan-500 transition-all duration-300 hover:scale-105 hover:shadow-lg active:scale-95"
                                id="refresh-btn-mobile-{{ $subscriber->id }}"
                                title="اضغط لتحديث الاستهلاك">
                            <i class="fas fa-user text-white text-sm" id="refresh-icon-mobile-{{ $subscriber->id }}"></i>
                        </button>
                        <div class="min-w-0 flex-1">
                            @if($subscriber->full_name)
                                <p class="font-bold text-gray-800 text-[15px] truncate">{{ $subscriber->full_name }}</p>
                                <p class="text-sm text-gray-400 truncate">{{ $subscriber->username }}</p>
                            @else
                                <p class="font-bold text-gray-800 text-[15px] truncate">{{ $subscriber->username }}</p>
                            @endif
                            <div class="flex items-center gap-1.5 text-sm text-gray-500 mt-0.5">
                                <span class="bg-gray-100 px-1.5 py-0.5 rounded truncate max-w-[80px]">{{ $subscriber->router->name ?? '-' }}</span>
                                @if($downloadSpeed)
                                <span class="text-green-600 bg-green-50 px-1.5 py-0.5 rounded">
                                    <i class="fas fa-tachometer-alt text-xs ml-0.5"></i>{{ $downloadSpeed }}
                                </span>
                                @else
                                <span class="text-purple-600 bg-purple-50 px-1.5 py-0.5 rounded truncate max-w-[70px]">{{ $subscriber->profile ?? 'default' }}</span>
                                @endif
                            </div>
                            @if($subscriber->expiration_date)
                            <div class="flex items-center gap-1 text-xs mt-0.5 {{ \Carbon\Carbon::parse($subscriber->expiration_date)->isPast() ? 'text-red-600' : 'text-gray-500' }}">
                                <i class="fas fa-calendar-alt {{ \Carbon\Carbon::parse($subscriber->expiration_date)->isPast() ? 'text-red-500' : 'text-blue-500' }}"></i>
                                <span>ينتهي: {{ \Carbon\Carbon::parse($subscriber->expiration_date)->format('Y-m-d') }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                    
                    {{-- Status badge and WhatsApp --}}
                    <div class="flex items-center gap-2">
                        @if($subscriber->phone)
                            @php
                                $phoneNum = preg_replace('/[^0-9]/', '', $subscriber->phone);
                                if(substr($phoneNum, 0, 1) === '0') $phoneNum = '963' . substr($phoneNum, 1);
                                elseif(substr($phoneNum, 0, 3) !== '963') $phoneNum = '963' . $phoneNum;
                                
                                $usedGbRaw = ($subscriber->total_bytes ?? 0) / 1073741824;
                                $usedGb = number_format(floatval($usedGbRaw), 2);
                                $dataLimitGbWaRaw = $subscriber->data_limit ? $subscriber->data_limit / 1073741824 : 0;
                                $dataLimitGbWa = $dataLimitGbWaRaw > 0 ? number_format(floatval($dataLimitGbWaRaw), 0) : 0;
                                $remainingGb = $dataLimitGbWaRaw > 0 ? number_format(max(0, $dataLimitGbWaRaw - $usedGbRaw), 2) : 0;
                                $expDate = $subscriber->expiration_date ? \Carbon\Carbon::parse($subscriber->expiration_date)->format('Y-m-d') : 'غير محدد';
                                
                                // رسالة استعلام فقط (بدون تذكير بالدفع)
                                $waMsg = "👋 مرحباً " . ($subscriber->full_name ?? $subscriber->username) . "\n\n";
                                $waMsg .= "📊 *استهلاكك الحالي:*\n";
                                $waMsg .= "• المستهلك: " . $usedGb . " GB\n";
                                if($dataLimitGbWa > 0) {
                                    $waMsg .= "• المتبقي: " . $remainingGb . " GB من " . $dataLimitGbWa . " GB\n";
                                }
                                $waMsg .= "\n📅 تاريخ التجديد: " . $expDate . "\n\n";
                                $waMsg .= "🔍 تفقد رصيدك من هنا:\nhttps://megawifi.site/check-balance\n\n";
                                $waMsg .= "✨ شكراً لكم - MegaWiFi 🌐";
                                $waType = $subscriber->router->whatsapp_type ?? 'regular';
                            @endphp
                            <a href="javascript:void(0)" onclick="openWhatsappDirect('{{ $phoneNum }}', '{{ urlencode($waMsg) }}', '{{ $waType }}')" 
                               class="w-8 h-8 bg-green-500 hover:bg-green-600 rounded-full flex items-center justify-center transition shadow-sm"
                               title="إرسال تفاصيل الاستهلاك">
                                <i class="fab fa-whatsapp text-white text-sm"></i>
                            </a>
                        @endif
                        {{-- Online/Offline indicator --}}
                        @if($subscriber->is_online)
                            <span class="relative flex h-3 w-3" id="online-badge-mobile-{{ $subscriber->id }}" title="متصل الآن">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                            </span>
                        @else
                            <span class="relative flex h-3 w-3" id="online-badge-mobile-{{ $subscriber->id }}" title="غير متصل">
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-gray-300"></span>
                            </span>
                        @endif
                        @if($subscriber->status === 'active')
                            <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold">نشط</span>
                        @elseif($subscriber->status === 'expired')
                            <span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs font-bold">منتهي</span>
                        @else
                            <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-bold">معطل</span>
                        @endif
                    </div>
                </div>
                
                {{-- Row 2: Data usage with progress bar (if has limit) --}}
                @if($dataLimitGb > 0)
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-2.5 mb-2" id="usage-box-mobile-{{ $subscriber->id }}">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-sm text-gray-600">
                            <i class="fas fa-database ml-1 text-blue-500"></i>
                            الاستهلاك
                        </span>
                        <span class="text-[12px] font-bold text-gray-700" id="usage-text-mobile-{{ $subscriber->id }}">
                            {{ number_format(floatval($totalUsed), 2) }} / {{ number_format(floatval($dataLimitGb), 0) }} GB
                        </span>
                    </div>
                    <div class="w-full h-2.5 bg-gray-200 rounded-full overflow-hidden mb-1.5">
                        <div class="h-full rounded-full transition-all {{ $usagePercent > 90 ? 'bg-red-500' : ($usagePercent > 70 ? 'bg-orange-500' : 'bg-blue-500') }}" 
                             style="width: {{ $usagePercent }}%" id="usage-bar-mobile-{{ $subscriber->id }}" data-limit="{{ $dataLimitGb }}"></div>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500" id="usage-percent-mobile-{{ $subscriber->id }}">{{ number_format(floatval($usagePercent), 0) }}% مستخدم</span>
                        <span class="text-sm font-bold {{ $remainingGb < 1 ? 'text-red-600' : ($remainingGb < 3 ? 'text-orange-600' : 'text-green-600') }}" id="usage-remaining-mobile-{{ $subscriber->id }}">
                            <i class="fas fa-hourglass-half ml-1"></i>
                            متبقي {{ number_format(floatval($remainingGb), 1) }} GB
                        </span>
                    </div>
                </div>
                @elseif($totalUsed > 0.01)
                <div class="flex items-center gap-2 mb-2" id="usage-simple-mobile-{{ $subscriber->id }}">
                    <span class="text-sm text-gray-500">الاستهلاك:</span>
                    <span class="text-[12px] font-bold text-blue-700 bg-blue-100 px-2 py-0.5 rounded" id="usage-value-mobile-{{ $subscriber->id }}">
                        <i class="fas fa-database ml-1"></i>
                        {{ number_format(floatval($totalUsed), 2) }} GB
                    </span>
                </div>
                @else
                <div class="flex items-center gap-2 mb-2 hidden" id="usage-simple-mobile-{{ $subscriber->id }}">
                    <span class="text-sm text-gray-500">الاستهلاك:</span>
                    <span class="text-[12px] font-bold text-blue-700 bg-blue-100 px-2 py-0.5 rounded" id="usage-value-mobile-{{ $subscriber->id }}">
                        <i class="fas fa-database ml-1"></i>
                        0 GB
                    </span>
                </div>
                @endif
                
                {{-- Row 3: Actions --}}
                <div class="flex items-center justify-between gap-2">
                    {{-- Payment Status --}}
                    <div id="paid-badge-mobile-{{ $subscriber->id }}">
                        @if($subscriber->is_paid)
                        @php
                            $expDatePaidM = $subscriber->expiration_date ? \Carbon\Carbon::parse($subscriber->expiration_date)->format('Y-m-d') : 'غير محدد';
                            $brandNameM = $subscriber->router->brand_name ?? 'MegaWiFi';
                        @endphp
                        <button onclick="sendPaidConfirmation({{ $subscriber->id }}, '{{ $subscriber->whatsapp_number ?? $subscriber->phone }}', '{{ addslashes($subscriber->full_name ?? $subscriber->username) }}', {{ $subscriber->subscription_price ?? 0 }}, '{{ $expDatePaidM }}', '{{ $subscriber->router->whatsapp_type ?? 'regular' }}', '{{ $brandNameM }}')"
                                class="paid-badge px-2.5 py-1.5 bg-green-100 text-green-700 rounded-full text-xs font-bold active:scale-95 transition"
                                title="إرسال تأكيد دفع">
                            <i class="fas fa-check-circle ml-1"></i>مدفوع
                        </button>
                        @elseif($subscriber->remaining_amount > 0)
                        @php 
                            $shamcashUrl1 = $subscriber->router->shamcash_qr ? url('storage/' . $subscriber->router->shamcash_qr) : '';
                            $brandNameM1 = $subscriber->router->brand_name ?? 'MegaWiFi';
                        @endphp
                        <button onclick="sendDebtReminder({{ $subscriber->id }}, '{{ $subscriber->whatsapp_number ?? $subscriber->phone }}', '{{ addslashes($subscriber->full_name ?? $subscriber->username) }}', {{ $subscriber->remaining_amount ?? 0 }}, '{{ $subscriber->router->whatsapp_type ?? 'regular' }}', '{{ $shamcashUrl1 }}', '{{ $brandNameM1 }}')"
                                class="debt-badge px-2.5 py-1.5 bg-orange-500 text-white rounded-full text-xs font-bold active:scale-95 transition"
                                title="إرسال تذكير دين">
                            <i class="fas fa-hand-holding-usd ml-1"></i>مدان {{ number_format(floatval($subscriber->remaining_amount), 0) }}
                        </button>
                        @else
                        @php
                            $usedGbPay = number_format(($subscriber->total_bytes ?? 0) / 1073741824, 2);
                            $expDatePay = $subscriber->expiration_date ? \Carbon\Carbon::parse($subscriber->expiration_date)->format('Y-m-d') : 'غير محدد';
                            $shamcashUrl2 = $subscriber->router->shamcash_qr ? url('storage/' . $subscriber->router->shamcash_qr) : '';
                            $brandNameM2 = $subscriber->router->brand_name ?? 'MegaWiFi';
                        @endphp
                        <button onclick="sendPaymentReminder({{ $subscriber->id }}, '{{ $subscriber->whatsapp_number ?? $subscriber->phone }}', '{{ addslashes($subscriber->full_name ?? $subscriber->username) }}', {{ $subscriber->remaining_amount ?? 0 }}, '{{ $subscriber->router->whatsapp_type ?? 'regular' }}', '{{ $usedGbPay }}', '{{ $expDatePay }}', {{ $subscriber->subscription_price ?? 0 }}, '{{ $shamcashUrl2 }}', '{{ $brandNameM2 }}')"
                                class="unpaid-badge px-2.5 py-1.5 bg-red-500 text-white rounded-full text-xs font-bold active:scale-95 transition"
                                title="إرسال تذكير دفع">
                            <i class="fas fa-exclamation-circle ml-1"></i>غير مدفوع
                        </button>
                        @endif
                    </div>
                    
                    {{-- Action Buttons --}}
                    <div class="flex items-center gap-2">
                        @if($subscriber->profile === 'STOP')
                        <button onclick="enableUser({{ $subscriber->id }}, '{{ $subscriber->mikrotik_id }}', {{ $subscriber->router_id }}, '{{ $subscriber->username }}')" 
                                class="flex items-center gap-1.5 px-3 py-1.5 bg-green-500 text-white rounded-lg text-xs font-bold" title="تمكين">
                            <i class="fas fa-check"></i>
                            <span>تمكين</span>
                        </button>
                        @endif
                        <button onclick="openEditModal({{ $subscriber->id }}, '{{ $subscriber->username }}', '{{ addslashes($subscriber->full_name ?? '') }}', '{{ $subscriber->phone ?? '' }}', '{{ $subscriber->national_id ?? '' }}', '{{ addslashes($subscriber->address ?? '') }}', '{{ $subscriber->whatsapp_number ?? '' }}')" 
                                class="flex items-center gap-1.5 px-3 py-1.5 bg-gray-500 text-white rounded-lg text-xs font-bold" title="تعديل">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="openTransferModal({{ $subscriber->id }}, '{{ $subscriber->username }}', '{{ addslashes($subscriber->full_name ?? $subscriber->username) }}', {{ $subscriber->router_id }}, '{{ $subscriber->router->name ?? '' }}', '{{ $subscriber->profile }}')" 
                                class="flex items-center gap-1.5 px-3 py-1.5 bg-amber-500 text-white rounded-lg text-xs font-bold" title="نقل لراوتر آخر">
                            <i class="fas fa-exchange-alt"></i>
                        </button>
                        <button onclick="openRenewModal({{ $subscriber->id }}, '{{ $subscriber->mikrotik_id }}', {{ $subscriber->router_id }}, '{{ $subscriber->profile }}', '{{ $subscriber->username }}', {{ $subscriber->subscription_price ?? 0 }}, {{ $subscriber->remaining_amount ?? 0 }}, '{{ $subscriber->whatsapp_number ?? '' }}', {{ $subscriber->is_paid ? 'true' : 'false' }})" 
                                class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-500 text-white rounded-lg text-xs font-bold" title="تجديد">
                            <i class="fas fa-sync-alt"></i>
                            <span>تجديد</span>
                        </button>
                        <button onclick="toggleIptvInline({{ $subscriber->id }}, this)" 
                                class="flex items-center gap-1.5 px-3 py-1.5 {{ $subscriber->iptv_enabled ? 'bg-purple-600' : 'bg-gray-400' }} text-white rounded-lg text-xs font-bold transition-colors duration-200" 
                                title="{{ $subscriber->iptv_enabled ? 'تعطيل IPTV' : 'تفعيل IPTV' }}"
                                data-enabled="{{ $subscriber->iptv_enabled ? '1' : '0' }}">
                            <i class="fas fa-tv"></i>
                            <span>IPTV</span>
                        </button>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        
        <!-- Desktop Table View -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-right text-sm font-bold text-gray-600">المستخدم</th>
                        <th class="px-4 py-3 text-right text-sm font-bold text-gray-600">الراوتر</th>
                        <th class="px-4 py-3 text-right text-sm font-bold text-gray-600">السرعة</th>
                        <th class="px-4 py-3 text-center text-sm font-bold text-gray-600">الاستهلاك</th>
                        <th class="px-4 py-3 text-center text-sm font-bold text-gray-600">تاريخ الانتهاء</th>
                        <th class="px-4 py-3 text-center text-sm font-bold text-gray-600">الحالة</th>
                        <th class="px-4 py-3 text-center text-sm font-bold text-gray-600">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($subscribers as $index => $subscriber)
                    <tr class="cursor-pointer {{ $index % 2 === 0 ? 'bg-white' : 'bg-slate-50' }} transition-all duration-300 ease-out hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-100 hover:shadow-[0_4px_20px_rgb(59,130,246,0.25)] hover:-translate-y-0.5 hover:scale-[1.01] relative hover:z-10" style="transform-style: preserve-3d;" data-subscriber-id="{{ $subscriber->id }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <button onclick="refreshUserUsage({{ $subscriber->id }}, '{{ $subscriber->mikrotik_id }}', {{ $subscriber->router_id }}, '{{ $subscriber->username }}')" 
                                        class="w-10 h-10 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-lg flex items-center justify-center shadow-sm hover:from-blue-500 hover:to-cyan-500 transition-all duration-300 hover:scale-110 hover:shadow-lg active:scale-95"
                                        id="refresh-btn-desktop-{{ $subscriber->id }}"
                                        title="اضغط لتحديث الاستهلاك">
                                    <i class="fas fa-user text-white text-sm" id="refresh-icon-desktop-{{ $subscriber->id }}"></i>
                                </button>
                                <div class="flex-1 min-w-0">
                                    @if($subscriber->full_name)
                                        <p class="font-medium text-gray-800">{{ $subscriber->full_name }}</p>
                                        <p class="text-xs text-gray-400">{{ $subscriber->username }}</p>
                                    @else
                                        <p class="font-medium text-gray-800">{{ $subscriber->username }}</p>
                                    @endif
                                    <div class="flex items-center gap-2 mt-1 text-sm {{ $subscriber->expiration_date && \Carbon\Carbon::parse($subscriber->expiration_date)->isPast() ? 'text-red-600' : 'text-gray-500' }}">
                                        <span class="{{ $subscriber->expiration_date && \Carbon\Carbon::parse($subscriber->expiration_date)->isPast() ? 'bg-red-100' : 'bg-gray-100' }} px-1.5 py-0.5 rounded">ينتهي:
                                            @if($subscriber->expiration_date)
                                                {{ \Carbon\Carbon::parse($subscriber->expiration_date)->format('Y-m-d') }}
                                            @else
                                                —
                                            @endif
                                        </span>
                                    </div>
                                </div>
                                @if($subscriber->phone)
                                    @php
                                        $phoneNumD = preg_replace('/[^0-9]/', '', $subscriber->phone);
                                        if(substr($phoneNumD, 0, 1) === '0') $phoneNumD = '963' . substr($phoneNumD, 1);
                                        elseif(substr($phoneNumD, 0, 3) !== '963') $phoneNumD = '963' . $phoneNumD;
                                        
                                        $usedGbDRaw = ($subscriber->total_bytes ?? 0) / 1073741824;
                                        $usedGbD = number_format(floatval($usedGbDRaw), 2);
                                        $dataLimitGbDRaw = $subscriber->data_limit ? $subscriber->data_limit / 1073741824 : 0;
                                        $dataLimitGbD = $dataLimitGbDRaw > 0 ? number_format(floatval($dataLimitGbDRaw), 0) : 0;
                                        $remainingGbD = $dataLimitGbDRaw > 0 ? number_format(max(0, $dataLimitGbDRaw - $usedGbDRaw), 2) : 0;
                                        $expDateD = $subscriber->expiration_date ? \Carbon\Carbon::parse($subscriber->expiration_date)->format('Y-m-d') : 'غير محدد';
                                        
                                        // رسالة استعلام فقط (بدون تذكير بالدفع)
                                        $waMsgD = "👋 مرحباً " . ($subscriber->full_name ?? $subscriber->username) . "\n\n";
                                        $waMsgD .= "📊 *استهلاكك الحالي:*\n";
                                        $waMsgD .= "• المستهلك: " . $usedGbD . " GB\n";
                                        if($dataLimitGbD > 0) {
                                            $waMsgD .= "• المتبقي: " . $remainingGbD . " GB من " . $dataLimitGbD . " GB\n";
                                        }
                                        $waMsgD .= "\n📅 تاريخ التجديد: " . $expDateD . "\n\n";
                                        $waMsgD .= "🔍 تفقد رصيدك من هنا:\nhttps://megawifi.site/check-balance\n\n";
                                        $waMsgD .= "✨ شكراً لكم - MegaWiFi 🌐";
                                        $waTypeD = $subscriber->router->whatsapp_type ?? 'regular';
                                    @endphp
                                    <a href="javascript:void(0)" onclick="openWhatsappDirect('{{ $phoneNumD }}', '{{ urlencode($waMsgD) }}', '{{ $waTypeD }}')" 
                                       class="w-8 h-8 bg-green-500 hover:bg-green-600 rounded-full flex items-center justify-center transition shadow-sm ml-2"
                                       title="إرسال تفاصيل الاستهلاك">
                                        <i class="fab fa-whatsapp text-white text-sm"></i>
                                    </a>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <span class="bg-gray-100 px-2 py-1 rounded-lg">{{ $subscriber->router->name ?? '-' }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @php
                                $umDataDesktop = is_string($subscriber->um_data) ? json_decode($subscriber->um_data, true) : ($subscriber->um_data ?? []);
                                $downloadSpeedDesktop = $umDataDesktop['download_speed'] ?? '';
                            @endphp
                            @if($downloadSpeedDesktop)
                            <span class="bg-green-50 text-green-600 px-2 py-1 rounded-lg">
                                <i class="fas fa-tachometer-alt ml-1"></i>{{ $downloadSpeedDesktop }}
                            </span>
                            @else
                            <span class="bg-purple-50 text-purple-600 px-2 py-1 rounded-lg">{{ $subscriber->profile ?? 'default' }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $umData = is_string($subscriber->um_data) ? json_decode($subscriber->um_data, true) : ($subscriber->um_data ?? []);
                                // bytes_out = data going TO subscriber = Download
                                // bytes_in = data coming FROM subscriber = Upload
                                $downloadUsed = ($umData['download_used'] ?? 0) > 0 
                                    ? $umData['download_used'] / 1073741824 
                                    : ($subscriber->bytes_out ?? 0) / 1073741824;
                                $uploadUsed = ($umData['upload_used'] ?? 0) > 0 
                                    ? $umData['upload_used'] / 1073741824 
                                    : ($subscriber->bytes_in ?? 0) / 1073741824;
                                $totalUsed = $downloadUsed + $uploadUsed;
                                
                                // Check data limit
                                $dataLimitBytes = floatval($subscriber->data_limit ?? ($subscriber->data_limit_gb ? $subscriber->data_limit_gb * 1073741824 : 0));
                                $dataLimitGb = $dataLimitBytes > 0 ? $dataLimitBytes / 1073741824 : 0;
                                $remainingGb = $dataLimitGb > 0 ? max(0, $dataLimitGb - $totalUsed) : 0;
                                $usagePercent = $dataLimitGb > 0 ? min(100, ($totalUsed / $dataLimitGb) * 100) : 0;
                            @endphp
                            
                            @if($dataLimitGb > 0)
                                {{-- Has data limit - show usage with remaining --}}
                                <div class="flex flex-col items-center gap-1" id="usage-box-desktop-{{ $subscriber->id }}">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-bold text-gray-700" id="usage-text-desktop-{{ $subscriber->id }}">{{ number_format(floatval($totalUsed), 2) }} / {{ number_format(floatval($dataLimitGb), 0) }} GB</span>
                                    </div>
                                    <div class="w-24 h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all {{ $usagePercent > 90 ? 'bg-red-500' : ($usagePercent > 70 ? 'bg-orange-500' : 'bg-blue-500') }}" 
                                             style="width: {{ $usagePercent }}%"
                                             id="usage-bar-desktop-{{ $subscriber->id }}"
                                             data-limit="{{ $dataLimitGb }}"></div>
                                    </div>
                                    <span class="text-xs font-medium {{ $remainingGb < 1 ? 'text-red-600' : ($remainingGb < 3 ? 'text-orange-600' : 'text-green-600') }}" id="usage-remaining-desktop-{{ $subscriber->id }}">
                                        <i class="fas fa-database ml-1"></i>
                                        متبقي {{ number_format(floatval($remainingGb), 1) }} GB
                                    </span>
                                </div>
                            @elseif($totalUsed > 0.01)
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r from-blue-50 to-indigo-50 text-blue-700 rounded-lg text-sm font-bold" id="usage-value-desktop-{{ $subscriber->id }}">
                                    <i class="fas fa-database"></i>
                                    {{ number_format(floatval($totalUsed), 2) }} GB
                                </span>
                            @else
                                <span class="text-gray-400" id="usage-value-desktop-{{ $subscriber->id }}">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center text-xs text-gray-600">
                            @if($subscriber->expiration_date)
                                @php
                                    $expDateObj = \Carbon\Carbon::parse($subscriber->expiration_date);
                                    $isPastExp = $expDateObj->isPast();
                                @endphp
                                <span class="{{ $isPastExp ? 'text-red-600 bg-red-50' : 'text-green-600 bg-green-50' }} px-2 py-1 rounded-lg">
                                    {{ $expDateObj->format('Y-m-d') }}
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                {{-- Online/Offline indicator --}}
                                @if($subscriber->is_online)
                                    <span class="relative flex h-3 w-3" id="online-badge-desktop-{{ $subscriber->id }}" title="متصل الآن">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                                    </span>
                                @else
                                    <span class="relative flex h-3 w-3" id="online-badge-desktop-{{ $subscriber->id }}" title="غير متصل">
                                        <span class="relative inline-flex rounded-full h-3 w-3 bg-gray-300"></span>
                                    </span>
                                @endif
                                @if($subscriber->status === 'active')
                                    <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium">نشط</span>
                                @elseif($subscriber->status === 'expired')
                                    <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-medium">منتهي</span>
                                @else
                                    <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-medium">معطل</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                {{-- Payment Status Badge --}}
                                <span id="paid-badge-desktop-{{ $subscriber->id }}">
                                @if($subscriber->is_paid)
                                @php
                                    $expDatePaidD = $subscriber->expiration_date ? \Carbon\Carbon::parse($subscriber->expiration_date)->format('Y-m-d') : 'غير محدد';
                                    $brandNameD = $subscriber->router->brand_name ?? 'MegaWiFi';
                                @endphp
                                <button onclick="sendPaidConfirmation({{ $subscriber->id }}, '{{ $subscriber->whatsapp_number ?? $subscriber->phone }}', '{{ addslashes($subscriber->full_name ?? $subscriber->username) }}', {{ $subscriber->subscription_price ?? 0 }}, '{{ $expDatePaidD }}', '{{ $subscriber->router->whatsapp_type ?? 'regular' }}', '{{ $brandNameD }}')"
                                        class="paid-badge group flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r from-green-400 to-emerald-500 text-white rounded-lg text-xs font-bold shadow-md shadow-green-500/30 hover:shadow-lg hover:scale-105 hover:-translate-y-0.5 transition-all duration-300"
                                        title="إرسال تأكيد دفع">
                                    <i class="fas fa-check-circle"></i>
                                    <span>مدفوع</span>
                                </button>
                                @elseif($subscriber->remaining_amount > 0)
                                @php
                                    $usedGbDebtD = number_format(($subscriber->total_bytes ?? 0) / 1073741824, 2);
                                    $expDateDebtD = $subscriber->expiration_date ? \Carbon\Carbon::parse($subscriber->expiration_date)->format('Y-m-d') : 'غير محدد';
                                    $shamcashUrlD1 = $subscriber->router->shamcash_qr ? url('storage/' . $subscriber->router->shamcash_qr) : '';
                                    $brandNameD1 = $subscriber->router->brand_name ?? 'MegaWiFi';
                                @endphp
                                <button onclick="sendDebtReminder({{ $subscriber->id }}, '{{ $subscriber->whatsapp_number ?? $subscriber->phone }}', '{{ addslashes($subscriber->full_name ?? $subscriber->username) }}', {{ $subscriber->remaining_amount ?? 0 }}, '{{ $subscriber->router->whatsapp_type ?? 'regular' }}', '{{ $shamcashUrlD1 }}', '{{ $brandNameD1 }}')"
                                        class="debt-badge group flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r from-orange-500 to-amber-500 text-white rounded-lg text-xs font-bold shadow-md shadow-orange-500/30 hover:shadow-lg hover:scale-105 hover:-translate-y-0.5 transition-all duration-300"
                                        title="إرسال تذكير دين">
                                    <i class="fas fa-hand-holding-usd"></i>
                                    <span>مدان {{ number_format(floatval($subscriber->remaining_amount), 0) }}</span>
                                </button>
                                @else
                                @php
                                    $usedGbPayD = number_format(($subscriber->total_bytes ?? 0) / 1073741824, 2);
                                    $expDatePayD = $subscriber->expiration_date ? \Carbon\Carbon::parse($subscriber->expiration_date)->format('Y-m-d') : 'غير محدد';
                                    $shamcashUrlD2 = $subscriber->router->shamcash_qr ? url('storage/' . $subscriber->router->shamcash_qr) : '';
                                    $brandNameD2 = $subscriber->router->brand_name ?? 'MegaWiFi';
                                @endphp
                                <button onclick="sendPaymentReminder({{ $subscriber->id }}, '{{ $subscriber->whatsapp_number ?? $subscriber->phone }}', '{{ addslashes($subscriber->full_name ?? $subscriber->username) }}', {{ $subscriber->remaining_amount ?? 0 }}, '{{ $subscriber->router->whatsapp_type ?? 'regular' }}', '{{ $usedGbPayD }}', '{{ $expDatePayD }}', {{ $subscriber->subscription_price ?? 0 }}, '{{ $shamcashUrlD2 }}', '{{ $brandNameD2 }}')"
                                        class="unpaid-badge group flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r from-red-500 to-rose-500 text-white rounded-lg text-xs font-bold shadow-md shadow-red-500/30 hover:shadow-lg hover:scale-105 hover:-translate-y-0.5 transition-all duration-300"
                                        title="إرسال تذكير دفع">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <span>غير مدفوع</span>
                                </button>
                                @endif
                                </span>
                                <button onclick="openEditModal({{ $subscriber->id }}, '{{ $subscriber->username }}', '{{ addslashes($subscriber->full_name ?? '') }}', '{{ $subscriber->phone ?? '' }}', '{{ $subscriber->national_id ?? '' }}', '{{ addslashes($subscriber->address ?? '') }}', '{{ $subscriber->whatsapp_number ?? '' }}')" 
                                        class="group flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r from-gray-500 to-gray-600 text-white rounded-lg text-xs font-bold shadow-md shadow-gray-500/30 hover:shadow-lg hover:scale-105 hover:-translate-y-0.5 transition-all duration-300" title="تعديل">
                                    <i class="fas fa-edit"></i>
                                    <span>تعديل</span>
                                </button>
                                <button onclick="openTransferModal({{ $subscriber->id }}, '{{ $subscriber->username }}', '{{ addslashes($subscriber->full_name ?? $subscriber->username) }}', {{ $subscriber->router_id }}, '{{ $subscriber->router->name ?? '' }}', '{{ $subscriber->profile }}')" 
                                        class="group flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r from-amber-500 to-orange-500 text-white rounded-lg text-xs font-bold shadow-md shadow-amber-500/30 hover:shadow-lg hover:scale-105 hover:-translate-y-0.5 transition-all duration-300" title="نقل لراوتر آخر">
                                    <i class="fas fa-exchange-alt"></i>
                                    <span>نقل</span>
                                </button>
                                <button onclick="openContractModal({{ $subscriber->id }}, '{{ $subscriber->username }}', '{{ addslashes($subscriber->full_name ?? '') }}', '{{ $subscriber->phone ?? '' }}', '{{ $subscriber->national_id ?? '' }}', '{{ addslashes($subscriber->address ?? '') }}', '{{ $subscriber->profile }}', '{{ $subscriber->router->name ?? '' }}')" 
                                        class="group flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r from-purple-500 to-violet-500 text-white rounded-lg text-xs font-bold shadow-md shadow-purple-500/30 hover:shadow-lg hover:scale-105 hover:-translate-y-0.5 transition-all duration-300" title="عقد">
                                    <i class="fas fa-file-contract"></i>
                                    <span>عقد</span>
                                </button>
                                <button onclick="openRenewModal({{ $subscriber->id }}, '{{ $subscriber->mikrotik_id }}', {{ $subscriber->router_id }}, '{{ $subscriber->profile }}', '{{ $subscriber->username }}', {{ $subscriber->subscription_price ?? 0 }}, {{ $subscriber->remaining_amount ?? 0 }}, '{{ $subscriber->whatsapp_number ?? '' }}', {{ $subscriber->is_paid ? 'true' : 'false' }})" 
                                        class="group flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r from-blue-500 to-indigo-500 text-white rounded-lg text-xs font-bold shadow-md shadow-blue-500/30 hover:shadow-lg hover:shadow-blue-500/40 hover:scale-105 hover:-translate-y-0.5 transition-all duration-300">
                                    <i class="fas fa-sync-alt group-hover:animate-spin"></i>
                                    <span>تجديد</span>
                                </button>
                                <button onclick="toggleIptvInline({{ $subscriber->id }}, this)" 
                                        class="group flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r {{ $subscriber->iptv_enabled ? 'from-purple-600 to-violet-600 shadow-purple-500/30 hover:shadow-purple-500/40' : 'from-gray-400 to-gray-500 shadow-gray-400/30 hover:shadow-gray-400/40' }} text-white rounded-lg text-xs font-bold shadow-md hover:shadow-lg hover:scale-105 hover:-translate-y-0.5 transition-all duration-300"
                                        title="{{ $subscriber->iptv_enabled ? 'تعطيل IPTV' : 'تفعيل IPTV' }}"
                                        data-enabled="{{ $subscriber->iptv_enabled ? '1' : '0' }}">
                                    <i class="fas fa-tv"></i>
                                    <span>IPTV</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <!-- Total Count -->
        <div class="px-4 py-3 border-t bg-gray-50 text-center">
            <span class="text-sm text-gray-600">
                <i class="fas fa-users ml-1"></i>
                إجمالي المشتركين: <span class="font-bold text-gray-800">{{ $subscribers->count() }}</span>
            </span>
        </div>
    </div>
@endif

<!-- Renew Subscription Modal -->
<div id="renewModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-start sm:items-center justify-center z-50 p-2 pt-4 sm:p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mb-24 sm:mb-0">
        <div class="p-3 sm:p-4 border-b bg-gradient-to-l from-green-500 to-emerald-600">
            <div class="flex justify-between items-center">
                <h3 class="text-base font-bold text-white">
                    <i class="fas fa-sync-alt ml-2"></i>
                    تجديد الاشتراك
                </h3>
                <button onclick="closeRenewModal()" class="w-8 h-8 flex items-center justify-center text-white/80 hover:text-white hover:bg-white/20 rounded-full transition">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
        </div>
        <div class="p-3 sm:p-4 max-h-[70vh] sm:max-h-[75vh] overflow-y-auto">
            <input type="hidden" id="renewUserId">
            <input type="hidden" id="renewMikrotikId">
            <input type="hidden" id="renewRouterId">
            
            <!-- Username Display -->
            <div class="mb-2 p-2 bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg border border-gray-200">
                <p class="text-xs text-gray-500">المشترك</p>
                <p class="font-bold text-gray-800 text-base" id="renewUsername">-</p>
            </div>
            
            <!-- Profile Selection -->
            <div class="mb-2">
                <label class="block text-xs font-semibold text-gray-700 mb-1">
                    <i class="fas fa-box ml-1 text-purple-500"></i>
                    اختيار الباقة
                </label>
                <select id="renewProfileSelect" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition text-sm">
                    <option value="">جاري التحميل...</option>
                </select>
            </div>
            
            <!-- Data Limit & Expiry in one row -->
            <div class="grid grid-cols-2 gap-2 mb-2">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        <i class="fas fa-database ml-1 text-blue-500"></i>
                        حد البيانات
                    </label>
                    <div class="flex gap-1 items-center">
                        <input type="number" id="renewDataLimit" placeholder="—" min="0" step="1"
                            class="flex-1 px-2 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm">
                        <span class="text-xs text-gray-500">GB</span>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        <i class="fas fa-calendar-plus ml-1 text-orange-500"></i>
                        إضافة أيام
                    </label>
                    <div class="flex gap-1 items-center">
                        <input type="number" id="renewExpiryDays" placeholder="—" min="1" step="1" value="30"
                            class="flex-1 px-2 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition text-sm">
                        <span class="text-xs text-gray-500">يوم</span>
                    </div>
                </div>
            </div>
            
            <!-- Reset Usage Checkbox -->
            <div class="mb-2">
                <label class="flex items-center gap-2 cursor-pointer p-2 bg-yellow-50 rounded-lg border border-yellow-200 hover:bg-yellow-100 transition">
                    <input type="checkbox" id="renewResetUsage" class="w-4 h-4 rounded text-green-500 focus:ring-green-500">
                    <div>
                        <p class="font-medium text-gray-800 text-sm">تصفير الاستهلاك</p>
                        <p class="text-xs text-gray-500">إعادة عداد التحميل والرفع إلى صفر</p>
                    </div>
                </label>
            </div>
            
            <!-- Payment Section -->
            <div class="p-2 bg-amber-50 rounded-xl border-2 border-amber-200 mb-2">
                <h4 class="font-bold text-amber-800 mb-2 flex items-center gap-2 text-xs">
                    <i class="fas fa-money-bill-wave"></i>
                    معلومات الدفع
                </h4>
                
                <div class="mb-2">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">سعر الاشتراك</label>
                    <input type="number" id="renewSubscriptionPrice" placeholder="0" min="0" step="1000"
                           class="w-full px-2 py-1.5 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition text-sm" dir="ltr">
                </div>
                
                <!-- Payment Status Options -->
                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-gray-700">حالة الدفع</label>
                    
                    <!-- مدفوع بالكامل -->
                    <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg hover:bg-green-100 transition">
                        <input type="radio" name="paymentStatus" id="renewIsPaid" value="paid" class="w-4 h-4 text-green-600 border-gray-300 focus:ring-green-500">
                        <span class="font-semibold text-gray-700 text-sm">
                            <i class="fas fa-check-circle text-green-500 ml-1"></i>
                            مدفوع بالكامل
                        </span>
                    </label>
                    
                    <!-- مدان -->
                    <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg hover:bg-orange-100 transition">
                        <input type="radio" name="paymentStatus" id="renewIsDebt" value="debt" class="w-4 h-4 text-orange-600 border-gray-300 focus:ring-orange-500" onchange="toggleDebtAmount()">
                        <span class="font-semibold text-gray-700 text-sm">
                            <i class="fas fa-hand-holding-usd text-orange-500 ml-1"></i>
                            مدان
                        </span>
                    </label>
                    
                    <!-- مربع قيمة الدين (يظهر عند اختيار مدان) -->
                    <div id="debtAmountContainer" class="hidden mr-6 mt-1">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">قيمة الدين</label>
                        <input type="number" id="renewDebtAmount" placeholder="0" min="0" step="1000"
                               class="w-full px-2 py-1.5 border-2 border-orange-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition text-sm bg-orange-50" dir="ltr">
                    </div>
                    
                    <!-- غير مدفوع -->
                    <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg hover:bg-red-100 transition">
                        <input type="radio" name="paymentStatus" id="renewNotPaid" value="unpaid" class="w-4 h-4 text-red-600 border-gray-300 focus:ring-red-500" checked onchange="toggleDebtAmount()">
                        <span class="font-semibold text-gray-700 text-sm">
                            <i class="fas fa-times-circle text-red-500 ml-1"></i>
                            غير مدفوع
                        </span>
                    </label>
                </div>
                <input type="hidden" id="renewRemainingAmount" value="0">
            </div>

            <!-- Info Note -->
            <div class="p-2 bg-blue-50 rounded-lg text-xs text-blue-700 border border-blue-200">
                <i class="fas fa-info-circle ml-1"></i>
                الحقول الفارغة ستستخدم قيم الباقة الافتراضية
            </div>
        </div>
        
        <!-- Footer Buttons -->
        <div class="p-3 border-t bg-gray-50 flex gap-2">
            <button onclick="closeRenewModal()" class="flex-1 px-4 py-2.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium text-sm">
                إلغاء
            </button>
            <button onclick="submitRenewal()" id="renewBtn" class="flex-[2] px-4 py-2.5 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-lg hover:from-green-600 hover:to-emerald-700 transition font-medium shadow-lg text-sm">
                <i class="fas fa-check ml-1"></i>
                تجديد
            </button>
        </div>
    </div>
</div>

<!-- Edit Subscriber Modal -->
<div id="editModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[85vh] overflow-hidden">
        <div class="p-3 border-b bg-gradient-to-l from-gray-600 to-gray-700">
            <div class="flex justify-between items-center">
                <h3 class="text-base font-bold text-white">
                    <i class="fas fa-edit ml-2"></i>
                    تعديل بيانات المشترك
                </h3>
                <button onclick="closeEditModal()" class="w-8 h-8 flex items-center justify-center text-white/80 hover:text-white hover:bg-white/20 rounded-full transition">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
        </div>
        <div class="p-3 overflow-y-auto max-h-[calc(85vh-120px)]">
            <input type="hidden" id="editSubscriberId">
            
            <!-- Username (readonly) -->
            <div class="mb-2 p-2 bg-gray-50 rounded-lg border">
                <p class="text-xs text-gray-500">اسم المستخدم</p>
                <p class="font-bold text-gray-800 text-base" id="editUsername">-</p>
            </div>
            
            <!-- Full Name -->
            <div class="mb-2">
                <label class="block text-xs font-semibold text-gray-700 mb-1">
                    <i class="fas fa-user ml-1 text-blue-500"></i>
                    الاسم الكامل
                </label>
                <input type="text" id="editFullName" placeholder="اسم المشترك"
                       class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm">
            </div>
            
            <!-- Phone & WhatsApp in one row -->
            <div class="grid grid-cols-2 gap-2 mb-2">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        <i class="fas fa-phone ml-1 text-green-500"></i>
                        الهاتف
                    </label>
                    <input type="tel" id="editPhone" placeholder="رقم الهاتف"
                           class="w-full px-2 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm" dir="ltr">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        <i class="fab fa-whatsapp ml-1 text-green-600"></i>
                        الواتساب
                    </label>
                    <input type="tel" id="editWhatsapp" placeholder="رقم الواتساب"
                           class="w-full px-2 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition text-sm" dir="ltr">
                </div>
            </div>
            
            <!-- National ID -->
            <div class="mb-2">
                <label class="block text-xs font-semibold text-gray-700 mb-1">
                    <i class="fas fa-id-card ml-1 text-purple-500"></i>
                    الرقم الوطني
                </label>
                <input type="text" id="editNationalId" placeholder="الرقم الوطني"
                       class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm" dir="ltr">
            </div>
            
            <!-- Address -->
            <div class="mb-2">
                <label class="block text-xs font-semibold text-gray-700 mb-1">
                    <i class="fas fa-map-marker-alt ml-1 text-red-500"></i>
                    العنوان
                </label>
                <textarea id="editAddress" placeholder="عنوان المشترك" rows="2"
                          class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm resize-none"></textarea>
            </div>
        </div>
        <div class="p-3 border-t bg-gray-50 flex gap-2">
            <button onclick="closeEditModal()" class="flex-1 px-4 py-2.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium text-sm">
                إلغاء
            </button>
            <button onclick="submitEdit()" id="editBtn" class="flex-[2] px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium text-sm">
                <i class="fas fa-save ml-1"></i>
                حفظ
            </button>
        </div>
    </div>
</div>

<!-- Transfer Modal -->
<div id="transferModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="p-3 border-b bg-gradient-to-l from-amber-500 to-orange-600">
            <div class="flex justify-between items-center">
                <h3 class="text-base font-bold text-white">
                    <i class="fas fa-exchange-alt ml-2"></i>
                    نقل المشترك لراوتر آخر
                </h3>
                <button onclick="closeTransferModal()" class="w-8 h-8 flex items-center justify-center text-white/80 hover:text-white hover:bg-white/20 rounded-full transition">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
        </div>
        <div class="p-4">
            <input type="hidden" id="transferSubscriberId">
            <input type="hidden" id="transferSourceRouterId">
            
            <!-- Subscriber Info -->
            <div class="mb-3 p-3 bg-gray-50 rounded-xl border">
                <div class="flex items-center gap-2 mb-1">
                    <i class="fas fa-user text-blue-500"></i>
                    <span class="font-bold text-gray-800" id="transferUsername">-</span>
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <i class="fas fa-id-badge text-gray-400"></i>
                    <span id="transferFullName">-</span>
                </div>
            </div>

            <!-- Current Router -->
            <div class="mb-3 p-3 bg-red-50 rounded-xl border border-red-200">
                <p class="text-xs text-red-500 font-semibold mb-1">
                    <i class="fas fa-sign-out-alt ml-1"></i>الراوتر الحالي (المصدر)
                </p>
                <p class="font-bold text-red-700" id="transferSourceRouter">-</p>
                <p class="text-xs text-red-400 mt-1">
                    <i class="fas fa-tag ml-1"></i>الباقة: <span id="transferProfile" class="font-bold">-</span>
                </p>
            </div>

            <!-- Target Router -->
            <div class="mb-3">
                <label class="block text-xs font-semibold text-gray-700 mb-1">
                    <i class="fas fa-sign-in-alt ml-1 text-green-500"></i>
                    الراوتر الهدف (النقل إليه)
                </label>
                <select id="transferTargetRouter" class="w-full px-3 py-2.5 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition text-sm font-medium">
                    <option value="">-- اختر الراوتر --</option>
                    @foreach($routers as $router)
                    <option value="{{ $router->id }}">{{ $router->name }} ({{ $router->ip_address }})</option>
                    @endforeach
                </select>
            </div>

            <!-- Warning -->
            <div class="mb-3 p-2.5 bg-yellow-50 border border-yellow-200 rounded-lg">
                <p class="text-xs text-yellow-700">
                    <i class="fas fa-exclamation-triangle ml-1 text-yellow-500"></i>
                    <strong>تنبيه:</strong> سيتم حذف المشترك من الراوتر المصدر وإنشاؤه على الراوتر الهدف. يجب أن تكون نفس الباقة موجودة على الراوتر الهدف. سيتم تصفير الاستهلاك.
                </p>
            </div>
        </div>
        <div class="p-3 border-t bg-gray-50 flex gap-2">
            <button onclick="closeTransferModal()" class="flex-1 px-4 py-2.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium text-sm">
                إلغاء
            </button>
            <button onclick="submitTransfer()" id="transferBtn" class="flex-[2] px-4 py-2.5 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition font-medium text-sm">
                <i class="fas fa-exchange-alt ml-1"></i>
                نقل المشترك
            </button>
        </div>
    </div>
</div>

<!-- Contract Modal -->
<div id="contractModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-2 sm:p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[95vh] sm:max-h-[90vh] overflow-hidden">
        <div class="p-4 sm:p-5 border-b bg-gradient-to-l from-purple-600 to-violet-700">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-white">
                    <i class="fas fa-file-contract ml-2"></i>
                    عقد اشتراك خدمة الإنترنت
                </h3>
                <button onclick="closeContractModal()" class="w-10 h-10 flex items-center justify-center text-white/80 hover:text-white hover:bg-white/20 rounded-full transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        <div class="p-6 overflow-y-auto max-h-[calc(95vh-180px)]" id="contractContent">
            <!-- Contract Content -->
            <div class="text-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-2">عقد اشتراك خدمة الإنترنت</h2>
                <p class="text-gray-500">التاريخ: <span id="contractDate"></span></p>
            </div>
            
            <div class="border-2 border-gray-200 rounded-lg p-5 mb-2">
                <h4 class="font-bold text-gray-700 mb-3 border-b pb-2">
                    <i class="fas fa-user ml-2 text-blue-500"></i>
                    بيانات المشترك (الطرف الأول)
                </h4>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <span class="text-gray-500">اسم المستخدم:</span>
                        <span class="font-bold text-gray-800 mr-1" id="contractUsername"></span>
                    </div>
                    <div>
                        <span class="text-gray-500">الاسم الكامل:</span>
                        <span class="font-bold text-gray-800 mr-1" id="contractFullName"></span>
                    </div>
                    <div>
                        <span class="text-gray-500">رقم الهاتف:</span>
                        <span class="font-bold text-gray-800 mr-1" id="contractPhone"></span>
                    </div>
                    <div>
                        <span class="text-gray-500">الرقم الوطني:</span>
                        <span class="font-bold text-gray-800 mr-1" id="contractNationalId"></span>
                    </div>
                    <div class="col-span-2">
                        <span class="text-gray-500">العنوان:</span>
                        <span class="font-bold text-gray-800 mr-1" id="contractAddress"></span>
                    </div>
                </div>
            </div>
            
            <div class="border-2 border-gray-200 rounded-lg p-5 mb-2">
                <h4 class="font-bold text-gray-700 mb-3 border-b pb-2">
                    <i class="fas fa-wifi ml-2 text-green-500"></i>
                    تفاصيل الاشتراك
                </h4>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <span class="text-gray-500">الباقة:</span>
                        <span class="font-bold text-gray-800 mr-1" id="contractProfile"></span>
                    </div>
                    <div>
                        <span class="text-gray-500">الراوتر:</span>
                        <span class="font-bold text-gray-800 mr-1" id="contractRouter"></span>
                    </div>
                </div>
            </div>
            
            <div class="border-2 border-gray-200 rounded-lg p-5 mb-2">
                <h4 class="font-bold text-gray-700 mb-3 border-b pb-2">
                    <i class="fas fa-building ml-2 text-purple-500"></i>
                    بيانات مزود الخدمة (الطرف الثاني)
                </h4>
                <div class="text-sm space-y-2">
                    <p><span class="text-gray-500">اسم الشركة:</span> <span class="font-bold">MegaWiFi للإنترنت</span></p>
                    <p><span class="text-gray-500">العنوان:</span> <span class="font-bold">سوريا</span></p>
                </div>
            </div>
            
            <div class="border-2 border-gray-200 rounded-lg p-5 mb-2">
                <h4 class="font-bold text-gray-700 mb-3 border-b pb-2">
                    <i class="fas fa-gavel ml-2 text-red-500"></i>
                    شروط وأحكام العقد
                </h4>
                <ol class="text-sm text-gray-600 space-y-2 list-decimal list-inside">
                    <li>يلتزم المشترك بدفع قيمة الاشتراك الشهري في موعده.</li>
                    <li>يحق لمزود الخدمة إيقاف الخدمة في حال التأخر عن السداد.</li>
                    <li>يلتزم المشترك باستخدام الخدمة بشكل قانوني.</li>
                    <li>يحق للمشترك إلغاء الاشتراك بإشعار مسبق.</li>
                    <li>مزود الخدمة غير مسؤول عن انقطاع الخدمة بسبب ظروف قاهرة.</li>
                </ol>
            </div>
            
            <div class="grid grid-cols-2 gap-8 mt-8 pt-4 border-t">
                <div class="text-center">
                    <p class="text-gray-500 mb-8">توقيع المشترك</p>
                    <div class="border-t-2 border-gray-400 pt-2">
                        <p class="text-sm text-gray-600" id="contractSignName"></p>
                    </div>
                </div>
                <div class="text-center">
                    <p class="text-gray-500 mb-8">توقيع مزود الخدمة</p>
                    <div class="border-t-2 border-gray-400 pt-2">
                        <p class="text-sm text-gray-600">MegaWiFi</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-4 sm:p-5 border-t bg-gray-50 flex flex-col sm:flex-row gap-2 sm:gap-2 sm:justify-end">
            <button onclick="closeContractModal()" class="w-full sm:w-auto px-5 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium">
                إغلاق
            </button>
            <button onclick="printContract()" class="w-full sm:w-auto px-5 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-medium">
                <i class="fas fa-print ml-2"></i>
                طباعة العقد
            </button>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-2 sm:p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[95vh] sm:max-h-[90vh] overflow-hidden">
        <!-- Header -->
        <div class="p-4 sm:p-5 border-b bg-gradient-to-l from-purple-500 to-indigo-600">
            <div class="flex justify-between items-center">
                <h3 class="text-lg sm:text-xl font-bold text-white">
                    <i class="fas fa-user-plus ml-2"></i>
                    إضافة مشترك جديد
                </h3>
                <button onclick="closeAddUserModal()" class="w-10 h-10 flex items-center justify-center text-white/80 hover:text-white hover:bg-white/20 rounded-full transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Form with scroll -->
        <form id="addUserForm" class="p-4 sm:p-6 space-y-2 sm:space-y-5 overflow-y-auto max-h-[calc(95vh-180px)] sm:max-h-[calc(90vh-180px)]">
            <input type="hidden" id="addUserRouterId" value="{{ request('router_id') ?? ($routers->first()->id ?? '') }}">
            
            <!-- اختيار الراوتر -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-server ml-1 text-indigo-500"></i> الراوتر
                </label>
                <select id="addUserRouterSelect" onchange="loadAddUserProfiles()" 
                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition text-base">
                    @foreach($routers as $router)
                        <option value="{{ $router->id }}" {{ (request('router_id') ?? ($routers->first()->id ?? '')) == $router->id ? 'selected' : '' }}>
                            {{ $router->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            <!-- اسم المستخدم -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-user ml-1 text-purple-500"></i> اسم المستخدم
                </label>
                <div class="flex gap-2">
                    <input type="text" id="addUserUsername" required placeholder="أدخل اسم المستخدم"
                        class="flex-1 min-w-0 px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition text-base">
                    <button type="button" onclick="generateRandomUsername()" 
                        class="flex-shrink-0 w-12 h-12 sm:px-4 sm:py-3 sm:w-auto sm:h-auto bg-gray-100 hover:bg-gray-200 rounded-lg transition flex items-center justify-center" title="توليد عشوائي">
                        <i class="fas fa-random text-gray-600"></i>
                    </button>
                </div>
            </div>
            
            <!-- كلمة المرور -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-lock ml-1 text-green-500"></i> كلمة المرور
                </label>
                <div class="flex gap-2">
                    <input type="text" id="addUserPassword" required placeholder="أدخل كلمة المرور"
                        class="flex-1 min-w-0 px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition text-base">
                    <button type="button" onclick="generateRandomPassword()" 
                        class="flex-shrink-0 w-12 h-12 sm:px-4 sm:py-3 sm:w-auto sm:h-auto bg-gray-100 hover:bg-gray-200 rounded-lg transition flex items-center justify-center" title="توليد عشوائي">
                        <i class="fas fa-random text-gray-600"></i>
                    </button>
                </div>
            </div>
            
            <!-- اختيار الباقة -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-box ml-1 text-orange-500"></i> الباقة (Profile)
                </label>
                <select id="addUserProfile" required
                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition text-base">
                    <option value="">-- جاري تحميل الباقات --</option>
                </select>
            </div>
            
            <!-- حد الجيجات (اختياري) -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-database ml-1 text-blue-500"></i> حد البيانات (اختياري)
                </label>
                <div class="flex gap-2 items-center">
                    <input type="number" id="addUserDataLimit" placeholder="بدون حد" min="0" step="1"
                        class="flex-1 min-w-0 px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-base">
                    <span class="text-gray-500 font-medium flex-shrink-0">GB</span>
                </div>
                <p class="text-xs text-gray-400 mt-1">اتركه فارغاً للاشتراك بدون حد</p>
            </div>
            
            <!-- ملاحظة -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-comment ml-1 text-gray-500"></i> ملاحظة (اختياري)
                </label>
                <input type="text" id="addUserComment" placeholder="ملاحظة أو اسم العميل"
                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-gray-400 focus:border-gray-400 transition text-base">
            </div>
        </form>
        
        <!-- Footer Buttons -->
        <div class="p-4 sm:p-5 border-t bg-gray-50 flex flex-col-reverse sm:flex-row gap-2 sm:gap-0 sm:justify-between">
            <button onclick="closeAddUserModal()" class="w-full sm:w-auto px-5 py-3 sm:py-2.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium text-base">
                إلغاء
            </button>
            <button onclick="submitAddUser()" id="addUserBtn" class="w-full sm:w-auto px-6 py-3 sm:py-2.5 bg-gradient-to-r from-purple-500 to-indigo-600 text-white rounded-lg hover:from-purple-600 hover:to-indigo-700 transition font-medium shadow-lg text-base">
                <i class="fas fa-plus ml-2"></i>
                إضافة المشترك
            </button>
        </div>
    </div>
</div>

<!-- Assign Profile Modal -->
<div id="assignProfileModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-2 sm:p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[95vh] sm:max-h-[90vh] overflow-hidden">
        <div class="p-4 sm:p-6 border-b bg-gradient-to-l from-indigo-500 to-purple-600">
            <div class="flex justify-between items-center">
                <h3 class="text-lg sm:text-xl font-bold text-white">
                    <i class="fas fa-link ml-2"></i>
                    <span id="assignModalTitle">ربط المستخدمين بـ Profile</span>
                </h3>
                <button onclick="closeAssignProfileModal()" class="w-10 h-10 flex items-center justify-center text-white/80 hover:text-white hover:bg-white/20 rounded-full transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        <div class="p-4 sm:p-6 overflow-y-auto max-h-[calc(95vh-180px)] sm:max-h-[60vh]">
            <!-- Router Selection -->
            <div class="mb-2 mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-2">اختر الراوتر</label>
                <select id="profileRouterId" class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 transition text-base" onchange="loadProfileCards()">
                    <option value="">-- اختر الراوتر --</option>
                    @foreach($routers as $router)
                        <option value="{{ $router->id }}">{{ $router->name }}</option>
                    @endforeach
                </select>
            </div>
            
            <!-- Hidden input for single user mode -->
            <input type="hidden" id="singleUserId" value="">
            <input type="hidden" id="singleUserMikrotikId" value="">
            
            <!-- Packages Cards Container -->
            <div id="packagesContainer" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-3">
                    <i class="fas fa-box-open ml-1 text-indigo-600"></i>
                    اختر الباقة
                </label>
                
                <!-- Search & Filter -->
                <div class="flex flex-col sm:flex-row gap-2 sm:gap-2 mb-2">
                    <div class="relative flex-1">
                        <input type="text" id="packageSearchModal" placeholder="بحث في الباقات..." 
                               class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-base">
                        <i class="fas fa-search absolute left-3 top-2 text-gray-400"></i>
                    </div>
                    <select id="packageSortModal" class="px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-base">
                        <option value="speed">ترتيب: السرعة</option>
                        <option value="price">ترتيب: السعر</option>
                        <option value="name">ترتيب: الاسم</option>
                    </select>
                </div>
                
                <!-- Packages Grid -->
                <div id="packagesGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-4">
                    <!-- Packages will be loaded here -->
                </div>
                
                <!-- Loading State -->
                <div id="packagesLoading" class="hidden text-center py-8">
                    <i class="fas fa-spinner fa-spin text-4xl text-indigo-500 mb-3"></i>
                    <p class="text-gray-500">جاري تحميل الباقات...</p>
                </div>
                
                <!-- Empty State -->
                <div id="packagesEmpty" class="hidden text-center py-8">
                    <i class="fas fa-box-open text-4xl text-gray-300 mb-3"></i>
                    <p class="text-gray-500">لا توجد باقات متاحة</p>
                </div>
            </div>
            
            <!-- Selected Package Preview -->
            <div id="selectedPackagePreview" class="hidden mt-4 p-2 sm:p-4 bg-gradient-to-br from-indigo-50 to-purple-50 rounded-lg border-2 border-indigo-200">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <p class="text-sm text-gray-500">الباقة المختارة</p>
                        <p id="selectedPackageName" class="text-base sm:text-lg font-bold text-indigo-700"></p>
                    </div>
                    <div class="text-right sm:text-left">
                        <p id="selectedPackagePrice" class="text-lg sm:text-xl font-bold text-green-600"></p>
                        <p id="selectedPackageSpeed" class="text-sm text-gray-500"></p>
                    </div>
                </div>
            </div>
            
            <p id="assignModeText" class="text-xs sm:text-sm text-gray-500 mt-4">
                <i class="fas fa-info-circle ml-1 text-indigo-500"></i>
                سيتم ربط جميع المستخدمين الذين ليس لديهم Profile بالـ Profile المحدد.
            </p>
        </div>
        <div class="p-4 sm:p-6 border-t bg-gray-50 rounded-b-2xl flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-2">
            <button onclick="closeAssignProfileModal()" class="w-full sm:w-auto px-5 py-3 sm:py-2.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium text-base">
                إلغاء
            </button>
            <button onclick="assignProfile()" id="assignProfileBtn" class="w-full sm:w-auto px-5 py-3 sm:py-2.5 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-lg hover:from-indigo-600 hover:to-purple-700 transition font-medium shadow-lg disabled:opacity-50 text-base" disabled>
                <i class="fas fa-link ml-1"></i> <span id="assignBtnText">ربط الجميع</span>
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
let selectedPackage = null;
let packagesData = [];
let assignMode = 'all'; // 'all' or 'single'
let addUserProfiles = [];

// ==================== نظام التحديث الموحد - Unified Update System ====================
const PageUpdater = {
    // تخزين موقع التمرير
    scrollPosition: 0,
    focusSubscriberId: null,
    
    // حفظ الحالة قبل التحديث
    saveState(subscriberId = null) {
        sessionStorage.setItem('scrollPosition', window.scrollY);
        if (subscriberId) {
            sessionStorage.setItem('focusSubscriberId', subscriberId);
        }
    },
    
    // استعادة الحالة بعد التحديث
    restoreState() {
        const scrollPos = sessionStorage.getItem('scrollPosition');
        const focusId = sessionStorage.getItem('focusSubscriberId');
        
        if (focusId) {
            setTimeout(() => {
                const element = document.querySelector(`[data-subscriber-id="${focusId}"]`);
                if (element) {
                    element.scrollIntoView({ behavior: 'auto', block: 'center' });
                    this.highlightElement(element, 'focus');
                } else if (scrollPos) {
                    window.scrollTo(0, parseInt(scrollPos));
                }
                sessionStorage.removeItem('focusSubscriberId');
                sessionStorage.removeItem('scrollPosition');
            }, 100);
        } else if (scrollPos) {
            setTimeout(() => {
                window.scrollTo(0, parseInt(scrollPos));
                sessionStorage.removeItem('scrollPosition');
            }, 100);
        }
    },
    
    // تمييز عنصر مؤقتاً
    highlightElement(element, type = 'success') {
        if (!element) return;
        
        const classes = {
            'success': ['ring-2', 'ring-green-500', 'bg-green-50'],
            'focus': ['ring-2', 'ring-purple-500', 'ring-opacity-50'],
            'warning': ['ring-2', 'ring-orange-500', 'bg-orange-50'],
            'error': ['ring-2', 'ring-red-500', 'bg-red-50']
        };
        
        const classesToAdd = classes[type] || classes['success'];
        element.classList.add(...classesToAdd);
        
        setTimeout(() => {
            element.classList.remove(...classesToAdd);
        }, 2500);
    },
    
    // إظهار إشعار Toast
    showToast(message, type = 'info', duration = 3000) {
        // إزالة أي toast موجود
        document.querySelectorAll('.page-updater-toast').forEach(t => t.remove());
        
        const icons = {
            'success': 'fa-check-circle',
            'error': 'fa-times-circle',
            'warning': 'fa-exclamation-triangle',
            'info': 'fa-info-circle',
            'loading': 'fa-spinner fa-spin'
        };
        
        const colors = {
            'success': 'from-green-500 to-emerald-600',
            'error': 'from-red-500 to-rose-600',
            'warning': 'from-orange-500 to-amber-600',
            'info': 'from-blue-500 to-indigo-600',
            'loading': 'from-purple-500 to-indigo-600'
        };
        
        const toast = document.createElement('div');
        toast.className = `page-updater-toast fixed top-4 right-4 z-[9999] px-5 py-3 rounded-xl shadow-2xl text-white font-bold transform transition-all duration-300 bg-gradient-to-r ${colors[type] || colors['info']}`;
        toast.style.transform = 'translateX(120%)';
        toast.innerHTML = `<i class="fas ${icons[type] || icons['info']} ml-2"></i>${message}`;
        
        document.body.appendChild(toast);
        
        // Animation in
        requestAnimationFrame(() => {
            toast.style.transform = 'translateX(0)';
        });
        
        // Animation out (if not loading)
        if (type !== 'loading') {
            setTimeout(() => {
                toast.style.transform = 'translateX(120%)';
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
        
        return toast;
    },
    
    // إخفاء toast التحميل
    hideLoadingToast() {
        document.querySelectorAll('.page-updater-toast').forEach(t => {
            t.style.transform = 'translateX(120%)';
            setTimeout(() => t.remove(), 300);
        });
    },
    
    // تحديث بيانات المشترك في DOM مباشرة
    updateSubscriberDOM(userId, data) {
        const totalGb = parseFloat(data.total_gb) || 0;
        const dataLimit = parseFloat(data.data_limit_gb) || 0;
        
        // تحديث للموبايل والديسكتوب معاً
        ['mobile', 'desktop'].forEach(view => {
            const usageText = document.getElementById(`usage-text-${view}-${userId}`);
            const usageBar = document.getElementById(`usage-bar-${view}-${userId}`);
            const usagePercent = document.getElementById(`usage-percent-${view}-${userId}`);
            const usageRemaining = document.getElementById(`usage-remaining-${view}-${userId}`);
            const usageValue = document.getElementById(`usage-value-${view}-${userId}`);
            const usageSimple = document.getElementById(`usage-simple-${view}-${userId}`);
            
            if (usageBar) {
                const limit = dataLimit || parseFloat(usageBar.dataset.limit) || 0;
                if (limit > 0) {
                    const percent = Math.min(100, (totalGb / limit) * 100);
                    const remaining = Math.max(0, limit - totalGb);
                    
                    usageBar.style.width = percent + '%';
                    usageBar.className = `h-full rounded-full transition-all duration-500 ${percent > 90 ? 'bg-red-500' : (percent > 70 ? 'bg-orange-500' : 'bg-blue-500')}`;
                    
                    if (usageText) usageText.textContent = `${totalGb.toFixed(2)} / ${limit.toFixed(0)} GB`;
                    if (usagePercent) usagePercent.textContent = `${percent.toFixed(0)}% مستخدم`;
                    if (usageRemaining) {
                        usageRemaining.innerHTML = `<i class="fas fa-hourglass-half ml-1"></i>متبقي ${remaining.toFixed(1)} GB`;
                        usageRemaining.className = `text-sm font-bold ${remaining < 1 ? 'text-red-600' : (remaining < 3 ? 'text-orange-600' : 'text-green-600')}`;
                    }
                }
            } else if (usageValue) {
                usageValue.innerHTML = `<i class="fas fa-database ml-1"></i>${totalGb.toFixed(2)} GB`;
                if (usageSimple && totalGb > 0.01) {
                    usageSimple.classList.remove('hidden');
                }
            }
        });
        
        // تحديث الحالة (online/offline/throttled)
        if (data.status) {
            ['mobile', 'desktop'].forEach(view => {
                const statusBadge = document.getElementById(`status-badge-${view}-${userId}`);
                if (statusBadge) {
                    const statusClasses = {
                        'active': 'bg-green-100 text-green-800',
                        'throttled': 'bg-orange-100 text-orange-800',
                        'disabled': 'bg-red-100 text-red-800',
                        'expired': 'bg-gray-100 text-gray-800'
                    };
                    statusBadge.className = `px-2 py-0.5 rounded-full text-xs font-medium ${statusClasses[data.status] || statusClasses['active']}`;
                }
            });
        }
        
        // تمييز العنصر المحدث
        const element = document.querySelector(`[data-subscriber-id="${userId}"]`);
        this.highlightElement(element, 'success');
    },
    
    // === الدالة الرئيسية: تحديث ذكي ===
    async smartUpdate(options = {}) {
        const {
            subscriberId = null,
            action = 'refresh',
            endpoint = null,
            method = 'POST',
            data = {},
            successMessage = 'تمت العملية بنجاح',
            errorMessage = 'حدث خطأ',
            requireReload = false,
            onSuccess = null,
            onError = null
        } = options;
        
        // إظهار toast التحميل
        const loadingToast = this.showToast('جاري التنفيذ...', 'loading');
        
        try {
            const response = await fetch(endpoint, {
                method: method,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: method !== 'GET' ? JSON.stringify(data) : undefined
            });
            
            const result = await response.json();
            
            // إخفاء toast التحميل
            this.hideLoadingToast();
            
            if (result.success) {
                this.showToast(result.message || successMessage, 'success');
                
                // تنفيذ callback النجاح
                if (onSuccess) onSuccess(result);
                
                // تحديث DOM مباشرة إذا توفرت البيانات
                if (result.data && subscriberId && !requireReload) {
                    this.updateSubscriberDOM(subscriberId, result.data);
                } else if (requireReload) {
                    // إعادة تحميل مع حفظ الموقع
                    setTimeout(() => {
                        this.saveState(subscriberId);
                        window.location.replace(window.location.pathname + '?t=' + Date.now());
                    }, 500);
                }
                
                return { success: true, data: result };
            } else {
                this.showToast(result.message || errorMessage, 'error');
                if (onError) onError(result);
                return { success: false, data: result };
            }
        } catch (error) {
            this.hideLoadingToast();
            this.showToast(errorMessage + ': خطأ في الاتصال', 'error');
            console.error('Update error:', error);
            if (onError) onError(error);
            return { success: false, error };
        }
    },
    
    // === تحديث سريع بإعادة تحميل ===
    reloadPage(subscriberId = null, delay = 0) {
        this.saveState(subscriberId);
        setTimeout(() => {
            window.location.replace(window.location.pathname + '?t=' + Date.now());
        }, delay);
    },
    
    // === تحديث بيانات مشترك من السيرفر بدون reload ===
    async refreshSubscriberData(subscriberId, routerId) {
        try {
            const response = await fetch(`/usermanager/${routerId}/subscriber/${subscriberId}/data`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.subscriber) {
                    this.updateSubscriberCard(subscriberId, data.subscriber);
                    return true;
                }
            }
            return false;
        } catch (e) {
            console.error('Failed to refresh subscriber:', e);
            return false;
        }
    },
    
    // === تحديث كارت المشترك بالكامل ===
    updateSubscriberCard(subscriberId, subscriber) {
        // تحديث الاستهلاك
        if (subscriber.total_bytes !== undefined) {
            const totalGb = (subscriber.total_bytes / 1073741824).toFixed(2);
            this.updateSubscriberDOM(subscriberId, {
                total_gb: totalGb,
                data_limit_gb: subscriber.data_limit ? (subscriber.data_limit / 1073741824) : 0,
                status: subscriber.status
            });
        }
        
        // تحديث اسم الباقة
        ['mobile', 'desktop'].forEach(view => {
            const profileEl = document.getElementById(`profile-${view}-${subscriberId}`);
            if (profileEl && subscriber.profile) {
                profileEl.textContent = subscriber.profile;
            }
            
            // تحديث حالة الاتصال
            const onlineBadge = document.getElementById(`online-badge-${view}-${subscriberId}`);
            if (onlineBadge) {
                if (subscriber.is_online) {
                    onlineBadge.classList.remove('hidden');
                    onlineBadge.classList.add('bg-green-500');
                } else {
                    onlineBadge.classList.add('hidden');
                }
            }
            
            // تحديث حالة التقييد
            const throttledBadge = document.getElementById(`throttled-badge-${view}-${subscriberId}`);
            if (throttledBadge) {
                if (subscriber.is_throttled) {
                    throttledBadge.classList.remove('hidden');
                } else {
                    throttledBadge.classList.add('hidden');
                }
            }
            
            // تحديث شارة الدفع
            const paidBadge = document.getElementById(`paid-badge-${view}-${subscriberId}`);
            if (paidBadge) {
                const shamcashUrl = subscriber.shamcash_qr_url || '';
                const expDate = subscriber.expiration_date || 'غير محدد';
                const price = subscriber.subscription_price || 0;
                const brandName = subscriber.brand_name || 'MegaWiFi';
                if (subscriber.is_paid) {
                    paidBadge.innerHTML = `<button onclick="sendPaidConfirmation(${subscriberId}, '${subscriber.whatsapp_number || subscriber.phone || ''}', '${subscriber.full_name || subscriber.username}', ${price}, '${expDate}', 'regular', '${brandName}')" class="paid-badge ${view === 'mobile' ? 'px-2.5 py-1.5 bg-green-100 text-green-700 rounded-full active:scale-95 transition' : 'group flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r from-green-400 to-emerald-500 text-white rounded-lg shadow-md shadow-green-500/30 hover:shadow-lg hover:scale-105 hover:-translate-y-0.5 transition-all duration-300'} text-xs font-bold" title="إرسال تأكيد دفع"><i class="fas fa-check-circle ${view === 'mobile' ? 'ml-1' : ''}"></i>${view === 'mobile' ? 'مدفوع' : '<span>مدفوع</span>'}</button>`;
                } else if (subscriber.remaining_amount > 0) {
                    const debtAmount = Number(subscriber.remaining_amount).toLocaleString();
                    paidBadge.innerHTML = `<button onclick="sendDebtReminder(${subscriberId}, '${subscriber.whatsapp_number || subscriber.phone || ''}', '${subscriber.full_name || subscriber.username}', ${subscriber.remaining_amount}, 'regular', '${shamcashUrl}', '${brandName}')" class="debt-badge ${view === 'mobile' ? 'px-2.5 py-1.5 bg-orange-500 text-white rounded-full' : 'group flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r from-orange-500 to-amber-500 text-white rounded-lg shadow-md shadow-orange-500/30 hover:shadow-lg hover:scale-105 hover:-translate-y-0.5 transition-all duration-300'} text-xs font-bold" title="إرسال تذكير دين"><i class="fas fa-hand-holding-usd ${view === 'mobile' ? 'ml-1' : ''}"></i>${view === 'mobile' ? 'مدان ' + debtAmount : '<span>مدان ' + debtAmount + '</span>'}</button>`;
                } else {
                    const usedGb = (subscriber.total_bytes / 1073741824).toFixed(2);
                    paidBadge.innerHTML = `<button onclick="sendPaymentReminder(${subscriberId}, '${subscriber.whatsapp_number || subscriber.phone || ''}', '${subscriber.full_name || subscriber.username}', 0, 'regular', '${usedGb}', '${expDate}', ${price}, '${shamcashUrl}', '${brandName}')" class="unpaid-badge ${view === 'mobile' ? 'px-2.5 py-1.5 bg-red-500 text-white rounded-full' : 'group flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r from-red-500 to-rose-500 text-white rounded-lg shadow-md shadow-red-500/30 hover:shadow-lg hover:scale-105 hover:-translate-y-0.5 transition-all duration-300'} text-xs font-bold" title="إرسال تذكير دفع"><i class="fas fa-exclamation-circle ${view === 'mobile' ? 'ml-1' : ''}"></i>${view === 'mobile' ? 'غير مدفوع' : '<span>غير مدفوع</span>'}</button>`;
                }
            }
        });
        
        // تمييز العنصر
        const element = document.querySelector(`[data-subscriber-id="${subscriberId}"]`);
        this.highlightElement(element, 'success');
    },
    
    // === تحديث ذكي - يحاول تحديث DOM أولاً، وإلا يعيد التحميل ===
    async smartReload(subscriberId, routerId) {
        // محاولة تحديث البيانات بدون reload
        const refreshed = await this.refreshSubscriberData(subscriberId, routerId);
        
        if (!refreshed) {
            // إذا فشل، نعيد تحميل الصفحة
            this.reloadPage(subscriberId, 300);
        }
    }
};

// ==================== دوال مختصرة للتوافق مع الكود القديم ====================
// حفظ موقع التمرير والمشترك المحدد
function saveScrollPosition(subscriberId = null) {
    PageUpdater.saveState(subscriberId);
}

// استعادة موقع التمرير والتركيز على المشترك
function restoreScrollPosition() {
    PageUpdater.restoreState();
}

// تحديث الصفحة مع الحفاظ على الموقع
function reloadWithPosition(subscriberId = null) {
    PageUpdater.reloadPage(subscriberId);
}

// تحديث بيانات المشترك في الصفحة مباشرة (بدون إعادة تحميل)
function updateSubscriberUsage(userId, data) {
    PageUpdater.updateSubscriberDOM(userId, data);
}

// Toast notification helper (للتوافق مع الكود القديم)
function showToast(message, type = 'info') {
    PageUpdater.showToast(message, type);
}

// استعادة الموقع عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', () => PageUpdater.restoreState());

// ==================== Add User Functions ====================
function openAddUserModal() {
    document.getElementById('addUserModal').classList.remove('hidden');
    document.getElementById('addUserModal').classList.add('flex');
    
    // Reset form
    document.getElementById('addUserForm').reset();
    
    // Load profiles for selected router
    loadAddUserProfiles();
}

function closeAddUserModal() {
    document.getElementById('addUserModal').classList.add('hidden');
    document.getElementById('addUserModal').classList.remove('flex');
}

function loadAddUserProfiles() {
    const routerId = document.getElementById('addUserRouterSelect').value;
    const profileSelect = document.getElementById('addUserProfile');
    
    profileSelect.innerHTML = '<option value="">-- جاري التحميل... --</option>';
    profileSelect.disabled = true;
    
    fetch(`/usermanager/${routerId}/packages-json`)
        .then(r => r.json())
        .then(data => {
            profileSelect.disabled = false;
            if (data.success && data.profiles) {
                addUserProfiles = data.profiles;
                profileSelect.innerHTML = '<option value="">-- اختر الباقة --</option>';
                data.profiles.forEach(profile => {
                    if (profile.name !== 'throttled') {
                        profileSelect.innerHTML += `<option value="${profile.name}">${profile.name}</option>`;
                    }
                });
            } else {
                profileSelect.innerHTML = '<option value="">-- فشل تحميل الباقات --</option>';
            }
        })
        .catch(() => {
            profileSelect.disabled = false;
            profileSelect.innerHTML = '<option value="">-- خطأ في الاتصال --</option>';
        });
}

function generateRandomUsername() {
    const prefix = 'user';
    const random = Math.floor(Math.random() * 900000) + 100000;
    document.getElementById('addUserUsername').value = prefix + random;
}

function generateRandomPassword() {
    const chars = 'abcdefghijkmnpqrstuvwxyz23456789';
    let password = '';
    for (let i = 0; i < 8; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('addUserPassword').value = password;
}

function submitAddUser() {
    const routerId = document.getElementById('addUserRouterSelect').value;
    const username = document.getElementById('addUserUsername').value.trim();
    const password = document.getElementById('addUserPassword').value.trim();
    const profile = document.getElementById('addUserProfile').value;
    const dataLimit = document.getElementById('addUserDataLimit').value;
    const comment = document.getElementById('addUserComment').value.trim();
    
    if (!username) {
        alert('الرجاء إدخال اسم المستخدم');
        return;
    }
    if (!password) {
        alert('الرجاء إدخال كلمة المرور');
        return;
    }
    if (!profile) {
        alert('الرجاء اختيار الباقة');
        return;
    }
    
    const btn = document.getElementById('addUserBtn');
    btn.disabled = true;
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-2"></i> جاري الإضافة...';
    
    fetch(`/usermanager/${routerId}/add-user`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            username: username,
            password: password,
            profile: profile,
            data_limit_gb: dataLimit || null,
            comment: comment
        })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
        
        if (data.success) {
            alert('تم إضافة المشترك بنجاح!\n\nاسم المستخدم: ' + username + '\nكلمة المرور: ' + password);
            closeAddUserModal();
            window.location.replace(window.location.pathname + '?add=' + Date.now());
        } else {
            alert('فشل الإضافة: ' + (data.message || 'خطأ غير معروف'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
        alert('خطأ في الاتصال');
    });
}

// ==================== Sync Functions ====================

// مزامنة فورية - من زر آخر تحديث
function syncAllRoutersNow() {
    const btn = document.getElementById('syncNowBtn');
    const icon = document.getElementById('syncIcon');
    
    btn.disabled = true;
    icon.classList.add('fa-spin');

    // Use single-router sync if a router is selected
    const selectedRouterId = '{{ request("router_id") }}';
    const syncUrl = selectedRouterId ? '/usermanager/' + selectedRouterId + '/sync' : '/usermanager/sync-all';

    fetch(syncUrl, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        icon.classList.remove('fa-spin');
        
        if (data.success) {
            showToast('✅ ' + data.message, 'success');
            const base2 = window.location.pathname;
            const selRouter = '{{ request("router_id") }}';
            const params2 = selRouter ? 'router_id=' + selRouter + '&sync=' + Date.now() : 'sync=' + Date.now();
            setTimeout(() => window.location.replace(base2 + '?' + params2), 1500);
        } else {
            showToast('❌ ' + (data.message || 'فشلت المزامنة'), 'error');
        }
    })
    .catch(err => {
        btn.disabled = false;
        icon.classList.remove('fa-spin');
        showToast('❌ خطأ في الاتصال', 'error');
    });
}

function syncAllRouters() {
    const routers = @json($routers->pluck('id'));
    if (routers.length === 0) {
        alert('لا يوجد راوترات');
        return;
    }

    // Check if a specific router is selected via URL parameter
    const selectedRouterId = '{{ request("router_id") }}';

    let syncUrl, confirmMsg;
    if (selectedRouterId) {
        // Sync only the selected router
        const routerSelect = document.querySelector('select[name="router_id"]');
        const routerName = routerSelect ? routerSelect.options[routerSelect.selectedIndex]?.text || 'المحدد' : 'المحدد';
        confirmMsg = 'مزامنة مستخدمين راوتر ' + routerName + '؟';
        syncUrl = '/usermanager/' + selectedRouterId + '/sync';
    } else {
        // Sync all routers
        confirmMsg = 'مزامنة جميع المستخدمين والاستهلاك من كل الراوترات؟\n\nسيتم مزامنة ' + routers.length + ' راوتر';
        syncUrl = '/usermanager/sync-all';
    }

    if (!confirm(confirmMsg)) return;

    // Support both buttons (header and empty state)
    const btnHeader = document.getElementById('syncAllBtnHeader');
    const btnEmpty = document.getElementById('syncAllBtn');
    const btn = btnHeader || btnEmpty;

    if (!btn) {
        console.error('Sync button not found');
        return;
    }

    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i> جاري المزامنة...';

    fetch(syncUrl, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalHTML;

        if (data.success) {
            showToast('✅ ' + data.message, 'success');
            setTimeout(() => {
                const base = window.location.pathname;
                const params = selectedRouterId ? 'router_id=' + selectedRouterId + '&sync=' + Date.now() : 'sync=' + Date.now();
                window.location.replace(base + '?' + params);
            }, 1500);
        } else {
            showToast('❌ ' + (data.message || 'فشلت المزامنة'), 'error');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
        showToast('❌ خطأ في الاتصال', 'error');
    });
}


// تحديث استهلاك جميع المشتركين (فصل وتوصيل)
function refreshAllUsage() {
    if (!confirm('تحديث استهلاك جميع المشتركين؟\n\nهذا سيقوم بـ:\n• فصل كل مشترك وإعادة توصيله\n• تحديث بيانات الاستهلاك من المايكروتك\n\nقد تستغرق العملية عدة دقائق')) return;
    
    const btn = document.getElementById('refreshUsageBtn');
    if (!btn) {
        console.error('Refresh button not found');
        return;
    }
    
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i> جاري التحديث...';
    
    fetch('/usermanager/refresh-all-usage', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
        
        if (data.success) {
            showToast('✅ ' + data.message, 'success');
            setTimeout(() => {
                window.location.replace(window.location.pathname + '?refresh=' + Date.now());
            }, 1500);
        } else {
            showToast('❌ ' + (data.message || 'فشل التحديث'), 'error');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
        showToast('❌ خطأ في الاتصال', 'error');
    });
}

function deleteSubscriber(id) {
    if (!confirm('هل تريد حذف هذا المشترك؟')) return;
    
    PageUpdater.smartUpdate({
        endpoint: `/usermanager/${id}`,
        method: 'DELETE',
        successMessage: 'تم حذف المشترك بنجاح',
        onSuccess: () => {
            setTimeout(() => window.location.replace(window.location.pathname + '?del=' + Date.now()), 500);
        }
    });
}

// Refresh single user usage by toggling (disconnect/reconnect)
function refreshUserUsage(userId, mikrotikId, routerId, username) {
    // Get button and icon elements for both mobile and desktop
    const btnMobile = document.getElementById(`refresh-btn-mobile-${userId}`);
    const btnDesktop = document.getElementById(`refresh-btn-desktop-${userId}`);
    const iconMobile = document.getElementById(`refresh-icon-mobile-${userId}`);
    const iconDesktop = document.getElementById(`refresh-icon-desktop-${userId}`);
    
    // Add loading state
    [btnMobile, btnDesktop].forEach(btn => {
        if (btn) {
            btn.disabled = true;
            btn.classList.add('animate-pulse');
        }
    });
    [iconMobile, iconDesktop].forEach(icon => {
        if (icon) {
            icon.classList.remove('fa-user');
            icon.classList.add('fa-sync-alt', 'animate-spin');
        }
    });
    
    fetch(`/usermanager/${routerId}/refresh-user-usage`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            subscriber_id: userId,
            mikrotik_id: mikrotikId
        })
    })
    .then(r => r.json())
    .then(data => {
        // Remove loading state
        [btnMobile, btnDesktop].forEach(btn => {
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('animate-pulse');
            }
        });
        [iconMobile, iconDesktop].forEach(icon => {
            if (icon) {
                icon.classList.remove('fa-sync-alt', 'animate-spin');
                icon.classList.add('fa-user');
            }
        });
        
        if (data.success) {
            // Show success with toast notification
            showToast(`✅ ${data.message}`, 'success');
            
            // تحديث البيانات في الصفحة مباشرة بدون إعادة تحميل
            if (data.data) {
                updateSubscriberUsage(userId, data.data);
            }
        } else {
            showToast('❌ ' + (data.message || 'فشل التحديث'), 'error');
        }
    })
    .catch(err => {
        // Remove loading state on error
        [btnMobile, btnDesktop].forEach(btn => {
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('animate-pulse');
            }
        });
        [iconMobile, iconDesktop].forEach(icon => {
            if (icon) {
                icon.classList.remove('fa-sync-alt', 'animate-spin');
                icon.classList.add('fa-user');
            }
        });
        showToast('❌ خطأ في الاتصال', 'error');
    });
}

// Throttle/Disable User - Change profile to STOP
function throttleUser(userId, mikrotikId, routerId, username) {
    if (!confirm(`هل تريد تعطيل المشترك "${username}"؟\n\nسيتم تحويله إلى باقة بطيئة جداً`)) {
        return;
    }
    
    PageUpdater.smartUpdate({
        subscriberId: userId,
        endpoint: `/usermanager/${routerId}/throttle-user`,
        data: { user_id: userId, mikrotik_id: mikrotikId },
        successMessage: 'تم تعطيل المشترك بنجاح',
        requireReload: false,
        onSuccess: async () => {
            // تحديث حالة التقييد في الواجهة
            ['mobile', 'desktop'].forEach(view => {
                const throttledBadge = document.getElementById(`throttled-badge-${view}-${userId}`);
                if (throttledBadge) throttledBadge.classList.remove('hidden');
                
                const profileEl = document.getElementById(`profile-${view}-${userId}`);
                if (profileEl) profileEl.textContent = 'STOP';
            });
            // تمييز العنصر
            const element = document.querySelector(`[data-subscriber-id="${userId}"]`);
            PageUpdater.highlightElement(element, 'warning');
        }
    });
}

// Enable/Unthrottle User - Restore original profile
function enableUser(userId, mikrotikId, routerId, username) {
    if (!confirm(`هل تريد تمكين المشترك "${username}"؟\n\nسيتم استعادة باقته الأصلية`)) {
        return;
    }
    
    PageUpdater.smartUpdate({
        subscriberId: userId,
        endpoint: `/usermanager/${routerId}/enable-user`,
        data: { user_id: userId, mikrotik_id: mikrotikId },
        successMessage: 'تم تمكين المشترك بنجاح',
        requireReload: false,
        onSuccess: async (result) => {
            // تحديث الواجهة
            ['mobile', 'desktop'].forEach(view => {
                const throttledBadge = document.getElementById(`throttled-badge-${view}-${userId}`);
                if (throttledBadge) throttledBadge.classList.add('hidden');
                
                // تحديث الباقة إذا أرجعها السيرفر
                if (result.data?.profile) {
                    const profileEl = document.getElementById(`profile-${view}-${userId}`);
                    if (profileEl) profileEl.textContent = result.data.profile;
                }
            });
            // تمييز العنصر
            const element = document.querySelector(`[data-subscriber-id="${userId}"]`);
            PageUpdater.highlightElement(element, 'success');
        }
    });
}

function openAssignProfileModal() {
    assignMode = 'all';
    document.getElementById('singleUserId').value = '';
    document.getElementById('singleUserMikrotikId').value = '';
    document.getElementById('assignModalTitle').textContent = 'ربط المستخدمين بـ Profile';
    document.getElementById('assignModeText').innerHTML = '<i class="fas fa-info-circle ml-1 text-indigo-500"></i> سيتم ربط جميع المستخدمين الذين ليس لديهم Profile بالـ Profile المحدد.';
    document.getElementById('assignBtnText').textContent = 'ربط الجميع';
    
    document.getElementById('assignProfileModal').classList.remove('hidden');
    document.getElementById('assignProfileModal').classList.add('flex');
    resetPackageSelection();
}

function changeUserPackage(userId, mikrotikId, routerId, currentProfile) {
    assignMode = 'single';
    document.getElementById('singleUserId').value = userId;
    document.getElementById('singleUserMikrotikId').value = mikrotikId;
    document.getElementById('assignModalTitle').textContent = 'تغيير باقة المشترك';
    document.getElementById('assignModeText').innerHTML = `<i class="fas fa-info-circle ml-1 text-indigo-500"></i> الباقة الحالية: <strong class="text-purple-600">${currentProfile || 'غير محدد'}</strong>`;
    document.getElementById('assignBtnText').textContent = 'تغيير الباقة';
    
    // Pre-select router
    const routerSelect = document.getElementById('profileRouterId');
    routerSelect.value = routerId;
    
    document.getElementById('assignProfileModal').classList.remove('hidden');
    document.getElementById('assignProfileModal').classList.add('flex');
    
    // Load packages for this router
    loadProfileCards();
}

// Renewal functions
function openRenewModal(userId, mikrotikId, routerId, currentProfile, username, subscriptionPrice, remainingAmount, whatsappNumber, isPaid) {
    document.getElementById('renewUserId').value = userId;
    document.getElementById('renewMikrotikId').value = mikrotikId;
    document.getElementById('renewRouterId').value = routerId;
    document.getElementById('renewUsername').textContent = username || 'المشترك';
    
    // Reset fields
    document.getElementById('renewDataLimit').value = '';
    document.getElementById('renewExpiryDays').value = '30';
    document.getElementById('renewResetUsage').checked = false;
    
    // Payment fields
    document.getElementById('renewSubscriptionPrice').value = subscriptionPrice || 0;
    document.getElementById('renewRemainingAmount').value = remainingAmount || 0;
    document.getElementById('renewDebtAmount').value = remainingAmount || 0;
    
    // Set payment status radio
    if (isPaid) {
        document.getElementById('renewIsPaid').checked = true;
        document.getElementById('debtAmountContainer').classList.add('hidden');
    } else if (remainingAmount > 0) {
        document.getElementById('renewIsDebt').checked = true;
        document.getElementById('debtAmountContainer').classList.remove('hidden');
    } else {
        document.getElementById('renewNotPaid').checked = true;
        document.getElementById('debtAmountContainer').classList.add('hidden');
    }
    
    // Load profiles for this router
    loadRenewProfiles(routerId, currentProfile);
    
    document.getElementById('renewModal').classList.remove('hidden');
    document.getElementById('renewModal').classList.add('flex');
}

function loadRenewProfiles(routerId, currentProfile) {
    const select = document.getElementById('renewProfileSelect');
    select.innerHTML = '<option value="">جاري التحميل...</option>';
    select.disabled = true;
    
    fetch(`/usermanager/${routerId}/packages/data`, {
        headers: { 'Accept': 'application/json' }
    })
        .then(r => r.json())
        .then(data => {
            select.disabled = false;
            if (data.success && data.profiles) {
                select.innerHTML = '<option value="">-- اختر الباقة --</option>';
                data.profiles.forEach(p => {
                    const name = p.name || 'غير محدد';
                    const validity = p.validity || '';
                    const selected = name === currentProfile ? 'selected' : '';
                    if (name !== 'throttled') {
                        select.innerHTML += `<option value="${name}" ${selected}>${name} ${validity ? '(' + validity + ')' : ''}</option>`;
                    }
                });
            } else {
                select.innerHTML = '<option value="">فشل تحميل الباقات</option>';
            }
        })
        .catch((err) => {
            select.disabled = false;
            console.error('Error loading profiles:', err);
            select.innerHTML = '<option value="">فشل تحميل الباقات</option>';
        });
}

function closeRenewModal() {
    // إعادة تعيين زر التجديد
    const btn = document.getElementById('renewBtn');
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check ml-1"></i> تجديد';
    }
    document.getElementById('renewModal').classList.remove('flex');
    document.getElementById('renewModal').classList.add('hidden');
    document.getElementById('renewResetUsage').checked = false;
    document.getElementById('renewDataLimit').value = '';
    document.getElementById('renewExpiryDays').value = '30';
    document.getElementById('renewSubscriptionPrice').value = '';
    document.getElementById('renewRemainingAmount').value = '';
    document.getElementById('renewDebtAmount').value = '';
    document.getElementById('renewNotPaid').checked = true;
    document.getElementById('debtAmountContainer').classList.add('hidden');
}

// Toggle debt amount visibility
function toggleDebtAmount() {
    const debtContainer = document.getElementById('debtAmountContainer');
    const isDebt = document.getElementById('renewIsDebt').checked;
    if (isDebt) {
        debtContainer.classList.remove('hidden');
    } else {
        debtContainer.classList.add('hidden');
    }
}

// Send paid confirmation via WhatsApp
function sendPaidConfirmation(subscriberId, phone, name, paidAmount, expiryDate, waType, brandName = 'MegaWiFi') {
    if (!phone) {
        showToast('لا يوجد رقم واتساب للمشترك', 'error');
        return;
    }
    
    let phoneNum = phone.replace(/[^0-9]/g, '');
    if (phoneNum.startsWith('0')) phoneNum = '963' + phoneNum.substring(1);
    else if (!phoneNum.startsWith('963')) phoneNum = '963' + phoneNum;
    
    const amount = paidAmount > 0 ? Number(paidAmount).toLocaleString() + ' ل.س' : 'غير محدد';
    const brand = brandName || 'MegaWiFi';
    
    const message = `مرحباً ${name} 👋

✅ *تم تفعيل خدمتك بنجاح!*

💰 *المبلغ المدفوع:* ${amount}
📅 *تاريخ انتهاء الاشتراك:* ${expiryDate}

🔍 تفقد رصيدك من هنا:
https://megawifi.site/check-balance

شكراً لثقتكم بنا ✨
${brand} 🌐`;

    openWhatsappDirect(phoneNum, encodeURIComponent(message), waType);
}

// Send debt reminder via WhatsApp
function sendDebtReminder(subscriberId, phone, name, debtAmount, waType, shamcashQrUrl = '', brandName = 'MegaWiFi') {
    if (!phone) {
        showToast('لا يوجد رقم واتساب للمشترك', 'error');
        return;
    }
    
    let phoneNum = phone.replace(/[^0-9]/g, '');
    if (phoneNum.startsWith('0')) phoneNum = '963' + phoneNum.substring(1);
    else if (!phoneNum.startsWith('963')) phoneNum = '963' + phoneNum;
    
    let message = `مرحباً ${name} 👋

💰 لديك دين مستحق بقيمة *${Number(debtAmount).toLocaleString()} ل.س*

نرجو تسديد المبلغ في أقرب وقت ممكن.`;

    if (shamcashQrUrl) {
        message += `

📲 *للدفع عبر شام كاش:*
${shamcashQrUrl}`;
    }

    const brand = brandName || 'MegaWiFi';
    message += `

شكراً لتعاملكم معنا ✨
${brand} 🌐`;

    openWhatsappDirect(phoneNum, encodeURIComponent(message), waType);
}

// Edit Modal Functions
function openEditModal(subscriberId, username, fullName, phone, nationalId, address, whatsappNumber) {
    document.getElementById('editSubscriberId').value = subscriberId;
    document.getElementById('editUsername').textContent = username;
    document.getElementById('editFullName').value = fullName || '';
    document.getElementById('editPhone').value = phone || '';
    document.getElementById('editNationalId').value = nationalId || '';
    document.getElementById('editAddress').value = address || '';
    document.getElementById('editWhatsapp').value = whatsappNumber || '';
    
    document.getElementById('editModal').classList.remove('hidden');
    document.getElementById('editModal').classList.add('flex');
}

function closeEditModal() {
    // إعادة تعيين زر التعديل
    const btn = document.getElementById('editBtn');
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = `<i class="fas fa-save ml-1"></i>
                حفظ`;
    }
    document.getElementById('editModal').classList.remove('flex');
    document.getElementById('editModal').classList.add('hidden');
}

function submitEdit() {
    const subscriberId = document.getElementById('editSubscriberId').value;
    const fullName = document.getElementById('editFullName').value;
    const phone = document.getElementById('editPhone').value;
    const nationalId = document.getElementById('editNationalId').value;
    const address = document.getElementById('editAddress').value;
    const whatsappNumber = document.getElementById('editWhatsapp').value;
    
    const btn = document.getElementById('editBtn');
    btn.disabled = true;
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-2"></i> جاري الحفظ...';
    
    PageUpdater.smartUpdate({
        subscriberId: subscriberId,
        endpoint: `/subscribers/${subscriberId}/update-info`,
        data: {
            full_name: fullName,
            phone: phone,
            national_id: nationalId,
            address: address,
            whatsapp_number: whatsappNumber
        },
        successMessage: 'تم حفظ البيانات بنجاح!',
        requireReload: false,
        onSuccess: async () => {
            closeEditModal();
            // تحديث الاسم في الواجهة مباشرة
            ['mobile', 'desktop'].forEach(view => {
                const nameEl = document.getElementById(`name-${view}-${subscriberId}`);
                if (nameEl && fullName) {
                    nameEl.textContent = fullName;
                }
                const phoneEl = document.getElementById(`phone-${view}-${subscriberId}`);
                if (phoneEl && phone) {
                    phoneEl.textContent = phone;
                }
            });
            // تمييز العنصر المحدث
            const element = document.querySelector(`[data-subscriber-id="${subscriberId}"]`);
            PageUpdater.highlightElement(element, 'success');
        },
        onError: () => {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    });
}

// Transfer Modal Functions
function openTransferModal(subscriberId, username, fullName, sourceRouterId, sourceRouterName, profile) {
    document.getElementById('transferSubscriberId').value = subscriberId;
    document.getElementById('transferSourceRouterId').value = sourceRouterId;
    document.getElementById('transferUsername').textContent = username || '-';
    document.getElementById('transferFullName').textContent = fullName || '-';
    document.getElementById('transferSourceRouter').textContent = sourceRouterName || '-';
    document.getElementById('transferProfile').textContent = profile || '-';
    document.getElementById('transferTargetRouter').value = '';
    
    // Hide source router from target options
    const select = document.getElementById('transferTargetRouter');
    Array.from(select.options).forEach(opt => {
        opt.hidden = (opt.value == sourceRouterId);
    });
    
    document.getElementById('transferModal').classList.remove('hidden');
    document.getElementById('transferModal').classList.add('flex');
}

function closeTransferModal() {
    const btn = document.getElementById('transferBtn');
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-exchange-alt ml-1"></i> نقل المشترك';
    }
    document.getElementById('transferModal').classList.remove('flex');
    document.getElementById('transferModal').classList.add('hidden');
}

function submitTransfer() {
    const subscriberId = document.getElementById('transferSubscriberId').value;
    const targetRouterId = document.getElementById('transferTargetRouter').value;
    
    if (!targetRouterId) {
        PageUpdater.showToast('يرجى اختيار الراوتر الهدف', 'warning');
        return;
    }
    
    const username = document.getElementById('transferUsername').textContent;
    const sourceRouter = document.getElementById('transferSourceRouter').textContent;
    const targetOption = document.getElementById('transferTargetRouter').selectedOptions[0]?.textContent || '';
    
    if (!confirm(`هل تريد نقل المشترك "${username}" من "${sourceRouter}" إلى "${targetOption}"?\n\nسيتم تصفير الاستهلاك.`)) {
        return;
    }
    
    const btn = document.getElementById('transferBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-2"></i> جاري النقل...';
    
    PageUpdater.smartUpdate({
        subscriberId: subscriberId,
        endpoint: `/usermanager/${subscriberId}/transfer`,
        data: { target_router_id: targetRouterId },
        successMessage: 'تم نقل المشترك بنجاح',
        requireReload: true,
        onSuccess: () => {
            closeTransferModal();
        },
        onError: () => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-exchange-alt ml-1"></i> نقل المشترك';
        }
    });
}

// Contract Modal Functions
function openContractModal(subscriberId, username, fullName, phone, nationalId, address, profile, router) {
    document.getElementById('contractDate').textContent = new Date().toLocaleDateString('ar-SY');
    document.getElementById('contractUsername').textContent = username || '-';
    document.getElementById('contractFullName').textContent = fullName || '-';
    document.getElementById('contractPhone').textContent = phone || '-';
    document.getElementById('contractNationalId').textContent = nationalId || '-';
    document.getElementById('contractAddress').textContent = address || '-';
    document.getElementById('contractProfile').textContent = profile || '-';
    document.getElementById('contractRouter').textContent = router || '-';
    document.getElementById('contractSignName').textContent = fullName || username;
    
    document.getElementById('contractModal').classList.remove('hidden');
    document.getElementById('contractModal').classList.add('flex');
}

function closeContractModal() {
    document.getElementById('contractModal').classList.remove('flex');
    document.getElementById('contractModal').classList.add('hidden');
}

function printContract() {
    const content = document.getElementById('contractContent').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html dir="rtl" lang="ar">
        <head>
            <meta charset="UTF-8">
            <title>عقد اشتراك - MegaWiFi</title>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; padding: 20px; direction: rtl; }
                h2 { text-align: center; margin-bottom: 10px; }
                .border-2 { border: 2px solid #e5e7eb; border-radius: 10px; padding: 15px; margin-bottom: 15px; }
                .grid { display: grid; gap: 10px; }
                .grid-cols-2 { grid-template-columns: repeat(2, 1fr); }
                .col-span-2 { grid-column: span 2; }
                .font-bold { font-weight: bold; }
                .text-gray-500 { color: #6b7280; }
                .text-gray-800 { color: #1f2937; }
                .text-center { text-align: center; }
                .border-t { border-top: 2px solid #9ca3af; padding-top: 10px; margin-top: 30px; }
                .mb-3 { margin-bottom: 12px; }
                .pb-2 { padding-bottom: 8px; border-bottom: 1px solid #e5e7eb; }
                ol { padding-right: 20px; }
                li { margin-bottom: 8px; }
                @media print { body { padding: 0; } }
            </style>
        </head>
        <body>
            ${content}
        </body>
        </html>
    `);
    printWindow.document.close();
    setTimeout(() => {
        printWindow.print();
    }, 250);
}

function submitRenewal() {
    const routerId = document.getElementById('renewRouterId').value;
    const mikrotikId = document.getElementById('renewMikrotikId').value;
    const userId = document.getElementById('renewUserId').value;
    const username = document.getElementById('renewUsername').textContent;
    const profile = document.getElementById('renewProfileSelect').value;
    const dataLimit = document.getElementById('renewDataLimit').value;
    const expiryDays = document.getElementById('renewExpiryDays').value;
    const resetUsage = document.getElementById('renewResetUsage').checked;
    const subscriptionPrice = document.getElementById('renewSubscriptionPrice').value;
    
    // Payment status from radio buttons
    const isPaid = document.getElementById('renewIsPaid').checked;
    const isDebt = document.getElementById('renewIsDebt').checked;
    const debtAmount = document.getElementById('renewDebtAmount').value;
    
    // Calculate remaining amount based on payment status
    let remainingAmount = 0;
    if (isDebt && debtAmount) {
        remainingAmount = parseFloat(debtAmount);
    }
    
    console.log('submitRenewal called with:', { routerId, mikrotikId, userId, username, profile, dataLimit, expiryDays, resetUsage, subscriptionPrice, remainingAmount, isPaid, isDebt });
    
    if (!profile) {
        showToast('الرجاء اختيار الباقة', 'error');
        return;
    }
    
    const btn = document.getElementById('renewBtn');
    if (!btn) {
        console.error('renewBtn not found!');
        return;
    }
    
    btn.disabled = true;
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-2"></i> جاري التجديد...';
    
    const requestData = {
        user_id: mikrotikId,
        username: username,
        subscriber_id: userId,
        profile: profile,
        data_limit_gb: dataLimit || null,
        expiry_days: expiryDays || null,
        reset_usage: resetUsage,
        subscription_price: subscriptionPrice !== '' ? parseFloat(subscriptionPrice) : null,
        remaining_amount: remainingAmount,
        is_paid: isPaid
    };
    
    console.log('Sending request:', requestData);
    
    PageUpdater.smartUpdate({
        subscriberId: userId,
        endpoint: `/usermanager/${routerId}/renew-user`,
        data: requestData,
        successMessage: 'تم تجديد الاشتراك بنجاح!',
        requireReload: false,
        onSuccess: async (result) => {
            closeRenewModal();
            // محاولة تحديث البيانات بدون reload
            const refreshed = await PageUpdater.refreshSubscriberData(userId, routerId);
            if (!refreshed) {
                // إذا فشل التحديث الذكي، نعيد تحميل الصفحة
                PageUpdater.reloadPage(userId, 500);
            }
        },
        onError: () => {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    });
}

function closeAssignProfileModal() {
    document.getElementById('assignProfileModal').classList.add('hidden');
    document.getElementById('assignProfileModal').classList.remove('flex');
    resetPackageSelection();
}

function resetPackageSelection() {
    selectedPackage = null;
    document.getElementById('packagesContainer').classList.add('hidden');
    document.getElementById('selectedPackagePreview').classList.add('hidden');
    document.getElementById('assignProfileBtn').disabled = true;
    document.querySelectorAll('.package-card-modal').forEach(card => {
        card.classList.remove('ring-2', 'ring-indigo-500', 'bg-indigo-50');
    });
}

function loadProfileCards() {
    const routerId = document.getElementById('profileRouterId').value;
    const container = document.getElementById('packagesContainer');
    const grid = document.getElementById('packagesGrid');
    const loading = document.getElementById('packagesLoading');
    const empty = document.getElementById('packagesEmpty');
    
    if (!routerId) {
        container.classList.add('hidden');
        return;
    }
    
    container.classList.remove('hidden');
    grid.classList.add('hidden');
    loading.classList.remove('hidden');
    empty.classList.add('hidden');
    
    fetch(`/usermanager/${routerId}/packages/data`, {
        headers: { 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        loading.classList.add('hidden');
        
        if (data.success && data.profiles && data.profiles.length > 0) {
            packagesData = data.profiles.map(profile => {
                // Find matching limitation
                const limitation = data.limitations?.find(l => l.name === profile.name) || {};
                let speed = 0;
                if (limitation['rate-limit']) {
                    const match = limitation['rate-limit'].match(/(\d+)/);
                    speed = match ? parseInt(match[1]) : 0;
                }
                return {
                    ...profile,
                    limitation,
                    speed,
                    rateLimit: limitation['rate-limit'] || null,
                    transferLimit: limitation['transfer-limit'] || null
                };
            }).sort((a, b) => b.speed - a.speed);
            
            renderPackageCards(packagesData);
            grid.classList.remove('hidden');
        } else {
            empty.classList.remove('hidden');
        }
    })
    .catch(err => {
        loading.classList.add('hidden');
        empty.classList.remove('hidden');
        console.error(err);
    });
}

function renderPackageCards(packages) {
    const grid = document.getElementById('packagesGrid');
    
    // Category colors based on speed
    const getCategory = (speed) => {
        if (speed >= 100) return { name: 'فائق السرعة', color: 'from-purple-500 to-indigo-600', icon: 'fa-rocket' };
        if (speed >= 50) return { name: 'سريع جداً', color: 'from-blue-500 to-cyan-600', icon: 'fa-bolt' };
        if (speed >= 20) return { name: 'سريع', color: 'from-green-500 to-teal-600', icon: 'fa-tachometer-alt' };
        if (speed >= 10) return { name: 'متوسط', color: 'from-yellow-500 to-orange-600', icon: 'fa-gauge-high' };
        if (speed > 0) return { name: 'أساسي', color: 'from-gray-500 to-gray-600', icon: 'fa-wifi' };
        return { name: 'غير مصنف', color: 'from-gray-400 to-gray-500', icon: 'fa-box' };
    };
    
    const formatBytes = (bytes) => {
        if (!bytes || bytes === '0') return 'غير محدود';
        const num = parseInt(bytes);
        if (num >= 1073741824) return (num / 1073741824).toFixed(1) + ' GB';
        if (num >= 1048576) return (num / 1048576).toFixed(1) + ' MB';
        return num + ' B';
    };
    
    grid.innerHTML = packages.map(pkg => {
        const cat = getCategory(pkg.speed);
        return `
            <div class="package-card-modal cursor-pointer bg-white rounded-lg border-2 border-gray-100 overflow-hidden hover:shadow-lg transition transform hover:-translate-y-1"
                 data-name="${pkg.name || ''}"
                 data-speed="${pkg.speed}"
                 data-price="${pkg.price || 0}"
                 onclick="selectPackage('${pkg.name}', '${pkg.price || 0}', '${pkg.rateLimit || ''}')">
                <!-- Card Header -->
                <div class="bg-gradient-to-l ${cat.color} p-2 text-white">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <i class="fas ${cat.icon}"></i>
                            <span class="font-bold">${pkg.name || '-'}</span>
                        </div>
                        <span class="text-xs bg-white/20 px-2 py-1 rounded-full">${cat.name}</span>
                    </div>
                </div>
                
                <!-- Card Body -->
                <div class="p-2">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-gray-500 text-sm"><i class="fas fa-clock ml-1"></i> الصلاحية</span>
                        <span class="text-sm font-medium">${pkg.validity || 'غير محدد'}</span>
                    </div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-gray-500 text-sm"><i class="fas fa-tachometer-alt ml-1"></i> السرعة</span>
                        <span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-medium">${pkg.rateLimit || 'غير محدد'}</span>
                    </div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-gray-500 text-sm"><i class="fas fa-database ml-1"></i> البيانات</span>
                        <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-medium">${formatBytes(pkg.transferLimit)}</span>
                    </div>
                    <div class="mt-3 pt-3 border-t flex justify-between items-center">
                        <span class="text-gray-500 text-sm">السعر</span>
                        <span class="text-lg font-bold text-green-600">${parseInt(pkg.price || 0).toLocaleString()} ل.س</span>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    // Search functionality
    document.getElementById('packageSearchModal').oninput = (e) => {
        const q = e.target.value.toLowerCase();
        document.querySelectorAll('.package-card-modal').forEach(card => {
            const name = card.dataset.name.toLowerCase();
            card.style.display = name.includes(q) ? '' : 'none';
        });
    };
    
    // Sort functionality
    document.getElementById('packageSortModal').onchange = (e) => {
        const sortBy = e.target.value;
        const sorted = [...packagesData].sort((a, b) => {
            if (sortBy === 'speed') return b.speed - a.speed;
            if (sortBy === 'price') return (b.price || 0) - (a.price || 0);
            if (sortBy === 'name') return (a.name || '').localeCompare(b.name || '');
            return 0;
        });
        renderPackageCards(sorted);
    };
}

function selectPackage(name, price, rateLimit) {
    selectedPackage = name;
    
    // Update visual selection
    document.querySelectorAll('.package-card-modal').forEach(card => {
        if (card.dataset.name === name) {
            card.classList.add('ring-2', 'ring-indigo-500', 'bg-indigo-50');
        } else {
            card.classList.remove('ring-2', 'ring-indigo-500', 'bg-indigo-50');
        }
    });
    
    // Show preview
    const preview = document.getElementById('selectedPackagePreview');
    document.getElementById('selectedPackageName').textContent = name;
    document.getElementById('selectedPackagePrice').textContent = parseInt(price).toLocaleString() + ' ل.س';
    document.getElementById('selectedPackageSpeed').textContent = rateLimit || 'السرعة غير محددة';
    preview.classList.remove('hidden');
    
    // Enable button
    document.getElementById('assignProfileBtn').disabled = false;
}

function assignProfile() {
    const routerId = document.getElementById('profileRouterId').value;
    
    if (!routerId || !selectedPackage) {
        alert('الرجاء اختيار الراوتر والباقة');
        return;
    }
    
    const btn = document.getElementById('assignProfileBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i> جاري التطبيق...';
    
    if (assignMode === 'single') {
        // Single user mode
        const userId = document.getElementById('singleUserId').value;
        const mikrotikId = document.getElementById('singleUserMikrotikId').value;
        
        fetch(`/usermanager/${routerId}/change-user-profile`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ 
                user_id: userId,
                mikrotik_id: mikrotikId,
                profile: selectedPackage 
            })
        })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                closeAssignProfileModal();
                window.location.replace(window.location.pathname + '?profile=' + Date.now());
            }
        })
        .catch(() => alert('خطأ في تغيير الباقة'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-link ml-1"></i> <span id="assignBtnText">تغيير الباقة</span>';
        });
    } else {
        // All users mode
        if (!confirm(`ربط جميع المستخدمين بـ Profile: ${selectedPackage}؟`)) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-link ml-1"></i> <span id="assignBtnText">ربط الجميع</span>';
            return;
        }
        
        fetch(`/usermanager/${routerId}/assign-profile`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ profile: selectedPackage })
        })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                closeAssignProfileModal();
            }
        })
        .catch(() => alert('خطأ في الربط'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-link ml-1"></i> <span id="assignBtnText">ربط الجميع</span>';
        });
    }
}

// Send Payment Reminder via WhatsApp (تذكير بالدفع مع الاستهلاك وتاريخ الانتهاء)
function sendPaymentReminder(subscriberId, whatsappNumber, customerName, remainingAmount, whatsappType, usedGb, expDate, subscriptionPrice = 0, shamcashQrUrl = '', brandName = 'MegaWiFi') {
    if (!whatsappNumber) {
        alert('لا يوجد رقم واتساب أو هاتف لهذا المشترك!\nيرجى إضافة الرقم من صفحة التعديل.');
        return;
    }
    
    // Format phone number (remove spaces, add country code if needed)
    let phone = whatsappNumber.replace(/\s+/g, '').replace(/-/g, '');
    if (phone.startsWith('0')) {
        phone = '963' + phone.substring(1); // Syria country code
    }
    if (!phone.startsWith('+') && !phone.startsWith('963')) {
        phone = '963' + phone;
    }
    
    // Create payment reminder message with usage and expiry
    // Use remaining_amount if > 0, otherwise use subscription_price
    let amount = 'غير محدد';
    if (remainingAmount > 0) {
        amount = remainingAmount.toLocaleString('ar-SY');
    } else if (subscriptionPrice > 0) {
        amount = subscriptionPrice.toLocaleString('ar-SY');
    }
    const usageText = usedGb ? usedGb + ' GB' : 'غير متوفر';
    const expiryText = expDate || 'غير محدد';
    const brand = brandName || 'MegaWiFi';
    
    let message = `⚠️ *تذكير بالدفع*

مرحباً ${customerName}،

💳 *المبلغ المطلوب:* ${amount} ل.س

📊 *استهلاكك الحالي:* ${usageText}
📅 *تاريخ انتهاء الاشتراك:* ${expiryText}

⏰ نرجو تسديد المبلغ في أقرب وقت لتجنب انقطاع الخدمة.`;

    if (shamcashQrUrl) {
        message += `

📲 *للدفع عبر شام كاش:*
${shamcashQrUrl}`;
    }

    message += `

🔍 تفقد رصيدك من هنا:
https://megawifi.site/check-balance

شكراً لتعاونكم 🙏
✨ ${brand}`;

    // Open WhatsApp directly based on router settings
    openWhatsappDirect(phone, encodeURIComponent(message), whatsappType || 'regular');
}

// Open WhatsApp directly without modal
function openWhatsappDirect(phone, message, type) {
    // Detect if mobile device
    const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
    const isAndroid = /Android/i.test(navigator.userAgent);
    
    let url;
    if (type === 'business') {
        if (isAndroid) {
            url = `intent://send?phone=${phone}&text=${message}#Intent;package=com.whatsapp.w4b;scheme=whatsapp;end`;
        } else if (isMobile) {
            url = `https://api.whatsapp.com/send?phone=${phone}&text=${message}`;
        } else {
            url = `https://web.whatsapp.com/send?phone=${phone}&text=${message}`;
        }
    } else {
        if (isAndroid) {
            url = `intent://send?phone=${phone}&text=${message}#Intent;package=com.whatsapp;scheme=whatsapp;end`;
        } else if (isMobile) {
            url = `https://wa.me/${phone}?text=${message}`;
        } else {
            url = `https://web.whatsapp.com/send?phone=${phone}&text=${message}`;
        }
    }
    
    if (isAndroid && url.startsWith('intent://')) {
        window.location.href = url;
    } else {
        window.open(url, '_blank');
    }
}

// Filter to show only unpaid subscribers
let unpaidFilterActive = false;
function filterUnpaid() {
    const desktopTable = document.querySelector('table tbody');
    const mobileCards = document.querySelectorAll('.lg\\:hidden .space-y-2 > div');
    
    unpaidFilterActive = !unpaidFilterActive;
    
    if (unpaidFilterActive) {
        // Filter desktop table rows
        if (desktopTable) {
            const rows = desktopTable.querySelectorAll('tr');
            rows.forEach(row => {
                // Check if row has green "مدفوع" badge (paid)
                const paidBadge = row.querySelector('.bg-green-100.text-green-700');
                if (paidBadge && paidBadge.textContent.includes('مدفوع')) {
                    row.style.display = 'none';
                } else {
                    row.style.display = '';
                }
            });
        }
        
        // Filter mobile cards
        mobileCards.forEach(card => {
            const paidBadge = card.querySelector('.bg-green-100.text-green-700');
            if (paidBadge && paidBadge.textContent.includes('مدفوع')) {
                card.style.display = 'none';
            } else {
                card.style.display = '';
            }
        });
        
        // Show notification
        showToast('تم عرض غير المدفوعين فقط - اضغط مرة أخرى لإظهار الجميع', 'info');
    } else {
        // Show all rows
        if (desktopTable) {
            const rows = desktopTable.querySelectorAll('tr');
            rows.forEach(row => {
                row.style.display = '';
            });
        }
        
        // Show all mobile cards
        mobileCards.forEach(card => {
            card.style.display = '';
        });
        
        showToast('تم إظهار جميع المشتركين', 'success');
    }
}

// Bulk Expiry Date Modal
function openBulkExpiryModal() {
    document.getElementById('bulkExpiryModal').classList.remove('hidden');
    // Set default date to 30 days from now
    const defaultDate = new Date();
    defaultDate.setDate(defaultDate.getDate() + 30);
    document.getElementById('bulkExpiryDate').value = defaultDate.toISOString().split('T')[0];
}

function closeBulkExpiryModal() {
    document.getElementById('bulkExpiryModal').classList.add('hidden');
}

function applyBulkExpiry() {
    const expiryDate = document.getElementById('bulkExpiryDate').value;
    const routerId = document.getElementById('bulkExpiryRouter').value;
    
    if (!routerId) {
        showToast('يرجى اختيار الراوتر', 'error');
        return;
    }

    if (!expiryDate) {
        showToast('يرجى تحديد تاريخ الانتهاء', 'error');
        return;
    }
    
    const btn = document.getElementById('applyBulkExpiryBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i> جاري التطبيق...';
    btn.disabled = true;
    
    fetch('{{ route("usermanager.bulk-expiry") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            expiry_date: expiryDate,
            router_id: routerId
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            closeBulkExpiryModal();
            setTimeout(() => window.location.replace(window.location.pathname + '?expiry=' + Date.now()), 1500);
        } else {
            showToast(data.message || 'حدث خطأ', 'error');
        }
    })
    .catch(err => {
        showToast('حدث خطأ في الاتصال', 'error');
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function toggleIptvInline(subscriberId, buttonElement) {
    const currentlyEnabled = buttonElement.dataset.enabled === '1';
    const originalHtml = buttonElement.innerHTML;
    
    // Show loading state
    buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    buttonElement.disabled = true;
    
    fetch(`/subscribers/${subscriberId}/toggle-iptv`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const newEnabled = !currentlyEnabled;
            buttonElement.dataset.enabled = newEnabled ? '1' : '0';
            
            // Update mobile button styling (simple bg colors)
            if (buttonElement.classList.contains('bg-purple-600') || buttonElement.classList.contains('bg-gray-400')) {
                buttonElement.classList.remove('bg-purple-600', 'bg-gray-400');
                buttonElement.classList.add(newEnabled ? 'bg-purple-600' : 'bg-gray-400');
            }
            
            // Update desktop button styling (gradients)
            if (buttonElement.classList.contains('from-purple-600') || buttonElement.classList.contains('from-gray-400')) {
                buttonElement.classList.remove('from-purple-600', 'to-violet-600', 'shadow-purple-500/30', 'hover:shadow-purple-500/40',
                                             'from-gray-400', 'to-gray-500', 'shadow-gray-400/30', 'hover:shadow-gray-400/40');
                if (newEnabled) {
                    buttonElement.classList.add('from-purple-600', 'to-violet-600', 'shadow-purple-500/30', 'hover:shadow-purple-500/40');
                } else {
                    buttonElement.classList.add('from-gray-400', 'to-gray-500', 'shadow-gray-400/30', 'hover:shadow-gray-400/40');
                }
            }
            
            // Update title
            buttonElement.title = newEnabled ? 'تعطيل IPTV' : 'تفعيل IPTV';
            
            showToast(data.message, 'success');
        } else {
            showToast(data.message || 'حدث خطأ', 'error');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        showToast('حدث خطأ في الاتصال', 'error');
    })
    .finally(() => {
        buttonElement.innerHTML = originalHtml;
        buttonElement.disabled = false;
    });
}
</script>

<!-- Bulk Expiry Date Modal -->
<div id="bulkExpiryModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="bg-gradient-to-r from-orange-500 to-red-500 px-6 py-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <i class="fas fa-calendar-alt"></i>
                    تحديد تاريخ انتهاء جماعي
                </h2>
                <button onclick="closeBulkExpiryModal()" class="text-white/80 hover:text-white transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        <div class="p-6 space-y-5">
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                <div class="flex items-start gap-2 text-amber-700">
                    <i class="fas fa-exclamation-triangle mt-0.5"></i>
                    <p class="text-sm">سيتم تطبيق تاريخ الانتهاء على <strong>جميع المستخدمين</strong> في الراوتر المحدد</p>
                </div>
            </div>
            
            
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-server text-orange-500 ml-1"></i>
                    اختر الراوتر
                </label>
                <select id="bulkExpiryRouter" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-orange-500 focus:ring-4 focus:ring-orange-100 transition" required>
                    <option value="">-- اختر الراوتر --</option>
                    @foreach($routers ?? [] as $router)
                        <option value="{{ $router->id }}">{{ $router->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-calendar text-orange-500 ml-1"></i>
                    تاريخ الانتهاء الجديد
                </label>
                <input type="date" id="bulkExpiryDate" 
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-orange-500 focus:ring-4 focus:ring-orange-100 transition">
            </div>
            
            <!-- Quick Date Buttons -->
            <div class="flex flex-wrap gap-2">
                <button type="button" onclick="setQuickDate(7)" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm transition">
                    أسبوع
                </button>
                <button type="button" onclick="setQuickDate(30)" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm transition">
                    شهر
                </button>
                <button type="button" onclick="setQuickDate(90)" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm transition">
                    3 أشهر
                </button>
                <button type="button" onclick="setQuickDate(180)" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm transition">
                    6 أشهر
                </button>
                <button type="button" onclick="setQuickDate(365)" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm transition">
                    سنة
                </button>
            </div>
            
            <div class="pt-4 border-t border-gray-100 flex gap-3">
                <button type="button" id="applyBulkExpiryBtn" onclick="applyBulkExpiry()"
                    class="flex-1 px-6 py-4 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white rounded-xl font-bold shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-2">
                    <i class="fas fa-check"></i>
                    تطبيق على الجميع
                </button>
                <button type="button" onclick="closeBulkExpiryModal()"
                    class="px-6 py-4 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl font-bold transition">
                    إلغاء
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function setQuickDate(days) {
    const date = new Date();
    date.setDate(date.getDate() + days);
    document.getElementById('bulkExpiryDate').value = date.toISOString().split('T')[0];
}

// Backup Modal Functions
function openBackupModal() {
    document.getElementById('umBackupModal').classList.remove('hidden');
}

function closeBackupModal() {
    document.getElementById('umBackupModal').classList.add('hidden');
}

// Export/Backup UserManager
function exportUmBackup() {
    const routerId = document.getElementById('umBackupRouter').value;
    const btn = document.querySelector('#umBackupModal button[onclick="exportUmBackup()"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i> جاري التصدير...';
    btn.disabled = true;
    
    fetch(`{{ url('usermanager/backup/export') }}?router_id=${routerId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const blob = new Blob([JSON.stringify(data.backup, null, 2)], { type: 'application/json' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `usermanager_backup_${data.router_name}_${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                a.remove();
                
                showToast(`تم تصدير ${data.backup.users.length} مستخدم بنجاح`, 'success');
            } else {
                showToast(data.message || 'فشل التصدير', 'error');
            }
        })
        .catch(err => {
            showToast('حدث خطأ في الاتصال', 'error');
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
}

// Import/Restore UserManager
function importUmBackup() {
    document.getElementById('umBackupFile').click();
}

function handleUmBackupFile(input) {
    if (!input.files || !input.files[0]) return;
    
    const file = input.files[0];
    const reader = new FileReader();
    
    reader.onload = function(e) {
        try {
            const backup = JSON.parse(e.target.result);
            
            if (!backup.users || !Array.isArray(backup.users)) {
                showToast('ملف غير صالح', 'error');
                return;
            }
            
            const count = backup.users.length;
            const routerName = backup.router_name || 'غير معروف';
            const backupDate = backup.backup_date || 'غير معروف';
            
            if (confirm(`هل تريد استعادة ${count} مستخدم من نسخة "${routerName}" بتاريخ ${backupDate}؟\n\nسيتم إضافتهم للراوتر المحدد.`)) {
                restoreUmBackup(backup);
            }
        } catch (err) {
            showToast('ملف غير صالح - يجب أن يكون JSON', 'error');
        }
    };
    
    reader.readAsText(file);
    input.value = '';
}

function restoreUmBackup(backup) {
    const routerId = document.getElementById('umBackupRouter').value;
    const btn = document.querySelector('#umBackupModal button[onclick="importUmBackup()"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i> جاري الاستعادة...';
    btn.disabled = true;
    
    fetch('{{ route("usermanager.backup.import") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            router_id: routerId,
            backup: backup
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            closeBackupModal();
            setTimeout(() => window.location.replace(window.location.pathname + '?restore=' + Date.now()), 1500);
        } else {
            showToast(data.message || 'فشل الاستعادة', 'error');
        }
    })
    .catch(err => {
        showToast('حدث خطأ في الاتصال', 'error');
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}
</script>

<!-- UserManager Backup Modal -->
<div id="umBackupModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden">
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fas fa-database"></i>
                نسخ احتياطي - يوزر مانجر
            </h3>
            <button onclick="closeBackupModal()" class="text-white/80 hover:text-white transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-router text-blue-500 ml-1"></i>
                    اختر الراوتر
                </label>
                <select id="umBackupRouter" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition">
                    @foreach($routers as $r)
                        <option value="{{ $r->id }}">{{ $r->name }}</option>
                    @endforeach
                </select>
            </div>
            
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-800">
                <div class="flex items-start gap-2">
                    <i class="fas fa-info-circle mt-0.5"></i>
                    <div>
                        <p class="font-semibold mb-1">معلومات:</p>
                        <ul class="list-disc list-inside space-y-1 text-xs">
                            <li>النسخ الاحتياطي يشمل: اسم المستخدم، كلمة المرور، الباقة، الاستهلاك</li>
                            <li>الاستعادة تضيف المستخدمين للراوتر مع نفس البيانات</li>
                            <li>مفيد قبل فورمات الراوتر</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <input type="file" id="umBackupFile" accept=".json" class="hidden" onchange="handleUmBackupFile(this)">
            
            <div class="grid grid-cols-2 gap-3">
                <button onclick="exportUmBackup()"
                    class="flex items-center justify-center gap-2 px-4 py-3 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white rounded-xl font-medium transition shadow-md">
                    <i class="fas fa-download"></i>
                    تصدير نسخة
                </button>
                <button onclick="importUmBackup()"
                    class="flex items-center justify-center gap-2 px-4 py-3 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white rounded-xl font-medium transition shadow-md">
                    <i class="fas fa-upload"></i>
                    استعادة نسخة
                </button>
            </div>
        </div>
        
        <div class="bg-gray-50 px-6 py-3 border-t">
            <button onclick="closeBackupModal()"
                class="w-full px-4 py-2 text-gray-600 hover:text-gray-800 font-medium transition">
                إغلاق
            </button>
        </div>
    </div>
</div>

@endpush
@endsection
