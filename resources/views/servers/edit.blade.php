@extends('layouts.app')

@section('title', 'تعديل سيرفر')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl md:text-2xl font-bold text-gray-800">
            <i class="fas fa-edit text-cyan-600 ml-2"></i>
            تعديل: {{ $server->name }}
        </h1>
        <a href="{{ route('servers.show', $server) }}" class="text-gray-500 hover:text-gray-700">
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

    <form action="{{ route('servers.update', $server) }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- معلومات السيرفر -->
        <div class="bg-white rounded-xl shadow p-5">
            <h2 class="text-base font-bold text-gray-800 mb-4 border-b pb-2">
                <i class="fas fa-desktop text-cyan-500 ml-2"></i>
                معلومات السيرفر
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">اسم السيرفر *</label>
                    <input type="text" name="name" value="{{ old('name', $server->name) }}" required
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-sm">
                    @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الموقع</label>
                    <input type="text" name="location" value="{{ old('location', $server->location) }}"
                           placeholder="غرفة السيرفرات"
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-sm">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <i class="fas fa-network-wired text-blue-500 ml-1"></i>
                        عنوان الاتصال *
                    </label>
                    <input type="text" name="hostname" value="{{ old('hostname', $server->hostname) }}" required
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-sm font-mono">
                    @error('hostname')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label>
                    <input type="text" name="description" value="{{ old('description', $server->description) }}"
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
                    <input type="number" name="ssh_port" value="{{ old('ssh_port', $server->ssh_port) }}" required
                           min="1" max="65535"
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-sm font-mono">
                    @error('ssh_port')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">اسم المستخدم *</label>
                    <input type="text" name="ssh_username" value="{{ old('ssh_username', $server->ssh_username) }}" required
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-sm font-mono">
                    @error('ssh_username')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور الجديدة</label>
                    <div class="relative">
                        <input type="password" name="ssh_password" id="ssh_password"
                               placeholder="اتركه فارغاً للإبقاء على الحالي"
                               class="w-full border rounded-lg px-4 py-2 pl-10 focus:ring-2 focus:ring-cyan-500 text-sm font-mono">
                        <button type="button" onclick="togglePassword()"
                                class="absolute left-2 top-2.5 text-gray-400 hover:text-gray-600">
                            <i id="passIcon" class="fas fa-eye text-sm"></i>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">اتركه فارغاً إذا لا تريد تغيير كلمة المرور</p>
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">الدومين العام</label>
                    <input type="text" name="public_host" value="{{ old('public_host', $server->public_host) }}"
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-sm font-mono">
                    @error('public_host')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">البورت الخارجي</label>
                    <input type="number" name="public_port" value="{{ old('public_port', $server->public_port) }}"
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-sm font-mono text-orange-600 font-bold">
                    @error('public_port')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="flex gap-3 justify-end">
            <a href="{{ route('servers.show', $server) }}"
               class="border px-5 py-2 rounded-lg text-gray-600 hover:bg-gray-50 text-sm">
                إلغاء
            </a>
            <button type="submit"
                    class="bg-cyan-600 text-white px-6 py-2 rounded-lg hover:bg-cyan-700 font-bold text-sm flex items-center gap-2">
                <i class="fas fa-save"></i>حفظ التعديلات
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
