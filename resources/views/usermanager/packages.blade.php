@extends('layouts.app')

@section('title', 'إدارة الباقات - ' . $router->name)

@section('content')
<div class="mb-6">
    <div class="flex flex-wrap justify-between items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                <i class="fas fa-cubes text-blue-600 ml-2"></i>
                إدارة الباقات
            </h1>
            <p class="text-gray-600">{{ $router->name }} - إنشاء وإدارة باقات UserManager</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('usermanager.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition">
                <i class="fas fa-arrow-right ml-1"></i> رجوع
            </a>
            <button onclick="openQuickProfilesModal()" class="bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white px-4 py-2 rounded-lg transition shadow-md">
                <i class="fas fa-magic ml-1"></i> إنشاء باقات سريعة
            </button>
            <button onclick="openCreateGroupModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition">
                <i class="fas fa-users-cog ml-1"></i> إنشاء مجموعة
            </button>
            <button onclick="openCreateModal()" class="bg-gradient-to-l from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-5 py-2 rounded-lg shadow-lg transition transform hover:scale-105">
                <i class="fas fa-plus ml-1"></i> إنشاء باقة جديدة
            </button>
        </div>
    </div>
</div>

@php
    $profiles = $profiles ?? [];
    $limitations = $limitations ?? [];
    $userGroups = $userGroups ?? [];
    $limitationsByName = collect($limitations)->keyBy('name');
    
    // التحقق من وجود بروفايل التقييد
    $hasThrottledProfile = collect($profiles)->contains(function($p) {
        return ($p['name'] ?? '') === 'throttled';
    });
    
    // تصنيف الباقات حسب السرعة
    $categorizedPackages = collect($profiles)->map(function($profile) use ($limitationsByName) {
        $limitation = $limitationsByName->get($profile['name'] ?? '') ?? null;
        $speed = 0;
        if ($limitation && !empty($limitation['rate-limit'])) {
            preg_match('/(\d+)/', $limitation['rate-limit'], $matches);
            $speed = isset($matches[1]) ? (int)$matches[1] : 0;
        }
        return [
            'profile' => $profile,
            'limitation' => $limitation,
            'speed' => $speed,
            'price' => (int)($profile['price'] ?? 0),
        ];
    })->sortByDesc('speed')->groupBy(function($item) {
        $speed = $item['speed'];
        if ($speed >= 100) return 'فائق السرعة (100M+)';
        if ($speed >= 50) return 'سريع جداً (50-99M)';
        if ($speed >= 20) return 'سريع (20-49M)';
        if ($speed >= 10) return 'متوسط (10-19M)';
        if ($speed > 0) return 'أساسي (أقل من 10M)';
        return 'غير مصنف';
    });
    
    $categoryColors = [
        'فائق السرعة (100M+)' => 'from-purple-500 to-indigo-600',
        'سريع جداً (50-99M)' => 'from-blue-500 to-cyan-600',
        'سريع (20-49M)' => 'from-green-500 to-teal-600',
        'متوسط (10-19M)' => 'from-yellow-500 to-orange-600',
        'أساسي (أقل من 10M)' => 'from-gray-500 to-gray-600',
        'غير مصنف' => 'from-gray-400 to-gray-500',
    ];
    
    $categoryIcons = [
        'فائق السرعة (100M+)' => 'fa-rocket',
        'سريع جداً (50-99M)' => 'fa-bolt',
        'سريع (20-49M)' => 'fa-tachometer-alt',
        'متوسط (10-19M)' => 'fa-gauge-high',
        'أساسي (أقل من 10M)' => 'fa-wifi',
        'غير مصنف' => 'fa-box',
    ];
@endphp

<!-- بطاقة إعدادات نظام تقييد الاستهلاك -->
<div class="bg-gradient-to-r from-rose-500 to-orange-500 rounded-xl shadow-lg p-5 mb-6 text-white">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-tachometer-alt text-3xl"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold">نظام تقييد الاستهلاك (Throttling)</h3>
                <p class="text-white/80 text-sm">عند تجاوز المشترك حد الجيجات يتم تقييد سرعته تلقائياً</p>
            </div>
        </div>
        <div class="flex flex-wrap gap-3">
            @if($hasThrottledProfile)
                <div class="flex items-center gap-2 bg-white/20 px-4 py-2 rounded-lg">
                    <i class="fas fa-check-circle text-green-300"></i>
                    <span>بروفايل التقييد موجود</span>
                </div>
            @else
                <button onclick="createThrottledProfile()" id="createThrottleBtn"
                        class="bg-white text-rose-600 hover:bg-rose-50 px-5 py-2 rounded-lg font-medium transition shadow-lg">
                    <i class="fas fa-plus-circle ml-1"></i> إنشاء بروفايل التقييد
                </button>
            @endif
            <button onclick="showThrottleInfo()" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition">
                <i class="fas fa-info-circle ml-1"></i> كيف يعمل؟
            </button>
        </div>
    </div>
    
    <!-- معلومات سريعة -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 pt-4 border-t border-white/20">
        <div class="text-center">
            <p class="text-2xl font-bold">STOP</p>
            <p class="text-white/70 text-xs">باقة التقييد</p>
        </div>
        <div class="text-center">
            <p class="text-2xl font-bold">5 دق</p>
            <p class="text-white/70 text-xs">فترة الفحص</p>
        </div>
        <div class="text-center">
            <p class="text-2xl font-bold">تلقائي</p>
            <p class="text-white/70 text-xs">رفع التقييد عند التجديد</p>
        </div>
        <div class="text-center">
            <p class="text-2xl font-bold">مركزي</p>
            <p class="text-white/70 text-xs">التحكم من السيرفر</p>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-4 text-center text-white transform hover:scale-105 transition">
        <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-2">
            <i class="fas fa-box text-2xl"></i>
        </div>
        <p class="text-3xl font-bold">{{ count($profiles) }}</p>
        <p class="text-blue-100 text-sm">إجمالي الباقات</p>
    </div>
    <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-4 text-center text-white transform hover:scale-105 transition">
        <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-2">
            <i class="fas fa-sliders-h text-2xl"></i>
        </div>
        <p class="text-3xl font-bold">{{ count($limitations) }}</p>
        <p class="text-purple-100 text-sm">القيود المتوفرة</p>
    </div>
    <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl shadow-lg p-4 text-center text-white transform hover:scale-105 transition">
        <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-2">
            <i class="fas fa-users text-2xl"></i>
        </div>
        <p class="text-3xl font-bold">{{ count($userGroups) }}</p>
        <p class="text-indigo-100 text-sm">مجموعات المستخدمين</p>
    </div>
    <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl shadow-lg p-4 text-center text-white transform hover:scale-105 transition">
        <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-2">
            <i class="fas fa-server text-2xl"></i>
        </div>
        <p class="text-xl font-bold truncate">{{ $router->name }}</p>
        <p class="text-emerald-100 text-sm">الراوتر الحالي</p>
    </div>
</div>

<!-- View Toggle & Search -->
<div class="bg-white rounded-xl shadow-sm p-4 mb-6">
    <div class="flex flex-wrap gap-3 items-center justify-between">
        <div class="flex flex-wrap gap-2">
            <button class="tab-btn px-4 py-2 rounded-lg bg-blue-600 text-white transition" data-tab="tab-cards">
                <i class="fas fa-th-large ml-1"></i> عرض البطاقات
            </button>
            <button class="tab-btn px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 transition" data-tab="tab-packages">
                <i class="fas fa-table ml-1"></i> عرض الجدول
            </button>
            <button class="tab-btn px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 transition" data-tab="tab-profiles">Profiles</button>
            <button class="tab-btn px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 transition" data-tab="tab-limitations">Limitations</button>
            <button class="tab-btn px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 transition" data-tab="tab-groups">User Groups</button>
        </div>
        <div class="flex gap-2 items-center">
            <select id="sortPackages" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                <option value="speed-desc">ترتيب: الأسرع أولاً</option>
                <option value="speed-asc">ترتيب: الأبطأ أولاً</option>
                <option value="price-desc">ترتيب: الأغلى أولاً</option>
                <option value="price-asc">ترتيب: الأرخص أولاً</option>
                <option value="name-asc">ترتيب: أبجدي</option>
            </select>
            <div class="relative min-w-[200px]">
                <input type="text" id="packageSearch" placeholder="بحث..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
            </div>
        </div>
    </div>
</div>

<!-- Cards View (New Default) -->
<div id="tab-cards" class="tab-panel mb-6">
    @forelse($categorizedPackages as $category => $packages)
        <div class="mb-8 package-category" data-category="{{ $category }}">
            <!-- Category Header -->
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-lg bg-gradient-to-br {{ $categoryColors[$category] ?? 'from-gray-500 to-gray-600' }} flex items-center justify-center shadow-lg">
                    <i class="fas {{ $categoryIcons[$category] ?? 'fa-box' }} text-white"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-800">{{ $category }}</h3>
                    <p class="text-sm text-gray-500">{{ $packages->count() }} باقة</p>
                </div>
            </div>
            
            <!-- Packages Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($packages as $item)
                    @php
                        $profile = $item['profile'];
                        $limitation = $item['limitation'];
                    @endphp
                    <div class="package-card bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-lg transition transform hover:-translate-y-1"
                         data-name="{{ strtolower($profile['name'] ?? '') }}"
                         data-speed="{{ $item['speed'] }}"
                         data-price="{{ $item['price'] }}">
                        <!-- Card Header -->
                        <div class="bg-gradient-to-l {{ $categoryColors[$category] ?? 'from-gray-500 to-gray-600' }} p-4 text-white">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="font-bold text-lg">{{ $profile['name'] ?? '-' }}</h4>
                                    <p class="text-white/80 text-sm">{{ $profile['validity'] ?? 'غير محدد' }}</p>
                                </div>
                                <div class="text-left">
                                    <p class="text-2xl font-bold">{{ number_format($profile['price'] ?? 0) }}</p>
                                    <p class="text-white/80 text-xs">ل.س</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Card Body -->
                        <div class="p-4">
                            <div class="space-y-3">
                                <!-- Speed -->
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500 text-sm"><i class="fas fa-tachometer-alt ml-1"></i> السرعة</span>
                                    @if($limitation && !empty($limitation['rate-limit']))
                                        <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-medium">{{ $limitation['rate-limit'] }}</span>
                                    @else
                                        <span class="text-gray-400 text-sm">غير محدد</span>
                                    @endif
                                </div>
                                
                                <!-- Data Limit -->
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500 text-sm"><i class="fas fa-database ml-1"></i> البيانات</span>
                                    @if($limitation && !empty($limitation['transfer-limit']) && $limitation['transfer-limit'] != '0')
                                        <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-sm font-medium">{{ formatBytes($limitation['transfer-limit']) }}</span>
                                    @else
                                        <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-medium">غير محدود</span>
                                    @endif
                                </div>
                                
                                <!-- Shared Users -->
                                @if($profile['shared-users'] ?? false)
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500 text-sm"><i class="fas fa-users ml-1"></i> الأجهزة</span>
                                    <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-sm font-medium">{{ $profile['shared-users'] }}</span>
                                </div>
                                @endif
                            </div>
                        </div>
                        
                        <!-- Card Footer -->
                        <div class="px-4 py-3 bg-gray-50 border-t flex justify-between items-center">
                            <button onclick="editPackage('{{ $profile['.id'] ?? '' }}', '{{ $limitation['.id'] ?? '' }}')"
                                    class="text-blue-600 hover:text-blue-800 text-sm font-medium transition">
                                <i class="fas fa-edit ml-1"></i> تعديل
                            </button>
                            <button onclick="deletePackagePair('{{ $profile['.id'] ?? '' }}','{{ $limitation['.id'] ?? '' }}')"
                                    class="text-red-500 hover:text-red-700 text-sm font-medium transition">
                                <i class="fas fa-trash ml-1"></i> حذف
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="bg-white rounded-xl shadow-sm p-12 text-center">
            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-box-open text-4xl text-gray-400"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-700 mb-2">لا توجد باقات</h3>
            <p class="text-gray-500 mb-4">لم يتم إنشاء أي باقات بعد</p>
            <button onclick="openCreateModal()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg transition">
                <i class="fas fa-plus ml-1"></i> إنشاء أول باقة
            </button>
        </div>
    @endforelse
</div>

<!-- Combined Packages Table -->
<div id="tab-packages" class="tab-panel bg-white rounded-xl shadow-sm overflow-hidden mb-6 hidden">
    <div class="p-4 border-b bg-gradient-to-l from-blue-500 to-blue-600 flex justify-between items-center">
        <h3 class="text-lg font-bold text-white"><i class="fas fa-table ml-2"></i> جدول الباقات</h3>
        <span class="text-sm text-blue-100">عرض كامل للبيانات</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full table-search">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-right text-sm font-bold text-gray-600">الاسم</th>
                    <th class="px-4 py-3 text-right text-sm font-bold text-gray-600">الصلاحية</th>
                    <th class="px-4 py-3 text-right text-sm font-bold text-gray-600">السعر</th>
                    <th class="px-4 py-3 text-right text-sm font-bold text-gray-600">السرعة</th>
                    <th class="px-4 py-3 text-right text-sm font-bold text-gray-600">حد البيانات</th>
                    <th class="px-4 py-3 text-center text-sm font-bold text-gray-600">الإجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($profiles as $profile)
                    @php
                        $limitation = $limitationsByName->get($profile['name'] ?? '') ?? null;
                    @endphp
                    <tr class="hover:bg-blue-50 transition">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center shadow">
                                    <i class="fas fa-box text-white text-sm"></i>
                                </div>
                                <span class="font-bold text-gray-800">{{ $profile['name'] ?? '-' }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-sm">{{ $profile['validity'] ?? 'غير محدد' }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="font-bold text-green-600">{{ number_format($profile['price'] ?? 0) }} ل.س</span>
                        </td>
                        <td class="px-4 py-3">
                            @if($limitation && !empty($limitation['rate-limit']))
                                <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-medium">{{ $limitation['rate-limit'] }}</span>
                            @else
                                <span class="text-gray-400">غير محدد</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($limitation && !empty($limitation['transfer-limit']) && $limitation['transfer-limit'] != '0')
                                <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-sm font-medium">{{ formatBytes($limitation['transfer-limit']) }}</span>
                            @else
                                <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm">غير محدود</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex justify-center gap-1">
                                <button onclick="editPackage('{{ $profile['.id'] ?? '' }}', '{{ $limitation['.id'] ?? '' }}')"
                                        class="p-2 text-blue-600 hover:bg-blue-100 rounded-lg transition" title="تعديل">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deletePackagePair('{{ $profile['.id'] ?? '' }}','{{ $limitation['.id'] ?? '' }}')"
                                        class="p-2 text-red-600 hover:bg-red-100 rounded-lg transition" title="حذف">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                            <i class="fas fa-box-open text-4xl mb-2 text-gray-300"></i>
                            <p>لا توجد باقات</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- User Groups Table -->
<div id="tab-groups" class="tab-panel bg-white rounded-xl shadow-sm overflow-hidden mb-6 hidden">
    <div class="p-4 border-b bg-gradient-to-l from-indigo-500 to-indigo-600">
        <h3 class="text-lg font-bold text-white"><i class="fas fa-users-cog ml-2"></i> مجموعات المستخدمين (User Groups)</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full table-search">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-right text-sm font-bold text-gray-600">الاسم</th>
                    <th class="px-4 py-3 text-right text-sm font-bold text-gray-600">Outer Auths</th>
                    <th class="px-4 py-3 text-right text-sm font-bold text-gray-600">Inner Auths</th>
                    <th class="px-4 py-3 text-center text-sm font-bold text-gray-600">الإجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($userGroups as $group)
                <tr class="hover:bg-indigo-50 transition">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-lg flex items-center justify-center shadow">
                                <i class="fas fa-users text-white text-sm"></i>
                            </div>
                            <span class="font-bold text-gray-800">{{ $group['name'] ?? '-' }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        @if(isset($group['outer-auths']) && $group['outer-auths'])
                            <div class="flex flex-wrap gap-1">
                                @foreach(explode(',', $group['outer-auths']) as $auth)
                                    <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-medium">{{ trim($auth) }}</span>
                                @endforeach
                            </div>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        @if(isset($group['inner-auths']) && $group['inner-auths'])
                            <div class="flex flex-wrap gap-1">
                                @foreach(explode(',', $group['inner-auths']) as $auth)
                                    <span class="px-2 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-medium">{{ trim($auth) }}</span>
                                @endforeach
                            </div>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        <button onclick="deleteUserGroup('{{ $group['.id'] ?? '' }}')" 
                                class="p-2 text-red-600 hover:bg-red-100 rounded-lg transition" title="حذف">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                        <i class="fas fa-users-slash text-4xl mb-2 text-gray-300"></i>
                        <p>لا توجد مجموعات</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Profiles Table -->
<div id="tab-profiles" class="tab-panel bg-white rounded-xl shadow-sm overflow-hidden mb-6 hidden">
    <div class="p-4 border-b bg-gradient-to-l from-blue-500 to-cyan-600">
        <h3 class="text-lg font-bold text-white"><i class="fas fa-layer-group ml-2"></i> الباقات (Profiles)</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full table-search">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-right text-sm font-bold text-gray-600">الاسم</th>
                    <th class="px-4 py-3 text-right text-sm font-bold text-gray-600">الصلاحية</th>
                    <th class="px-4 py-3 text-right text-sm font-bold text-gray-600">السعر</th>
                    <th class="px-4 py-3 text-center text-sm font-bold text-gray-600">الإجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($profiles as $profile)
                <tr class="hover:bg-cyan-50 transition">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-lg flex items-center justify-center shadow">
                                <i class="fas fa-box text-white text-sm"></i>
                            </div>
                            <span class="font-bold text-gray-800">{{ $profile['name'] ?? '-' }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-sm">{{ $profile['validity'] ?? 'غير محدد' }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="font-bold text-green-600">{{ number_format($profile['price'] ?? 0) }} ل.س</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <button onclick="deleteItem('profile', '{{ $profile['.id'] ?? '' }}')" 
                                class="p-2 text-red-600 hover:bg-red-100 rounded-lg transition" title="حذف">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                        <i class="fas fa-layer-group text-4xl mb-2 text-gray-300"></i>
                        <p>لا توجد باقات</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Limitations Table -->
<div id="tab-limitations" class="tab-panel bg-white rounded-xl shadow-sm overflow-hidden hidden">
    <div class="p-4 border-b bg-gradient-to-l from-purple-500 to-pink-600">
        <h3 class="text-lg font-bold text-white"><i class="fas fa-tachometer-alt ml-2"></i> القيود (Limitations)</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full table-search">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-right text-sm font-bold text-gray-600">الاسم</th>
                    <th class="px-4 py-3 text-right text-sm font-bold text-gray-600">السرعة</th>
                    <th class="px-4 py-3 text-right text-sm font-bold text-gray-600">حد البيانات</th>
                    <th class="px-4 py-3 text-center text-sm font-bold text-gray-600">الإجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($limitations as $limitation)
                <tr class="hover:bg-purple-50 transition">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-600 rounded-lg flex items-center justify-center shadow">
                                <i class="fas fa-sliders-h text-white text-sm"></i>
                            </div>
                            <span class="font-bold text-gray-800">{{ $limitation['name'] ?? '-' }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        @if(isset($limitation['rate-limit']) && $limitation['rate-limit'])
                            <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-medium">{{ $limitation['rate-limit'] }}</span>
                        @else
                            <span class="text-gray-400">غير محدد</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if(isset($limitation['transfer-limit']) && $limitation['transfer-limit'] && $limitation['transfer-limit'] != '0')
                            <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-sm font-medium">{{ formatBytes($limitation['transfer-limit']) }}</span>
                        @else
                            <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm">غير محدود</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        <button onclick="deleteItem('limitation', '{{ $limitation['.id'] ?? '' }}')" 
                                class="p-2 text-red-600 hover:bg-red-100 rounded-lg transition" title="حذف">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                        <i class="fas fa-sliders-h text-4xl mb-2 text-gray-300"></i>
                        <p>لا توجد قيود</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Create User Group Modal -->
<div id="createGroupModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto transform transition-all">
        <div class="p-6 border-b sticky top-0 bg-gradient-to-l from-indigo-500 to-indigo-600 rounded-t-2xl z-10">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-bold text-white"><i class="fas fa-users-cog ml-2"></i> إنشاء مجموعة مستخدمين</h3>
                <button onclick="closeCreateGroupModal()" class="text-white/80 hover:text-white transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        <form id="createGroupForm" class="p-6">
            <!-- Group Name -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">اسم المجموعة <span class="text-red-500">*</span></label>
                <input type="text" name="group_name" id="groupName" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                       placeholder="مثال: default">
            </div>

            <!-- Outer Auths Section -->
            <div class="mb-4 p-4 bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl border border-blue-200">
                <h4 class="font-bold text-blue-800 mb-3"><i class="fas fa-shield-alt ml-1"></i> Outer Auths (المصادقة الخارجية)</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <label class="flex items-center gap-2 cursor-pointer bg-white p-2 rounded-lg border border-blue-200 hover:border-blue-400 transition">
                        <input type="checkbox" name="outer_auths[]" value="pap" checked class="w-4 h-4 text-blue-600 rounded">
                        <span class="text-sm">PAP</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer bg-white p-2 rounded-lg border border-blue-200 hover:border-blue-400 transition">
                        <input type="checkbox" name="outer_auths[]" value="chap" checked class="w-4 h-4 text-blue-600 rounded">
                        <span class="text-sm">CHAP</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer bg-white p-2 rounded-lg border border-blue-200 hover:border-blue-400 transition">
                        <input type="checkbox" name="outer_auths[]" value="mschap1" checked class="w-4 h-4 text-blue-600 rounded">
                        <span class="text-sm">MSCHAP1</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer bg-white p-2 rounded-lg border border-blue-200 hover:border-blue-400 transition">
                        <input type="checkbox" name="outer_auths[]" value="mschap2" checked class="w-4 h-4 text-blue-600 rounded">
                        <span class="text-sm">MSCHAP2</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer bg-white p-2 rounded-lg border border-blue-200 hover:border-blue-400 transition">
                        <input type="checkbox" name="outer_auths[]" value="eap-tls" checked class="w-4 h-4 text-blue-600 rounded">
                        <span class="text-sm">EAP TLS</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer bg-white p-2 rounded-lg border border-blue-200 hover:border-blue-400 transition">
                        <input type="checkbox" name="outer_auths[]" value="eap-ttls" checked class="w-4 h-4 text-blue-600 rounded">
                        <span class="text-sm">EAP TTLS</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer bg-white p-2 rounded-lg border border-blue-200 hover:border-blue-400 transition">
                        <input type="checkbox" name="outer_auths[]" value="eap-peap" class="w-4 h-4 text-blue-600 rounded">
                        <span class="text-sm">EAP PEAP</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer bg-white p-2 rounded-lg border border-blue-200 hover:border-blue-400 transition">
                        <input type="checkbox" name="outer_auths[]" value="eap-mschap2" class="w-4 h-4 text-blue-600 rounded">
                        <span class="text-sm">EAP MSCHAP2</span>
                    </label>
                </div>
            </div>

            <!-- Inner Auths Section -->
            <div class="mb-4 p-4 bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl border border-purple-200">
                <h4 class="font-bold text-purple-800 mb-3"><i class="fas fa-lock ml-1"></i> Inner Auths (المصادقة الداخلية)</h4>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    <label class="flex items-center gap-2 cursor-pointer bg-white p-2 rounded-lg border border-purple-200 hover:border-purple-400 transition">
                        <input type="checkbox" name="inner_auths[]" value="ttls-pap" checked class="w-4 h-4 text-purple-600 rounded">
                        <span class="text-sm">TTLS PAP</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer bg-white p-2 rounded-lg border border-purple-200 hover:border-purple-400 transition">
                        <input type="checkbox" name="inner_auths[]" value="ttls-chap" checked class="w-4 h-4 text-purple-600 rounded">
                        <span class="text-sm">TTLS CHAP</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer bg-white p-2 rounded-lg border border-purple-200 hover:border-purple-400 transition">
                        <input type="checkbox" name="inner_auths[]" value="ttls-mschap1" checked class="w-4 h-4 text-purple-600 rounded">
                        <span class="text-sm">TTLS MSCHAP1</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer bg-white p-2 rounded-lg border border-purple-200 hover:border-purple-400 transition">
                        <input type="checkbox" name="inner_auths[]" value="ttls-mschap2" checked class="w-4 h-4 text-purple-600 rounded">
                        <span class="text-sm">TTLS MSCHAP2</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer bg-white p-2 rounded-lg border border-purple-200 hover:border-purple-400 transition">
                        <input type="checkbox" name="inner_auths[]" value="peap-mschap2" class="w-4 h-4 text-purple-600 rounded">
                        <span class="text-sm">PEAP MSCHAP2</span>
                    </label>
                </div>
            </div>

            <!-- Attributes Section -->
            <div class="mb-4 p-4 bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl border border-gray-200">
                <h4 class="font-bold text-gray-800 mb-3"><i class="fas fa-tags ml-1"></i> Attributes (اختياري)</h4>
                <select id="groupAttributes" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 transition">
                    <option value="">-- بدون Attribute --</option>
                    @foreach($attributes ?? [] as $attr)
                        <option value="{{ $attr['name'] ?? '' }}">{{ $attr['name'] ?? '' }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-2">يمكنك اختيار Attribute محدد مسبقاً لربطه بالمجموعة</p>
            </div>
        </form>
        <div class="p-6 border-t bg-gray-50 rounded-b-2xl flex justify-end gap-3">
            <button onclick="closeCreateGroupModal()" class="px-5 py-2.5 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition font-medium">
                إلغاء
            </button>
            <button onclick="createUserGroup()" id="createGroupBtn" class="px-5 py-2.5 bg-gradient-to-l from-indigo-500 to-indigo-600 text-white rounded-xl hover:from-indigo-600 hover:to-indigo-700 transition font-medium shadow-lg">
                <i class="fas fa-plus ml-1"></i> إنشاء المجموعة
            </button>
        </div>
    </div>
</div>

<!-- Create Package Modal -->
<div id="createModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto transform transition-all">
        <div class="p-6 border-b sticky top-0 bg-gradient-to-l from-green-500 to-emerald-600 rounded-t-2xl">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-bold text-white"><i class="fas fa-box-open ml-2"></i> إنشاء باقة جديدة</h3>
                <button onclick="closeCreateModal()" class="text-white/80 hover:text-white transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        <form id="createForm" class="p-6">
            <!-- Package Name -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">اسم الباقة <span class="text-red-500">*</span></label>
                <input type="text" name="name" id="packageName" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition"
                       placeholder="مثال: Gold_50M">
            </div>

            <!-- Live Preview -->
            <div class="mb-4 p-4 rounded-xl border-2 border-dashed border-green-300 bg-gradient-to-br from-green-50 to-emerald-50">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm text-gray-500">معاينة الباقة</p>
                        <p id="previewName" class="text-lg font-bold text-gray-800">—</p>
                    </div>
                    <div class="text-left">
                        <p id="previewPrice" class="text-2xl font-bold text-green-600">0 ل.س</p>
                        <p id="previewValidity" class="text-xs text-gray-500">صلاحية: غير محدد</p>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    <span id="previewSpeed" class="px-3 py-1.5 bg-green-100 text-green-700 rounded-full font-medium">سرعة: —</span>
                    <span id="previewData" class="px-3 py-1.5 bg-yellow-100 text-yellow-700 rounded-full font-medium">بيانات: غير محدود</span>
                    <span id="previewShared" class="px-3 py-1.5 bg-purple-100 text-purple-700 rounded-full font-medium">أجهزة: 1</span>
                </div>
            </div>

            <!-- Speed Section -->
            <div class="mb-4 p-4 bg-gradient-to-br from-green-50 to-teal-50 rounded-xl border border-green-200">
                <h4 class="font-bold text-green-800 mb-3"><i class="fas fa-tachometer-alt ml-1"></i> السرعة</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">سرعة التحميل <span class="text-red-500">*</span></label>
                        <div class="flex">
                            <input type="number" name="download_speed" id="downloadSpeed" required min="1"
                                   class="flex-1 px-3 py-2.5 border border-gray-300 rounded-r-xl focus:ring-2 focus:ring-green-500 transition"
                                   placeholder="50">
                            <select name="speed_unit" id="speedUnit" class="px-3 py-2.5 border border-gray-300 rounded-l-xl bg-gray-50 focus:ring-2 focus:ring-green-500">
                                <option value="M">Mbps</option>
                                <option value="K">Kbps</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">سرعة الرفع <span class="text-red-500">*</span></label>
                        <input type="number" name="upload_speed" id="uploadSpeed" required min="1"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 transition"
                               placeholder="50">
                    </div>
                </div>
            </div>

            <!-- Data Limit Section -->
            <div class="mb-4 p-4 bg-gradient-to-br from-yellow-50 to-orange-50 rounded-xl border border-yellow-200">
                <h4 class="font-bold text-yellow-800 mb-3"><i class="fas fa-database ml-1"></i> حد البيانات</h4>
                <div class="flex items-center gap-2 mb-3">
                    <input type="checkbox" id="dataUnlimited" checked class="w-5 h-5 text-yellow-600 rounded focus:ring-yellow-500">
                    <label for="dataUnlimited" class="text-sm text-gray-700 font-medium">غير محدود</label>
                </div>
                <div class="flex gap-4">
                    <div class="flex-1">
                        <label class="block text-sm text-gray-600 mb-1">الكمية</label>
                        <input type="number" name="data_limit" id="dataLimit" min="0"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-yellow-500 transition"
                               placeholder="100">
                    </div>
                    <div class="w-24">
                        <label class="block text-sm text-gray-600 mb-1">الوحدة</label>
                        <select name="data_unit" id="dataUnit" class="w-full px-3 py-2.5 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-yellow-500">
                            <option value="GB">GB</option>
                            <option value="MB">MB</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Validity Section -->
            <div class="mb-4 p-4 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl border border-blue-200">
                <h4 class="font-bold text-blue-800 mb-3"><i class="fas fa-clock ml-1"></i> مدة الصلاحية</h4>
                <div class="flex items-center gap-2 mb-3">
                    <input type="checkbox" id="validityUnlimited" checked class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500">
                    <label for="validityUnlimited" class="text-sm text-gray-700 font-medium">غير محدد</label>
                </div>
                <div class="flex gap-4">
                    <div class="flex-1">
                        <label class="block text-sm text-gray-600 mb-1">المدة</label>
                        <input type="number" name="validity_value" id="validityValue" min="1"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 transition"
                               placeholder="30">
                    </div>
                    <div class="w-24">
                        <label class="block text-sm text-gray-600 mb-1">الوحدة</label>
                        <select name="validity_unit" id="validityUnit" class="w-full px-3 py-2.5 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-500">
                            <option value="d">يوم</option>
                            <option value="h">ساعة</option>
                            <option value="w">أسبوع</option>
                            <option value="m">شهر</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Additional Options -->
            <div class="mb-4 p-4 bg-gradient-to-br from-gray-50 to-slate-100 rounded-xl border border-gray-200">
                <h4 class="font-bold text-gray-800 mb-3"><i class="fas fa-cog ml-1"></i> خيارات إضافية</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">السعر (ل.س)</label>
                        <input type="number" name="price" id="price" min="0"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-gray-500 transition"
                               placeholder="0">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">عدد الأجهزة</label>
                        <input type="number" name="shared_users" id="sharedUsers" min="1" max="10" value="1"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-gray-500 transition">
                    </div>
                </div>
            </div>
        </form>
        <div class="p-6 border-t bg-gray-50 rounded-b-2xl flex justify-end gap-3">
            <button onclick="closeCreateModal()" class="px-5 py-2.5 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition font-medium">
                إلغاء
            </button>
            <button onclick="createPackage()" id="createBtn" class="px-5 py-2.5 bg-gradient-to-l from-green-500 to-emerald-600 text-white rounded-xl hover:from-green-600 hover:to-emerald-700 transition font-medium shadow-lg">
                <i class="fas fa-plus ml-1"></i> إنشاء الباقة
            </button>
        </div>
    </div>
</div>

<!-- Modal معلومات نظام التقييد -->
<div id="throttleInfoModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">
                <i class="fas fa-info-circle text-blue-600 ml-2"></i>
                كيف يعمل نظام تقييد الاستهلاك؟
            </h3>
            <button onclick="closeThrottleInfoModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div class="space-y-4">
            <div class="bg-blue-50 rounded-lg p-4">
                <h4 class="font-bold text-blue-800 mb-2"><i class="fas fa-cogs ml-1"></i> آلية العمل</h4>
                <ol class="list-decimal list-inside space-y-2 text-blue-700 text-sm">
                    <li>تحدد حد استهلاك (جيجات) لكل مشترك من صفحة تفاصيله</li>
                    <li>السيستم يراقب استهلاك كل مشترك كل 5 دقائق</li>
                    <li>عند تجاوز الحد → يتم تغيير بروفايله لـ "STOP" (سرعة 1k/1k)</li>
                    <li>عند تجديد الاشتراك → يتم تصفير الاستهلاك وإرجاع السرعة الأصلية</li>
                </ol>
            </div>
            
            <div class="bg-green-50 rounded-lg p-4">
                <h4 class="font-bold text-green-800 mb-2"><i class="fas fa-check-circle ml-1"></i> المميزات</h4>
                <ul class="space-y-1 text-green-700 text-sm">
                    <li>• تحكم مركزي من السيرفر</li>
                    <li>• لا حاجة لإنشاء باقات متعددة لكل حد استهلاك</li>
                    <li>• المشترك يبقى متصل لكن بسرعة محدودة</li>
                    <li>• يمكن تعديل الحد في أي وقت</li>
                </ul>
            </div>
            
            <div class="bg-yellow-50 rounded-lg p-4">
                <h4 class="font-bold text-yellow-800 mb-2"><i class="fas fa-exclamation-triangle ml-1"></i> ملاحظات</h4>
                <ul class="space-y-1 text-yellow-700 text-sm">
                    <li>• يجب إنشاء بروفايل "STOP" على الراوتر (زر أعلاه)</li>
                    <li>• سرعة التقييد الافتراضية: 1k/1k (يمكن تعديلها)</li>
                    <li>• يعمل فقط مع مشتركي UserManager</li>
                </ul>
            </div>
            
            <div class="bg-purple-50 rounded-lg p-4">
                <h4 class="font-bold text-purple-800 mb-2"><i class="fas fa-database ml-1"></i> تحديد حد لمشترك</h4>
                <p class="text-purple-700 text-sm">
                    اذهب لصفحة المشترك → اضغط "تحديد الجيجات" → أدخل الحد بالجيجابايت
                </p>
            </div>
        </div>
        
        <div class="mt-6 text-center">
            <button onclick="closeThrottleInfoModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-2 rounded-lg transition">
                فهمت
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
const routerId = {{ $router->id }};

// Throttle Profile Functions
function createThrottledProfile() {
    if (!confirm('هل تريد إنشاء بروفايل "STOP" على الراوتر؟\n\nهذا البروفايل يستخدم لتقييد سرعة المشتركين عند تجاوز حد الجيجات.\n\nالسرعة: 1k/1k')) {
        return;
    }
    
    const btn = document.getElementById('createThrottleBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i> جاري الإنشاء...';
    }
    
    fetch(`/usermanager/${routerId}/create-throttled-profile`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        }
    })
    .then(r => r.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            location.reload();
        } else if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus-circle ml-1"></i> إنشاء بروفايل التقييد';
        }
    })
    .catch(() => {
        alert('حدث خطأ في الاتصال');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus-circle ml-1"></i> إنشاء بروفايل التقييد';
        }
    });
}

