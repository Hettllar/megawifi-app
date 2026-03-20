@extends('layouts.app')

@section('title', 'تقرير الجلسات')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">تقرير الجلسات والترافيك</h1>
        <p class="text-gray-600">الاتصالات واستهلاك البيانات</p>
    </div>
    <a href="{{ route('reports.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
        <i class="fas fa-arrow-right ml-1"></i> رجوع
    </a>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm p-4 mb-6">
    <form method="GET" action="{{ route('reports.sessions') }}" class="flex flex-wrap gap-4">
        <div>
            <label class="block text-sm text-gray-600 mb-1">من تاريخ</label>
            <input type="date" name="from_date" value="{{ $fromDate->format('Y-m-d') }}"
                   class="border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm text-gray-600 mb-1">إلى تاريخ</label>
            <input type="date" name="to_date" value="{{ $toDate->format('Y-m-d') }}"
                   class="border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm text-gray-600 mb-1">الراوتر</label>
            <select name="router_id" class="border border-gray-300 rounded-lg px-3 py-2">
                <option value="">الكل</option>
                @foreach($routers as $router)
                    <option value="{{ $router->id }}" {{ request('router_id') == $router->id ? 'selected' : '' }}>
                        {{ $router->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                <i class="fas fa-search ml-1"></i> بحث
            </button>
        </div>
    </form>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border-r-4 border-green-500">
        <p class="text-3xl font-bold text-green-600">{{ $stats['active_now'] }}</p>
        <p class="text-gray-500 text-sm">متصل الآن</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border-r-4 border-blue-500">
        <p class="text-3xl font-bold text-blue-600">{{ number_format($stats['total_download'] / 1073741824, 2) }} GB</p>
        <p class="text-gray-500 text-sm">إجمالي التحميل</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border-r-4 border-purple-500">
        <p class="text-3xl font-bold text-purple-600">{{ number_format($stats['total_upload'] / 1073741824, 2) }} GB</p>
        <p class="text-gray-500 text-sm">إجمالي الرفع</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border-r-4 border-yellow-500">
        <p class="text-3xl font-bold text-yellow-600">{{ $stats['peak_users'] }}</p>
        <p class="text-gray-500 text-sm">أعلى عدد متصلين</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Active Sessions -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="font-bold text-gray-800 mb-4">
            <i class="fas fa-signal text-green-500 ml-2"></i>
            الاتصالات النشطة الآن
        </h3>
        @if($activeSessions->count() > 0)
        <div class="space-y-2 max-h-80 overflow-y-auto">
            @foreach($activeSessions as $session)
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div>
                    <p class="font-medium text-gray-800">{{ $session->username }}</p>
                    <p class="text-xs text-gray-500">{{ $session->ip_address }} - {{ $session->router->name ?? '' }}</p>
                </div>
                <div class="text-left">
                    <p class="text-sm text-green-600">↓ {{ number_format($session->bytes_out / 1048576, 1) }} MB</p>
                    <p class="text-sm text-blue-600">↑ {{ number_format($session->bytes_in / 1048576, 1) }} MB</p>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-gray-500 text-center py-8">لا يوجد اتصالات نشطة</p>
        @endif
    </div>

    <!-- Traffic by Date -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="font-bold text-gray-800 mb-4">
            <i class="fas fa-chart-area text-blue-500 ml-2"></i>
            الترافيك اليومي
        </h3>
        @if($trafficByDate->count() > 0)
        <div class="space-y-2 max-h-80 overflow-y-auto">
            @foreach($trafficByDate as $item)
            <div class="p-3 bg-gray-50 rounded-lg">
                <div class="flex justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700">{{ $item['date'] }}</span>
                    <span class="text-sm text-gray-500">{{ round($item['users']) }} مستخدم</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-green-600">↓ {{ number_format($item['download'] / 1073741824, 2) }} GB</span>
                    <span class="text-blue-600">↑ {{ number_format($item['upload'] / 1073741824, 2) }} GB</span>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-gray-500 text-center py-8">لا توجد بيانات ترافيك</p>
        @endif
    </div>
</div>
@endsection
