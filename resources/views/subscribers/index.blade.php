@extends('layouts.app')

@section('title', 'مشتركين البرودباند')

@push('styles')
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush

@section('content')
<div class="space-y-4" x-data="{ expandedId: null }" x-cloak>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-gray-800 flex items-center gap-2">
                <span class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-broadcast-tower text-white"></i>
                </span>
                مشتركين البرودباند
            </h1>
            <p class="text-gray-500 text-sm mt-1 mr-12">إدارة مشتركي PPPoE</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <button onclick="openBackupModal()" 
                class="group flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white rounded-xl transition-all duration-300 shadow-md hover:shadow-lg hover:scale-105">
                <i class="fas fa-database"></i>
                <span class="font-medium">نسخ احتياطي</span>
            </button>
            <button onclick="syncSessions()" 
                class="group flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white rounded-xl transition-all duration-300 shadow-md hover:shadow-lg hover:scale-105">
                <i class="fas fa-sync group-hover:animate-spin"></i>
                <span class="font-medium">مزامنة</span>
            </button>
            <a href="{{ route('subscribers.create') }}" 
                class="group flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white rounded-xl transition-all duration-300 shadow-md hover:shadow-lg hover:scale-105">
                <span class="w-6 h-6 bg-white/20 rounded-lg flex items-center justify-center group-hover:bg-white/30 transition">
                    <i class="fas fa-plus text-sm"></i>
                </span>
                <span class="font-bold">إضافة مشترك</span>
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-4 gap-2 lg:gap-4">
        <!-- الإجمالي -->
        <div class="group bg-gradient-to-br from-blue-50 to-white rounded-xl p-3 lg:p-4 shadow-sm border-2 border-blue-100 hover:border-blue-400 hover:shadow-lg hover:scale-105 transition-all duration-300 cursor-pointer">
            <div class="text-center">
                <div class="w-10 h-10 lg:w-12 lg:h-12 bg-blue-500 rounded-full flex items-center justify-center mx-auto mb-2 group-hover:animate-bounce">
                    <i class="fas fa-users text-white text-sm lg:text-lg"></i>
                </div>
                <p class="text-xl lg:text-3xl font-bold text-blue-600 group-hover:text-blue-700">{{ $subscribers->count() }}</p>
                <p class="text-xs text-gray-500 font-medium">الإجمالي</p>
            </div>
        </div>
        
        <!-- نشط -->
        <div class="group bg-gradient-to-br from-green-50 to-white rounded-xl p-3 lg:p-4 shadow-sm border-2 border-green-100 hover:border-green-400 hover:shadow-lg hover:scale-105 transition-all duration-300 cursor-pointer">
            <div class="text-center">
                <div class="w-10 h-10 lg:w-12 lg:h-12 bg-green-500 rounded-full flex items-center justify-center mx-auto mb-2 group-hover:animate-bounce">
                    <i class="fas fa-check-circle text-white text-sm lg:text-lg"></i>
                </div>
                <p class="text-xl lg:text-3xl font-bold text-green-600 group-hover:text-green-700">{{ $subscribers->where('status', 'active')->count() }}</p>
                <p class="text-xs text-gray-500 font-medium">نشط</p>
            </div>
        </div>
        
        <!-- متصل -->
        <div class="group bg-gradient-to-br from-emerald-50 to-white rounded-xl p-3 lg:p-4 shadow-sm border-2 border-emerald-100 hover:border-emerald-400 hover:shadow-lg hover:scale-105 transition-all duration-300 cursor-pointer">
            <div class="text-center">
                <div class="w-10 h-10 lg:w-12 lg:h-12 bg-emerald-500 rounded-full flex items-center justify-center mx-auto mb-2 group-hover:animate-pulse">
                    <i class="fas fa-wifi text-white text-sm lg:text-lg"></i>
                </div>
                <p class="text-xl lg:text-3xl font-bold text-emerald-600 group-hover:text-emerald-700">{{ $subscribers->filter(fn($s) => $s->activeSessions->isNotEmpty())->count() }}</p>
                <p class="text-xs text-gray-500 font-medium">متصل</p>
            </div>
        </div>
        
        <!-- معطل -->
        <div class="group bg-gradient-to-br from-red-50 to-white rounded-xl p-3 lg:p-4 shadow-sm border-2 border-red-100 hover:border-red-400 hover:shadow-lg hover:scale-105 transition-all duration-300 cursor-pointer">
            <div class="text-center">
                <div class="w-10 h-10 lg:w-12 lg:h-12 bg-red-500 rounded-full flex items-center justify-center mx-auto mb-2 group-hover:animate-bounce">
                    <i class="fas fa-ban text-white text-sm lg:text-lg"></i>
                </div>
                <p class="text-xl lg:text-3xl font-bold text-red-600 group-hover:text-red-700">{{ $subscribers->where('status', 'disabled')->count() }}</p>
                <p class="text-xs text-gray-500 font-medium">معطل</p>
            </div>
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="bg-white rounded-xl p-4 shadow-sm border" x-data="{ showFilters: false }">
        <form method="GET" class="space-y-3">
            <input type="hidden" name="type" value="pppoe">
            
            <!-- Search Bar -->
            <div class="flex gap-2">
                <div class="flex-1 relative">
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="بحث بالاسم أو اسم المستخدم أو الهاتف..." 
                        class="w-full pr-10 pl-4 py-3 border-2 border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                </div>
                
                <!-- Filter Toggle Button -->
                <button type="button" @click="showFilters = !showFilters" 
                    class="px-4 py-3 border-2 rounded-xl transition flex items-center gap-2"
                    :class="showFilters || '{{ request('router_id') || request('status') }}' ? 'border-blue-500 bg-blue-50 text-blue-600' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
                    <i class="fas fa-filter"></i>
                    <span class="hidden sm:inline">فلتر</span>
                    @if(request('router_id') || request('status'))
                    <span class="w-5 h-5 bg-blue-500 text-white text-xs rounded-full flex items-center justify-center">
                        {{ (request('router_id') ? 1 : 0) + (request('status') ? 1 : 0) }}
                    </span>
                    @endif
                </button>
                
                <!-- Search Button -->
                <button type="submit" class="px-5 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white rounded-xl transition shadow-md flex items-center gap-2">
                    <i class="fas fa-search"></i>
                    <span class="hidden sm:inline">بحث</span>
                </button>
            </div>
            
            <!-- Filters Panel -->
            <div x-show="showFilters" x-collapse x-cloak class="pt-3 border-t border-gray-200">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- Router Filter -->
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1.5">
                            <i class="fas fa-router ml-1"></i> الراوتر
                        </label>
                        <select name="router_id" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-white">
                            <option value="">كل الراوترات</option>
                            @foreach($routers as $router)
                                <option value="{{ $router->id }}" {{ request('router_id') == $router->id ? 'selected' : '' }}>
                                    {{ $router->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <!-- Status Filter -->
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1.5">
                            <i class="fas fa-toggle-on ml-1"></i> الحالة
                        </label>
                        <select name="status" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-white">
                            <option value="">الكل</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>✅ نشط</option>
                            <option value="disabled" {{ request('status') == 'disabled' ? 'selected' : '' }}>🚫 معطل</option>
                        </select>
                    </div>
                </div>
                
                <!-- Clear Filters -->
                @if(request('router_id') || request('status') || request('search'))
                <div class="mt-3 flex justify-end">
                    <a href="{{ route('subscribers.index', ['type' => 'pppoe']) }}" 
                       class="text-sm text-red-500 hover:text-red-600 flex items-center gap-1">
                        <i class="fas fa-times-circle"></i>
                        مسح الفلاتر
                    </a>
                </div>
                @endif
            </div>
        </form>
    </div>

    @if($subscribers->isEmpty())
        <div class="bg-white rounded-lg p-8 text-center shadow-sm">
            <i class="fas fa-users text-gray-300 text-4xl mb-4"></i>
            <p class="text-gray-500">لا يوجد مشتركين</p>
        </div>
    @else
        <!-- Desktop Table View -->
        <div class="hidden lg:block bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-100 border-b-2 border-gray-200">
                    <tr>
                        <th class="text-right px-4 py-3 text-sm font-bold text-gray-700">المشترك</th>
                        <th class="text-right px-4 py-3 text-sm font-bold text-gray-700">الحالة</th>
                        <th class="text-right px-4 py-3 text-sm font-bold text-gray-700">الراوتر</th>
                        <th class="text-right px-4 py-3 text-sm font-bold text-gray-700">الاستهلاك</th>
                        <th class="text-center px-4 py-3 text-sm font-bold text-gray-700">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($subscribers as $index => $subscriber)
                    @php
                        $isOnline = $subscriber->activeSessions->isNotEmpty();
                        $session = $subscriber->activeSessions->first();
                        // المجموع التراكمي = الجلسات السابقة (في subscribers) + الجلسة الحالية (في active_sessions)
                        $previousBytesIn = $subscriber->bytes_in ?? 0;
                        $previousBytesOut = $subscriber->bytes_out ?? 0;
                        $currentBytesIn = $session?->bytes_in ?? 0;
                        $currentBytesOut = $session?->bytes_out ?? 0;
                        $bytesIn = $previousBytesIn + $currentBytesIn;
                        $bytesOut = $previousBytesOut + $currentBytesOut;
                        $totalBytes = $bytesIn + $bytesOut;
                        $rowClass = $index % 2 === 0 ? 'bg-white' : 'bg-gray-50/70';
                    @endphp
                    <tr class="{{ $rowClass }} hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 hover:shadow-md hover:scale-[1.01] border-b-2 border-gray-200 transition-all duration-200 cursor-pointer" onclick="window.location='{{ route('subscribers.edit', $subscriber) }}'">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="relative">
                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center text-white font-bold shadow">
                                        {{ mb_substr($subscriber->full_name ?? $subscriber->username, 0, 1) }}
                                    </div>
                                    @if($isOnline)
                                    <span class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-green-500 border-2 border-white rounded-full animate-pulse"></span>
                                    @endif
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800">{{ $subscriber->full_name ?? $subscriber->username }}</p>
                                    <p class="text-xs text-gray-500">{{ $subscriber->username }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if($isOnline)
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-100 text-green-700 rounded-full text-xs font-bold shadow-sm">
                                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                                    متصل الآن
                                </span>
                            @elseif($subscriber->status === 'active')
                                <span class="px-3 py-1.5 bg-blue-100 text-blue-700 rounded-full text-xs font-bold">نشط</span>
                            @else
                                <span class="px-3 py-1.5 bg-red-100 text-red-700 rounded-full text-xs font-bold">معطل</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-sm text-gray-700 font-medium">{{ $subscriber->router->name ?? '-' }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1 px-2 py-1 bg-purple-50 text-purple-700 rounded-lg text-sm font-bold">
                                <i class="fas fa-chart-pie text-xs"></i>
                                {{ formatBytes($totalBytes) }}
                            </span>
                        </td>
                        <td class="px-4 py-3" onclick="event.stopPropagation()">
                            <div class="flex items-center justify-center gap-2">
                                @if($subscriber->phone)
                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $subscriber->phone) }}?text={{ urlencode("مرحباً " . ($subscriber->full_name ?? $subscriber->username) . " 👋\n\n📊 *تفاصيل اشتراكك:*\n\n👤 اسم المستخدم: " . $subscriber->username . "\n📦 الباقة: " . ($subscriber->profile ?? 'غير محدد') . "\n📈 الاستهلاك: " . formatBytes($totalBytes) . "\n   ⬇️ تنزيل: " . formatBytes($bytesIn) . "\n   ⬆️ رفع: " . formatBytes($bytesOut) . "\n✅ الحالة: " . ($subscriber->status === 'active' ? 'نشط' : 'معطل') . ($isOnline ? ' (متصل الآن)' : '') . "\n\nشكراً لاشتراكك معنا 🙏") }}" 
                                   target="_blank"
                                   class="p-2 bg-green-500 hover:bg-green-600 text-white rounded-lg transition shadow-sm"
                                   title="إرسال عبر واتساب">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                    </svg>
                                </a>
                                @endif
                                @if($subscriber->status === 'active')
                                <button onclick="disconnect({{ $subscriber->id }})" 
                                    class="px-3 py-1.5 {{ $isOnline ? 'bg-orange-500 hover:bg-orange-600' : 'bg-gray-400 hover:bg-gray-500' }} text-white rounded-lg text-xs font-bold transition shadow-sm"
                                    title="{{ $isOnline ? 'قطع الاتصال الحالي' : 'المشترك غير متصل' }}">
                                    قطع
                                </button>
                                @endif
                                
                                @if($subscriber->profile !== 'stop')
                                <button onclick="toggleStatus({{ $subscriber->id }})" 
                                    class="px-3 py-1.5 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg text-xs font-bold transition shadow-sm"
                                    title="تغيير البروفايل إلى stop (1k/1k)">
                                    إيقاف
                                </button>
                                @else
                                <button onclick="toggleStatus({{ $subscriber->id }})" 
                                    class="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-xs font-bold transition shadow-sm"
                                    title="استعادة البروفايل الأصلي">
                                    استعادة
                                </button>
                                @endif
                                
                                <button onclick="event.stopPropagation(); renewSubscriber({{ $subscriber->id }}, '{{ $subscriber->username }}')" 
                                    class="px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg text-xs font-bold transition shadow-sm">
                                    تجديد
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards View -->
        <div class="lg:hidden space-y-2">
            @foreach($subscribers as $index => $subscriber)
            @php
                $isOnline = $subscriber->activeSessions->isNotEmpty();
                $session = $subscriber->activeSessions->first();
                // المجموع التراكمي = الجلسات السابقة (في subscribers) + الجلسة الحالية (في active_sessions)
                $previousBytesIn = $subscriber->bytes_in ?? 0;
                $previousBytesOut = $subscriber->bytes_out ?? 0;
                $currentBytesIn = $session?->bytes_in ?? 0;
                $currentBytesOut = $session?->bytes_out ?? 0;
                $bytesIn = $previousBytesIn + $currentBytesIn;
                $bytesOut = $previousBytesOut + $currentBytesOut;
                $totalBytes = $bytesIn + $bytesOut;
                $cardBg = $index % 2 === 0 ? 'bg-white' : 'bg-gray-50';
            @endphp
            <div class="{{ $cardBg }} rounded-lg shadow-sm border-2 {{ $isOnline ? 'border-green-300' : 'border-gray-200' }} overflow-hidden">
                <!-- Card Header -->
                <div class="p-3 flex items-center gap-3">
                    <!-- Avatar & Info - Clickable to Edit -->
                    <div class="flex items-center gap-3 flex-1 min-w-0 cursor-pointer" onclick="window.location='{{ route('subscribers.edit', $subscriber) }}'">
                        <div class="relative flex-shrink-0">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center text-white font-bold shadow">
                                {{ mb_substr($subscriber->full_name ?? $subscriber->username, 0, 1) }}
                            </div>
                            @if($isOnline)
                            <span class="absolute -bottom-0.5 -right-0.5 w-4 h-4 bg-green-500 border-2 border-white rounded-full animate-pulse"></span>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="font-bold text-gray-800 truncate">{{ $subscriber->full_name ?? $subscriber->username }}</p>
                                @if($isOnline)
                                    <span class="flex-shrink-0 px-2 py-0.5 bg-green-500 text-white text-xs rounded-full font-bold">متصل</span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500">{{ $subscriber->username }}</p>
                            <p class="text-sm text-purple-600 font-bold">{{ formatBytes($totalBytes) }}</p>
                        </div>
                    </div>
                    
                    <!-- Status Badge -->
                    <span class="px-2 py-1 text-xs rounded-full font-bold flex-shrink-0 {{ $subscriber->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        {{ $subscriber->status === 'active' ? 'نشط' : 'معطل' }}
                    </span>
                    
                    <!-- Actions Toggle Button -->
                    <button @click="expandedId = expandedId === {{ $subscriber->id }} ? null : {{ $subscriber->id }}" 
                            class="flex-shrink-0 w-10 h-10 bg-blue-500 hover:bg-blue-600 text-white rounded-lg flex items-center justify-center shadow-md transition-all"
                            :class="expandedId === {{ $subscriber->id }} ? 'bg-blue-700 rotate-180' : ''">
                        <svg class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </div>
                
                <!-- Card Details - Expandable -->
                <div x-show="expandedId === {{ $subscriber->id }}" 
                     x-collapse 
                     x-cloak
                     style="display: none;"
                     class="border-t-2 border-gray-200 bg-gray-50">
                    <div class="p-4 space-y-4">
                        <!-- Traffic Info -->
                        <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg p-4 text-center text-white shadow">
                            <i class="fas fa-chart-pie text-2xl mb-1"></i>
                            <p class="text-2xl font-bold">{{ formatBytes($totalBytes) }}</p>
                            <p class="text-purple-200 text-sm">إجمالي الاستهلاك</p>
                        </div>
                        
                        <!-- Details -->
                        <div class="bg-white rounded-lg p-3 space-y-2">
                            @if($subscriber->full_name)
                            <div class="flex justify-between items-center py-1 border-b">
                                <span class="text-gray-500 text-sm">الاسم:</span>
                                <span class="text-gray-800 font-medium">{{ $subscriber->full_name }}</span>
                            </div>
                            @endif
                            @if($subscriber->phone)
                            <div class="flex justify-between items-center py-1 border-b">
                                <span class="text-gray-500 text-sm">الهاتف:</span>
                                <span class="text-gray-800 font-medium">{{ $subscriber->phone }}</span>
                            </div>
                            @endif
                            <div class="flex justify-between items-center py-1 border-b">
                                <span class="text-gray-500 text-sm">الراوتر:</span>
                                <span class="text-gray-800 font-medium">{{ $subscriber->router->name ?? '-' }}</span>
                            </div>
                            @if($subscriber->profile)
                            <div class="flex justify-between items-center py-1 border-b">
                                <span class="text-gray-500 text-sm">الباقة:</span>
                                <span class="text-gray-800 font-medium">{{ $subscriber->profile }}</span>
                            </div>
                            @endif
                            @if($isOnline && $session?->ip_address)
                            <div class="flex justify-between items-center py-1">
                                <span class="text-gray-500 text-sm">IP:</span>
                                <span class="text-gray-800 font-mono text-sm bg-gray-100 px-2 py-0.5 rounded">{{ $session->ip_address }}</span>
                            </div>
                            @endif
                        </div>
                        
                        <!-- Actions -->
                        <div class="grid grid-cols-2 gap-2">
                            @if($subscriber->phone)
                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $subscriber->phone) }}?text={{ urlencode("مرحباً " . ($subscriber->full_name ?? $subscriber->username) . " 👋\n\n📊 *تفاصيل اشتراكك:*\n\n👤 اسم المستخدم: " . $subscriber->username . "\n📦 الباقة: " . ($subscriber->profile ?? 'غير محدد') . "\n📈 الاستهلاك: " . formatBytes($totalBytes) . "\n   ⬇️ تنزيل: " . formatBytes($bytesIn) . "\n   ⬆️ رفع: " . formatBytes($bytesOut) . "\n✅ الحالة: " . ($subscriber->status === 'active' ? 'نشط' : 'معطل') . ($isOnline ? ' (متصل الآن)' : '') . "\n\nشكراً لاشتراكك معنا 🙏") }}" 
                               target="_blank"
                               class="col-span-2 py-2.5 bg-green-500 hover:bg-green-600 text-white rounded-lg font-bold shadow transition flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                </svg>
                                واتساب
                            </a>
                            @endif
                            @if($subscriber->status === 'active')
                            <button onclick="disconnect({{ $subscriber->id }})" 
                                class="py-2.5 {{ $isOnline ? 'bg-orange-500 hover:bg-orange-600' : 'bg-gray-400 hover:bg-gray-500' }} text-white rounded-lg font-bold shadow transition"
                                title="{{ $isOnline ? 'قطع الاتصال الحالي' : 'المشترك غير متصل' }}">
                                قطع الاتصال
                            </button>
                            @endif
                            
                            @if($subscriber->profile !== 'stop')
                            <button onclick="toggleStatus({{ $subscriber->id }})" 
                                class="py-2.5 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg font-bold shadow transition">
                                إيقاف مؤقت
                            </button>
                            @else
                            <button onclick="toggleStatus({{ $subscriber->id }})" 
                                class="py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-bold shadow transition">
                                استعادة السرعة
                            </button>
                            @endif
                            
                            <button onclick="renewSubscriber({{ $subscriber->id }}, '{{ $subscriber->username }}')" 
                                class="py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg font-bold shadow transition {{ $isOnline || $subscriber->profile === 'stop' ? '' : 'col-span-2' }}">
                                تجديد
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

    @endif
