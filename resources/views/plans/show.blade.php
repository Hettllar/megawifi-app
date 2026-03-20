@extends('layouts.app')

@section('title', 'تفاصيل الباقة: ' . $plan->name)

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ $plan->name }}</h1>
        <p class="text-gray-600">{{ $plan->name_en ?? '' }}</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('plans.edit', $plan) }}" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg">
            <i class="fas fa-edit ml-1"></i> تعديل
        </a>
        <a href="{{ route('plans.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
            <i class="fas fa-arrow-right ml-1"></i> رجوع
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- تفاصيل الباقة -->
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-6">
                <div class="text-center flex-1">
                    <p class="text-5xl font-bold text-blue-600">{{ number_format($plan->price, 0) }}</p>
                    <p class="text-gray-500">ر.ي / {{ $plan->validity_days }} يوم</p>
                </div>
                <div class="border-r pr-6">
                    <span class="px-4 py-2 rounded-full text-lg
                        @if($plan->is_active) bg-green-100 text-green-800 
                        @else bg-gray-100 text-gray-800 @endif">
                        {{ $plan->is_active ? 'نشط' : 'غير نشط' }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 border-t pt-6">
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                    <i class="fas fa-download text-2xl text-blue-500 mb-2"></i>
                    <p class="text-gray-500 text-sm">سرعة التحميل</p>
                    <p class="text-xl font-bold">{{ $plan->download_speed }} Mbps</p>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <i class="fas fa-upload text-2xl text-green-500 mb-2"></i>
                    <p class="text-gray-500 text-sm">سرعة الرفع</p>
                    <p class="text-xl font-bold">{{ $plan->upload_speed }} Mbps</p>
                </div>
                <div class="text-center p-4 bg-purple-50 rounded-lg">
                    <i class="fas fa-database text-2xl text-purple-500 mb-2"></i>
                    <p class="text-gray-500 text-sm">حد البيانات</p>
                    <p class="text-xl font-bold">
                        @if($plan->data_limit)
                            {{ $plan->data_limit / (1024*1024*1024) }} GB
                        @else
                            <i class="fas fa-infinity"></i>
                        @endif
                    </p>
                </div>
                <div class="text-center p-4 bg-orange-50 rounded-lg">
                    <i class="fas fa-users text-2xl text-orange-500 mb-2"></i>
                    <p class="text-gray-500 text-sm">المشتركين</p>
                    <p class="text-xl font-bold">{{ $plan->subscribers->count() }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">تفاصيل إضافية</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-gray-500 text-sm">نوع الخدمة</label>
                    <p class="font-medium">
                        @if($plan->service_type === 'ppp') PPP
                        @elseif($plan->service_type === 'hotspot') Hotspot
                        @elseif($plan->service_type === 'usermanager') UserManager
                        @else جميع الأنواع @endif
                    </p>
                </div>
                <div>
                    <label class="text-gray-500 text-sm">الأولوية</label>
                    <p class="font-medium">{{ $plan->priority ?? 8 }}</p>
                </div>
                <div>
                    <label class="text-gray-500 text-sm">MikroTik Profile</label>
                    <p class="font-medium font-mono">{{ $plan->mikrotik_profile ?? '-' }}</p>
                </div>
                <div>
                    <label class="text-gray-500 text-sm">Address Pool</label>
                    <p class="font-medium font-mono">{{ $plan->address_pool ?? '-' }}</p>
                </div>
                <div>
                    <label class="text-gray-500 text-sm">Burst Limit</label>
                    <p class="font-medium font-mono">{{ $plan->burst_limit ?? '-' }}</p>
                </div>
                <div>
                    <label class="text-gray-500 text-sm">أجهزة مسموحة</label>
                    <p class="font-medium">{{ $plan->shared_users ?? 1 }}</p>
                </div>
            </div>
            @if($plan->description)
            <div class="mt-4 pt-4 border-t">
                <label class="text-gray-500 text-sm">الوصف</label>
                <p class="mt-1">{{ $plan->description }}</p>
            </div>
            @endif
        </div>

        <!-- المشتركين في الباقة -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">المشتركين في هذه الباقة</h2>
            @if($plan->subscribers->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-right text-sm">المستخدم</th>
                            <th class="px-4 py-2 text-right text-sm">الحالة</th>
                            <th class="px-4 py-2 text-right text-sm">تاريخ الانتهاء</th>
                            <th class="px-4 py-2 text-center text-sm">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($plan->subscribers->take(10) as $subscriber)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2">{{ $subscriber->username }}</td>
                            <td class="px-4 py-2">
                                @if($subscriber->status === 'active')
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">نشط</span>
                                @else
                                    <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-xs">{{ $subscriber->status }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-sm">{{ $subscriber->expiry_date?->format('Y-m-d') ?? '-' }}</td>
                            <td class="px-4 py-2 text-center">
                                <a href="{{ route('subscribers.show', $subscriber) }}" class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($plan->subscribers->count() > 10)
            <p class="text-center text-gray-500 mt-4">و {{ $plan->subscribers->count() - 10 }} مشترك آخر...</p>
            @endif
            @else
            <p class="text-gray-500 text-center py-8">لا يوجد مشتركين في هذه الباقة</p>
            @endif
        </div>
    </div>

    <!-- الشريط الجانبي -->
    <div class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">إجراءات</h2>
            <div class="space-y-3">
                <a href="{{ route('plans.edit', $plan) }}" 
                   class="block w-full text-center bg-yellow-500 hover:bg-yellow-600 text-white py-2 rounded-lg">
                    <i class="fas fa-edit ml-1"></i> تعديل الباقة
                </a>
                <form action="{{ route('plans.toggle', $plan) }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full {{ $plan->is_active ? 'bg-gray-500 hover:bg-gray-600' : 'bg-green-500 hover:bg-green-600' }} text-white py-2 rounded-lg">
                        @if($plan->is_active)
                            <i class="fas fa-pause ml-1"></i> تعطيل
                        @else
                            <i class="fas fa-play ml-1"></i> تفعيل
                        @endif
                    </button>
                </form>
                <form action="{{ route('plans.destroy', $plan) }}" method="POST"
                      onsubmit="return confirm('هل أنت متأكد من حذف هذه الباقة؟ سيتم إلغاء ارتباط {{ $plan->subscribers->count() }} مشترك.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white py-2 rounded-lg">
                        <i class="fas fa-trash ml-1"></i> حذف الباقة
                    </button>
                </form>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">إحصائيات</h2>
            <div class="space-y-4">
                <div class="flex justify-between">
                    <span class="text-gray-500">إجمالي المشتركين</span>
                    <span class="font-bold">{{ $plan->subscribers->count() }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">النشطين</span>
                    <span class="font-bold text-green-600">{{ $plan->subscribers->where('status', 'active')->count() }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">المنتهين</span>
                    <span class="font-bold text-red-600">{{ $plan->subscribers->where('status', 'expired')->count() }}</span>
                </div>
                <div class="flex justify-between border-t pt-4">
                    <span class="text-gray-500">الإيرادات الشهرية المتوقعة</span>
                    <span class="font-bold text-blue-600">{{ number_format($plan->subscribers->where('status', 'active')->count() * $plan->price, 0) }} ر.ي</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
