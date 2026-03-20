@extends('layouts.app')

@section('title', 'الجلسات النشطة')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">الجلسات النشطة</h1>
        <p class="text-gray-600">مراقبة الاتصالات الحية على جميع الراوترات</p>
    </div>
    <button onclick="refreshSessions()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
        <i class="fas fa-sync-alt ml-1"></i> تحديث
    </button>
</div>

<!-- فلتر -->
<div class="bg-white rounded-xl shadow-sm p-4 mb-6">
    <form action="{{ route('sessions.index') }}" method="GET" class="flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-gray-700 mb-2 text-sm">الراوتر</label>
            <select name="router_id" class="w-full border border-gray-300 rounded-lg px-4 py-2">
                <option value="">جميع الراوترات</option>
                @foreach($routers as $router)
                    <option value="{{ $router->id }}" {{ request('router_id') == $router->id ? 'selected' : '' }}>
                        {{ $router->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-[200px]">
            <label class="block text-gray-700 mb-2 text-sm">نوع الخدمة</label>
            <select name="service_type" class="w-full border border-gray-300 rounded-lg px-4 py-2">
                <option value="">الكل</option>
                <option value="ppp" {{ request('service_type') == 'ppp' ? 'selected' : '' }}>PPP</option>
                <option value="hotspot" {{ request('service_type') == 'hotspot' ? 'selected' : '' }}>Hotspot</option>
            </select>
        </div>
        <div class="flex-1 min-w-[200px]">
            <label class="block text-gray-700 mb-2 text-sm">بحث</label>
            <input type="text" name="search" value="{{ request('search') }}" 
                   placeholder="اسم المستخدم أو IP..."
                   class="w-full border border-gray-300 rounded-lg px-4 py-2">
        </div>
        <div>
            <button type="submit" class="bg-gray-800 hover:bg-gray-900 text-white px-6 py-2 rounded-lg">
                <i class="fas fa-search ml-1"></i> بحث
            </button>
        </div>
    </form>
</div>

<!-- إحصائيات -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl p-4 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-green-100">إجمالي الجلسات</p>
                <p class="text-3xl font-bold">{{ $sessions->total() }}</p>
            </div>
            <i class="fas fa-wifi text-4xl text-green-200"></i>
        </div>
    </div>
    <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl p-4 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-blue-100">PPP</p>
                <p class="text-3xl font-bold">{{ $sessions->where('service_type', 'ppp')->count() }}</p>
            </div>
            <i class="fas fa-network-wired text-4xl text-blue-200"></i>
        </div>
    </div>
    <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-xl p-4 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-orange-100">Hotspot</p>
                <p class="text-3xl font-bold">{{ $sessions->where('service_type', 'hotspot')->count() }}</p>
            </div>
            <i class="fas fa-broadcast-tower text-4xl text-orange-200"></i>
        </div>
    </div>
    <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl p-4 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-purple-100">حركة البيانات</p>
                <p class="text-3xl font-bold">{{ formatBytes($sessions->sum('bytes_in') + $sessions->sum('bytes_out')) }}</p>
            </div>
            <i class="fas fa-chart-line text-4xl text-purple-200"></i>
        </div>
    </div>
</div>

<!-- جدول الجلسات -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">المستخدم</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">IP Address</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">MAC Address</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">الراوتر</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">النوع</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">مدة الاتصال</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">↓ تحميل</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">↑ رفع</th>
                    <th class="px-6 py-3 text-center text-sm font-medium text-gray-700">إجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($sessions as $session)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="flex items-center">
                            <span class="w-2 h-2 bg-green-500 rounded-full ml-2 animate-pulse"></span>
                            <div>
                                <p class="font-medium">{{ $session->subscriber->username ?? $session->username }}</p>
                                <p class="text-sm text-gray-500">{{ $session->subscriber->full_name ?? '' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 font-mono text-sm">{{ $session->ip_address }}</td>
                    <td class="px-6 py-4 font-mono text-sm">{{ $session->mac_address ?? '-' }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 bg-gray-100 rounded text-sm">{{ $session->router->name ?? '-' }}</span>
                    </td>
                    <td class="px-6 py-4">
                        @if($session->service_type === 'ppp')
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-sm">PPP</span>
                        @else
                            <span class="px-2 py-1 bg-orange-100 text-orange-800 rounded text-sm">Hotspot</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm">
                        {{ $session->uptime ?? ($session->started_at ? $session->started_at->diffForHumans(null, true) : '-') }}
                    </td>
                    <td class="px-6 py-4 text-sm text-blue-600">
                        {{ formatBytes($session->bytes_in ?? 0) }}
                    </td>
                    <td class="px-6 py-4 text-sm text-green-600">
                        {{ formatBytes($session->bytes_out ?? 0) }}
                    </td>
                    <td class="px-6 py-4 text-center">
                        <form action="{{ route('sessions.disconnect', $session) }}" method="POST" class="inline"
                              onsubmit="return confirm('هل أنت متأكد من قطع الاتصال؟')">
                            @csrf
                            <button type="submit" class="text-red-600 hover:text-red-800" title="قطع الاتصال">
                                <i class="fas fa-plug"></i>
                            </button>
                        </form>
                        @if($session->subscriber_id)
                        <a href="{{ route('subscribers.show', $session->subscriber_id) }}" 
                           class="text-blue-600 hover:text-blue-800 mr-2" title="عرض المشترك">
                            <i class="fas fa-user"></i>
                        </a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                        <i class="fas fa-wifi text-4xl mb-2"></i>
                        <p>لا توجد جلسات نشطة حالياً</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <div class="px-6 py-4 border-t">
        {{ $sessions->links() }}
    </div>
</div>

@php
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
}
@endphp

<script>
function refreshSessions() {
    location.reload();
}

// تحديث تلقائي كل 30 ثانية
setInterval(function() {
    location.reload();
}, 30000);
</script>
@endsection