</div>

<!-- Renew Modal -->
<div id="renewModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-emerald-500 to-emerald-600 px-6 py-4">
            <h3 class="text-xl font-bold text-white flex items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                تجديد الاشتراك
            </h3>
            <p class="text-emerald-100 text-sm mt-1" id="renewUsername"></p>
        </div>
        
        <!-- Modal Body -->
        <div class="p-6 space-y-5">
            <!-- Days Selection -->
            <div>
                <label class="block text-gray-700 font-bold mb-3">
                    <svg class="w-4 h-4 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    عدد الأيام
                </label>
                <div class="grid grid-cols-4 gap-2">
                    <button type="button" onclick="selectDays(7)" class="days-btn px-3 py-2 border-2 border-gray-200 rounded-lg text-center hover:border-emerald-500 hover:bg-emerald-50 transition font-bold">
                        7
                    </button>
                    <button type="button" onclick="selectDays(15)" class="days-btn px-3 py-2 border-2 border-gray-200 rounded-lg text-center hover:border-emerald-500 hover:bg-emerald-50 transition font-bold">
                        15
                    </button>
                    <button type="button" onclick="selectDays(30)" class="days-btn px-3 py-2 border-2 border-emerald-500 bg-emerald-50 rounded-lg text-center text-emerald-700 font-bold">
                        30
                    </button>
                    <button type="button" onclick="selectDays(60)" class="days-btn px-3 py-2 border-2 border-gray-200 rounded-lg text-center hover:border-emerald-500 hover:bg-emerald-50 transition font-bold">
                        60
                    </button>
                </div>
                <div class="mt-2">
                    <input type="number" id="customDays" min="1" max="365" value="30" 
                           class="w-full border-2 border-gray-200 rounded-lg px-4 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                           placeholder="أو أدخل عدد مخصص">
                </div>
            </div>

            <!-- Data Limit Selection -->
            <div>
                <label class="block text-gray-700 font-bold mb-3">
                    <svg class="w-4 h-4 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                    </svg>
                    حد البيانات (GB)
                </label>
                <div class="grid grid-cols-5 gap-2">
                    <button type="button" onclick="selectData(0)" class="data-btn px-2 py-2 border-2 border-emerald-500 bg-emerald-50 rounded-lg text-center text-emerald-700 font-bold text-sm">
                        ∞
                    </button>
                    <button type="button" onclick="selectData(10)" class="data-btn px-2 py-2 border-2 border-gray-200 rounded-lg text-center hover:border-emerald-500 hover:bg-emerald-50 transition font-bold text-sm">
                        10
                    </button>
                    <button type="button" onclick="selectData(30)" class="data-btn px-2 py-2 border-2 border-gray-200 rounded-lg text-center hover:border-emerald-500 hover:bg-emerald-50 transition font-bold text-sm">
                        30
                    </button>
                    <button type="button" onclick="selectData(50)" class="data-btn px-2 py-2 border-2 border-gray-200 rounded-lg text-center hover:border-emerald-500 hover:bg-emerald-50 transition font-bold text-sm">
                        50
                    </button>
                    <button type="button" onclick="selectData(100)" class="data-btn px-2 py-2 border-2 border-gray-200 rounded-lg text-center hover:border-emerald-500 hover:bg-emerald-50 transition font-bold text-sm">
                        100
                    </button>
                </div>
                <div class="mt-2">
                    <input type="number" id="customData" min="0" value="0" 
                           class="w-full border-2 border-gray-200 rounded-lg px-4 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                           placeholder="0 = بدون حد">
                </div>
            </div>

            <!-- Reset Consumption -->
            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                <input type="checkbox" id="resetConsumption" class="w-5 h-5 text-emerald-500 rounded focus:ring-emerald-500">
                <label for="resetConsumption" class="text-gray-700 font-medium">
                    تصفير الاستهلاك (البدء من 0)
                </label>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-4 bg-gray-50 flex gap-3">
            <button onclick="confirmRenew()" 
                    class="flex-1 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white py-3 rounded-xl font-bold transition shadow-lg flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                تجديد
            </button>
            <button onclick="closeRenewModal()" 
                    class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl font-bold transition">
                إلغاء
            </button>
        </div>
    </div>
