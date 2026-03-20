@extends('layouts.app')

@section('title', 'تعديل ' . $router->name)

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-edit text-blue-600 ml-2"></i>
            تعديل: {{ $router->name }}
        </h1>
        <a href="{{ route('routers.show', $router) }}" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times text-xl"></i>
        </a>
    </div>

    <form action="{{ route('routers.update', $router) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')
        
        <!-- معلومات أساسية -->
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">
                <i class="fas fa-info-circle text-blue-500 ml-2"></i>
                معلومات الراوتر
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">اسم الراوتر *</label>
                    <input type="text" name="name" value="{{ old('name', $router->name) }}" required
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500">
                    @error('name')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الموقع</label>
                    <input type="text" name="location" value="{{ old('location', $router->location) }}"
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">WinBox (الدخول البعيد)</label>
                    <input type="text" name="public_ip" value="{{ old('public_ip', $router->public_ip) }}" placeholder="مثال: megawifi.site"
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 font-mono">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">بورت WinBox</label>
                    <input type="number" name="public_port" value="{{ old('public_port', $router->public_port ?? $router->api_port) }}" placeholder="8291"
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">IP للاتصال (WireGuard/ZeroTier/Local)</label>
                    <input type="text" name="ip_address" value="{{ old('ip_address', $router->ip_address) }}"
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 font-mono">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">منفذ API *</label>
                    <input type="number" name="api_port" value="{{ old('api_port', $router->api_port) }}" required
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
                    <input type="text" name="api_username" value="{{ old('api_username', $router->api_username) }}" required
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور (اتركه فارغ للإبقاء)</label>
                    <input type="password" name="api_password"
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500">
                </div>
            </div>
        </div>

        <!-- WireGuard Info -->
        @if($router->wg_enabled)
        <div class="bg-purple-100 rounded-xl p-6">
            <h2 class="text-lg font-bold text-purple-800 mb-2">
                <i class="fas fa-shield-alt ml-2"></i>
                WireGuard VPN
            </h2>
            <p class="text-purple-700">IP: <span class="font-mono font-bold">{{ $router->wg_client_ip }}</span></p>
            <p class="text-purple-600 text-sm mt-1">لتعديل إعدادات WireGuard، اذهب لصفحة تفاصيل الراوتر</p>
        </div>
        @endif

        <!-- ZeroTier Info -->
        @if($router->zt_enabled)
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-4">
            <h4 class="font-bold text-blue-800 flex items-center gap-2">
                <i class="fas fa-network-wired"></i>
                ZeroTier VPN
            </h4>
            <p class="text-blue-700 mt-1">Network ID: <span class="font-mono font-bold">{{ $router->zt_network_id }}</span></p>
            <p class="text-blue-700">IP: <span class="font-mono font-bold">{{ $router->zt_ip }}</span></p>
            <p class="text-blue-600 text-sm mt-1">
                الحالة: 
                @if($router->zt_connected)
                    <span class="text-green-600 font-bold"><i class="fas fa-check-circle"></i> متصل</span>
                @else
                    <span class="text-red-600"><i class="fas fa-times-circle"></i> غير متصل</span>
                @endif
            </p>
        </div>
        @endif

        <!-- ZeroTier Info -->
        @if($router->zt_enabled)
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-4">
            <h4 class="font-bold text-blue-800 flex items-center gap-2">
                <i class="fas fa-network-wired"></i>
                ZeroTier VPN
            </h4>
            <p class="text-blue-700 mt-1">Network ID: <span class="font-mono font-bold">{{ $router->zt_network_id }}</span></p>
            <p class="text-blue-700">IP: <span class="font-mono font-bold">{{ $router->zt_ip }}</span></p>
            <p class="text-blue-600 text-sm mt-1">
                الحالة: 
                @if($router->zt_connected)
                    <span class="text-green-600 font-bold"><i class="fas fa-check-circle"></i> متصل</span>
                @else
                    <span class="text-red-600"><i class="fas fa-times-circle"></i> غير متصل</span>
                @endif
            </p>
        </div>
        @endif

        <!-- إعدادات الوكلاء -->
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">
                <i class="fas fa-users text-amber-500 ml-2"></i>
                إعدادات الوكلاء
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">سعر الجيجا الواحدة (ل.س)</label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="price_per_gb" value="{{ old('price_per_gb', $router->price_per_gb) }}" min="0" step="100"
                               class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-amber-500" placeholder="0">
                        <span class="text-gray-500 text-sm whitespace-nowrap">ل.س / GB</span>
                    </div>
                    <p class="text-gray-500 text-xs mt-1">هذا السعر سيُخصم من رصيد الوكيل عند إنشاء بطاقات الهوت سبوت</p>
                </div>
            </div>
        </div>

        <!-- شام كاش QR -->
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">
                <i class="fas fa-qrcode text-green-500 ml-2"></i>
                كود شام كاش للدفع
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">صورة كود QR لشام كاش</label>
                    @if($router->shamcash_qr)
                        <div class="mb-3 relative inline-block">
                            <img src="{{ asset('storage/' . $router->shamcash_qr) }}" alt="ShamCash QR" 
                                 class="w-32 h-32 object-contain border rounded-lg">
                            <button type="button" onclick="deleteShamcashQR()" 
                                    class="absolute -top-2 -left-2 w-6 h-6 bg-red-500 text-white rounded-full text-xs hover:bg-red-600">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    @endif
                    <input type="file" name="shamcash_qr" accept="image/*"
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-green-500">
                    <p class="text-gray-500 text-xs mt-1">سيتم إرفاق رابط هذه الصورة عند إرسال رسائل التذكير بالدفع على واتساب</p>
                </div>
                
                @if($router->shamcash_qr)
                <div class="flex items-center">
                    <div class="text-sm">
                        <p class="text-gray-600">رابط صورة شام كاش:</p>
                        <code class="block mt-1 bg-gray-100 p-2 rounded text-xs break-all">{{ url('storage/' . $router->shamcash_qr) }}</code>
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- اسم الشبكة/الشعار -->
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">
                <i class="fas fa-signature text-blue-500 ml-2"></i>
                شعار رسائل واتساب
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">اسم الشبكة/الشعار</label>
                    <input type="text" name="brand_name" value="{{ old('brand_name', $router->brand_name ?? 'MegaWiFi') }}"
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500" placeholder="MegaWiFi">
                    <p class="text-gray-500 text-xs mt-1">هذا الاسم سيظهر في نهاية رسائل الواتساب المرسلة للمشتركين</p>
                </div>
                <div class="flex items-center">
                    <div class="p-4 bg-gray-50 rounded-lg text-sm">
                        <p class="text-gray-600 mb-2">مثال على الرسالة:</p>
                        <p class="text-gray-800">شكراً لتعاملكم معنا ✨</p>
                        <p class="text-blue-600 font-bold">{{ $router->brand_name ?? 'MegaWiFi' }} 🌐</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div class="flex justify-between">
            <button type="button" onclick="confirmDelete()" class="px-6 py-3 bg-red-500 text-white rounded-xl hover:bg-red-600">
                <i class="fas fa-trash ml-2"></i>حذف
            </button>
            
            <div class="flex gap-3">
                <a href="{{ route('routers.show', $router) }}" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300">
                    إلغاء
                </a>
                <button type="submit" class="px-6 py-3 bg-purple-600 text-white rounded-xl hover:bg-purple-700 font-bold">
                    <i class="fas fa-save ml-2"></i>حفظ التعديلات
                </button>
            </div>
        </div>
    </form>
    
    <!-- Delete Form (خارج form التعديل) -->
    <form id="delete-form" action="{{ route('routers.destroy', $router) }}" method="POST" class="hidden">
        @csrf
        @method('DELETE')
    </form>
</div>

<script>
function confirmDelete() {
    if (confirm('هل أنت متأكد من حذف هذا الراوتر؟ سيتم حذف جميع البيانات المرتبطة به.')) {
        document.getElementById('delete-form').submit();
    }
}

function deleteShamcashQR() {
    if (confirm('هل تريد حذف صورة شام كاش؟')) {
        fetch('{{ route("routers.delete-shamcash-qr", $router) }}', {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'حدث خطأ');
            }
        })
        .catch(() => alert('حدث خطأ في الاتصال'));
    }
}
</script>
@endsection
