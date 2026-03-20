@extends('layouts.app')

@section('title', 'عرض الوكيل: ' . $reseller->name)

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ $reseller->name }}</h1>
        <p class="text-gray-600">{{ $reseller->company_name ?? 'وكيل/بائع' }}</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('resellers.deposit', $reseller) }}" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
            <i class="fas fa-wallet ml-1"></i> شحن الرصيد
        </a>
        @if(auth()->user()->isSuperAdmin())
        <a href="{{ route('resellers.permissions', $reseller) }}" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg">
            <i class="fas fa-key ml-1"></i> الصلاحيات
        </a>
        <a href="{{ route('resellers.edit', $reseller) }}" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg">
            <i class="fas fa-edit ml-1"></i> تعديل
        </a>
        @endif
        <a href="{{ route('resellers.index') }}" class="text-blue-600 hover:text-blue-800 px-4 py-2">
            <i class="fas fa-arrow-right ml-1"></i> رجوع
        </a>
    </div>
</div>

@if(session('success'))
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
    {{ session('success') }}
</div>
@endif

<!-- إحصائيات -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl p-4 text-white">
        <p class="text-green-100 text-sm">الرصيد الحالي</p>
        <p class="text-3xl font-bold">{{ number_format($reseller->balance, 0) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4">
        <p class="text-gray-500 text-sm">إجمالي المشتركين</p>
        <p class="text-2xl font-bold text-gray-800">{{ $stats['total_subscribers'] }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4">
        <p class="text-gray-500 text-sm">المشتركين النشطين</p>
        <p class="text-2xl font-bold text-blue-600">{{ $stats['active_subscribers'] }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4">
        <p class="text-gray-500 text-sm">إجمالي المشتريات</p>
        <p class="text-2xl font-bold text-red-600">{{ number_format($stats['total_purchases'], 0) }}</p>
    </div>
</div>

<div class="grid md:grid-cols-3 gap-6">
    <!-- معلومات الوكيل -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-store text-green-600"></i> معلومات الوكيل
        </h2>
        
        <div class="space-y-3">
            <div>
                <label class="text-sm text-gray-500">الاسم</label>
                <p class="font-medium text-gray-800">{{ $reseller->name }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-500">البريد الإلكتروني</label>
                <p class="font-medium text-gray-800">{{ $reseller->email }}</p>
            </div>
            @if($reseller->phone)
            <div>
                <label class="text-sm text-gray-500">الهاتف</label>
                <p class="font-medium text-gray-800">{{ $reseller->phone }}</p>
            </div>
            @endif
            @if($reseller->company_name)
            <div>
                <label class="text-sm text-gray-500">الشركة</label>
                <p class="font-medium text-gray-800">{{ $reseller->company_name }}</p>
            </div>
            @endif
            @if($reseller->address)
            <div>
                <label class="text-sm text-gray-500">العنوان</label>
                <p class="font-medium text-gray-800">{{ $reseller->address }}</p>
            </div>
            @endif
            <div>
                <label class="text-sm text-gray-500">نسبة العمولة</label>
                <p class="font-medium text-green-600">{{ $reseller->commission_rate }}%</p>
            </div>
            @if($reseller->max_subscribers)
            <div>
                <label class="text-sm text-gray-500">حد المشتركين</label>
                <p class="font-medium text-gray-800">
                    {{ $stats['total_subscribers'] }} / {{ $reseller->max_subscribers }}
                </p>
            </div>
            @endif
            <div>
                <label class="text-sm text-gray-500">الحالة</label>
                <p>
                    @if($reseller->is_active)
                        <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm">نشط</span>
                    @else
                        <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm">معطّل</span>
                    @endif
                </p>
            </div>
        </div>
    </div>

    <!-- الراوترات والصلاحيات -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-server text-blue-600"></i> الراوترات المفعّلة
        </h2>
        
        @if($reseller->resellerPermissions->count() > 0)
        <div class="space-y-3">
            @foreach($reseller->resellerPermissions as $perm)
            <div class="p-3 bg-gray-50 rounded-lg">
                <div class="flex items-center justify-between mb-2">
                    <span class="font-medium text-gray-800">{{ $perm->router->name }}</span>
                    <span class="px-2 py-1 text-xs {{ $perm->router->is_online ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }} rounded">
                        {{ $perm->router->is_online ? 'متصل' : 'غير متصل' }}
                    </span>
                </div>
                <div class="flex flex-wrap gap-1">
                    @if($perm->hasHotspotAccess())
                        <span class="px-2 py-0.5 bg-orange-100 text-orange-700 rounded text-xs">Hotspot</span>
                    @endif
                    @if($perm->hasPppAccess())
                        <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs">PPP</span>
                    @endif
                    @if($perm->hasUserManagerAccess())
                        <span class="px-2 py-0.5 bg-purple-100 text-purple-700 rounded text-xs">UserManager</span>
                    @endif
                    @if($perm->can_generate_vouchers)
                        <span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs">كروت</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="text-center py-8 text-gray-500">
            <i class="fas fa-server text-4xl mb-2 opacity-50"></i>
            <p>لا توجد صلاحيات</p>
            <a href="{{ route('resellers.permissions', $reseller) }}" class="text-blue-600 hover:underline mt-2 inline-block">
                إضافة صلاحيات
            </a>
        </div>
        @endif
    </div>

    <!-- آخر المعاملات -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-history text-purple-600"></i> آخر المعاملات
            </h2>
            <a href="{{ route('resellers.transactions', $reseller) }}" class="text-blue-600 hover:underline text-sm">
                عرض الكل
            </a>
        </div>
        
        @if($transactions->count() > 0)
        <div class="space-y-3">
            @foreach($transactions->take(8) as $transaction)
            <div class="flex items-center justify-between p-2 border-b">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-{{ $transaction->type_color }}-100 flex items-center justify-center">
                        <i class="fas {{ $transaction->type_icon }} text-{{ $transaction->type_color }}-600 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-800">{{ $transaction->type_label }}</p>
                        <p class="text-xs text-gray-500">{{ Str::limit($transaction->description, 25) }}</p>
                    </div>
                </div>
                <div class="text-left">
                    <p class="font-bold {{ in_array($transaction->type, ['deposit', 'refund', 'commission']) ? 'text-green-600' : 'text-red-600' }}">
                        {{ in_array($transaction->type, ['deposit', 'refund', 'commission']) ? '+' : '-' }}{{ number_format($transaction->amount, 0) }}
                    </p>
                    <p class="text-xs text-gray-400">{{ $transaction->created_at->format('m/d H:i') }}</p>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="text-center py-8 text-gray-500">
            <i class="fas fa-history text-4xl mb-2 opacity-50"></i>
            <p>لا توجد معاملات</p>
        </div>
        @endif
    </div>
</div>

<!-- آخر المشتركين -->
@if($reseller->resellerSubscribers->count() > 0)
<div class="bg-white rounded-xl shadow-sm p-6 mt-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
        <i class="fas fa-users text-blue-600"></i> آخر المشتركين
    </h2>
    
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-right text-sm font-medium text-gray-700">اسم المستخدم</th>
                    <th class="px-4 py-2 text-right text-sm font-medium text-gray-700">الراوتر</th>
                    <th class="px-4 py-2 text-right text-sm font-medium text-gray-700">الخطة</th>
                    <th class="px-4 py-2 text-right text-sm font-medium text-gray-700">الحالة</th>
                    <th class="px-4 py-2 text-right text-sm font-medium text-gray-700">تاريخ الإنشاء</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach($reseller->resellerSubscribers as $subscriber)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 font-medium text-gray-800">{{ $subscriber->username }}</td>
                    <td class="px-4 py-2 text-gray-600">{{ $subscriber->router->name ?? '-' }}</td>
                    <td class="px-4 py-2 text-gray-600">{{ $subscriber->servicePlan->name ?? '-' }}</td>
                    <td class="px-4 py-2">
                        <span class="px-2 py-1 rounded text-xs {{ $subscriber->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ $subscriber->status === 'active' ? 'نشط' : $subscriber->status }}
                        </span>
                    </td>
                    <td class="px-4 py-2 text-gray-500 text-sm">{{ $subscriber->created_at->format('Y-m-d') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
