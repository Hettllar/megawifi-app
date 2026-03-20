@extends('layouts.app')

@section('title', 'إضافة سيرفر')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl md:text-2xl font-bold text-gray-800">
            <i class="fas fa-plus-circle text-cyan-600 ml-2"></i>
            إضافة سيرفر جديد
        </h1>
        <a href="{{ route('servers.index') }}" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times text-xl"></i>
        </a>
    </div>

    @if ($errors->any())
    <div class="bg-red-100 border border-red-400 text-red-700 rounded-xl p-4 mb-4">
        <ul class="list-disc list-inside text-sm space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form action="{{ route('servers.store') }}" method="POST" class="space-y-6">
        @csrf

        <!-- معلومات السيرفر -->
        <div class="bg-white rounded-xl shadow p-5">
            <h2 class="text-base font-bold text-gray-800 mb-4 border-b pb-2">
                <i class="fas fa-desktop text-cyan-500 ml-2"></i>
                معلومات السيرفر
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">اسم السيرفر *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           placeholder="مثال: سيرفر الإدارة المحلي"
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-sm">
                    @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الموقع</label>
                    <input type="text" name="location" value="{{ old('location') }}"
                           placeholder="مثال: غرفة السيرفرات"
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-sm">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <i class="fas fa-network-wired text-blue-500 ml-1"></i>
                        عنوان الاتصال (IP أو Hostname) *
                    </label>
                    <input type="text" name="hostname" value="{{ old('hostname') }}" required
                           placeholder="مثال: 172.10.0.5 أو 10.3.1.219"
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-sm font-mono">
                    <p class="text-xs text-gray-500 mt-1">
                        يُفضَّل استخدام IP ثابت أو عنوان WireGuard
                    </p>
                    @error('hostname')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label>
                    <input type="text" name="description" value="{{ old('description') }}"
                           placeholder="وصف اختياري"
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-sm">
                </div>
            </div>
        </div>

        <!-- بيانات SSH -->
        <div class="bg-white rounded-xl shadow p-5">
            <h2 class="text-base font-bold text-gray-800 mb-4 border-b pb-2">
                <i class="fas fa-terminal text-green-500 ml-2"></i>
                بيانات SSH
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">بورت SSH *</label>
                    <input type="number" name="ssh_port" value="{{ old('ssh_port', 22) }}" required
                           min="1" max="65535"
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-sm font-mono">
                    @error('ssh_port')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">اسم المستخدم *</label>
                    <input type="text" name="ssh_username" value="{{ old('ssh_username', 'root') }}" required
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-sm font-mono">
                    @error('ssh_username')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور *</label>
                    <div class="relative">
                        <input type="password" name="ssh_password" id="ssh_password" required
                               class="w-full border rounded-lg px-4 py-2 pl-10 focus:ring-2 focus:ring-cyan-500 text-sm font-mono">
                        <button type="button" onclick="togglePassword()"
                                class="absolute left-2 top-2.5 text-gray-400 hover:text-gray-600">
                            <i id="passIcon" class="fas fa-eye text-sm"></i>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-lock text-gray-400 ml-1"></i>
                        يتم تشفير كلمة المرور قبل الحفظ
                    </p>
                    @error('ssh_password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <!-- إعدادات الوصول الخارجي -->
        <div class="bg-white rounded-xl shadow p-5">
            <h2 class="text-base font-bold text-gray-800 mb-4 border-b pb-2">
                <i class="fas fa-globe text-cyan-500 ml-2"></i>
                إعدادات الوصول الخارجي
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <i class="fas fa-link text-green-500 ml-1"></i>
                        الدومين العام
                    </label>
                    <input type="text" name="public_host" value="{{ old('public_host', $defaultHost ?? 'syrianew.live') }}"
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-sm font-mono">
                    @error('public_host')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <i class="fas fa-plug text-orange-500 ml-1"></i>
                        بورت خارجي (تلقائي)
                    </label>
                    <input type="number" name="public_port" value="{{ old('public_port', $nextPort ?? 22100) }}"
                           class="w-full border rounded-lg px-4 py-2 bg-gray-100 font-mono text-orange-600 font-bold text-sm focus:ring-2 focus:ring-cyan-500"
                           readonly>
                    <p class="text-xs text-green-600 mt-1">
                        <i class="fas fa-check-circle ml-1"></i>
                        يتم تعيين البورت تلقائياً
                    </p>
                </div>
            </div>

            <div class="mt-4 bg-cyan-50 border border-cyan-200 rounded-lg p-3 text-sm text-cyan-800">
                <i class="fas fa-info-circle ml-1"></i>
                بعد الإضافة افتح تفاصيل السيرفر وانقر <strong>فتح بورت SSH</strong> لتفعيل إعادة التوجيه.
            </div>
        </div>

        <div class="flex gap-3 justify-end">
            <a href="{{ route('servers.index') }}"
               class="border px-5 py-2 rounded-lg text-gray-600 hover:bg-gray-50 text-sm">
                إلغاء
            </a>
            <button type="submit"
                    class="bg-cyan-600 text-white px-6 py-2 rounded-lg hover:bg-cyan-700 font-bold text-sm flex items-center gap-2">
                <i class="fas fa-save"></i>حفظ السيرفر
            </button>
        </div>
    </form>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('ssh_password');
    const icon  = document.getElementById('passIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash text-sm';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye text-sm';
    }
}
</script>
@endsection