</div>

@if(session('success'))
<div id="toast" class="fixed bottom-20 left-4 right-4 sm:left-auto sm:right-4 sm:w-80 bg-green-500 text-white px-4 py-3 rounded-lg shadow-lg z-50">
    <i class="fas fa-check-circle ml-2"></i>{{ session('success') }}
</div>
@endif

@push('scripts')
<script>
setTimeout(() => { const t = document.getElementById('toast'); if(t) t.remove(); }, 4000);

function syncSessions() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري المزامنة...';
    btn.disabled = true;
    
    fetch('{{ route("subscribers.sync-sessions") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).then(r => r.json()).then(d => { 
        // Show result in a nice modal
        showSyncResult(d);
    }).catch(() => {
        alert('خطأ في المزامنة');
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function showSyncResult(data) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
    modal.innerHTML = `
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
            <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4 text-center">
                <div class="text-4xl mb-2">✅</div>
                <h3 class="text-xl font-bold text-white">تمت المزامنة</h3>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-blue-50 rounded-xl p-4 text-center">
                        <div class="text-2xl font-bold text-blue-600">${data.online || 0}</div>
                        <div class="text-sm text-blue-500">👥 متصل الآن</div>
                    </div>
                    <div class="bg-green-50 rounded-xl p-4 text-center">
                        <div class="text-2xl font-bold text-green-600">${data.synced || 0}</div>
                        <div class="text-sm text-green-500">🔄 تمت مزامنته</div>
                    </div>
                </div>
                <div class="bg-gray-50 rounded-xl p-4">
                    <div class="text-center text-gray-600 text-sm mb-2">استهلاك الجلسات النشطة</div>
                    <div class="flex justify-around">
                        <div class="text-center">
                            <div class="font-bold text-blue-600">${formatBytesJS(data.traffic_in || 0)}</div>
                            <div class="text-xs text-gray-500">⬇️ تنزيل</div>
                        </div>
                        <div class="text-center">
                            <div class="font-bold text-orange-600">${formatBytesJS(data.traffic_out || 0)}</div>
                            <div class="text-xs text-gray-500">⬆️ رفع</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 bg-gray-50">
                <button onclick="this.closest('.fixed').remove(); location.reload();" 
                        class="w-full bg-blue-500 hover:bg-blue-600 text-white py-3 rounded-xl font-bold transition">
                    حسناً
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

function formatBytesJS(bytes) {
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
    return bytes + ' B';
}

function disconnect(id) {
    if(!confirm('قطع اتصال المشترك؟ سيتم إعادة الاتصال تلقائياً.')) return;
    fetch(`/subscribers/${id}/disconnect`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).then(r => r.json()).then(d => { 
        if(d.success) {
            showNotification('✅ ' + d.message, 'success');
        } else {
            showNotification('❌ ' + d.message, 'error');
        }
    }).catch(() => showNotification('❌ خطأ في الاتصال', 'error'));
}

function showNotification(message, type = 'success') {
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        info: 'bg-blue-500'
    };
    const notification = document.createElement('div');
    notification.className = `fixed top-4 left-1/2 transform -translate-x-1/2 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50`;
    notification.innerHTML = message;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3000);
}

function toggleStatus(id) {
    if(!confirm('تغيير بروفايل المشترك؟ (إيقاف مؤقت / استعادة السرعة)')) return;
    fetch(`/subscribers/${id}/toggle`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).then(r => r.json()).then(d => { 
        alert(d.message);
        if(d.success) location.reload(); 
    }).catch(() => alert('خطأ'));
}

function renewSubscriber(id, username) {
    currentRenewId = id;
    document.getElementById('renewUsername').textContent = username || 'المشترك #' + id;
    document.getElementById('renewModal').classList.remove('hidden');
    document.getElementById('renewModal').classList.add('flex');
}

function closeRenewModal() {
    document.getElementById('renewModal').classList.add('hidden');
    document.getElementById('renewModal').classList.remove('flex');
    currentRenewId = null;
}

let currentRenewId = null;

function selectDays(days) {
    document.getElementById('customDays').value = days;
    document.querySelectorAll('.days-btn').forEach(btn => {
        btn.classList.remove('border-emerald-500', 'bg-emerald-50', 'text-emerald-700');
        btn.classList.add('border-gray-200');
    });
    event.target.classList.remove('border-gray-200');
    event.target.classList.add('border-emerald-500', 'bg-emerald-50', 'text-emerald-700');
}

function selectData(gb) {
    document.getElementById('customData').value = gb;
    document.querySelectorAll('.data-btn').forEach(btn => {
        btn.classList.remove('border-emerald-500', 'bg-emerald-50', 'text-emerald-700');
        btn.classList.add('border-gray-200');
    });
    event.target.classList.remove('border-gray-200');
    event.target.classList.add('border-emerald-500', 'bg-emerald-50', 'text-emerald-700');
}

function confirmRenew() {
    if(!currentRenewId) return;
    
    const days = document.getElementById('customDays').value || 30;
    const dataLimit = document.getElementById('customData').value || 0;
    const resetConsumption = document.getElementById('resetConsumption').checked;
    
    fetch(`/subscribers/${currentRenewId}/renew`, {
        method: 'POST',
        headers: { 
            'X-CSRF-TOKEN': '{{ csrf_token() }}', 
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ days, data_limit: dataLimit, reset_consumption: resetConsumption })
    }).then(r => r.json()).then(d => { 
        alert(d.message); 
        if(d.success) location.reload(); 
    }).catch(() => alert('خطأ في التجديد'));
    
    closeRenewModal();
}

function deleteSubscriber(id) {
    if(!confirm('حذف المشترك نهائياً؟')) return;
    fetch(`/subscribers/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).then(r => r.json()).then(d => { if(d.success) location.reload(); else alert(d.message); }).catch(() => alert('خطأ'));
}

