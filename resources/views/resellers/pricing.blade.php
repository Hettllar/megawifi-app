@extends('layouts.app')

@section('title', 'تسعير الوكلاء - ' . $router->name)

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">تسعير خدمات الوكلاء</h1>
        <p class="text-gray-600">{{ $router->name }} - {{ $router->ip_address }}</p>
    </div>
    <a href="{{ route('routers.show', $router) }}" class="text-blue-600 hover:text-blue-800">
        <i class="fas fa-arrow-right ml-1"></i> رجوع للراوتر
    </a>
</div>

@if(session('success'))
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
    {{ session('success') }}
</div>
@endif

<form action="{{ route('resellers.pricing.update', $router) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="mb-6">
            <h2 class="text-lg font-bold text-gray-800 mb-2">أسعار الخدمات للوكلاء</h2>
            <p class="text-gray-500 text-sm">حدد سعر كل خدمة لتُخصم تلقائياً من رصيد الوكيل عند إنشاء مشترك</p>
        </div>

        <div class="space-y-6">
            @foreach($serviceTypes as $typeKey => $typeName)
            <div class="border rounded-lg p-4">
                <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                    @if($typeKey === 'hotspot')
                        <i class="fas fa-wifi text-orange-600"></i>
                    @elseif($typeKey === 'ppp')
                        <i class="fas fa-network-wired text-blue-600"></i>
                    @else
                        <i class="fas fa-users-cog text-purple-600"></i>
                    @endif
                    {{ $typeName }}
                </h3>

                @php
                    $existingPricing = $pricing->where('service_type', $typeKey)->first();
                @endphp

                <div class="grid md:grid-cols-4 gap-4">
                    <input type="hidden" name="pricing[{{ $loop->index }}][service_type]" value="{{ $typeKey }}">
                    
                    <div>
                        <label class="block text-gray-700 mb-2 text-sm">نوع التسعير</label>
                        <select name="pricing[{{ $loop->index }}][pricing_type]"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            @foreach($pricingTypes as $pKey => $pLabel)
                            <option value="{{ $pKey }}" {{ ($existingPricing?->pricing_type ?? 'per_gb') === $pKey ? 'selected' : '' }}>
                                {{ $pLabel }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-700 mb-2 text-sm">السعر لكل وحدة</label>
                        <input type="number" name="pricing[{{ $loop->index }}][price_per_unit]"
                               value="{{ $existingPricing?->price_per_unit ?? 0 }}"
                               min="0" step="0.01"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-gray-700 mb-2 text-sm">العملة</label>
                        <select name="pricing[{{ $loop->index }}][currency]"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            @foreach($currencies as $cKey => $cLabel)
                            <option value="{{ $cKey }}" {{ ($existingPricing?->currency ?? 'SYP') === $cKey ? 'selected' : '' }}>
                                {{ $cLabel }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-700 mb-2 text-sm">الحالة</label>
                        <label class="flex items-center gap-2 mt-2">
                            <input type="checkbox" name="pricing[{{ $loop->index }}][is_active]" value="1"
                                   {{ ($existingPricing?->is_active ?? true) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                            <span class="text-gray-700">مفعّل</span>
                        </label>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="block text-gray-700 mb-2 text-sm">وصف (اختياري)</label>
                    <input type="text" name="pricing[{{ $loop->index }}][description]"
                           value="{{ $existingPricing?->description ?? '' }}"
                           placeholder="مثال: سعر خاص للوكلاء المميزين"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>
            @endforeach
        </div>

        <!-- أمثلة -->
        <div class="mt-6 p-4 bg-blue-50 rounded-lg">
            <h4 class="font-bold text-blue-800 mb-2"><i class="fas fa-lightbulb ml-1"></i> أمثلة على التسعير:</h4>
            <ul class="text-blue-700 text-sm space-y-1">
                <li>• <strong>لكل جيجابايت:</strong> إذا كان السعر 1000 ل.س/جيجا، وأنشأ الوكيل بطاقة 10GB، سيُخصم 10,000 ل.س</li>
                <li>• <strong>لكل يوم:</strong> إذا كان السعر 500 ل.س/يوم، وأنشأ الوكيل اشتراك 30 يوم، سيُخصم 15,000 ل.س</li>
                <li>• <strong>سعر ثابت:</strong> سيُخصم نفس المبلغ بغض النظر عن حجم البيانات أو المدة</li>
            </ul>
        </div>
    </div>

    <div class="mt-6 flex gap-4">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium">
            <i class="fas fa-save ml-1"></i> حفظ التسعير
        </button>
        <a href="{{ route('routers.show', $router) }}" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-lg font-medium">
            إلغاء
        </a>
    </div>
</form>
@endsection