function showThrottleInfo() {
    document.getElementById('throttleInfoModal').classList.remove('hidden');
    document.getElementById('throttleInfoModal').classList.add('flex');
}

function closeThrottleInfoModal() {
    document.getElementById('throttleInfoModal').classList.add('hidden');
    document.getElementById('throttleInfoModal').classList.remove('flex');
}

document.getElementById('throttleInfoModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeThrottleInfoModal();
});

// Tabs
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => {
            b.classList.remove('bg-blue-600', 'text-white');
            b.classList.add('bg-gray-100', 'text-gray-700');
        });
        btn.classList.add('bg-blue-600', 'text-white');
        btn.classList.remove('bg-gray-100', 'text-gray-700');

        document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.add('hidden'));
        const target = document.getElementById(btn.dataset.tab);
        if (target) target.classList.remove('hidden');

        // Apply search filter after tab switch
        if (searchInput && searchInput.value) {
            searchInput.dispatchEvent(new Event('input'));
        }
    });
});

// Search filter
const searchInput = document.getElementById('packageSearch');
if (searchInput) {
    searchInput.addEventListener('input', () => {
        const q = searchInput.value.trim().toLowerCase();
        
        // Filter cards view
        document.querySelectorAll('.package-card').forEach(card => {
            const name = card.dataset.name || '';
            card.style.display = name.includes(q) ? '' : 'none';
        });
        
        // Filter table rows
        document.querySelectorAll('.tab-panel:not(.hidden) .table-search tbody tr').forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(q) ? '' : 'none';
        });
        
        // Show/hide empty categories
        document.querySelectorAll('.package-category').forEach(category => {
            const visibleCards = category.querySelectorAll('.package-card:not([style*="display: none"])');
            category.style.display = visibleCards.length > 0 ? '' : 'none';
        });
    });
}

