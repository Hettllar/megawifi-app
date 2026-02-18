@extends('layouts.app')

@section('title', 'إضافة راوتر')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-plus-circle text-purple-600 ml-2"></i>
            إضافة راوتر جديد
        </h1>
        <a href="{{ route('routers.index') }}" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times text-xl"></i>
        </a>
    </div>

    <form action="{{ route('routers.store') }}" method="POST" class="space-y-6">
        @csrf
        
        <!-- معلومات أساسية -->
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">
                <i class="fas fa-info-circle text-blue-500 ml-2"></i>
                معلومات الراوتر
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">اسم الراوتر *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           placeholder="مثال: راوتر الفرع الرئيسي"
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500">
                    @error('name')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الموقع</label>
                    <input type="text" name="location" value="{{ old('location') }}"
                           placeholder="مثال: صنعاء - شارع الزبيري"
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <i class="fas fa-desktop text-blue-500 ml-1"></i>
                        رابط WinBox
                    </label>
                    <input type="text" name="public_ip" value="{{ old('public_ip', 'megawifi.site') }}"
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 font-mono">
                    <p class="text-xs text-gray-500 mt-1">عنوان الدخول عبر WinBox</p>
                    @error('public_ip')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <i class="fas fa-plug text-orange-500 ml-1"></i>
                        بورت WinBox (تلقائي)
                    </label>
                    <input type="number" name="public_port" value="{{ old('public_port', $nextPort ?? 8291) }}" readonly
                           class="w-full border rounded-lg px-4 py-2 bg-gray-100 font-mono text-orange-600 font-bold">
                    <p class="text-xs text-green-600 mt-1">
                        <i class="fas fa-check-circle ml-1"></i>
                        يتم تعيين البورت تلقائياً وفتحه على السيرفر
                    </p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">IP للاتصال (WireGuard) *</label>
                    <input type="text" name="ip_address" value="{{ old('ip_address', $nextWgIP ?? '10.0.0.10') }}" readonly
                           class="w-full border rounded-lg px-4 py-2 bg-gray-100 font-mono text-purple-600 font-bold">
                    <p class="text-xs text-gray-500 mt-1">سيتم الاتصال بالراوتر عبر هذا العنوان من خلال VPN</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">منفذ API *</label>
                    <input type="number" name="api_port" value="{{ old('api_port', 8728) }}" required
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500">
                </div>
            </div>
        </div>

        <!-- بيانات الدخول -->
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">
                <i class="fas fa-key text-green-500 ml-2"></i>
                بيانات API
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">اسم المستخدم *</label>
                    <input type="text" name="api_username" value="{{ old('api_username', 'admin') }}" required
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور *</label>
                    <input type="password" name="api_password" required
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500">
                </div>
            </div>
        </div>

        <!-- WireGuard -->
        <div class="bg-gradient-to-l from-purple-600 to-indigo-700 rounded-xl shadow p-6 text-white">
            <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
                <i class="fas fa-shield-alt"></i>
                إعدادات WireGuard VPN
            </h2>
            
            <div class="bg-white/10 rounded-lg p-4 mb-4">
                <p class="text-purple-200 text-sm mb-2">سيتم تخصيص عنوان IP تلقائياً للراوتر:</p>
                <p class="text-3xl font-bold font-mono">{{ $nextWgIP ?? '10.0.0.10' }}</p>
            </div>
            
            <input type="hidden" name="wg_enabled" value="1">
            <input type="hidden" name="wg_client_ip" value="{{ $nextWgIP ?? '10.0.0.10' }}">
            
            <div class="grid grid-cols-3 gap-4 text-sm">
                <div class="bg-white/10 rounded-lg p-3">
                    <p class="text-purple-200 text-xs">السيرفر</p>
                    <p class="font-mono">{{ config('wireguard.endpoint') }}</p>
                </div>
                <div class="bg-white/10 rounded-lg p-3">
                    <p class="text-purple-200 text-xs">الشبكة</p>
                    <p class="font-mono">10.0.0.0/24</p>
                </div>
                <div class="bg-white/10 rounded-lg p-3">
                    <p class="text-purple-200 text-xs">المنفذ</p>
                    <p class="font-mono">13231/UDP</p>
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div class="flex justify-end gap-3">
            <a href="{{ route('routers.index') }}" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300">
                إلغاء
            </a>
            <button type="submit" class="px-6 py-3 bg-purple-600 text-white rounded-xl hover:bg-purple-700 font-bold">
                <i class="fas fa-plus ml-2"></i>
                إضافة الراوتر
            </button>
        </div>
    </form>
</div>
@endsection
