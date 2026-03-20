@extends('layouts.app')

@section('title', 'تقرير الإيرادات')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">تقرير الإيرادات</h1>
        <p class="text-gray-600">الإيرادات والفواتير</p>
    </div>
    <a href="{{ route('reports.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
        <i class="fas fa-arrow-right ml-1"></i> رجوع
    </a>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm p-4 mb-6">
    <form method="GET" action="{{ route('reports.revenue') }}" class="flex flex-wrap gap-4">
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
        <p class="text-3xl font-bold text-green-600">{{ number_format($stats['total_revenue']) }}</p>
        <p class="text-gray-500 text-sm">إجمالي الإيرادات</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border-r-4 border-yellow-500">
        <p class="text-3xl font-bold text-yellow-600">{{ number_format($stats['pending']) }}</p>
        <p class="text-gray-500 text-sm">معلق</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border-r-4 border-blue-500">
        <p class="text-3xl font-bold text-blue-600">{{ $stats['invoices_count'] }}</p>
        <p class="text-gray-500 text-sm">عدد الفواتير</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border-r-4 border-purple-500">
        <p class="text-3xl font-bold text-purple-600">{{ $stats['paid_count'] }}</p>
        <p class="text-gray-500 text-sm">مدفوعة</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Revenue by Router -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="font-bold text-gray-800 mb-4">
            <i class="fas fa-chart-pie text-blue-500 ml-2"></i>
            الإيرادات حسب الراوتر
        </h3>
        @if($revenueByRouter->count() > 0)
        <div class="space-y-3">
            @foreach($revenueByRouter as $item)
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div>
                    <p class="font-medium text-gray-800">{{ $item['router'] }}</p>
                    <p class="text-sm text-gray-500">{{ $item['count'] }} فاتورة</p>
                </div>
                <p class="text-lg font-bold text-green-600">{{ number_format($item['amount']) }}</p>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-gray-500 text-center py-8">لا توجد بيانات</p>
        @endif
    </div>

    <!-- Daily Revenue Chart -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="font-bold text-gray-800 mb-4">
            <i class="fas fa-chart-bar text-green-500 ml-2"></i>
            الإيرادات اليومية
        </h3>
        @if($dailyRevenue->count() > 0)
        <div class="space-y-2 max-h-64 overflow-y-auto">
            @foreach($dailyRevenue as $item)
            <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                <span class="text-sm text-gray-600">{{ $item['date'] }}</span>
                <span class="font-medium text-green-600">{{ number_format($item['amount']) }}</span>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-gray-500 text-center py-8">لا توجد بيانات</p>
        @endif
    </div>
</div>

<!-- Invoices Table -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="p-4 border-b">
        <h3 class="font-bold text-gray-800">الفواتير</h3>
    </div>
    
    @if($invoices->count() > 0)
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">رقم الفاتورة</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">المشترك</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">الراوتر</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">المبلغ</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">الحالة</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">التاريخ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach($invoices as $invoice)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $invoice->invoice_number }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $invoice->subscriber->username ?? '-' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $invoice->router->name ?? '-' }}</td>
                    <td class="px-4 py-3 font-bold text-green-600">{{ number_format($invoice->amount) }}</td>
                    <td class="px-4 py-3">
                        @if($invoice->status === 'paid')
                            <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">مدفوعة</span>
                        @elseif($invoice->status === 'pending')
                            <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">معلقة</span>
                        @else
                            <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">ملغاة</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $invoice->created_at->format('Y-m-d H:i') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="p-8 text-center text-gray-500">
        <i class="fas fa-file-invoice text-4xl text-gray-300 mb-3"></i>
        <p>لا توجد فواتير في هذه الفترة</p>
    </div>
    @endif
</div>
@endsection
