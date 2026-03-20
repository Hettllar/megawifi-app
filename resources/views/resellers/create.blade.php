@extends('layouts.app')

@section('title', 'إضافة وكيل جديد')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">إضافة وكيل جديد</h1>
        <p class="text-gray-600">إنشاء حساب وكيل/بائع جديد</p>
    </div>
    <a href="{{ route('resellers.index') }}" class="text-blue-600 hover:text-blue-800 px-4 py-2">
        <i class="fas fa-arrow-right ml-1"></i> رجوع للقائمة
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
    <form method="POST" action="{{ route('resellers.store') }}">
        @csrf

        <div class="grid md:grid-cols-2 gap-6">
            <!-- المعلومات الأساسية -->
            <div class="space-y-4">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-user text-blue-600"></i> المعلومات الأساسية
                </h3>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الاسم <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني <span class="text-red-500">*</span></label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور <span class="text-red-500">*</span></label>
                    <input type="password" name="password" required
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تأكيد كلمة المرور <span class="text-red-500">*</span></label>
                    <input type="password" name="password_confirmation" required
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                    <input type="text" name="phone" value="{{ old('phone') }}"
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
                    <input type="text" name="company_name" value="{{ old('company_name') }}"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">العنوان</label>
                    <textarea name="address" rows="2"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('address') }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نسبة العمولة (%)</label>
                    <input type="number" name="commission_rate" value="{{ old('commission_rate', 0) }}" min="0" max="100" step="0.01"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">حد المشتركين (اتركه فارغاً للتعطيل)</label>
                    <input type="number" name="max_subscribers" value="{{ old('max_subscribers') }}" min="0"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الرصيد الابتدائي</label>
                    <input type="number" name="balance" value="{{ old('balance', 0) }}" min="0" step="0.01"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" id="is_active" checked
                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <label for="is_active" class="text-sm font-medium text-gray-700">الحساب نشط</label>
                </div>
            </div>
        </div>

        <div class="mt-6 pt-6 border-t flex justify-end gap-3">
            <a href="{{ route('resellers.index') }}" class="px-6 py-2 text-gray-600 hover:text-gray-800 font-medium">
                إلغاء
            </a>
            <button type="submit" class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium">
                <i class="fas fa-plus ml-1"></i> إنشاء الوكيل
            </button>
        </div>
    </form>
</div>
@endsection
