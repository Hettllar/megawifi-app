@extends('layouts.app')

@section('title', 'صلاحيات الوكيل: ' . $reseller->name)

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">صلاحيات الوكيل</h1>
        <p class="text-gray-600">{{ $reseller->name }} - {{ $reseller->company_name ?? '' }}</p>
    </div>
    <a href="{{ route('resellers.show', $reseller) }}" class="text-blue-600 hover:text-blue-800">
        <i class="fas fa-arrow-right ml-1"></i> رجوع
    </a>
</div>

@if(session('success'))
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
    {{ session('success') }}
</div>
@endif

<form action="{{ route('resellers.permissions.update', $reseller) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="space-y-6">
        @foreach($routers as $router)
        @php
            $perm = $permissions[$router->id] ?? null;
            $enabled = $perm !== null;
        @endphp
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <!-- عنوان الراوتر -->
            <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-b">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full {{ $router->is_online ? 'bg-green-100' : 'bg-red-100' }} flex items-center justify-center">
                        <i class="fas fa-server {{ $router->is_online ? 'text-green-600' : 'text-red-600' }}"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-800">{{ $router->name }}</h3>
                        <p class="text-sm text-gray-500">{{ $router->ip_address }}</p>
                    </div>
                </div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="routers[{{ $loop->index }}][router_id]" value="{{ $router->id }}">
                    <input type="checkbox" name="routers[{{ $loop->index }}][enabled]" value="1"
                           {{ $enabled ? 'checked' : '' }}
                           class="router-toggle rounded border-gray-300 text-green-600 focus:ring-green-500 w-5 h-5"
                           data-router="{{ $router->id }}">
                    <span class="text-gray-700 font-medium">تفعيل</span>
                </label>
            </div>

            <!-- صلاحيات الراوتر -->
            <div class="p-6 router-permissions" id="permissions-{{ $router->id }}" style="{{ $enabled ? '' : 'display: none;' }}">
                <div class="grid md:grid-cols-3 gap-6">
                    <!-- صلاحيات Hotspot -->
                    <div class="bg-orange-50 rounded-lg p-4">
                        <h4 class="font-bold text-orange-800 mb-3 flex items-center gap-2">
                            <i class="fas fa-wifi"></i> Hotspot
                        </h4>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="routers[{{ $loop->index }}][can_create_hotspot]" value="1"
                                       {{ $perm?->can_create_hotspot ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                                <span class="text-gray-700">إنشاء مستخدم</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="routers[{{ $loop->index }}][can_edit_hotspot]" value="1"
                                       {{ $perm?->can_edit_hotspot ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                                <span class="text-gray-700">تعديل مستخدم</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="routers[{{ $loop->index }}][can_delete_hotspot]" value="1"
                                       {{ $perm?->can_delete_hotspot ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                                <span class="text-gray-700">حذف مستخدم</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="routers[{{ $loop->index }}][can_enable_disable_hotspot]" value="1"
                                       {{ $perm?->can_enable_disable_hotspot ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                                <span class="text-gray-700">تفعيل/تعطيل</span>
                            </label>
                        </div>
                    </div>

                    <!-- صلاحيات PPP -->
                    <div class="bg-blue-50 rounded-lg p-4">
                        <h4 class="font-bold text-blue-800 mb-3 flex items-center gap-2">
                            <i class="fas fa-network-wired"></i> PPP/PPPoE
                        </h4>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="routers[{{ $loop->index }}][can_create_ppp]" value="1"
                                       {{ $perm?->can_create_ppp ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="text-gray-700">إنشاء مستخدم</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="routers[{{ $loop->index }}][can_edit_ppp]" value="1"
                                       {{ $perm?->can_edit_ppp ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="text-gray-700">تعديل مستخدم</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="routers[{{ $loop->index }}][can_delete_ppp]" value="1"
                                       {{ $perm?->can_delete_ppp ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="text-gray-700">حذف مستخدم</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="routers[{{ $loop->index }}][can_enable_disable_ppp]" value="1"
                                       {{ $perm?->can_enable_disable_ppp ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="text-gray-700">تفعيل/تعطيل</span>
                            </label>
                        </div>
                    </div>

                    <!-- صلاحيات UserManager -->
                    <div class="bg-purple-50 rounded-lg p-4">
                        <h4 class="font-bold text-purple-800 mb-3 flex items-center gap-2">
                            <i class="fas fa-users-cog"></i> UserManager
                        </h4>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="routers[{{ $loop->index }}][can_create_usermanager]" value="1"
                                       {{ $perm?->can_create_usermanager ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                <span class="text-gray-700">إنشاء مستخدم</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="routers[{{ $loop->index }}][can_edit_usermanager]" value="1"
                                       {{ $perm?->can_edit_usermanager ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                <span class="text-gray-700">تعديل مستخدم</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="routers[{{ $loop->index }}][can_renew_usermanager]" value="1"
                                       {{ $perm?->can_renew_usermanager ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                <span class="text-gray-700">تجديد اشتراك</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="routers[{{ $loop->index }}][can_delete_usermanager]" value="1"
                                       {{ $perm?->can_delete_usermanager ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                <span class="text-gray-700">حذف مستخدم</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="routers[{{ $loop->index }}][can_enable_disable_usermanager]" value="1"
                                       {{ $perm?->can_enable_disable_usermanager ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                <span class="text-gray-700">تفعيل/تعطيل</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- صلاحيات إضافية -->
                <div class="mt-4 pt-4 border-t flex flex-wrap gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="routers[{{ $loop->index }}][can_view_reports]" value="1"
                               {{ $perm?->can_view_reports ? 'checked' : '' }}
                               class="rounded border-gray-300 text-gray-600 focus:ring-gray-500">
                        <span class="text-gray-700"><i class="fas fa-chart-bar ml-1"></i> عرض التقارير</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="routers[{{ $loop->index }}][can_generate_vouchers]" value="1"
                               {{ $perm?->can_generate_vouchers ? 'checked' : '' }}
                               class="rounded border-gray-300 text-gray-600 focus:ring-gray-500">
                        <span class="text-gray-700"><i class="fas fa-ticket-alt ml-1"></i> إنشاء كروت</span>
                    </label>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-6 flex gap-4">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium">
            <i class="fas fa-save ml-1"></i> حفظ الصلاحيات
        </button>
        <a href="{{ route('resellers.show', $reseller) }}" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-lg font-medium">
            إلغاء
        </a>
    </div>
</form>

<script>
document.querySelectorAll('.router-toggle').forEach(toggle => {
    toggle.addEventListener('change', function() {
        const routerId = this.dataset.router;
        const permissionsDiv = document.getElementById('permissions-' + routerId);
        if (this.checked) {
            permissionsDiv.style.display = 'block';
        } else {
            permissionsDiv.style.display = 'none';
        }
    });
});
</script>
@endsection
