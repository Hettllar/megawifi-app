@extends('layouts.app')

@section('title', 'شحن رصيد: ' . $reseller->name)

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">شحن رصيد الوكيل</h1>
        <p class="text-gray-600">{{ $reseller->name }} - {{ $reseller->company_name ?? '' }}</p>
    </div>
    <a href="{{ route('resellers.show', $reseller) }}" class="text-blue-600 hover:text-blue-800">
        <i class="fas fa-arrow-right ml-1"></i> رجوع
    </a>
</div>

@if(session('success'))
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
    {{ session('success') }}
</div>
@endif

<div class="grid md:grid-cols-2 gap-6">
    <!-- نموذج الشحن -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-wallet text-green-600"></i> شحن الرصيد
        </h2>

        <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl p-6 text-white mb-6">
            <p class="text-green-100 text-sm">الرصيد الحالي</p>
            <p class="text-4xl font-bold">{{ number_format($reseller->balance, 0) }}</p>
        </div>

        <form action="{{ route('resellers.deposit.process', $reseller) }}" method="POST">
            @csrf
            
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 mb-2">المبلغ <span class="text-red-500">*</span></label>
                    <input type="number" name="amount" required min="1" step="1"
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 text-xl font-bold focus:ring-2 focus:ring-green-500 focus:border-transparent"
                           placeholder="0">
                    @error('amount')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-gray-700 mb-2">ملاحظة (اختياري)</label>
                    <input type="text" name="description"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-green-500 focus:border-transparent"
                           placeholder="مثال: شحن نقدي">
                </div>

                <!-- أزرار سريعة -->
                <div>
                    <label class="block text-gray-700 mb-2">شحن سريع</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach([10000, 25000, 50000, 100000, 250000, 500000] as $amount)
                        <button type="button" onclick="document.querySelector('input[name=amount]').value = {{ $amount }}"
                                class="px-4 py-2 bg-gray-100 hover:bg-green-100 text-gray-700 hover:text-green-700 rounded-lg transition">
                            {{ number_format($amount, 0) }}
                        </button>
                        @endforeach
                    </div>
                </div>

                <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg font-bold text-lg">
                    <i class="fas fa-plus-circle ml-1"></i> شحن الرصيد
                </button>
            </div>
        </form>
    </div>

    <!-- آخر عمليات الشحن -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-history text-blue-600"></i> آخر عمليات الشحن
        </h2>

        @if($recentTransactions->count() > 0)
        <div class="space-y-3">
            @foreach($recentTransactions as $transaction)
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-plus text-green-600"></i>
                    </div>
                    <div>
                        <p class="font-medium text-gray-800">+{{ number_format($transaction->amount, 0) }}</p>
                        <p class="text-sm text-gray-500">{{ $transaction->description }}</p>
                    </div>
                </div>
                <div class="text-left">
                    <p class="text-sm text-gray-500">{{ $transaction->created_at->format('Y-m-d') }}</p>
                    <p class="text-xs text-gray-400">{{ $transaction->created_at->format('H:i') }}</p>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="text-center py-8 text-gray-500">
            <i class="fas fa-history text-4xl mb-2 opacity-50"></i>
            <p>لا توجد عمليات شحن سابقة</p>
        </div>
        @endif

        <a href="{{ route('resellers.transactions', $reseller) }}" class="block text-center text-blue-600 hover:underline mt-4">
            عرض كل المعاملات
        </a>
    </div>
</div>
@endsection
