@extends('layouts.app')

@section('title', 'لوحة تحكم الوكيل')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="bg-gradient-to-r from-green-600 to-emerald-600 rounded-2xl p-6 text-white shadow-lg">
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center">
                <i class="fas fa-user-tie text-3xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold">مرحباً {{ auth()->user()->name }}</h1>
                <p class="text-green-100">لوحة تحكم الوكيل</p>
            </div>
            <div class="mr-auto text-left">
                <p class="text-green-100 text-sm">رصيدك الحالي</p>
                <p class="text-3xl font-bold">{{ number_format($stats['balance'], 0) }} <span class="text-lg">ل.س</span></p>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-4 shadow-sm border">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800">{{ $stats['total_subscribers'] }}</p>
                    <p class="text-sm text-gray-500">إجمالي المشتركين</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-4 shadow-sm border">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-user-check text-green-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800">{{ $stats['active_subscribers'] }}</p>
                    <p class="text-sm text-gray-500">مشتركين نشطين</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-4 shadow-sm border">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-server text-purple-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800">{{ $stats['routers_count'] }}</p>
                    <p class="text-sm text-gray-500">الراوترات المتاحة</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-4 shadow-sm border">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-wallet text-amber-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800">{{ number_format($stats['balance'], 0) }}</p>
                    <p class="text-sm text-gray-500">الرصيد (ل.س)</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid md:grid-cols-2 gap-4">
        @php
            $hasHotspotPermission = $permissions->where('can_create_hotspot', true)->isNotEmpty();
            $hasRenewPermission = $permissions->where('can_renew_usermanager', true)->isNotEmpty();
        @endphp
        
        @if($hasHotspotPermission)
        <a href="{{ route('reseller.hotspot') }}" class="block bg-white rounded-xl p-6 shadow-sm border hover:shadow-lg hover:border-orange-300 transition-all group">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-gradient-to-br from-orange-400 to-red-500 rounded-xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
                    <i class="fas fa-wifi text-white text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-800">إنشاء بطاقة هوتسبوت</h3>
                    <p class="text-gray-500 text-sm">إنشاء بطاقات WiFi جديدة للعملاء</p>
                </div>
                <i class="fas fa-chevron-left text-gray-400 mr-auto group-hover:text-orange-500 transition-colors"></i>
            </div>
        </a>
        @endif
        
        @if($hasRenewPermission)
        <a href="{{ route('reseller.usermanager') }}" class="block bg-white rounded-xl p-6 shadow-sm border hover:shadow-lg hover:border-purple-300 transition-all group">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-gradient-to-br from-purple-400 to-purple-600 rounded-xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
                    <i class="fas fa-sync-alt text-white text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-800">تجديد اشتراكات</h3>
                    <p class="text-gray-500 text-sm">تجديد اشتراكات UserManager للعملاء</p>
                </div>
                <i class="fas fa-chevron-left text-gray-400 mr-auto group-hover:text-purple-500 transition-colors"></i>
            </div>
        </a>
        @endif
    </div>

    <!-- Permissions Overview -->
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b">
            <h2 class="text-lg font-bold text-gray-800">
                <i class="fas fa-key text-green-600 ml-2"></i>
                صلاحياتك على الراوترات
            </h2>
        </div>
        <div class="p-6">
            @if($permissions->isEmpty())
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-lock text-4xl mb-3 opacity-50"></i>
                    <p>لم يتم تخصيص أي صلاحيات لك بعد</p>
                    <p class="text-sm">تواصل مع المدير لتفعيل صلاحياتك</p>
                </div>
            @else
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($permissions as $perm)
                    <div class="border rounded-lg p-4 {{ $perm->router?->is_online ? 'border-green-200 bg-green-50/50' : 'border-gray-200' }}">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-lg {{ $perm->router?->is_online ? 'bg-green-100' : 'bg-gray-100' }} flex items-center justify-center">
                                <i class="fas fa-server {{ $perm->router?->is_online ? 'text-green-600' : 'text-gray-400' }}"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800">{{ $perm->router?->name ?? 'راوتر محذوف' }}</h4>
                                <span class="text-xs {{ $perm->router?->is_online ? 'text-green-600' : 'text-gray-500' }}">
                                    {{ $perm->router?->is_online ? '● متصل' : '○ غير متصل' }}
                                </span>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-1">
                            @if($perm->can_create_hotspot)
                                <span class="px-2 py-1 bg-orange-100 text-orange-700 rounded text-xs">هوتسبوت</span>
                            @endif
                            @if($perm->can_renew_usermanager)
                                <span class="px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs">تجديد UM</span>
                            @endif
                            @if($perm->can_view_reports)
                                <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">تقارير</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- Recent Subscribers -->
    @if($recentSubscribers->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b">
            <h2 class="text-lg font-bold text-gray-800">
                <i class="fas fa-history text-blue-600 ml-2"></i>
                آخر المشتركين
            </h2>
        </div>
        <div class="divide-y">
            @foreach($recentSubscribers as $sub)
            <div class="px-6 py-3 flex items-center gap-4">
                <div class="w-10 h-10 rounded-full {{ $sub->type === 'hotspot' ? 'bg-orange-100' : 'bg-purple-100' }} flex items-center justify-center">
                    <i class="fas {{ $sub->type === 'hotspot' ? 'fa-wifi text-orange-600' : 'fa-user text-purple-600' }}"></i>
                </div>
                <div class="flex-1">
                    <p class="font-medium text-gray-800">{{ $sub->username }}</p>
                    <p class="text-xs text-gray-500">{{ $sub->router?->name }} • {{ $sub->created_at->diffForHumans() }}</p>
                </div>
                <span class="px-2 py-1 rounded text-xs {{ $sub->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                    {{ $sub->status === 'active' ? 'نشط' : 'معطل' }}
                </span>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
