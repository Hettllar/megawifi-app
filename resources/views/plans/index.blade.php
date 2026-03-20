@extends('layouts.app')

@section('title', 'باقات الخدمة')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">باقات الخدمة</h1>
        <p class="text-gray-600">إدارة باقات الإنترنت وخطط الاشتراك</p>
    </div>
    <a href="{{ route('plans.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
        <i class="fas fa-plus ml-1"></i> إضافة باقة
    </a>
</div>

@if(session('success'))
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
    {{ session('success') }}
</div>
@endif

<!-- الباقات -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @forelse($plans as $plan)
    <div class="bg-white rounded-xl shadow-sm overflow-hidden hover:shadow-lg transition-shadow">
        <div class="p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-xl font-bold text-gray-800">{{ $plan->name }}</h3>
                    <p class="text-gray-500 text-sm">{{ $plan->name_en ?? '' }}</p>
                </div>
                <span class="px-3 py-1 rounded-full text-sm font-medium
                    @if($plan->is_active) bg-green-100 text-green-800 
                    @else bg-gray-100 text-gray-800 @endif">
                    {{ $plan->is_active ? 'نشط' : 'غير نشط' }}
                </span>
            </div>

            <div class="text-center py-4 border-y border-gray-100">
                <p class="text-4xl font-bold text-blue-600">{{ number_format($plan->price, 0) }}</p>
                <p class="text-gray-500">ر.ي / {{ $plan->validity_days }} يوم</p>
            </div>

            <div class="mt-4 space-y-3">
                <div class="flex items-center text-gray-600">
                    <i class="fas fa-tachometer-alt w-6 text-blue-500"></i>
                    <span class="mr-2">السرعة: {{ $plan->download_speed }}M / {{ $plan->upload_speed }}M</span>
                </div>
                @if($plan->data_limit)
                <div class="flex items-center text-gray-600">
                    <i class="fas fa-database w-6 text-purple-500"></i>
                    <span class="mr-2">الحد: {{ $plan->data_limit / (1024*1024*1024) }} GB</span>
                </div>
                @else
                <div class="flex items-center text-gray-600">
                    <i class="fas fa-infinity w-6 text-green-500"></i>
                    <span class="mr-2">غير محدود</span>
                </div>
                @endif
                <div class="flex items-center text-gray-600">
                    <i class="fas fa-users w-6 text-orange-500"></i>
                    <span class="mr-2">{{ $plan->subscribers_count ?? 0 }} مشترك</span>
                </div>
                @if($plan->service_type)
                <div class="flex items-center text-gray-600">
                    <i class="fas fa-cog w-6 text-gray-500"></i>
                    <span class="mr-2">
                        @if($plan->service_type === 'ppp') PPP
                        @elseif($plan->service_type === 'hotspot') Hotspot
                        @else UserManager @endif
                    </span>
                </div>
                @endif
            </div>

            @if($plan->description)
            <p class="mt-4 text-gray-500 text-sm">{{ $plan->description }}</p>
            @endif
        </div>

        <div class="px-6 py-4 bg-gray-50 flex justify-between items-center">
            <div class="flex gap-2">
                <a href="{{ route('plans.edit', $plan) }}" class="text-yellow-600 hover:text-yellow-800">
                    <i class="fas fa-edit"></i>
                </a>
                <form action="{{ route('plans.destroy', $plan) }}" method="POST" class="inline"
                      onsubmit="return confirm('هل أنت متأكد من حذف هذه الباقة؟')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
            <a href="{{ route('plans.show', $plan) }}" class="text-blue-600 hover:text-blue-800 text-sm">
                عرض التفاصيل <i class="fas fa-arrow-left mr-1"></i>
            </a>
        </div>
    </div>
    @empty
    <div class="col-span-full text-center py-12">
        <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
        <p class="text-gray-500">لا توجد باقات مضافة</p>
        <a href="{{ route('plans.create') }}" class="inline-block mt-4 text-blue-600 hover:text-blue-800">
            <i class="fas fa-plus ml-1"></i> إضافة أول باقة
        </a>
    </div>
    @endforelse
</div>

<div class="mt-6">
    {{ $plans->links() }}
</div>
@endsection
