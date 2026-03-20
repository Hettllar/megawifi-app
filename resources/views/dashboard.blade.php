@extends('layouts.app')

@section('title', 'لوحة التحكم')

@push('styles')
<style>
    [] { display: none !important; }
</style>
@endpush

@section('content')
<div class="space-y-2" >
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <h1 class="text-lg lg:text-xl font-bold text-gray-800 flex items-center gap-2">
                <span class="w-8 h-8 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-tachometer-alt text-white text-sm"></i>
                </span>
                لوحة التحكم
            </h1>
            <p class="text-gray-500 text-xs mt-1 mr-10">نظرة عامة على الشبكة</p>
        </div>
        <button onclick="refreshStats()" class="flex items-center gap-2 px-3 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg text-sm transition">
            <i class="fas fa-sync"></i>
            <span>تحديث</span>
        </button>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-4 gap-2">
        <!-- الراوترات -->
        <div class="group bg-gradient-to-br from-blue-50 to-white rounded-lg p-2 lg:p-2 border border-blue-100 hover:border-blue-400 transition-all cursor-pointer" onclick="window.location='{{ route('routers.index') }}'">
            <div class="text-center">
                <div class="w-8 h-8 lg:w-10 lg:h-10 bg-blue-500 rounded-full flex items-center justify-center mx-auto mb-1">
                    <i class="fas fa-server text-white text-xs lg:text-sm"></i>
                </div>
                <p class="text-lg lg:text-xl font-bold text-blue-600">{{ $stats['total_routers'] }}</p>
                <p class="text-[10px] lg:text-xs text-gray-500">الراوترات</p>
                <div class="flex items-center justify-center gap-2 mt-1 text-[10px]">
                    <span class="text-green-600"> {{ $stats['online_routers'] }}</span>
                    <span class="text-red-600"> {{ $stats['offline_routers'] }}</span>
                </div>
            </div>
        </div>

        <!-- المشتركين -->
        <div class="group bg-gradient-to-br from-green-50 to-white rounded-lg p-2 lg:p-2 border border-green-100 hover:border-green-400 transition-all cursor-pointer" onclick="window.location='{{ route('subscribers.index') }}'">
            <div class="text-center">
                <div class="w-8 h-8 lg:w-10 lg:h-10 bg-green-500 rounded-full flex items-center justify-center mx-auto mb-1">
                    <i class="fas fa-users text-white text-xs lg:text-sm"></i>
                </div>
                <p class="text-lg lg:text-xl font-bold text-green-600">{{ number_format($stats['total_subscribers']) }}</p>
                <p class="text-[10px] lg:text-xs text-gray-500">المشتركين</p>
                <div class="flex items-center justify-center gap-2 mt-1 text-[10px]">
                    <span class="text-green-600"> {{ number_format($stats['active_subscribers']) }}</span>
                    <span class="text-red-600"> {{ $stats['expired_subscribers'] }}</span>
                </div>
            </div>
        </div>

        <!-- المتصلون الآن -->
        <div class="group bg-gradient-to-br from-emerald-50 to-white rounded-lg p-2 lg:p-2 border border-emerald-100 hover:border-emerald-400 transition-all cursor-pointer" onclick="window.location='{{ route('sessions.index') }}'">
            <div class="text-center">
                <div class="w-8 h-8 lg:w-10 lg:h-10 bg-emerald-500 rounded-full flex items-center justify-center mx-auto mb-1">
                    <i class="fas fa-wifi text-white text-xs lg:text-sm"></i>
                </div>
                <p class="text-lg lg:text-xl font-bold text-emerald-600" id="stat-online-users">{{ $stats['online_users'] }}</p>
                <p class="text-[10px] lg:text-xs text-gray-500">متصل الآن</p>
                <div class="flex items-center justify-center gap-1 mt-1 text-[10px]">
                    <span class="text-blue-600">PPP:{{ $stats['ppp_users'] }}</span>
                    <span class="text-orange-600">HS:{{ $stats['hotspot_users'] }}</span>
                </div>
            </div>
        </div>

        <!-- إيرادات الشهر -->
        <div class="group bg-gradient-to-br from-purple-50 to-white rounded-lg p-2 lg:p-2 border border-purple-100 hover:border-purple-400 transition-all cursor-pointer">
            <div class="text-center">
                <div class="w-8 h-8 lg:w-10 lg:h-10 bg-purple-500 rounded-full flex items-center justify-center mx-auto mb-1">
                    <i class="fas fa-coins text-white text-xs lg:text-sm"></i>
                </div>
                <p class="text-lg lg:text-xl font-bold text-purple-600">{{ number_format($monthlyRevenue, 0) }}</p>
                <p class="text-[10px] lg:text-xs text-gray-500">إيرادات الشهر</p>
                <p class="text-[10px] text-gray-400 mt-1">ريال يمني</p>
            </div>
        </div>
    </div>

    <!-- Traffic Summary -->
    @if($todayTraffic)
    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="bg-gradient-to-r from-indigo-500 to-purple-600 px-4 py-2">
            <h2 class="text-sm font-bold text-white flex items-center gap-2">
                <i class="fas fa-chart-area"></i>
                إجمالي حركة البيانات
            </h2>
        </div>
        <div class="p-2">
            <div class="grid grid-cols-3 gap-2">
                <div class="bg-green-50 rounded-lg p-2 text-center border border-green-200">
                    <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center mx-auto mb-1">
                        <i class="fas fa-download text-white text-xs"></i>
                    </div>
                    <p class="text-lg font-bold text-green-600">{{ number_format(($todayTraffic->total_out ?? 0) / 1073741824, 2) }}</p>
                    <p class="text-[10px] text-green-700">GB تنزيل</p>
                </div>
                <div class="bg-blue-50 rounded-lg p-2 text-center border border-blue-200">
                    <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center mx-auto mb-1">
                        <i class="fas fa-upload text-white text-xs"></i>
                    </div>
                    <p class="text-lg font-bold text-blue-600">{{ number_format(($todayTraffic->total_in ?? 0) / 1073741824, 2) }}</p>
                    <p class="text-[10px] text-blue-700">GB رفع</p>
                </div>
                <div class="bg-purple-50 rounded-lg p-2 text-center border border-purple-200">
                    <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center mx-auto mb-1">
                        <i class="fas fa-database text-white text-xs"></i>
                    </div>
                    <p class="text-lg font-bold text-purple-600">{{ number_format(($todayTraffic->total_bytes ?? 0) / 1073741824, 2) }}</p>
                    <p class="text-[10px] text-purple-700">GB إجمالي</p>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-2">
        <!-- Routers Status -->
        <div class="bg-white rounded-xl border overflow-hidden">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-2">
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <i class="fas fa-server"></i>
                    حالة الراوترات
                </h3>
            </div>
            <div class="p-2 space-y-1 max-h-64 overflow-y-auto">
                @forelse($routers as $router)
                <div class="flex items-center justify-between p-2 bg-gray-50 hover:bg-blue-50 rounded-lg transition cursor-pointer text-sm" onclick="window.location='{{ route('routers.show', $router) }}'">
                    <div class="flex items-center gap-2">
                        <div class="relative">
                            <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center text-white">
                                <i class="fas fa-server text-xs"></i>
                            </div>
                            <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full border-2 border-white {{ $router->status === 'online' ? 'bg-green-500' : 'bg-red-500' }}"></span>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800 text-xs">{{ $router->name }}</p>
                            <p class="text-[10px] text-gray-500 font-mono">{{ $router->ip_address }}</p>
                        </div>
                    </div>
                    <div class="text-left">
                        <p class="text-xs font-bold text-emerald-600">{{ $router->active_sessions_count }}</p>
                        <p class="text-[10px] text-gray-400">{{ $router->subscribers_count }} مشترك</p>
                    </div>
                </div>
                @empty
                <div class="text-center py-4">
                    <i class="fas fa-server text-gray-300 text-2xl mb-2"></i>
                    <p class="text-gray-500 text-xs">لا توجد راوترات</p>
                </div>
                @endforelse
            </div>
            <div class="p-2 border-t bg-gray-50">
                <a href="{{ route('routers.index') }}" class="flex items-center justify-center gap-1 text-blue-600 hover:text-blue-800 text-xs font-medium">
                    عرض الكل <i class="fas fa-arrow-left text-[10px]"></i>
                </a>
            </div>
        </div>

        <!-- Recent Sessions -->
        <div class="bg-white rounded-xl border overflow-hidden">
            <div class="bg-gradient-to-r from-emerald-500 to-emerald-600 px-4 py-2">
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <i class="fas fa-wifi"></i>
                    آخر الاتصالات
                </h3>
            </div>
            <div class="p-2 space-y-1 max-h-64 overflow-y-auto">
                @forelse($recentSessions as $session)
                <div class="flex items-center justify-between p-2 bg-gray-50 hover:bg-emerald-50 rounded-lg transition text-sm">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 {{ $session->type === 'ppp' ? 'bg-blue-500' : 'bg-orange-500' }} rounded-lg flex items-center justify-center text-white">
                            <i class="fas {{ $session->type === 'ppp' ? 'fa-broadcast-tower' : 'fa-signal' }} text-xs"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800 text-xs">{{ $session->username }}</p>
                            <p class="text-[10px] text-gray-500 font-mono">{{ $session->ip_address }}</p>
                        </div>
                    </div>
                    <div class="text-left">
                        <p class="text-[10px] text-gray-600">{{ $session->router?->name }}</p>
                        <p class="text-[10px] text-gray-400">{{ $session->started_at->diffForHumans() }}</p>
                    </div>
                </div>
                @empty
                <div class="text-center py-4">
                    <i class="fas fa-wifi text-gray-300 text-2xl mb-2"></i>
                    <p class="text-gray-500 text-xs">لا توجد اتصالات نشطة</p>
                </div>
                @endforelse
            </div>
            <div class="p-2 border-t bg-gray-50">
                <a href="{{ route('sessions.index') }}" class="flex items-center justify-center gap-1 text-emerald-600 hover:text-emerald-800 text-xs font-medium">
                    عرض الكل <i class="fas fa-arrow-left text-[10px]"></i>
                </a>
            </div>
        </div>

        <!-- Expiring Subscribers -->
        <div class="bg-white rounded-xl border overflow-hidden">
            <div class="bg-gradient-to-r from-red-500 to-red-600 px-4 py-2">
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <i class="fas fa-clock"></i>
                    تنتهي قريباً
                </h3>
            </div>
            <div class="p-2 space-y-1 max-h-64 overflow-y-auto">
                @forelse($expiringSubscribers as $subscriber)
                <div class="flex items-center justify-between p-2 bg-red-50 hover:bg-red-100 rounded-lg transition cursor-pointer text-sm" onclick="window.location='{{ route('subscribers.edit', $subscriber) }}'">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-red-500 rounded-lg flex items-center justify-center text-white">
                            <i class="fas fa-user-clock text-xs"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800 text-xs">{{ $subscriber->full_name ?? $subscriber->username }}</p>
                            <p class="text-[10px] text-gray-500">{{ $subscriber->router?->name }}</p>
                        </div>
                    </div>
                    <div class="text-left">
                        <p class="text-xs font-bold text-red-600">{{ \Carbon\Carbon::parse($subscriber->expiry_date)->diffForHumans() }}</p>
                        <p class="text-[10px] text-gray-400">{{ \Carbon\Carbon::parse($subscriber->expiry_date)->format('Y-m-d') }}</p>
                    </div>
                </div>
                @empty
                <div class="text-center py-4">
                    <i class="fas fa-check-circle text-green-300 text-2xl mb-2"></i>
                    <p class="text-gray-500 text-xs">لا توجد اشتراكات تنتهي قريباً</p>
                </div>
                @endforelse
            </div>
            <div class="p-2 border-t bg-gray-50">
                <a href="{{ route('subscribers.index') }}?status=active" class="flex items-center justify-center gap-1 text-red-600 hover:text-red-800 text-xs font-medium">
                    عرض الكل <i class="fas fa-arrow-left text-[10px]"></i>
                </a>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function refreshStats() {
    const btn = event.target.closest('button');
    const icon = btn.querySelector('i');
    icon.classList.add('animate-spin');

    fetch('{{ route('api.stats') }}')
        .then(response => response.json())
        .then(data => {
            document.getElementById('stat-online-users').textContent = data.online_users;
            icon.classList.remove('animate-spin');
        })
        .catch(() => {
            icon.classList.remove('animate-spin');
        });
}

setInterval(function() {
    fetch('{{ route('api.stats') }}')
        .then(response => response.json())
        .then(data => {
            document.getElementById('stat-online-users').textContent = data.online_users;
        });
}, 30000);
</script>
@endpush
@endsection
