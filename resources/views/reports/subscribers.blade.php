@extends('layouts.app')

@section('title', 'تقرير المشتركين')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">تقرير المشتركين</h1>
        <p class="text-gray-600">إحصائيات تفصيلية عن المشتركين</p>
    </div>
    <a href="{{ route('reports.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
        <i class="fas fa-arrow-right ml-1"></i> رجوع
    </a>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm p-4 mb-6">
    <form method="GET" action="{{ route('reports.subscribers') }}" class="flex flex-wrap gap-4">
        <div>
            <label class="block text-sm text-gray-600 mb-1">من تاريخ</label>
            <input type="date" name="from_date" value="{{ request('from_date') }}"
                   class="border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm text-gray-600 mb-1">إلى تاريخ</label>
            <input type="date" name="to_date" value="{{ request('to_date') }}"
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
        <div>
            <label class="block text-sm text-gray-600 mb-1">الحالة</label>
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2">
                <option value="">الكل</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>نشط</option>
                <option value="disabled" {{ request('status') === 'disabled' ? 'selected' : '' }}>معطل</option>
                <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>منتهي</option>
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
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
        <p class="text-3xl font-bold text-blue-600">{{ $stats['total'] }}</p>
        <p class="text-gray-500 text-sm">إجمالي المشتركين</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
        <p class="text-3xl font-bold text-green-600">{{ $stats['active'] }}</p>
        <p class="text-gray-500 text-sm">نشط</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
        <p class="text-3xl font-bold text-yellow-600">{{ $stats['disabled'] }}</p>
        <p class="text-gray-500 text-sm">معطل</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
        <p class="text-3xl font-bold text-red-600">{{ $stats['expired'] }}</p>
        <p class="text-gray-500 text-sm">منتهي</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
        <p class="text-3xl font-bold text-purple-600">{{ $stats['ppp'] }}</p>
        <p class="text-gray-500 text-sm">PPP</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
        <p class="text-3xl font-bold text-orange-600">{{ $stats['hotspot'] }}</p>
        <p class="text-gray-500 text-sm">Hotspot</p>
    </div>
</div>

<!-- Subscribers Table -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="p-4 border-b flex justify-between items-center">
        <h3 class="font-bold text-gray-800">قائمة المشتركين</h3>
        <a href="{{ route('reports.export', ['type' => 'subscribers'] + request()->all()) }}" 
           class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm">
            <i class="fas fa-download ml-1"></i> تصدير CSV
        </a>
    </div>
    
    @if($subscribers->count() > 0)
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">#</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">اسم المستخدم</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">الاسم</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">الهاتف</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">الراوتر</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">الباقة</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">النوع</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">الحالة</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاريخ الإنشاء</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach($subscribers as $index => $subscriber)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $index + 1 }}</td>
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $subscriber->username }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $subscriber->full_name ?? '-' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $subscriber->phone ?? '-' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $subscriber->router->name ?? '-' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $subscriber->servicePlan->name ?? $subscriber->profile }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs rounded-full {{ $subscriber->type === 'ppp' ? 'bg-blue-100 text-blue-800' : 'bg-orange-100 text-orange-800' }}">
                            {{ strtoupper($subscriber->type) }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        @if($subscriber->status === 'active')
                            <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">نشط</span>
                        @elseif($subscriber->status === 'disabled')
                            <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">معطل</span>
                        @else
                            <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">منتهي</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $subscriber->created_at->format('Y-m-d') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="p-8 text-center text-gray-500">
        <i class="fas fa-users text-4xl text-gray-300 mb-3"></i>
        <p>لا توجد بيانات للعرض</p>
    </div>
    @endif
</div>
@endsection