// Backup Modal Functions
function openBackupModal() {
    document.getElementById('pppBackupModal').classList.remove('hidden');
}

function closeBackupModal() {
    document.getElementById('pppBackupModal').classList.add('hidden');
}

// Export PPP Backup
function exportPppBackup() {
    const routerId = document.getElementById('pppBackupRouter').value;
    const btn = document.querySelector('#pppBackupModal button[onclick="exportPppBackup()"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i> جاري التصدير...';
    btn.disabled = true;
    
    fetch(`{{ url('subscribers/backup/export') }}?router_id=${routerId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const blob = new Blob([JSON.stringify(data.backup, null, 2)], { type: 'application/json' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `broadband_backup_${data.router_name}_${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                a.remove();
                
                showToast(`تم تصدير ${data.backup.users.length} مشترك بنجاح`, 'success');
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

// Import PPP Backup
function importPppBackup() {
    document.getElementById('pppBackupFile').click();
}

function handlePppBackupFile(input) {
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
            
            if (confirm(`هل تريد استعادة ${count} مشترك من نسخة "${routerName}" بتاريخ ${backupDate}؟\n\nسيتم إضافتهم للراوتر المحدد.`)) {
                restorePppBackup(backup);
            }
        } catch (err) {
            showToast('ملف غير صالح - يجب أن يكون JSON', 'error');
        }
    };
    
    reader.readAsText(file);
    input.value = '';
}

function restorePppBackup(backup) {
    const routerId = document.getElementById('pppBackupRouter').value;
    const btn = document.querySelector('#pppBackupModal button[onclick="importPppBackup()"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i> جاري الاستعادة...';
    btn.disabled = true;
    
    fetch('{{ route("subscribers.backup.import") }}', {
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
            setTimeout(() => location.reload(), 1500);
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

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `fixed top-4 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-lg shadow-lg z-50 ${type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500'} text-white font-medium`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
</script>

<!-- PPP Backup Modal -->
<div id="pppBackupModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden">
        <div class="bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fas fa-database"></i>
                نسخ احتياطي - البرودباند
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
                <select id="pppBackupRouter" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition">
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
                            <li>النسخ الاحتياطي يشمل: اسم المستخدم، كلمة المرور، الباقة، العنوان</li>
                            <li>الاستعادة تضيف المشتركين للراوتر مع نفس البيانات</li>
                            <li>مفيد قبل فورمات الراوتر</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <input type="file" id="pppBackupFile" accept=".json" class="hidden" onchange="handlePppBackupFile(this)">
            
            <div class="grid grid-cols-2 gap-3">
                <button onclick="exportPppBackup()"
                    class="flex items-center justify-center gap-2 px-4 py-3 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white rounded-xl font-medium transition shadow-md">
                    <i class="fas fa-download"></i>
                    تصدير نسخة
                </button>
                <button onclick="importPppBackup()"
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

@php
function formatBytes($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}
@endphp
