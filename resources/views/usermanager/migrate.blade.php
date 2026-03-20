@extends('layouts.app')

@section('title', 'ترحيل PPP إلى UserManager')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-gray-800 flex items-center gap-2">
                <span class="w-10 h-10 bg-gradient-to-br from-green-500 to-green-600 rounded-xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-exchange-alt text-white"></i>
                </span>
                ترحيل PPP إلى UserManager
            </h1>
            <p class="text-gray-500 text-sm mt-1 mr-12">{{ $router->name }}</p>
        </div>
        <a href="{{ route('usermanager.index', ['router_id' => $router->id]) }}" 
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">
            <i class="fas fa-arrow-right"></i>
            العودة
        </a>
    </div>

    @if(isset($error))
        <div class="bg-red-50 border border-red-200 rounded-xl p-4">
            <div class="flex items-center gap-3">
                <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                <div>
                    <h3 class="font-bold text-red-800">خطأ في الاتصال</h3>
                    <p class="text-red-700 text-sm">{{ $error }}</p>
                </div>
            </div>
        </div>
    @elseif(empty($pppUsers))
        <div class="bg-white rounded-xl p-8 text-center shadow-sm">
            <i class="fas fa-users-slash text-gray-300 text-5xl mb-4"></i>
            <p class="text-gray-500 text-lg">لا يوجد مستخدمين PPP في الراوتر</p>
            <p class="text-gray-400 text-sm mt-2">تأكد من وجود مستخدمين في PPP > Secrets</p>
        </div>
    @else
        <!-- Info Card -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-info text-white"></i>
                </div>
                <div>
                    <h3 class="font-bold text-blue-800">ما هو الترحيل؟</h3>
                    <p class="text-blue-700 text-sm mt-1">
                        سيتم نقل المستخدمين المحددين من PPP Secrets في الراوتر إلى UserManager 7.
                        يمكنك اختيار حذفهم من PPP بعد النقل.
                    </p>
                </div>
            </div>
        </div>

        <!-- Migration Form -->
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="text-white">
                        <h2 class="text-lg font-bold">مستخدمين PPP من الراوتر</h2>
                        <p class="text-green-100 text-sm">اختر المستخدمين للترحيل إلى User Manager</p>
                    </div>
                    <div class="text-white text-center">
                        <p class="text-3xl font-bold">{{ count($pppUsers) }}</p>
                        <p class="text-green-100 text-xs">مستخدم</p>
                    </div>
                </div>
            </div>

            <div class="p-6">
                <!-- Options -->
                <div class="grid sm:grid-cols-2 gap-4 mb-6">
                    <!-- Group Selection -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-layer-group ml-1 text-green-500"></i>
                            مجموعة UserManager
                        </label>
                        <select id="umGroup" class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="default">Default</option>
                            @foreach($groups as $group)
                                @php
                                    $groupName = $group['name'] ?? $group['group'] ?? $group['.id'] ?? 'unknown';
                                @endphp
                                <option value="{{ $groupName }}">{{ $groupName }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <!-- Delete from PPP option -->
                    <div class="flex items-end">
                        <label class="flex items-center gap-3 cursor-pointer p-3 bg-orange-50 border border-orange-200 rounded-lg hover:bg-orange-100 transition">
                            <input type="checkbox" id="deleteFromPPP" class="w-5 h-5 text-orange-600 rounded border-gray-300 focus:ring-orange-500">
                            <div>
                                <span class="font-medium text-orange-800">حذف من PPP بعد النقل</span>
                                <p class="text-orange-600 text-xs">سيتم إزالة المستخدم من PPP Secrets</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Select All -->
                <div class="flex items-center justify-between mb-4 pb-4 border-b">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="selectAll" class="w-5 h-5 text-green-600 rounded border-gray-300 focus:ring-green-500">
                        <span class="font-medium text-gray-700">تحديد الكل</span>
                    </label>
                    <span id="selectedCount" class="text-sm text-gray-500">0 محدد</span>
                </div>

                <!-- Users List -->
                <div class="space-y-2 max-h-96 overflow-y-auto" id="usersList">
                    @foreach($pppUsers as $user)
                    <label class="flex items-center gap-3 p-3 bg-gray-50 hover:bg-gray-100 rounded-lg cursor-pointer transition user-item">
                        <input type="checkbox" name="users[]" value="{{ $user['name'] }}" 
                               class="w-5 h-5 text-green-600 rounded border-gray-300 focus:ring-green-500 user-checkbox">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-800">{{ $user['name'] }}</span>
                                @if($user['disabled'])
                                    <span class="px-2 py-0.5 bg-red-100 text-red-600 text-xs rounded-full">معطل</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-3 text-xs text-gray-500 mt-1">
                                <span><i class="fas fa-layer-group ml-1"></i>{{ $user['profile'] }}</span>
                                @if($user['comment'])
                                    <span><i class="fas fa-comment ml-1"></i>{{ $user['comment'] }}</span>
                                @endif
                            </div>
                        </div>
                    </label>
                    @endforeach
                </div>

                <!-- Action Button -->
                <div class="mt-6 pt-4 border-t">
                    <button onclick="migrateUsers()" id="migrateBtn"
                            class="w-full sm:w-auto px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white font-bold rounded-xl transition shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-exchange-alt ml-2"></i>
                        ترحيل المستخدمين المحددين
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
const selectAllCheckbox = document.getElementById('selectAll');
const userCheckboxes = document.querySelectorAll('.user-checkbox');
const selectedCountEl = document.getElementById('selectedCount');
const migrateBtn = document.getElementById('migrateBtn');

function updateSelectedCount() {
    const count = document.querySelectorAll('.user-checkbox:checked').length;
    if (selectedCountEl) selectedCountEl.textContent = count + ' محدد';
    if (migrateBtn) migrateBtn.disabled = count === 0;
}

if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener('change', function() {
        userCheckboxes.forEach(cb => cb.checked = this.checked);
        updateSelectedCount();
    });
}