// Sort packages
const sortSelect = document.getElementById('sortPackages');
if (sortSelect) {
    sortSelect.addEventListener('change', () => {
        const sortBy = sortSelect.value;
        const cardsContainer = document.getElementById('tab-cards');
        if (!cardsContainer) return;
        
        const allCards = Array.from(document.querySelectorAll('.package-card'));
        
        allCards.sort((a, b) => {
            const speedA = parseInt(a.dataset.speed) || 0;
            const speedB = parseInt(b.dataset.speed) || 0;
            const priceA = parseInt(a.dataset.price) || 0;
            const priceB = parseInt(b.dataset.price) || 0;
            const nameA = a.dataset.name || '';
            const nameB = b.dataset.name || '';
            
            switch(sortBy) {
                case 'speed-desc': return speedB - speedA;
                case 'speed-asc': return speedA - speedB;
                case 'price-desc': return priceB - priceA;
                case 'price-asc': return priceA - priceB;
                case 'name-asc': return nameA.localeCompare(nameB);
                default: return 0;
            }
        });
        
        // Hide categories for custom sort
        document.querySelectorAll('.package-category').forEach(cat => cat.style.display = 'none');
        
        // Create sorted container
        let sortedContainer = document.getElementById('sorted-packages');
        if (!sortedContainer) {
            sortedContainer = document.createElement('div');
            sortedContainer.id = 'sorted-packages';
            sortedContainer.className = 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4';
            cardsContainer.appendChild(sortedContainer);
        }
        sortedContainer.innerHTML = '';
        sortedContainer.style.display = 'grid';
        
        allCards.forEach(card => {
            const clone = card.cloneNode(true);
            sortedContainer.appendChild(clone);
        });
    });
}

