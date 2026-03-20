@extends('layouts.app')

@section('title', 'المنتهية صلاحيتهم')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">المشتركين قرب انتهاء الصلاحية</h1>
        <p class="text-gray-600">المشتركين الذين ستنتهي صلاحيتهم خلال {{ $days }} يوم</p>
    </div>
    <a href="{{ route('reports.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
        <i class="fas fa-arrow-right ml-1"></i> رجوع
    </a>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm p-4 mb-6">
    <form method="GET" action="{{ route('reports.expiring') }}" class="flex flex-wrap gap-4">
        <div>
            <label class="block text-sm text-gray-600 mb-1">عدد الأيام</label>
            <select name="days" class="border border-gray-300 rounded-lg px-3 py-2">
                <option value="3" {{ $days == 3 ? 'selected' : '' }}>3 أيام</option>
                <option value="7" {{ $days == 7 ? 'selected' : '' }}>7 أيام</option>
                <option value="14" {{ $days == 14 ? 'selected' : '' }}>14 يوم</option>
                <option value="30" {{ $days == 30 ? 'selected' : '' }}>30 يوم</option>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                <i class="fas fa-search ml-1"></i> بحث
            </button>
        </div>
    </form>
</div>

<!-- Alert -->
@if($subscribers->count() > 0)
<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
    <div class="flex items-center">
        <i class="fas fa-exclamation-triangle text-yellow-600 text-xl ml-3"></i>
        <div>
            <h4 class="font-bold text-yellow-800">تنبيه!</h4>
            <p class="text-yellow-700">يوجد {{ $subscribers->count() }} مشترك ستنتهي صلاحيتهم خلال {{ $days }} يوم. تواصل معهم لتجديد الاشتراك.</p>
        </div>
    </div>
</div>
@endif

<!-- Subscribers Table -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
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
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاريخ الانتهاء</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">المتبقي</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">إجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach($subscribers as $index => $subscriber)
                @php
                    $daysLeft = now()->diffInDays($subscriber->expiration_date, false);
                    $urgency = $daysLeft <= 1 ? 'bg-red-50' : ($daysLeft <= 3 ? 'bg-yellow-50' : '');
                @endphp
                <tr class="hover:bg-gray-50 {{ $urgency }}">
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $index + 1 }}</td>
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $subscriber->username }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $subscriber->full_name ?? '-' }}</td>
                    <td class="px-4 py-3">
                        @if($subscriber->phone)
                        <a href="tel:{{ $subscriber->phone }}" class="text-blue-600 hover:underline">
                            {{ $subscriber->phone }}
                        </a>
                        @else
                        <span class="text-gray-400">-</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $subscriber->router->name ?? '-' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $subscriber->servicePlan->name ?? $subscriber->profile }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $subscriber->expiration_date->format('Y-m-d') }}</td>
                    <td class="px-4 py-3">
                        @if($daysLeft <= 0)
                            <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">اليوم!</span>
                        @elseif($daysLeft == 1)
                            <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">غداً</span>
                        @elseif($daysLeft <= 3)
                            <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">{{ $daysLeft }} أيام</span>
                        @else
                            <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">{{ $daysLeft }} يوم</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex gap-2">
                            <a href="{{ route('subscribers.show', $subscriber) }}" 
                               class="text-blue-600 hover:text-blue-800" title="عرض">
                                <i class="fas fa-eye"></i>
                            </a>
                            <form action="{{ route('subscribers.renew', $subscriber) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="text-green-600 hover:text-green-800" title="تجديد">
                                    <i class="fas fa-redo"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="p-8 text-center text-gray-500">
        <i class="fas fa-check-circle text-4xl text-green-500 mb-3"></i>
        <p class="text-green-600 font-medium">لا يوجد مشتركين قرب انتهاء الصلاحية</p>
        <p class="text-gray-500 text-sm mt-1">جميع المشتركين لديهم صلاحية كافية</p>
    </div>
    @endif
</div>
@endsection
