@extends('layouts.app')

@section('title', 'معاملات الوكيل: ' . $reseller->name)

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">سجل المعاملات</h1>
        <p class="text-gray-600">{{ $reseller->name }} - الرصيد الحالي: {{ number_format($reseller->balance, 0) }}</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('resellers.deposit', $reseller) }}" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
            <i class="fas fa-plus ml-1"></i> شحن رصيد
        </a>
        <a href="{{ route('resellers.show', $reseller) }}" class="text-blue-600 hover:text-blue-800 px-4 py-2">
            <i class="fas fa-arrow-right ml-1"></i> رجوع
        </a>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">#</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">النوع</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">المبلغ</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">الرصيد قبل</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">الرصيد بعد</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">الوصف</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">بواسطة</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">التاريخ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($transactions as $transaction)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-gray-500">{{ $transaction->id }}</td>
                    <td class="px-6 py-4">
                        <span class="px-3 py-1 bg-{{ $transaction->type_color }}-100 text-{{ $transaction->type_color }}-800 rounded-full text-sm">
                            <i class="fas {{ $transaction->type_icon }} ml-1"></i>
                            {{ $transaction->type_label }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="font-bold {{ in_array($transaction->type, ['deposit', 'refund', 'commission']) ? 'text-green-600' : 'text-red-600' }}">
                            {{ in_array($transaction->type, ['deposit', 'refund', 'commission']) ? '+' : '-' }}{{ number_format($transaction->amount, 0) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-gray-600">{{ number_format($transaction->balance_before, 0) }}</td>
                    <td class="px-6 py-4 text-gray-800 font-medium">{{ number_format($transaction->balance_after, 0) }}</td>
                    <td class="px-6 py-4 text-gray-600">
                        {{ $transaction->description }}
                        @if($transaction->subscriber)
                            <br><span class="text-xs text-blue-600">{{ $transaction->subscriber->username }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-gray-600">
                        {{ $transaction->admin?->name ?? '-' }}
                    </td>
                    <td class="px-6 py-4 text-gray-500 text-sm">
                        {{ $transaction->created_at->format('Y-m-d H:i') }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                        <i class="fas fa-history text-4xl mb-2 opacity-50"></i>
                        <p>لا توجد معاملات</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    @if($transactions->hasPages())
    <div class="px-6 py-4 border-t">
        {{ $transactions->links() }}
    </div>
    @endif
</div>
@endsection