function openCreateModal() {
    document.getElementById('createModal').classList.remove('hidden');
    document.getElementById('createModal').classList.add('flex');
    document.getElementById('createForm').reset();
    document.getElementById('dataUnlimited').checked = true;
    document.getElementById('validityUnlimited').checked = true;
    toggleUnlimitedFields();
    updatePreview();
}

function editPackage(profileId, limitationId) {
    // يمكن إضافة وظيفة التعديل هنا
    alert('ميزة التعديل قيد التطوير');
}

function closeCreateModal() {
    document.getElementById('createModal').classList.add('hidden');
    document.getElementById('createModal').classList.remove('flex');
}

function createPackage() {
    const form = document.getElementById('createForm');
    const name = document.getElementById('packageName').value.trim();
    const downloadSpeed = document.getElementById('downloadSpeed').value;
    const uploadSpeed = document.getElementById('uploadSpeed').value;
    
    if (!name || !downloadSpeed || !uploadSpeed) {
        alert('الرجاء ملء الحقول المطلوبة');
        return;
    }

    const btn = document.getElementById('createBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i> جاري الإنشاء...';

    const data = {
        name: name,
        download_speed: downloadSpeed,
        upload_speed: uploadSpeed,
        speed_unit: document.getElementById('speedUnit').value,
        data_limit: document.getElementById('dataUnlimited').checked ? null : (document.getElementById('dataLimit').value || null),
        data_unit: document.getElementById('dataUnit').value,
        validity_value: document.getElementById('validityUnlimited').checked ? null : (document.getElementById('validityValue').value || null),
        validity_unit: document.getElementById('validityUnit').value,
        price: document.getElementById('price').value || 0,
        shared_users: document.getElementById('sharedUsers').value || 1,
    };

    fetch(`/usermanager/${routerId}/packages`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(response => {
        alert(response.message);
        if (response.success) {
            location.reload();
        }
    })
    .catch(err => {
        alert('حدث خطأ أثناء الإنشاء');
        console.error(err);
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-plus ml-1"></i> إنشاء الباقة';
    });
}

function deleteItem(type, id) {
    if (!id) {
        alert('معرف غير صالح');
        return;
    }
    
    if (!confirm('هل أنت متأكد من الحذف؟')) return;

    const data = {};
    if (type === 'profile') {
        data.profile_id = id;
    } else {
        data.limitation_id = id;
    }

    fetch(`/usermanager/${routerId}/packages`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(response => {
        alert(response.message);
        if (response.success) {
            location.reload();
        }
    })
    .catch(err => {
        alert('حدث خطأ أثناء الحذف');
        console.error(err);
    });
}

function deletePackagePair(profileId, limitationId) {
    if (!profileId && !limitationId) {
        alert('معرف غير صالح');
        return;
    }
    if (!confirm('هل تريد حذف الباقة بالكامل؟')) return;

    const data = { profile_id: profileId || null, limitation_id: limitationId || null };

    fetch(`/usermanager/${routerId}/packages`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(response => {
        alert(response.message);
        if (response.success) {
            location.reload();
        }
    })
    .catch(err => {
        alert('حدث خطأ أثناء الحذف');
        console.error(err);
    });
}

// Live preview + helpers
['packageName','downloadSpeed','uploadSpeed','speedUnit','dataLimit','dataUnit','validityValue','validityUnit','price','sharedUsers','dataUnlimited','validityUnlimited']
    .forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', () => { toggleUnlimitedFields(); updatePreview(); });
        if (el && (el.type === 'checkbox' || el.tagName === 'SELECT')) {
            el.addEventListener('change', () => { toggleUnlimitedFields(); updatePreview(); });
        }
    });

function toggleUnlimitedFields() {
    const dataUnlimited = document.getElementById('dataUnlimited');
    const dataLimit = document.getElementById('dataLimit');
    const dataUnit = document.getElementById('dataUnit');
    if (dataUnlimited && dataLimit && dataUnit) {
        dataLimit.disabled = dataUnlimited.checked;
        dataUnit.disabled = dataUnlimited.checked;
    }

    const validityUnlimited = document.getElementById('validityUnlimited');
    const validityValue = document.getElementById('validityValue');
    const validityUnit = document.getElementById('validityUnit');
    if (validityUnlimited && validityValue && validityUnit) {
        validityValue.disabled = validityUnlimited.checked;
        validityUnit.disabled = validityUnlimited.checked;
    }
}

function updatePreview() {
    const name = document.getElementById('packageName')?.value || '—';
    const price = document.getElementById('price')?.value || 0;
    const shared = document.getElementById('sharedUsers')?.value || 1;
    const down = document.getElementById('downloadSpeed')?.value || '—';
    const up = document.getElementById('uploadSpeed')?.value || '—';
    const unit = document.getElementById('speedUnit')?.value || 'M';
    const dataUnlimited = document.getElementById('dataUnlimited')?.checked;
    const dataLimit = document.getElementById('dataLimit')?.value || 0;
    const dataUnit = document.getElementById('dataUnit')?.value || 'GB';
    const validityUnlimited = document.getElementById('validityUnlimited')?.checked;
    const validityValue = document.getElementById('validityValue')?.value || 0;
    const validityUnit = document.getElementById('validityUnit')?.value || 'd';

    const previewName = document.getElementById('previewName');
    const previewPrice = document.getElementById('previewPrice');
    const previewValidity = document.getElementById('previewValidity');
    const previewSpeed = document.getElementById('previewSpeed');
    const previewData = document.getElementById('previewData');
    const previewShared = document.getElementById('previewShared');

    if (previewName) previewName.textContent = name || '—';
    if (previewPrice) previewPrice.textContent = `${price || 0} ل.س`;
    if (previewValidity) previewValidity.textContent = validityUnlimited ? 'صلاحية: غير محدد' : `صلاحية: ${validityValue || 0}${validityUnit}`;
    if (previewSpeed) previewSpeed.textContent = `سرعة: ${up}${unit}/${down}${unit}`;
    if (previewData) previewData.textContent = dataUnlimited ? 'بيانات: غير محدود' : `بيانات: ${dataLimit || 0}${dataUnit}`;
    if (previewShared) previewShared.textContent = `أجهزة: ${shared}`;
}

// User Group Functions
function openCreateGroupModal() {
    document.getElementById('createGroupModal').classList.remove('hidden');
    document.getElementById('createGroupModal').classList.add('flex');
    document.getElementById('createGroupForm').reset();
    // Check all default checkboxes
    document.querySelectorAll('#createGroupForm input[type="checkbox"]').forEach(cb => {
        if (['pap', 'chap', 'mschap1', 'mschap2', 'eap-tls', 'eap-ttls', 'ttls-pap', 'ttls-chap', 'ttls-mschap1', 'ttls-mschap2'].includes(cb.value)) {
            cb.checked = true;
        }
    });
}

function closeCreateGroupModal() {
    document.getElementById('createGroupModal').classList.add('hidden');
    document.getElementById('createGroupModal').classList.remove('flex');
}

function createUserGroup() {
    const name = document.getElementById('groupName').value.trim();
    
    if (!name) {
        alert('الرجاء إدخال اسم المجموعة');
        return;
    }

    const btn = document.getElementById('createGroupBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i> جاري الإنشاء...';

    // Collect outer auths
    const outerAuths = [];
    document.querySelectorAll('input[name="outer_auths[]"]:checked').forEach(cb => {
        outerAuths.push(cb.value);
    });

    // Collect inner auths
    const innerAuths = [];
    document.querySelectorAll('input[name="inner_auths[]"]:checked').forEach(cb => {
        innerAuths.push(cb.value);
    });

    const data = {
        name: name,
        outer_auths: outerAuths,
        inner_auths: innerAuths,
        attributes: document.getElementById('groupAttributes').value || '',
    };

    fetch(`/usermanager/${routerId}/user-groups`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(response => {
        alert(response.message);
        if (response.success) {
            location.reload();
        }
    })
    .catch(err => {
        alert('حدث خطأ أثناء الإنشاء');
        console.error(err);
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-plus ml-1"></i> إنشاء المجموعة';
    });
}

function deleteUserGroup(id) {
    if (!id) {
        alert('معرف غير صالح');
        return;
    }
    
    if (!confirm('هل أنت متأكد من حذف هذه المجموعة؟')) return;

    fetch(`/usermanager/${routerId}/user-groups`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ group_id: id })
    })
    .then(r => r.json())
    .then(response => {
        alert(response.message);
        if (response.success) {
            location.reload();
        }
    })
    .catch(err => {
        alert('حدث خطأ أثناء الحذف');
        console.error(err);
    });
}

// ==================== Quick Profiles Modal ====================
function openQuickProfilesModal() {
    document.getElementById('quickProfilesModal').classList.remove('hidden');
    document.getElementById('quickProfilesModal').classList.add('flex');
}

function closeQuickProfilesModal() {
    document.getElementById('quickProfilesModal').classList.add('hidden');
    document.getElementById('quickProfilesModal').classList.remove('flex');
}

function toggleAllProfiles(checkbox) {
    document.querySelectorAll('.profile-checkbox').forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    const count = document.querySelectorAll('.profile-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = count;
}

function createQuickProfiles() {
    const selectedProfiles = [];
    document.querySelectorAll('.profile-checkbox:checked').forEach(cb => {
        selectedProfiles.push({
            name: cb.dataset.name,
            download: cb.dataset.download,
            upload: cb.dataset.upload,
            price: cb.dataset.price,
            validity: cb.dataset.validity
        });
    });

    if (selectedProfiles.length === 0) {
        alert('الرجاء اختيار باقة واحدة على الأقل');
        return;
    }

    const btn = document.getElementById('createQuickProfilesBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i> جاري الإنشاء...';

    fetch(`/usermanager/${routerId}/quick-profiles`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ profiles: selectedProfiles })
    })
    .then(r => r.json())
    .then(response => {
        if (response.success) {
            alert(`تم إنشاء ${response.created} باقة بنجاح!\n${response.skipped > 0 ? `تم تخطي ${response.skipped} باقة موجودة مسبقاً` : ''}`);
            location.reload();
        } else {
            alert('خطأ: ' + response.message);
        }
    })
    .catch(err => {
        alert('حدث خطأ أثناء الإنشاء');
        console.error(err);
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-magic ml-1"></i> إنشاء الباقات المحددة';
    });
}
</script>

