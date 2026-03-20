@extends('layouts.app')

@section('title', 'تعديل الوكيل: ' . $reseller->name)

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">تعديل الوكيل: {{ $reseller->name }}</h1>
        <p class="text-gray-600">تحديث بيانات الوكيل</p>
    </div>
    <a href="{{ route('resellers.show', $reseller) }}" class="text-blue-600 hover:text-blue-800 px-4 py-2">
        <i class="fas fa-arrow-right ml-1"></i> رجوع
    </a>
</div>

@if($errors->any())
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
    <ul class="list-disc list-inside">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="bg-white rounded-xl shadow-sm p-6">
    <form method="POST" action="{{ route('resellers.update', $reseller) }}">
        @csrf
        @method('PUT')

        <div class="grid md:grid-cols-2 gap-6">
            <!-- المعلومات الأساسية -->
            <div class="space-y-4">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-user text-blue-600"></i> المعلومات الأساسية
                </h3>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الاسم <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $reseller->name) }}" required
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني <span class="text-red-500">*</span></label>
                    <input type="email" name="email" value="{{ old('email', $reseller->email) }}" required
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور الجديدة (اتركها فارغة للاحتفاظ بالقديمة)</label>
                    <input type="password" name="password"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تأكيد كلمة المرور</label>
                    <input type="password" name="password_confirmation"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                    <input type="text" name="phone" value="{{ old('phone', $reseller->phone) }}"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            <!-- معلومات إضافية -->
            <div class="space-y-4">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-building text-green-600"></i> معلومات إضافية
                </h3>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">اسم الشركة</label>
                    <input type="text" name="company_name" value="{{ old('company_name', $reseller->company_name) }}"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">العنوان</label>
                    <textarea name="address" rows="2"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('address', $reseller->address) }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نسبة العمولة (%)</label>
                    <input type="number" name="commission_rate" value="{{ old('commission_rate', $reseller->commission_rate) }}" min="0" max="100" step="0.01"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">حد المشتركين (اتركه فارغاً للتعطيل)</label>
                    <input type="number" name="max_subscribers" value="{{ old('max_subscribers', $reseller->max_subscribers) }}" min="0"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                    <p class="text-sm text-blue-800">
                        <i class="fas fa-wallet ml-1"></i>
                        الرصيد الحالي: <span class="font-bold">{{ number_format($reseller->balance, 0) }}</span>
                        <a href="{{ route('resellers.deposit', $reseller) }}" class="text-blue-600 hover:underline mr-2">شحن رصيد</a>
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" id="is_active" {{ $reseller->is_active ? 'checked' : '' }}
                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <label for="is_active" class="text-sm font-medium text-gray-700">الحساب نشط</label>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="can_view_hotspot_password" value="1" id="can_view_hotspot_password" {{ $reseller->can_view_hotspot_password ? 'checked' : '' }}
                        class="w-4 h-4 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                    <label for="can_view_hotspot_password" class="text-sm font-medium text-gray-700">
                        <i class="fas fa-eye text-orange-500 ml-1"></i>
                        إظهار كلمة مرور بطاقة الهوتسبوت للوكيل
                    </label>
                </div>
                <p class="text-xs text-gray-500 mr-6">عند التفعيل، سيتمكن الوكيل من رؤية كلمة المرور بعد إنشاء البطاقة. عند التعطيل، ستكون كلمة المرور مخفية.</p>
            </div>
        </div>

        <div class="mt-6 pt-6 border-t flex justify-between">
            <form action="{{ route('resellers.destroy', $reseller) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا الوكيل؟ سيتم حذف جميع البيانات المرتبطة به.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 text-red-600 hover:text-red-800 font-medium">
                    <i class="fas fa-trash ml-1"></i> حذف الوكيل
                </button>
            </form>

            <div class="flex gap-3">
                <a href="{{ route('resellers.show', $reseller) }}" class="px-6 py-2 text-gray-600 hover:text-gray-800 font-medium">
                    إلغاء
                </a>
                <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium">
                    <i class="fas fa-save ml-1"></i> حفظ التغييرات
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
