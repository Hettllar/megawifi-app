@extends('layouts.app')

@section('title', 'تعديل المشترك: ' . $subscriber->username)

@php
function formatBytes($bytes, $precision = 2) {
    if ($bytes <= 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}
@endphp

@section('content')
<div class="max-w-4xl mx-auto px-2 sm:px-0">
    <!-- Success Notification -->
    @if(session('success'))
    <div id="successNotification" class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 bg-green-500 text-white px-6 py-3 rounded-xl shadow-lg flex items-center gap-3 animate-bounce">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <span class="font-bold">{{ session('success') }}</span>
    </div>
    <script>
        setTimeout(function() {
            document.getElementById('successNotification').style.display = 'none';
        }, 3000);
    </script>
    @endif

    <!-- Header -->
    <div class="mb-4 sm:mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-3 sm:gap-4">
            <a href="{{ route('subscribers.index', ['router' => $subscriber->router_id]) }}" 
               class="p-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-gray-800">{{ $subscriber->username }}</h1>
                <p class="text-gray-500 text-sm">{{ $subscriber->router->name }}</p>
            </div>
        </div>
        
        <!-- Status Badge -->
        <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
            @if($subscriber->activeSessions->isNotEmpty())
                <span class="px-3 py-1.5 sm:px-4 sm:py-2 bg-green-100 text-green-700 rounded-full font-bold flex items-center gap-2 text-sm sm:text-base">
                    <span class="w-2 h-2 sm:w-3 sm:h-3 bg-green-500 rounded-full animate-pulse"></span>
                    متصل
                </span>
            @endif
            <span class="px-3 py-1.5 sm:px-4 sm:py-2 rounded-full font-bold text-sm sm:text-base {{ $subscriber->status === 'active' ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700' }}">
                {{ $subscriber->status === 'active' ? 'نشط' : 'معطل' }}
            </span>
        </div>
    </div>

    <form action="{{ route('subscribers.update', $subscriber) }}" method="POST" id="editForm">
        @csrf
        @method('PUT')
        
        <div class="space-y-6">
            <!-- معلومات الحساب الأساسية -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                <div class="p-4 bg-gradient-to-r from-blue-500 to-blue-600 text-white">
                    <h2 class="text-lg font-bold flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        معلومات الحساب
                    </h2>
                </div>
                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                        <!-- البروفايل -->
                        <div>
                            <label class="block text-gray-700 font-bold mb-2 text-sm sm:text-base">
                                <svg class="w-4 h-4 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                البروفايل (السرعة)
                            </label>
                            <select name="profile" id="profile" required
                                    class="w-full border-2 border-gray-200 rounded-xl px-3 sm:px-4 py-2.5 sm:py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm sm:text-base">
                                @foreach($profiles as $profile)
                                    <option value="{{ $profile['name'] }}" {{ $subscriber->profile == $profile['name'] ? 'selected' : '' }}>
                                        {{ $profile['name'] }}
                                        @if(isset($profile['rate-limit']) && $profile['rate-limit'])
                                            ({{ $profile['rate-limit'] }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('profile')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- كلمة المرور -->
                        <div>
                            <label class="block text-gray-700 font-bold mb-2">
                                <svg class="w-4 h-4 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                كلمة المرور
                            </label>
                            <div class="relative">
                                <input type="text" name="password" id="password"
                                       placeholder="اتركها فارغة للإبقاء على الحالية"
                                       class="w-full border-2 border-gray-200 rounded-xl px-3 sm:px-4 py-2.5 sm:py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition font-mono text-sm sm:text-base">
                            </div>
                            @error('password')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- الحالة -->
                        <div>
                            <label class="block text-gray-700 font-bold mb-2">
                                <svg class="w-4 h-4 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                الحالة
                            </label>
                            <select name="status" id="status" required
                                    class="w-full border-2 border-gray-200 rounded-xl px-3 sm:px-4 py-2.5 sm:py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm sm:text-base">
                                <option value="active" {{ $subscriber->status == 'active' ? 'selected' : '' }}>✅ نشط</option>
                                <option value="disabled" {{ $subscriber->status == 'disabled' ? 'selected' : '' }}>⛔ معطل</option>
                            </select>
                            @error('status')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Caller ID (MAC Binding) -->
                        <div>
                            <label class="block text-gray-700 font-bold mb-2">
                                <svg class="w-4 h-4 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                MAC Address (تقييد الجهاز)
                            </label>
                            <input type="text" name="caller_id" id="caller_id"
                                   value="{{ old('caller_id', $subscriber->caller_id) }}"
                                   placeholder="فارغ = أي جهاز"
                                   class="w-full border-2 border-gray-200 rounded-xl px-3 sm:px-4 py-2.5 sm:py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition font-mono text-sm sm:text-base">
                            @error('caller_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- معلومات العميل -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                <div class="p-4 bg-gradient-to-r from-purple-500 to-purple-600 text-white">
                    <h2 class="text-lg font-bold flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        معلومات العميل
                    </h2>
                </div>
                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                        <!-- الاسم الكامل -->
                        <div>
                            <label class="block text-gray-700 font-bold mb-2 text-sm sm:text-base">الاسم الكامل</label>
                            <input type="text" name="full_name" id="full_name"
                                   value="{{ old('full_name', $subscriber->full_name) }}"
                                   placeholder="اسم العميل"
                                   class="w-full border-2 border-gray-200 rounded-xl px-3 sm:px-4 py-2.5 sm:py-3 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition text-sm sm:text-base">
                        </div>

                        <!-- رقم الهاتف -->
                        <div>
                            <label class="block text-gray-700 font-bold mb-2">رقم الهاتف</label>
                            <input type="text" name="phone" id="phone"
                                   value="{{ old('phone', $subscriber->phone) }}"
                                   placeholder="مثال: 777123456"
                                   class="w-full border-2 border-gray-200 rounded-xl px-3 sm:px-4 py-2.5 sm:py-3 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition text-sm sm:text-base">
                        </div>

                        <!-- الرقم الوطني -->
                        <div>
                            <label class="block text-gray-700 font-bold mb-2 text-sm sm:text-base">
                                <svg class="w-4 h-4 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                                </svg>
                                الرقم الوطني
                            </label>
                            <input type="text" name="national_id" id="national_id"
                                   value="{{ old('national_id', $subscriber->national_id) }}"
                                   placeholder="رقم الهوية الوطنية"
                                   class="w-full border-2 border-gray-200 rounded-xl px-3 sm:px-4 py-2.5 sm:py-3 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition text-sm sm:text-base">
                        </div>

                        <!-- العنوان -->
                        <div class="sm:col-span-2">
                            <label class="block text-gray-700 font-bold mb-2 text-sm sm:text-base">العنوان</label>
                            <input type="text" name="address" id="address"
                                   value="{{ old('address', $subscriber->address) }}"
                                   placeholder="عنوان العميل"
                                   class="w-full border-2 border-gray-200 rounded-xl px-3 sm:px-4 py-2.5 sm:py-3 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition text-sm sm:text-base">
                        </div>
                    </div>
                </div>
            </div>

            <!-- معلومات الاستهلاك (للعرض فقط) -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                <div class="p-4 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white">
                    <h2 class="text-lg font-bold flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        معلومات الاستهلاك
                    </h2>
                </div>
                <div class="p-4 sm:p-6">
                    @php
                        $session = $subscriber->activeSessions->first();
                        $currentBytesIn = $session?->bytes_in ?? 0;
                        $currentBytesOut = $session?->bytes_out ?? 0;
                        $totalIn = ($subscriber->bytes_in ?? 0) + $currentBytesIn;
                        $totalOut = ($subscriber->bytes_out ?? 0) + $currentBytesOut;
                        $totalBytes = $totalIn + $totalOut;
                    @endphp
                    
                    <div class="grid grid-cols-3 gap-2 sm:gap-4">
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-2 sm:p-4 text-center">
                            <div class="text-lg sm:text-3xl font-bold text-blue-600">{{ formatBytes($totalIn) }}</div>
                            <div class="text-xs sm:text-sm text-blue-500 mt-1">⬇️ تنزيل</div>
                        </div>
                        <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-2 sm:p-4 text-center">
                            <div class="text-lg sm:text-3xl font-bold text-orange-600">{{ formatBytes($totalOut) }}</div>
                            <div class="text-xs sm:text-sm text-orange-500 mt-1">⬆️ رفع</div>
                        </div>
                        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-2 sm:p-4 text-center">
                            <div class="text-lg sm:text-3xl font-bold text-purple-600">{{ formatBytes($totalBytes) }}</div>
                            <div class="text-xs sm:text-sm text-purple-500 mt-1">📊 إجمالي</div>
                        </div>
                    </div>

                    @if($session)
                    <div class="mt-4 p-4 bg-green-50 rounded-xl border border-green-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></span>
                                <span class="font-bold text-green-700">الجلسة الحالية</span>
                            </div>
                            <div class="text-sm text-green-600">
                                IP: {{ $session->ip_address ?? '-' }}
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- ملاحظات -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                <div class="p-4 bg-gradient-to-r from-gray-500 to-gray-600 text-white">
                    <h2 class="text-lg font-bold flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        ملاحظات
                    </h2>
                </div>
                <div class="p-4 sm:p-6">
                    <textarea name="comment" id="comment" rows="3"
                              placeholder="أضف ملاحظات..."
                              class="w-full border-2 border-gray-200 rounded-xl px-3 sm:px-4 py-2.5 sm:py-3 focus:ring-2 focus:ring-gray-500 focus:border-gray-500 transition text-sm sm:text-base">{{ old('comment', $subscriber->comment) }}</textarea>
                </div>
            </div>
        </div>

        <!-- أزرار الحفظ -->
        <div class="mt-4 sm:mt-6 flex flex-col sm:flex-row gap-3 sm:gap-4">
            <button type="submit" 
                    class="flex-1 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-4 sm:px-6 py-3 sm:py-4 rounded-xl font-bold text-base sm:text-lg shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-2">
                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                حفظ التغييرات
            </button>
            <a href="{{ route('subscribers.contract', $subscriber) }}" target="_blank"
               class="px-4 sm:px-6 py-3 sm:py-4 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white rounded-xl font-bold text-base sm:text-lg shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-2">
                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                إنشاء عقد
            </a>
            <a href="{{ route('subscribers.index', ['router' => $subscriber->router_id]) }}" 
               class="px-4 sm:px-6 py-3 sm:py-4 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl font-bold text-base sm:text-lg transition text-center">
                إلغاء
            </a>
        </div>
    </form>
</div>
@endsection