<!-- Quick Profiles Modal -->
<div id="quickProfilesModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-2 sm:p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[95vh] sm:max-h-[90vh] overflow-hidden">
        <div class="bg-gradient-to-r from-amber-500 to-orange-600 text-white p-4 sm:p-5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 bg-white/20 rounded-xl flex items-center justify-center">
                        <i class="fas fa-magic text-lg sm:text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg sm:text-xl font-bold">إنشاء باقات سريعة</h3>
                        <p class="text-white/80 text-xs sm:text-sm">اختر الباقات الجاهزة لإضافتها تلقائياً</p>
                    </div>
                </div>
                <button onclick="closeQuickProfilesModal()" class="w-10 h-10 flex items-center justify-center text-white/80 hover:text-white hover:bg-white/20 rounded-full transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <div class="p-4 sm:p-5 overflow-y-auto max-h-[calc(95vh-180px)] sm:max-h-[60vh]">
            <!-- Select All -->
            <div class="mb-4 p-3 bg-gray-100 rounded-xl flex items-center justify-between">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" onchange="toggleAllProfiles(this)" class="w-5 h-5 rounded text-amber-500">
                    <span class="font-bold text-gray-700 text-sm sm:text-base">تحديد الكل</span>
                </label>
                <span class="text-gray-600 text-sm">المحدد: <strong id="selectedCount" class="text-amber-600">0</strong> باقة</span>
            </div>
            
            <!-- Profiles Grid - 2 cols on mobile -->
            <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-3">
                <!-- 1M Package -->
                <label class="profile-card border-2 border-gray-200 hover:border-amber-400 rounded-xl p-3 sm:p-4 cursor-pointer transition-all">
                    <div class="flex items-start gap-2 sm:gap-3">
                        <input type="checkbox" class="profile-checkbox w-5 h-5 mt-1 rounded text-amber-500" 
                               data-name="1M" data-download="1M" data-upload="1M" data-price="3000" data-validity="30d"
                               onchange="updateSelectedCount()">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <span class="font-bold text-gray-800 text-sm sm:text-base">1M</span>
                                <span class="text-[10px] sm:text-xs bg-gray-100 text-gray-600 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full">اقتصادي</span>
                            </div>
                            <div class="text-xs sm:text-sm text-gray-500 mt-1">
                                <div><i class="fas fa-download text-green-500 w-3 sm:w-4"></i> 1 ميجا</div>
                                <div class="hidden sm:block"><i class="fas fa-upload text-blue-500 w-4"></i> 1 ميجا</div>
                            </div>
                        </div>
                    </div>
                </label>
                
                <!-- 2M Package -->
                <label class="profile-card border-2 border-gray-200 hover:border-amber-400 rounded-xl p-3 sm:p-4 cursor-pointer transition-all">
                    <div class="flex items-start gap-2 sm:gap-3">
                        <input type="checkbox" class="profile-checkbox w-5 h-5 mt-1 rounded text-amber-500" 
                               data-name="2M" data-download="2M" data-upload="2M" data-price="5000" data-validity="30d"
                               onchange="updateSelectedCount()">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <span class="font-bold text-gray-800 text-sm sm:text-base">2M</span>
                                <span class="text-[10px] sm:text-xs bg-blue-100 text-blue-600 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full">أساسي</span>
                            </div>
                            <div class="text-xs sm:text-sm text-gray-500 mt-1">
                                <div><i class="fas fa-download text-green-500 w-3 sm:w-4"></i> 2 ميجا</div>
                                <div class="hidden sm:block"><i class="fas fa-upload text-blue-500 w-4"></i> 2 ميجا</div>
                            </div>
                        </div>
                    </div>
                </label>
                
                <!-- 3M Package -->
                <label class="profile-card border-2 border-gray-200 hover:border-amber-400 rounded-xl p-3 sm:p-4 cursor-pointer transition-all">
                    <div class="flex items-start gap-2 sm:gap-3">
                        <input type="checkbox" class="profile-checkbox w-5 h-5 mt-1 rounded text-amber-500" 
                               data-name="3M" data-download="3M" data-upload="3M" data-price="6000" data-validity="30d"
                               onchange="updateSelectedCount()">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <span class="font-bold text-gray-800 text-sm sm:text-base">3M</span>
                                <span class="text-[10px] sm:text-xs bg-blue-100 text-blue-600 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full">أساسي</span>
                            </div>
                            <div class="text-xs sm:text-sm text-gray-500 mt-1">
                                <div><i class="fas fa-download text-green-500 w-3 sm:w-4"></i> 3 ميجا</div>
                                <div class="hidden sm:block"><i class="fas fa-upload text-blue-500 w-4"></i> 3 ميجا</div>
                            </div>
                        </div>
                    </div>
                </label>
                
                <!-- 4M Package -->
                <label class="profile-card border-2 border-gray-200 hover:border-amber-400 rounded-xl p-3 sm:p-4 cursor-pointer transition-all">
                    <div class="flex items-start gap-2 sm:gap-3">
                        <input type="checkbox" class="profile-checkbox w-5 h-5 mt-1 rounded text-amber-500" 
                               data-name="4M" data-download="4M" data-upload="4M" data-price="7000" data-validity="30d"
                               onchange="updateSelectedCount()">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <span class="font-bold text-gray-800 text-sm sm:text-base">4M</span>
                                <span class="text-[10px] sm:text-xs bg-blue-100 text-blue-600 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full">أساسي</span>
                            </div>
                            <div class="text-xs sm:text-sm text-gray-500 mt-1">
                                <div><i class="fas fa-download text-green-500 w-3 sm:w-4"></i> 4 ميجا</div>
                                <div class="hidden sm:block"><i class="fas fa-upload text-blue-500 w-4"></i> 4 ميجا</div>
                            </div>
                        </div>
                    </div>
                </label>
                
                <!-- 5M Package -->
                <label class="profile-card border-2 border-gray-200 hover:border-amber-400 rounded-xl p-3 sm:p-4 cursor-pointer transition-all">
                    <div class="flex items-start gap-2 sm:gap-3">
                        <input type="checkbox" class="profile-checkbox w-5 h-5 mt-1 rounded text-amber-500" 
                               data-name="5M" data-download="5M" data-upload="5M" data-price="8000" data-validity="30d"
                               onchange="updateSelectedCount()">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <span class="font-bold text-gray-800 text-sm sm:text-base">5M</span>
                                <span class="text-[10px] sm:text-xs bg-green-100 text-green-600 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full">متوسط</span>
                            </div>
                            <div class="text-xs sm:text-sm text-gray-500 mt-1">
                                <div><i class="fas fa-download text-green-500 w-3 sm:w-4"></i> 5 ميجا</div>
                                <div class="hidden sm:block"><i class="fas fa-upload text-blue-500 w-4"></i> 5 ميجا</div>
                            </div>
                        </div>
                    </div>
                </label>
                
                <!-- 6M Package -->
                <label class="profile-card border-2 border-gray-200 hover:border-amber-400 rounded-xl p-3 sm:p-4 cursor-pointer transition-all">
                    <div class="flex items-start gap-2 sm:gap-3">
                        <input type="checkbox" class="profile-checkbox w-5 h-5 mt-1 rounded text-amber-500" 
                               data-name="6M" data-download="6M" data-upload="6M" data-price="9000" data-validity="30d"
                               onchange="updateSelectedCount()">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <span class="font-bold text-gray-800 text-sm sm:text-base">6M</span>
                                <span class="text-[10px] sm:text-xs bg-green-100 text-green-600 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full">متوسط</span>
                            </div>
                            <div class="text-xs sm:text-sm text-gray-500 mt-1">
                                <div><i class="fas fa-download text-green-500 w-3 sm:w-4"></i> 6 ميجا</div>
                                <div class="hidden sm:block"><i class="fas fa-upload text-blue-500 w-4"></i> 6 ميجا</div>
                            </div>
                        </div>
                    </div>
                </label>
                
                <!-- 8M Package -->
                <label class="profile-card border-2 border-gray-200 hover:border-amber-400 rounded-xl p-3 sm:p-4 cursor-pointer transition-all">
                    <div class="flex items-start gap-2 sm:gap-3">
                        <input type="checkbox" class="profile-checkbox w-5 h-5 mt-1 rounded text-amber-500" 
                               data-name="8M" data-download="8M" data-upload="8M" data-price="11000" data-validity="30d"
                               onchange="updateSelectedCount()">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <span class="font-bold text-gray-800 text-sm sm:text-base">8M</span>
                                <span class="text-[10px] sm:text-xs bg-green-100 text-green-600 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full">متوسط</span>
                            </div>
                            <div class="text-xs sm:text-sm text-gray-500 mt-1">
                                <div><i class="fas fa-download text-green-500 w-3 sm:w-4"></i> 8 ميجا</div>
                                <div class="hidden sm:block"><i class="fas fa-upload text-blue-500 w-4"></i> 8 ميجا</div>
                            </div>
                        </div>
                    </div>
                </label>
                
                <!-- 10M Package -->
                <label class="profile-card border-2 border-gray-200 hover:border-amber-400 rounded-xl p-3 sm:p-4 cursor-pointer transition-all">
                    <div class="flex items-start gap-2 sm:gap-3">
                        <input type="checkbox" class="profile-checkbox w-5 h-5 mt-1 rounded text-amber-500" 
                               data-name="10M" data-download="10M" data-upload="10M" data-price="13000" data-validity="30d"
                               onchange="updateSelectedCount()">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <span class="font-bold text-gray-800 text-sm sm:text-base">10M</span>
                                <span class="text-[10px] sm:text-xs bg-yellow-100 text-yellow-600 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full">سريع</span>
                            </div>
                            <div class="text-xs sm:text-sm text-gray-500 mt-1">
                                <div><i class="fas fa-download text-green-500 w-3 sm:w-4"></i> 10 ميجا</div>
                                <div class="hidden sm:block"><i class="fas fa-upload text-blue-500 w-4"></i> 10 ميجا</div>
                            </div>
                        </div>
                    </div>
                </label>
                
                <!-- 15M Package -->
                <label class="profile-card border-2 border-gray-200 hover:border-amber-400 rounded-xl p-3 sm:p-4 cursor-pointer transition-all">
                    <div class="flex items-start gap-2 sm:gap-3">
                        <input type="checkbox" class="profile-checkbox w-5 h-5 mt-1 rounded text-amber-500" 
                               data-name="15M" data-download="15M" data-upload="15M" data-price="16000" data-validity="30d"
                               onchange="updateSelectedCount()">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <span class="font-bold text-gray-800 text-sm sm:text-base">15M</span>
                                <span class="text-[10px] sm:text-xs bg-yellow-100 text-yellow-600 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full">سريع</span>
                            </div>
                            <div class="text-xs sm:text-sm text-gray-500 mt-1">
                                <div><i class="fas fa-download text-green-500 w-3 sm:w-4"></i> 15 ميجا</div>
                                <div class="hidden sm:block"><i class="fas fa-upload text-blue-500 w-4"></i> 15 ميجا</div>
                            </div>
                        </div>
                    </div>
                </label>
                
                <!-- 20M Package -->
                <label class="profile-card border-2 border-gray-200 hover:border-amber-400 rounded-xl p-3 sm:p-4 cursor-pointer transition-all">
                    <div class="flex items-start gap-2 sm:gap-3">
                        <input type="checkbox" class="profile-checkbox w-5 h-5 mt-1 rounded text-amber-500" 
                               data-name="20M" data-download="20M" data-upload="20M" data-price="20000" data-validity="30d"
                               onchange="updateSelectedCount()">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <span class="font-bold text-gray-800 text-sm sm:text-base">20M</span>
                                <span class="text-[10px] sm:text-xs bg-purple-100 text-purple-600 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full">سريع جداً</span>
                            </div>
                            <div class="text-xs sm:text-sm text-gray-500 mt-1">
                                <div><i class="fas fa-download text-green-500 w-3 sm:w-4"></i> 20 ميجا</div>
                                <div class="hidden sm:block"><i class="fas fa-upload text-blue-500 w-4"></i> 20 ميجا</div>
                            </div>
                        </div>
                    </div>
                </label>
                
                <!-- 30M Package -->
                <label class="profile-card border-2 border-gray-200 hover:border-amber-400 rounded-xl p-3 sm:p-4 cursor-pointer transition-all">
                    <div class="flex items-start gap-2 sm:gap-3">
                        <input type="checkbox" class="profile-checkbox w-5 h-5 mt-1 rounded text-amber-500" 
                               data-name="30M" data-download="30M" data-upload="30M" data-price="25000" data-validity="30d"
                               onchange="updateSelectedCount()">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <span class="font-bold text-gray-800 text-sm sm:text-base">30M</span>
                                <span class="text-[10px] sm:text-xs bg-purple-100 text-purple-600 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full">سريع جداً</span>
                            </div>
                            <div class="text-xs sm:text-sm text-gray-500 mt-1">
                                <div><i class="fas fa-download text-green-500 w-3 sm:w-4"></i> 30 ميجا</div>
                                <div class="hidden sm:block"><i class="fas fa-upload text-blue-500 w-4"></i> 30 ميجا</div>
                            </div>
                        </div>
                    </div>
                </label>
                
                <!-- 50M Package -->
                <label class="profile-card border-2 border-gray-200 hover:border-amber-400 rounded-xl p-3 sm:p-4 cursor-pointer transition-all">
                    <div class="flex items-start gap-2 sm:gap-3">
                        <input type="checkbox" class="profile-checkbox w-5 h-5 mt-1 rounded text-amber-500" 
                               data-name="50M" data-download="50M" data-upload="50M" data-price="35000" data-validity="30d"
                               onchange="updateSelectedCount()">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <span class="font-bold text-gray-800 text-sm sm:text-base">50M</span>
                                <span class="text-[10px] sm:text-xs bg-indigo-100 text-indigo-600 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full">فائق</span>
                            </div>
                            <div class="text-xs sm:text-sm text-gray-500 mt-1">
                                <div><i class="fas fa-download text-green-500 w-3 sm:w-4"></i> 50 ميجا</div>
                                <div class="hidden sm:block"><i class="fas fa-upload text-blue-500 w-4"></i> 50 ميجا</div>
                            </div>
                        </div>
                    </div>
                </label>
                
                <!-- 100M Package -->
                <label class="profile-card border-2 border-gray-200 hover:border-amber-400 rounded-xl p-3 sm:p-4 cursor-pointer transition-all">
                    <div class="flex items-start gap-2 sm:gap-3">
                        <input type="checkbox" class="profile-checkbox w-5 h-5 mt-1 rounded text-amber-500" 
                               data-name="100M" data-download="100M" data-upload="100M" data-price="50000" data-validity="30d"
                               onchange="updateSelectedCount()">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <span class="font-bold text-gray-800 text-sm sm:text-base">100M</span>
                                <span class="text-[10px] sm:text-xs bg-rose-100 text-rose-600 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full">فائق</span>
                            </div>
                            <div class="text-xs sm:text-sm text-gray-500 mt-1">
                                <div><i class="fas fa-download text-green-500 w-3 sm:w-4"></i> 100 ميجا</div>
                                <div class="hidden sm:block"><i class="fas fa-upload text-blue-500 w-4"></i> 100 ميجا</div>
                            </div>
                        </div>
                    </div>
                </label>
                
                <!-- Unlimited Package -->
                <label class="profile-card border-2 border-gray-200 hover:border-amber-400 rounded-xl p-3 sm:p-4 cursor-pointer transition-all">
                    <div class="flex items-start gap-2 sm:gap-3">
                        <input type="checkbox" class="profile-checkbox w-5 h-5 mt-1 rounded text-amber-500" 
                               data-name="unlimited" data-download="0" data-upload="0" data-price="60000" data-validity="30d"
                               onchange="updateSelectedCount()">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <span class="font-bold text-gray-800 text-sm sm:text-base">غير محدود</span>
                                <span class="text-[10px] sm:text-xs bg-gradient-to-r from-purple-500 to-pink-500 text-white px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full">VIP</span>
                            </div>
                            <div class="text-xs sm:text-sm text-gray-500 mt-1">
                                <div><i class="fas fa-infinity text-purple-500 w-3 sm:w-4"></i> بلا حدود</div>
            <!-- Info Note -->
            <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-xl text-blue-700 text-xs sm:text-sm">
                <i class="fas fa-info-circle ml-1"></i>
                <strong>ملاحظة:</strong> سيتم إنشاء Limitation و Profile لكل باقة. الباقات الموجودة مسبقاً سيتم تخطيها تلقائياً.
            </div>
        </div>
        
        <div class="p-4 sm:p-5 border-t bg-gray-50 flex flex-col-reverse sm:flex-row sm:justify-between gap-2 sm:gap-0">
            <button onclick="closeQuickProfilesModal()" class="w-full sm:w-auto px-6 py-3 sm:py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition text-base">
                إلغاء
            </button>
            <button onclick="createQuickProfiles()" id="createQuickProfilesBtn" 
                    class="w-full sm:w-auto px-6 py-3 sm:py-2 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white rounded-lg font-bold transition shadow-lg text-base">
                <i class="fas fa-magic ml-1"></i> إنشاء الباقات المحددة
            </button>
        </div>
    </div>
</div>
@endpush

@php
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
@endphp
@endsection
