@extends('layouts.app')

@section('title', 'هوتسبوت')

@section('content')
<div class="space-y-4" x-data="{ expandedId: null }" x-cloak>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="w-10 h-10 bg-gradient-to-br from-orange-500 to-red-600 rounded-xl flex items-center justify-center shadow-lg flex-shrink-0">
                <i class="fas fa-wifi text-white"></i>
            </span>
            <div>
                <h1 class="text-lg lg:text-2xl font-bold text-gray-800">مستخدمين الهوتسبوت</h1>
                <p class="text-gray-500 text-xs lg:text-sm">إدارة بطاقات Hotspot</p>
            </div>
        </div>
        @if($routers->isNotEmpty())
        <div class="flex items-center gap-2">
            <button onclick="openBackupModal()" 
                class="group flex items-center justify-center gap-1.5 px-3 py-2 lg:px-4 lg:py-2.5 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white rounded-xl transition-all duration-300 shadow-md hover:shadow-lg hover:scale-105">
                <i class="fas fa-download text-sm"></i>
                <span class="font-medium text-sm hidden sm:inline">نسخ احتياطي</span>
            </button>
            <button onclick="syncAll()" 
                class="group flex items-center justify-center gap-1.5 px-3 py-2 lg:px-4 lg:py-2.5 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white rounded-xl transition-all duration-300 shadow-md hover:shadow-lg hover:scale-105">
                <i class="fas fa-sync text-sm group-hover:animate-spin"></i>
                <span class="font-medium text-sm hidden sm:inline">مزامنة</span>
            </button>
            <a href="{{ route('hotspot.cards') }}" 
                class="group flex items-center justify-center gap-1.5 px-3 py-2 lg:px-4 lg:py-2.5 bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white rounded-xl transition-all duration-300 shadow-md hover:shadow-lg hover:scale-105">
                <i class="fas fa-id-card text-sm"></i>
                <span class="font-medium text-sm hidden sm:inline">مولد</span>
            </a>
            <a href="{{ route('hotspot.create') }}" 
                class="group flex items-center justify-center gap-1.5 px-3 py-2 lg:px-4 lg:py-2.5 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white rounded-xl transition-all duration-300 shadow-md hover:shadow-lg hover:scale-105">
                <i class="fas fa-plus text-sm"></i>
                <span class="font-medium text-sm hidden sm:inline">إضافة</span>
            </a>
        </div>
        @endif
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 lg:gap-3">
        <!-- الإجمالي -->
        <div class="group bg-gradient-to-br from-blue-50 to-white rounded-xl p-2.5 sm:p-3 lg:p-4 shadow-sm border border-blue-100 hover:border-blue-400 hover:shadow-lg sm:hover:scale-105 transition-all duration-300 cursor-pointer">
            <div class="flex sm:flex-col items-center sm:items-center gap-2 sm:gap-0 sm:text-center">
                <div class="w-8 h-8 sm:w-8 sm:h-8 lg:w-10 lg:h-10 bg-blue-500 rounded-full flex items-center justify-center sm:mx-auto sm:mb-1.5 flex-shrink-0">
                    <i class="fas fa-id-card text-white text-xs lg:text-sm"></i>
                </div>
                <div class="flex-1 sm:flex-none">
                    <p class="text-base sm:text-lg lg:text-2xl font-bold text-blue-600">{{ $stats['total'] ?? 0 }}</p>
                    <p class="text-[10px] lg:text-xs text-gray-500 font-medium">الإجمالي</p>
                </div>
            </div>
        </div>
        
        <!-- قيد الاستخدام -->
        <div class="group bg-gradient-to-br from-cyan-50 to-white rounded-xl p-2.5 sm:p-3 lg:p-4 shadow-sm border border-cyan-100 hover:border-cyan-400 hover:shadow-lg sm:hover:scale-105 transition-all duration-300 cursor-pointer" title="بطاقات قيد الاستخدام ولم تكتمل">
            <div class="flex sm:flex-col items-center sm:items-center gap-2 sm:gap-0 sm:text-center">
                <div class="w-8 h-8 sm:w-8 sm:h-8 lg:w-10 lg:h-10 bg-cyan-500 rounded-full flex items-center justify-center sm:mx-auto sm:mb-1.5 flex-shrink-0">
                    <i class="fas fa-spinner text-white text-xs lg:text-sm"></i>
                </div>
                <div class="flex-1 sm:flex-none">
                    <p class="text-base sm:text-lg lg:text-2xl font-bold text-cyan-600">{{ $stats['inUse'] ?? 0 }}</p>
                    <p class="text-[10px] lg:text-xs text-gray-500 font-medium">قيد الاستخدام</p>
                </div>
            </div>
        </div>
        
        <!-- مستهلكة بالكامل -->
        <div class="group bg-gradient-to-br from-orange-50 to-white rounded-xl p-2.5 sm:p-3 lg:p-4 shadow-sm border border-orange-200 hover:border-orange-400 hover:shadow-lg sm:hover:scale-105 transition-all duration-300 cursor-pointer" onclick="deleteUsed()" title="بطاقات وصلت للحد الأقصى - اضغط للحذف">
            <div class="flex sm:flex-col items-center sm:items-center gap-2 sm:gap-0 sm:text-center">
                <div class="w-8 h-8 sm:w-8 sm:h-8 lg:w-10 lg:h-10 bg-orange-500 rounded-full flex items-center justify-center sm:mx-auto sm:mb-1.5 flex-shrink-0">
                    <i class="fas fa-battery-empty text-white text-xs lg:text-sm"></i>
                </div>
                <div class="flex-1 sm:flex-none">
                    <p class="text-base sm:text-lg lg:text-2xl font-bold text-orange-600">{{ $stats['consumed'] ?? 0 }}</p>
                    <p class="text-[10px] lg:text-xs text-gray-500 font-medium">مستهلكة</p>
                </div>
            </div>
        </div>
        
        <!-- غير مستخدمة -->
        <div class="group bg-gradient-to-br from-gray-50 to-white rounded-xl p-2.5 sm:p-3 lg:p-4 shadow-sm border border-gray-200 hover:border-gray-400 hover:shadow-lg sm:hover:scale-105 transition-all duration-300 cursor-pointer" onclick="deleteUnused()" title="بطاقات لم تستخدم أبداً - اضغط للحذف">
            <div class="flex sm:flex-col items-center sm:items-center gap-2 sm:gap-0 sm:text-center">
                <div class="w-8 h-8 sm:w-8 sm:h-8 lg:w-10 lg:h-10 bg-gray-500 rounded-full flex items-center justify-center sm:mx-auto sm:mb-1.5 flex-shrink-0">
                    <i class="fas fa-box text-white text-xs lg:text-sm"></i>
                </div>
                <div class="flex-1 sm:flex-none">
                    <p class="text-base sm:text-lg lg:text-2xl font-bold text-gray-600">{{ $stats['unused'] ?? 0 }}</p>
                    <p class="text-[10px] lg:text-xs text-gray-500 font-medium">غير مستخدمة</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="bg-white rounded-xl p-3 sm:p-4 shadow-sm border" x-data="{ showFilters: false }">
        <form method="GET" class="space-y-3">
            <!-- Search Bar -->
            <div class="flex flex-col sm:flex-row gap-2">
                <div class="flex-1 relative">
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="بحث باسم المستخدم أو الهاتف..." 
                        class="w-full pr-10 pl-4 py-3 border-2 border-gray-200 rounded-xl text-base focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition">
                </div>
                
                <!-- Buttons Row -->
                <div class="flex gap-2">
                    <!-- Filter Toggle Button -->
                    <button type="button" @click="showFilters = !showFilters" 
                        class="flex-1 sm:flex-none px-4 py-3 border-2 rounded-xl transition flex items-center justify-center gap-2"
                        :class="showFilters || '{{ request('router_id') || request('status') }}' ? 'border-orange-500 bg-orange-50 text-orange-600' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
                        <i class="fas fa-filter"></i>
                        <span>فلتر</span>
                        @if(request('router_id') || request('status'))
                        <span class="w-5 h-5 bg-orange-500 text-white text-xs rounded-full flex items-center justify-center">
                            {{ (request('router_id') ? 1 : 0) + (request('status') ? 1 : 0) }}
                        </span>
                        @endif
                    </button>
                    
                    <!-- Search Button -->
                    <button type="submit" class="flex-1 sm:flex-none px-5 py-3 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white rounded-xl transition shadow-md flex items-center justify-center gap-2">
                        <i class="fas fa-search"></i>
                        <span>بحث</span>
                    </button>
                </div>
            </div>
            
            <!-- Filters Panel -->
            <div x-show="showFilters" x-collapse x-cloak class="pt-3 border-t border-gray-200">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- Router Filter -->
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1.5">
                            <i class="fas fa-router ml-1"></i> الراوتر
                        </label>
                        <select name="router_id" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl text-base focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition bg-white">
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
                        <select name="status" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl text-base focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition bg-white">
                            <option value="">الكل</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>✅ نشط</option>
                            <option value="disabled" {{ request('status') == 'disabled' ? 'selected' : '' }}>🚫 معطل</option>
                        </select>
                    </div>
                </div>
                
                <!-- Clear Filters -->
                @if(request('router_id') || request('status') || request('search'))
                <div class="mt-3 flex justify-end">
                    <a href="{{ route('hotspot.index') }}" 
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
            <i class="fas fa-wifi text-gray-300 text-4xl mb-4"></i>
            <p class="text-gray-500 mb-4">لا يوجد مستخدمين</p>
            @if($routers->isNotEmpty())
            <button onclick="syncAll()" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm">
                <i class="fas fa-sync ml-2"></i>مزامنة من الراوتر
            </button>
            @endif
        </div>
    @else
        <!-- Desktop Table View -->
        <div class="hidden lg:block bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-right px-4 py-3 text-sm font-medium text-gray-600">المستخدم</th>
                        <th class="text-right px-4 py-3 text-sm font-medium text-gray-600">الحالة</th>
                        <th class="text-right px-4 py-3 text-sm font-medium text-gray-600">حالة الاستهلاك</th>
                        <th class="text-right px-4 py-3 text-sm font-medium text-gray-600">الباقة</th>
                        <th class="text-right px-4 py-3 text-sm font-medium text-gray-600">الراوتر</th>
                        <th class="text-right px-4 py-3 text-sm font-medium text-gray-600">الاستهلاك</th>
                        <th class="text-center px-4 py-3 text-sm font-medium text-gray-600">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($subscribers as $subscriber)
                    @php
                        $isOnline = $subscriber->activeSessions->isNotEmpty();
                        $bytesIn = $subscriber->bytes_in ?? 0;
                        $bytesOut = $subscriber->bytes_out ?? 0;
                        $totalUsed = $bytesIn + $bytesOut;
                        $limitBytes = $subscriber->limit_bytes_total ?? 0;
                        
                        // تحديد حالة الاستهلاك
                        if ($totalUsed == 0) {
                            $consumptionStatus = 'unused'; // غير مستخدمة
                            $consumptionLabel = 'غير مستخدمة';
                            $consumptionColor = 'gray';
                            $consumptionIcon = 'fa-circle';
                        } elseif ($limitBytes > 0 && $totalUsed >= $limitBytes) {
                            $consumptionStatus = 'fully_consumed'; // مستهلكة بالكامل
                            $consumptionLabel = 'مستهلكة بالكامل';
                            $consumptionColor = 'orange';
                            $consumptionIcon = 'fa-check-circle';
                        } elseif ($subscriber->status === 'disabled' && $totalUsed > 0) {
                            $consumptionStatus = 'fully_consumed'; // معطلة ومستخدمة
                            $consumptionLabel = 'منتهية';
                            $consumptionColor = 'red';
                            $consumptionIcon = 'fa-times-circle';
                        } else {
                            $consumptionStatus = 'in_use'; // قيد الاستخدام
                            $consumptionLabel = 'قيد الاستخدام';
                            $consumptionColor = 'cyan';
                            $consumptionIcon = 'fa-spinner';
                        }
                        
                        // حساب نسبة الاستهلاك
                        $usagePercent = $limitBytes > 0 ? min(100, round(($totalUsed / $limitBytes) * 100)) : 0;
                    @endphp
                    <tr class="hover:bg-gray-50 {{ $consumptionStatus === 'fully_consumed' ? 'bg-orange-50/50' : ($consumptionStatus === 'unused' ? 'bg-gray-50/50' : '') }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="relative">
                                    <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center text-orange-600 font-bold">
                                        {{ mb_substr($subscriber->username, 0, 1) }}
                                    </div>
                                    @if($isOnline)
                                    <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-green-500 border-2 border-white rounded-full"></span>
                                    @endif
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800">{{ $subscriber->username }}</p>
                                    <p class="text-xs text-gray-500">{{ $subscriber->full_name ?? $subscriber->phone ?? '-' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if($isOnline)
                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs">
                                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                                    متصل
                                </span>
                            @elseif($subscriber->status === 'active')
                                <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-xs">نشط</span>
                            @else
                                <span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs">معطل</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-{{ $consumptionColor }}-100 text-{{ $consumptionColor }}-700 rounded-full text-xs font-medium">
                                    <i class="fas {{ $consumptionIcon }} {{ $consumptionStatus === 'in_use' ? 'animate-spin' : '' }}"></i>
                                    {{ $consumptionLabel }}
                                </span>
                                @if($limitBytes > 0)
                                <div class="w-16 bg-gray-200 rounded-full h-1.5" title="{{ $usagePercent }}% مستخدم">
                                    <div class="h-1.5 rounded-full {{ $usagePercent >= 100 ? 'bg-red-500' : ($usagePercent >= 80 ? 'bg-orange-500' : 'bg-cyan-500') }}" style="width: {{ $usagePercent }}%"></div>
                                </div>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $subscriber->profile ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $subscriber->router->name ?? '-' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-purple-50 text-purple-700 rounded-lg text-sm font-bold">
                                <i class="fas fa-chart-pie text-xs"></i>
                                {{ formatBytes($bytesIn + $bytesOut) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1.5">
                                @if($subscriber->phone)
                                <button onclick="sendHotspotWhatsApp('{{ $subscriber->phone }}', '{{ $subscriber->username }}', {{ number_format(($subscriber->limit_bytes_total ?? 0) / 1073741824, 2, '.', '') }}, {{ number_format(($subscriber->bytes_in + $subscriber->bytes_out) / 1073741824, 2, '.', '') }}, '{{ $subscriber->router->whatsapp_type ?? 'regular' }}')" 
                                    class="group w-9 h-9 bg-gradient-to-br from-green-400 to-green-500 hover:from-green-500 hover:to-green-600 text-white rounded-lg shadow-sm hover:shadow-md transition-all duration-200 hover:scale-110 flex items-center justify-center" 
                                    title="إرسال واتساب">
                                    <i class="fab fa-whatsapp text-sm"></i>
                                </button>
                                @endif
                                @if($isOnline)
                                <button onclick="disconnectUser({{ $subscriber->id }})" 
                                    class="group w-9 h-9 bg-gradient-to-br from-orange-400 to-orange-500 hover:from-orange-500 hover:to-orange-600 text-white rounded-lg shadow-sm hover:shadow-md transition-all duration-200 hover:scale-110 flex items-center justify-center" 
                                    title="قطع الاتصال">
                                    <i class="fas fa-plug text-sm group-hover:animate-pulse"></i>
                                </button>
                                @endif
                                <button onclick="toggleUser({{ $subscriber->id }})" 
                                    class="group w-9 h-9 {{ $subscriber->status === 'active' ? 'bg-gradient-to-br from-red-400 to-red-500 hover:from-red-500 hover:to-red-600' : 'bg-gradient-to-br from-green-400 to-green-500 hover:from-green-500 hover:to-green-600' }} text-white rounded-lg shadow-sm hover:shadow-md transition-all duration-200 hover:scale-110 flex items-center justify-center" 
                                    title="{{ $subscriber->status === 'active' ? 'تعطيل' : 'تفعيل' }}">
                                    <i class="fas {{ $subscriber->status === 'active' ? 'fa-ban' : 'fa-check' }} text-sm"></i>
                                </button>
                                <a href="{{ route('hotspot.edit', $subscriber) }}" 
                                    class="group w-9 h-9 bg-gradient-to-br from-blue-400 to-blue-500 hover:from-blue-500 hover:to-blue-600 text-white rounded-lg shadow-sm hover:shadow-md transition-all duration-200 hover:scale-110 flex items-center justify-center" 
                                    title="تعديل">
                                    <i class="fas fa-edit text-sm"></i>
                                </a>
                                <button onclick="deleteUser({{ $subscriber->id }})" 
                                    class="group w-9 h-9 bg-gradient-to-br from-gray-400 to-gray-500 hover:from-red-500 hover:to-red-600 text-white rounded-lg shadow-sm hover:shadow-md transition-all duration-200 hover:scale-110 flex items-center justify-center" 
                                    title="حذف">
                                    <i class="fas fa-trash text-sm"></i>
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
            @foreach($subscribers as $subscriber)
            @php
                $isOnline = $subscriber->activeSessions->isNotEmpty();
                $session = $subscriber->activeSessions->first();
                $bytesIn = $subscriber->bytes_in ?? 0;
                $bytesOut = $subscriber->bytes_out ?? 0;
                $totalBytes = $bytesIn + $bytesOut;
                $limitBytes = $subscriber->limit_bytes_total ?? 0;
                
                // تحديد حالة الاستهلاك
                if ($totalBytes == 0) {
                    $mConsumptionStatus = 'unused';
                    $mConsumptionLabel = 'غير مستخدمة';
                    $mConsumptionColor = 'gray';
                    $mConsumptionIcon = 'fa-circle';
                    $mBorderColor = 'border-gray-200';
                } elseif ($limitBytes > 0 && $totalBytes >= $limitBytes) {
                    $mConsumptionStatus = 'fully_consumed';
                    $mConsumptionLabel = 'مستهلكة بالكامل';
                    $mConsumptionColor = 'orange';
                    $mConsumptionIcon = 'fa-check-circle';
                    $mBorderColor = 'border-orange-300';
                } elseif ($subscriber->status === 'disabled' && $totalBytes > 0) {
                    $mConsumptionStatus = 'fully_consumed';
                    $mConsumptionLabel = 'منتهية';
                    $mConsumptionColor = 'red';
                    $mConsumptionIcon = 'fa-times-circle';
                    $mBorderColor = 'border-red-300';
                } else {
                    $mConsumptionStatus = 'in_use';
                    $mConsumptionLabel = 'قيد الاستخدام';
                    $mConsumptionColor = 'cyan';
                    $mConsumptionIcon = 'fa-spinner';
                    $mBorderColor = 'border-cyan-300';
                }
                
                $mUsagePercent = $limitBytes > 0 ? min(100, round(($totalBytes / $limitBytes) * 100)) : 0;
            @endphp
            <div class="bg-white rounded-xl shadow-sm border-2 {{ $mBorderColor }} overflow-hidden {{ $mConsumptionStatus === 'fully_consumed' ? 'bg-orange-50/30' : '' }}">
                <!-- Card Header - Always Visible -->
                <div class="p-3 cursor-pointer" @click="expandedId = expandedId === {{ $subscriber->id }} ? null : {{ $subscriber->id }}">
                    <div class="flex items-center gap-3">
                        <div class="relative flex-shrink-0">
                            <div class="w-12 h-12 bg-gradient-to-br from-{{ $mConsumptionColor }}-400 to-{{ $mConsumptionColor }}-500 rounded-full flex items-center justify-center text-white font-bold shadow">
                                {{ mb_substr($subscriber->username, 0, 1) }}
                            </div>
                            @if($isOnline)
                            <span class="absolute -bottom-0.5 -right-0.5 w-4 h-4 bg-green-500 border-2 border-white rounded-full animate-pulse"></span>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="font-bold text-gray-800 truncate">{{ $subscriber->username }}</p>
                                @if($isOnline)
                                    <span class="flex-shrink-0 px-2 py-0.5 bg-green-500 text-white text-[10px] rounded-full font-bold">متصل</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-xs text-gray-500">{{ $subscriber->profile ?? 'بدون باقة' }}</span>
                                <span class="text-purple-600 font-bold text-sm">{{ formatBytes($totalBytes) }}</span>
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-1">
                            <span class="px-2 py-0.5 text-xs rounded-full font-bold {{ $subscriber->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $subscriber->status === 'active' ? 'نشط' : 'معطل' }}
                            </span>
                            <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform" :class="expandedId === {{ $subscriber->id }} ? 'rotate-180' : ''"></i>
                        </div>
                    </div>
                    
                    <!-- Progress Bar - Always Visible -->
                    @if($limitBytes > 0)
                    <div class="mt-3">
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="inline-flex items-center gap-1 text-{{ $mConsumptionColor }}-600 font-medium">
                                <i class="fas {{ $mConsumptionIcon }} {{ $mConsumptionStatus === 'in_use' ? 'animate-spin' : '' }} text-[10px]"></i>
                                {{ $mConsumptionLabel }}
                            </span>
                            <span class="text-gray-600 font-bold">{{ $mUsagePercent }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                            <div class="h-2.5 rounded-full transition-all duration-500 {{ $mUsagePercent >= 100 ? 'bg-gradient-to-r from-red-400 to-red-500' : ($mUsagePercent >= 80 ? 'bg-gradient-to-r from-orange-400 to-orange-500' : 'bg-gradient-to-r from-cyan-400 to-cyan-500') }}" style="width: {{ $mUsagePercent }}%"></div>
                        </div>
                        <div class="flex justify-between text-[10px] text-gray-400 mt-0.5">
                            <span>{{ formatBytes($totalBytes) }}</span>
                            <span>{{ formatBytes($limitBytes) }}</span>
                        </div>
                    </div>
                    @else
                    <div class="mt-2 flex items-center gap-2">
                        <span class="inline-flex items-center gap-1 px-2 py-1 bg-{{ $mConsumptionColor }}-100 text-{{ $mConsumptionColor }}-700 rounded-lg text-xs font-medium">
                            <i class="fas {{ $mConsumptionIcon }} {{ $mConsumptionStatus === 'in_use' ? 'animate-spin' : '' }} text-[10px]"></i>
                            {{ $mConsumptionLabel }}
                        </span>
                    </div>
                    @endif
                </div>
                
                <!-- Card Details - Expandable -->
                <div x-show="expandedId === {{ $subscriber->id }}" x-collapse class="border-t-2 border-gray-100 bg-gray-50">
                    <div class="p-3 space-y-3">
                        
                        <!-- Traffic Info -->
                        <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl p-4 text-center text-white shadow">
                            <i class="fas fa-chart-pie text-2xl mb-1"></i>
                            <p class="text-2xl font-bold">{{ formatBytes($totalBytes) }}</p>
                            <p class="text-purple-200 text-xs">إجمالي الاستهلاك</p>
                        </div>
                        
                        <!-- Details -->
                        <div class="bg-white rounded-xl p-3 space-y-2">
                            @if($subscriber->full_name)
                            <div class="flex justify-between">
                                <span class="text-gray-500 text-sm">الاسم:</span>
                                <span class="text-gray-800 font-medium">{{ $subscriber->full_name }}</span>
                            </div>
                            @endif
                            @if($subscriber->phone)
                            <div class="flex justify-between">
                                <span class="text-gray-500 text-sm">الهاتف:</span>
                                <span class="text-gray-800 font-medium">{{ $subscriber->phone }}</span>
                            </div>
                            @endif
                            <div class="flex justify-between">
                                <span class="text-gray-500 text-sm">الراوتر:</span>
                                <span class="text-gray-800 font-medium">{{ $subscriber->router->name ?? '-' }}</span>
                            </div>
                            @if($isOnline && $session?->ip_address)
                            <div class="flex justify-between">
                                <span class="text-gray-500 text-sm">IP:</span>
                                <span class="text-gray-800 font-mono text-xs bg-gray-100 px-2 py-0.5 rounded">{{ $session->ip_address }}</span>
                            </div>
                            @endif
                            @if($isOnline && $session?->mac_address)
                            <div class="flex justify-between">
                                <span class="text-gray-500 text-sm">MAC:</span>
                                <span class="text-gray-800 font-mono text-xs bg-gray-100 px-2 py-0.5 rounded">{{ $session->mac_address }}</span>
                            </div>
                            @endif
                        </div>
                        
                        <!-- Actions -->
                        @php
                            $hasPhone = !empty($subscriber->phone);
                            $actionCols = 3 + ($isOnline ? 1 : 0) + ($hasPhone ? 1 : 0);
                        @endphp
                        <div class="grid grid-cols-{{ $actionCols }} gap-2 pt-3 border-t border-gray-200">
                            @if($hasPhone)
                            <button onclick="sendHotspotWhatsApp('{{ $subscriber->phone }}', '{{ $subscriber->username }}', {{ number_format(($subscriber->limit_bytes_total ?? 0) / 1073741824, 2, '.', '') }}, {{ number_format(($subscriber->bytes_in + $subscriber->bytes_out) / 1073741824, 2, '.', '') }}, '{{ $subscriber->router->whatsapp_type ?? 'regular' }}')" 
                                class="flex flex-col items-center gap-1 py-3 bg-gradient-to-br from-green-400 to-green-500 hover:from-green-500 hover:to-green-600 text-white rounded-xl shadow-md hover:shadow-lg transition-all duration-200 active:scale-95">
                                <i class="fab fa-whatsapp text-lg"></i>
                                <span class="text-xs font-medium">واتساب</span>
                            </button>
                            @endif
                            @if($isOnline)
                            <button onclick="disconnectUser({{ $subscriber->id }})" 
                                class="flex flex-col items-center gap-1 py-3 bg-gradient-to-br from-orange-400 to-orange-500 hover:from-orange-500 hover:to-orange-600 text-white rounded-xl shadow-md hover:shadow-lg transition-all duration-200 active:scale-95">
                                <i class="fas fa-plug text-lg"></i>
                                <span class="text-xs font-medium">قطع</span>
                            </button>
                            @endif
                            <button onclick="toggleUser({{ $subscriber->id }})" 
                                class="flex flex-col items-center gap-1 py-3 {{ $subscriber->status === 'active' ? 'bg-gradient-to-br from-red-400 to-red-500 hover:from-red-500 hover:to-red-600' : 'bg-gradient-to-br from-green-400 to-green-500 hover:from-green-500 hover:to-green-600' }} text-white rounded-xl shadow-md hover:shadow-lg transition-all duration-200 active:scale-95">
                                <i class="fas {{ $subscriber->status === 'active' ? 'fa-ban' : 'fa-check' }} text-lg"></i>
                                <span class="text-xs font-medium">{{ $subscriber->status === 'active' ? 'تعطيل' : 'تفعيل' }}</span>
                            </button>
                            <a href="{{ route('hotspot.edit', $subscriber) }}" 
                                class="flex flex-col items-center gap-1 py-3 bg-gradient-to-br from-blue-400 to-blue-500 hover:from-blue-500 hover:to-blue-600 text-white rounded-xl shadow-md hover:shadow-lg transition-all duration-200 active:scale-95">
                                <i class="fas fa-edit text-lg"></i>
                                <span class="text-xs font-medium">تعديل</span>
                            </a>
                            <button onclick="deleteUser({{ $subscriber->id }})" 
                                class="flex flex-col items-center gap-1 py-3 bg-gradient-to-br from-gray-400 to-gray-500 hover:from-red-500 hover:to-red-600 text-white rounded-xl shadow-md hover:shadow-lg transition-all duration-200 active:scale-95">
                                <i class="fas fa-trash text-lg"></i>
                                <span class="text-xs font-medium">حذف</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="mt-4 px-2">
            {{ $subscribers->links() }}
        </div>
        @endif
</div>

@if(session('success'))
<div id="toast" class="fixed bottom-20 left-4 right-4 sm:left-auto sm:right-4 sm:w-80 bg-green-500 text-white px-4 py-3 rounded-lg shadow-lg z-50">
    <i class="fas fa-check-circle ml-2"></i>{{ session('success') }}
</div>
@endif

@push('scripts')
<script>
setTimeout(() => { const t = document.getElementById('toast'); if(t) t.remove(); }, 4000);

function syncAll() {
    if(!confirm('مزامنة مستخدمي الهوتسبوت؟')) return;
    const routers = @json($routers->pluck('id'));
    let completed = 0;
    routers.forEach(id => {
        fetch(`/hotspot/${id}/sync`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
        }).finally(() => { completed++; if(completed === routers.length) location.reload(); });
    });
}

function disconnectUser(id) {
    if(!confirm('قطع اتصال المستخدم؟')) return;
    fetch(`/hotspot/${id}/disconnect`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).then(r => r.json()).then(d => { if(d.success) location.reload(); else alert(d.message); }).catch(() => alert('خطأ'));
}

function toggleUser(id) {
    if(!confirm('تغيير حالة المستخدم؟')) return;
    fetch(`/hotspot/${id}/toggle`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).then(r => r.json()).then(d => { if(d.success) location.reload(); else alert(d.message); }).catch(() => alert('خطأ'));
}

function deleteUser(id) {
    if(!confirm('حذف المستخدم نهائياً؟')) return;
    fetch(`/hotspot/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).then(r => r.json()).then(d => { if(d.success) location.reload(); else alert(d.message); }).catch(() => alert('خطأ'));
}

function deleteUsed() {
    if(!confirm('هل أنت متأكد من حذف البطاقات المستهلكة بالكامل فقط؟\n\n⚠️ سيتم حذف البطاقات التي وصلت للحد الأقصى للاستخدام فقط.\n✅ البطاقات قيد الاستخدام (لم تكتمل) لن يتم حذفها.\n\nهذا الإجراء لا يمكن التراجع عنه!')) return;
    fetch('/hotspot/delete-used', {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).then(r => r.json()).then(d => { 
        alert(d.message); 
        if(d.success) location.reload(); 
    }).catch(() => alert('حدث خطأ'));
}

function deleteUnused() {
    if(!confirm('هل أنت متأكد من حذف جميع البطاقات غير المستخدمة؟\nهذا الإجراء لا يمكن التراجع عنه!')) return;
    fetch('/hotspot/delete-unused', {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).then(r => r.json()).then(d => { 
        alert(d.message); 
        if(d.success) location.reload(); 
    }).catch(() => alert('حدث خطأ'));
}

// Send WhatsApp message for Hotspot user
function sendHotspotWhatsApp(phone, username, limitGb, usedGb, whatsappType) {
    // Format phone number
    let formattedPhone = phone.replace(/[\s\-]/g, '');
    if (formattedPhone.startsWith('0')) {
        formattedPhone = '963' + formattedPhone.substring(1);
    }
    if (!formattedPhone.startsWith('+') && !formattedPhone.startsWith('963')) {
        formattedPhone = '963' + formattedPhone;
    }
    
    // Calculate remaining
    const remainingGb = Math.max(0, limitGb - usedGb).toFixed(2);
    const usagePercent = limitGb > 0 ? Math.min(100, (usedGb / limitGb * 100)).toFixed(0) : 0;
    
    // Create message
    let message = `مرحباً 👋\n\n`;
    message += `📊 معلومات بطاقتك:\n`;
    message += `👤 اسم المستخدم: ${username}\n`;
    if (limitGb > 0) {
        message += `📦 الباقة: ${limitGb} GB\n`;
        message += `📈 المستهلك: ${usedGb} GB (${usagePercent}%)\n`;
        message += `📉 المتبقي: ${remainingGb} GB\n`;
    }
    message += `\n🔍 تفقد رصيدك من هنا:\nhttps://megawifi.site/check-balance\n\n`;
    message += `شكراً لاستخدامكم خدماتنا ✨\nMegaWiFi`;
    
    openWhatsappDirect(formattedPhone, encodeURIComponent(message), whatsappType);
}

// Open WhatsApp directly
function openWhatsappDirect(phone, message, type) {
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

// Backup Modal Functions
function openBackupModal() {
    document.getElementById('backupModal').classList.remove('hidden');
}

function closeBackupModal() {
    document.getElementById('backupModal').classList.add('hidden');
}

// Export/Backup
function exportBackup() {
    const routerId = document.getElementById('backupRouter').value;
    const btn = document.querySelector('#backupModal button[onclick="exportBackup()"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i> جاري التصدير...';
    btn.disabled = true;
    
    fetch(`{{ url('hotspot/backup/export') }}?router_id=${routerId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Download as JSON file
                const blob = new Blob([JSON.stringify(data.backup, null, 2)], { type: 'application/json' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `hotspot_backup_${data.router_name}_${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                a.remove();
                
                showToast(`تم تصدير ${data.backup.cards.length} بطاقة بنجاح`, 'success');
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

// Import/Restore
function importBackup() {
    document.getElementById('backupFile').click();
}

function handleBackupFile(input) {
    if (!input.files || !input.files[0]) return;
    
    const file = input.files[0];
    const reader = new FileReader();
    
    reader.onload = function(e) {
        try {
            const backup = JSON.parse(e.target.result);
            
            if (!backup.cards || !Array.isArray(backup.cards)) {
                showToast('ملف غير صالح', 'error');
                return;
            }
            
            // Show restore confirmation
            const count = backup.cards.length;
            const routerName = backup.router_name || 'غير معروف';
            const backupDate = backup.backup_date || 'غير معروف';
            
            if (confirm(`هل تريد استعادة ${count} بطاقة من نسخة "${routerName}" بتاريخ ${backupDate}؟\n\nسيتم إضافتها للراوتر المحدد.`)) {
                restoreBackup(backup);
            }
        } catch (err) {
            showToast('ملف غير صالح - يجب أن يكون JSON', 'error');
        }
    };
    
    reader.readAsText(file);
    input.value = ''; // Reset for re-upload
}

function restoreBackup(backup) {
    const routerId = document.getElementById('backupRouter').value;
    const btn = document.querySelector('#backupModal button[onclick="importBackup()"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i> جاري الاستعادة...';
    btn.disabled = true;
    
    fetch('{{ route("hotspot.backup.import") }}', {
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
</script>

<!-- Backup/Restore Modal -->
<div id="backupModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fas fa-database"></i>
                النسخ الاحتياطي والاستعادة
            </h3>
            <button onclick="closeBackupModal()" class="text-white/80 hover:text-white transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <!-- Body -->
        <div class="p-6 space-y-4">
            <!-- Router Selection -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-router text-blue-500 ml-1"></i>
                    اختر الراوتر
                </label>
                <select id="backupRouter" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition">
                    @foreach($routers as $r)
                        <option value="{{ $r->id }}">{{ $r->name }}</option>
                    @endforeach
                </select>
            </div>
            
            <!-- Info Box -->
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-800">
                <div class="flex items-start gap-2">
                    <i class="fas fa-info-circle mt-0.5"></i>
                    <div>
                        <p class="font-semibold mb-1">معلومات:</p>
                        <ul class="list-disc list-inside space-y-1 text-xs">
                            <li>النسخ الاحتياطي يشمل: اسم المستخدم، كلمة المرور، الباقة، الاستهلاك</li>
                            <li>الاستعادة تضيف البطاقات للراوتر مع نفس البيانات</li>
                            <li>مفيد قبل فورمات الراوتر</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Hidden file input -->
            <input type="file" id="backupFile" accept=".json" class="hidden" onchange="handleBackupFile(this)">
            
            <!-- Action Buttons -->
            <div class="grid grid-cols-2 gap-3">
                <button onclick="exportBackup()"
                    class="flex items-center justify-center gap-2 px-4 py-3 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white rounded-xl font-medium transition shadow-md">
                    <i class="fas fa-download"></i>
                    تصدير نسخة
                </button>
                <button onclick="importBackup()"
                    class="flex items-center justify-center gap-2 px-4 py-3 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white rounded-xl font-medium transition shadow-md">
                    <i class="fas fa-upload"></i>
                    استعادة نسخة
                </button>
            </div>
        </div>
        
        <!-- Footer -->
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