userCheckboxes.forEach(cb => {
    cb.addEventListener('change', function() {
        const allChecked = document.querySelectorAll('.user-checkbox:checked').length === userCheckboxes.length;
        if (selectAllCheckbox) selectAllCheckbox.checked = allChecked;
        updateSelectedCount();
    });
});

function migrateUsers() {
    const selectedUsers = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
    
    if (selectedUsers.length === 0) {
        alert('الرجاء تحديد مستخدمين للترحيل');
        return;
    }

    const deleteFromPPP = document.getElementById('deleteFromPPP')?.checked || false;
    const confirmMsg = deleteFromPPP 
        ? `هل تريد ترحيل ${selectedUsers.length} مستخدم وحذفهم من PPP؟`
        : `هل تريد ترحيل ${selectedUsers.length} مستخدم إلى UserManager؟`;
    
    if (!confirm(confirmMsg)) {
        return;
    }

    const btn = migrateBtn;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-2"></i> جاري الترحيل...';
    btn.disabled = true;

    fetch('{{ route("usermanager.migrate-ppp", $router->id) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            usernames: selectedUsers,
            group: document.getElementById('umGroup')?.value || 'default',
            delete_from_ppp: deleteFromPPP
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
            modal.innerHTML = `
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
                    <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4 text-center">
                        <div class="text-4xl mb-2">✅</div>
                        <h3 class="text-xl font-bold text-white">تم الترحيل بنجاح</h3>
                    </div>
                    <div class="p-6 text-center">
                        <p class="text-2xl font-bold text-green-600 mb-2">${data.migrated} مستخدم</p>
                        ${data.failed > 0 ? `<p class="text-red-500 text-sm">فشل: ${data.failed}</p>` : ''}
                        ${data.errors && data.errors.length > 0 ? `<div class="text-right text-xs text-red-500 mt-2 max-h-24 overflow-y-auto">${data.errors.join('<br>')}</div>` : ''}
                        <button onclick="location.reload()" class="mt-4 px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg">
                            تحديث الصفحة
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        } else {
            alert(data.message || 'حدث خطأ');
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    })
    .catch(err => {
        alert('خطأ في الاتصال');
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// Initial state
updateSelectedCount();
</script>
@endpush
@endsection
