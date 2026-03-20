@extends('layouts.app')

@section('title', 'تفاصيل المشترك: ' . $subscriber->username)

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ $subscriber->username }}</h1>
        <p class="text-gray-600">{{ $subscriber->router->name ?? 'غير محدد' }}</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('subscribers.edit', $subscriber) }}" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg">
            <i class="fas fa-edit ml-1"></i> تعديل
        </a>
        <a href="{{ route('subscribers.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
            <i class="fas fa-arrow-right ml-1"></i> رجوع
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- معلومات المشترك -->
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">معلومات الحساب</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-gray-500 text-sm">اسم المستخدم</label>
                    <p class="font-medium">{{ $subscriber->username }}</p>
                </div>
                <div>
                    <label class="text-gray-500 text-sm">كلمة المرور</label>
                    <p class="font-medium font-mono">{{ $subscriber->password }}</p>
                </div>
                <div>
                    <label class="text-gray-500 text-sm">الحالة</label>
                    <p>
                        @if($subscriber->status === 'active')
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-sm">نشط</span>
                        @elseif($subscriber->status === 'expired')
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-sm">منتهي</span>
                        @elseif($subscriber->status === 'suspended')
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">موقوف</span>
                        @else
                            <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-sm">غير نشط</span>
                        @endif
                    </p>
                </div>
                <div>
                    <label class="text-gray-500 text-sm">نوع الخدمة</label>
                    <p class="font-medium">
                        @if($subscriber->service_type === 'ppp')
                            <span class="text-blue-600">PPP</span>
                        @elseif($subscriber->service_type === 'hotspot')
                            <span class="text-orange-600">Hotspot</span>
                        @else
                            <span class="text-purple-600">UserManager</span>
                        @endif
                    </p>
                </div>
                <div>
                    <label class="text-gray-500 text-sm">الباقة</label>
                    <p class="font-medium">{{ $subscriber->servicePlan->name ?? 'غير محدد' }}</p>
                </div>
                <div>
                    <label class="text-gray-500 text-sm">الراوتر</label>
                    <p class="font-medium">{{ $subscriber->router->name ?? 'غير محدد' }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">معلومات العميل</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-gray-500 text-sm">الاسم الكامل</label>
                    <p class="font-medium">{{ $subscriber->full_name ?? '-' }}</p>
                </div>
                <div>
                    <label class="text-gray-500 text-sm">رقم الهاتف</label>
                    <p class="font-medium">{{ $subscriber->phone ?? '-' }}</p>
                </div>
                <div>
                    <label class="text-gray-500 text-sm">البريد الإلكتروني</label>
                    <p class="font-medium">{{ $subscriber->email ?? '-' }}</p>
                </div>
                <div>
                    <label class="text-gray-500 text-sm">العنوان</label>
                    <p class="font-medium">{{ $subscriber->address ?? '-' }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">معلومات الاشتراك</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-gray-500 text-sm">تاريخ البداية</label>
                    <p class="font-medium">{{ $subscriber->start_date ? $subscriber->start_date->format('Y-m-d') : '-' }}</p>
                </div>
                <div>
                    <label class="text-gray-500 text-sm">تاريخ الانتهاء</label>
                    <p class="font-medium {{ $subscriber->expiry_date && $subscriber->expiry_date->isPast() ? 'text-red-600' : '' }}">
                        {{ $subscriber->expiry_date ? $subscriber->expiry_date->format('Y-m-d') : '-' }}
                        @if($subscriber->expiry_date && $subscriber->expiry_date->isPast())
                            <span class="text-red-600">(منتهي)</span>
                        @elseif($subscriber->expiry_date)
                            ({{ $subscriber->expiry_date->diffForHumans() }})
                        @endif
                    </p>
                </div>
                <div>
                    <label class="text-gray-500 text-sm">IP ثابت</label>
                    <p class="font-medium font-mono">{{ $subscriber->static_ip ?? 'ديناميكي' }}</p>
                </div>
                <div>
                    <label class="text-gray-500 text-sm">MAC Address</label>
                    <p class="font-medium font-mono">{{ $subscriber->mac_address ?? '-' }}</p>
                </div>
            </div>
        </div>

        <!-- استخدام البيانات -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">استخدام البيانات</h2>
            <div class="grid grid-cols-3 gap-4 text-center">
                <div class="bg-blue-50 rounded-lg p-4">
                    <i class="fas fa-download text-3xl text-blue-500 mb-2"></i>
                    <p class="text-gray-500 text-sm">التحميل</p>
                    <p class="text-xl font-bold text-blue-600">{{ formatBytes($subscriber->download_used ?? 0) }}</p>
                </div>
                <div class="bg-green-50 rounded-lg p-4">
                    <i class="fas fa-upload text-3xl text-green-500 mb-2"></i>
                    <p class="text-gray-500 text-sm">الرفع</p>
                    <p class="text-xl font-bold text-green-600">{{ formatBytes($subscriber->upload_used ?? 0) }}</p>
                </div>
                <div class="bg-purple-50 rounded-lg p-4">
                    <i class="fas fa-chart-pie text-3xl text-purple-500 mb-2"></i>
                    <p class="text-gray-500 text-sm">الإجمالي</p>
                    <p class="text-xl font-bold text-purple-600">{{ formatBytes(($subscriber->download_used ?? 0) + ($subscriber->upload_used ?? 0)) }}</p>
                </div>
            </div>
            @if($subscriber->data_limit)
            <div class="mt-4">
                <div class="flex justify-between text-sm mb-1">
                    <span>الاستخدام</span>
                    <span>{{ number_format((($subscriber->download_used + $subscriber->upload_used) / $subscriber->data_limit) * 100, 1) }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    @php
                        $percentage = min((($subscriber->download_used + $subscriber->upload_used) / $subscriber->data_limit) * 100, 100);
                    @endphp
                    <div class="h-3 rounded-full {{ $percentage > 90 ? 'bg-red-500' : ($percentage > 70 ? 'bg-yellow-500' : 'bg-blue-500') }}" 
                         style="width: {{ $percentage }}%"></div>
                </div>
                <p class="text-sm text-gray-500 mt-1">الحد الأقصى: {{ formatBytes($subscriber->data_limit) }}</p>
            </div>
            @endif
        </div>
    </div>

    <!-- الشريط الجانبي -->
    <div class="space-y-6">
        <!-- إجراءات سريعة -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">إجراءات سريعة</h2>
            <div class="space-y-3">
                @if($subscriber->status === 'active')
                <form action="{{ route('subscribers.toggle', $subscriber) }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full bg-yellow-500 hover:bg-yellow-600 text-white py-2 rounded-lg">
                        <i class="fas fa-pause ml-1"></i> إيقاف مؤقت
                    </button>
                </form>
                <form action="{{ route('subscribers.disconnect', $subscriber) }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white py-2 rounded-lg">
                        <i class="fas fa-plug ml-1"></i> قطع الاتصال
                    </button>
                </form>
                @else
                <form action="{{ route('subscribers.toggle', $subscriber) }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white py-2 rounded-lg">
                        <i class="fas fa-play ml-1"></i> تفعيل
                    </button>
                </form>
                @endif
                <form action="{{ route('subscribers.renew', $subscriber) }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white py-2 rounded-lg">
                        <i class="fas fa-redo ml-1"></i> تجديد الاشتراك
                    </button>
                </form>
                
                <!-- Toggle IPTV -->
                <form action="{{ route('subscribers.toggle-iptv', $subscriber) }}" method="POST" id="iptvToggleForm">
                    @csrf
                    <button type="submit" class="w-full {{ $subscriber->iptv_enabled ? 'bg-purple-500 hover:bg-purple-600' : 'bg-gray-500 hover:bg-gray-600' }} text-white py-2 rounded-lg transition-colors" id="iptvToggleBtn">
                        <i class="fas fa-tv ml-1"></i> 
                        <span id="iptvBtnText">{{ $subscriber->iptv_enabled ? 'إيقاف IPTV' : 'تفعيل IPTV' }}</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- IPTV Settings (if enabled) -->
        @if($subscriber->iptv_enabled)
        <div class="bg-gradient-to-br from-purple-50 to-indigo-50 rounded-xl shadow-sm p-6 border border-purple-200">
            <h2 class="text-lg font-bold text-purple-800 mb-4 flex items-center gap-2">
                <i class="fas fa-tv"></i>
                إعدادات IPTV
            </h2>
            
            @if($subscriber->iptvSubscription)
            <div class="space-y-3">
                <div class="bg-white/70 rounded-lg p-3">
                    <p class="text-xs text-gray-600 mb-1">اسم المستخدم</p>
                    <p class="font-mono text-sm font-medium text-gray-900">{{ $subscriber->iptvSubscription->username }}</p>
                </div>
                
                <div class="bg-white/70 rounded-lg p-3">
                    <p class="text-xs text-gray-600 mb-1">كلمة المرور</p>
                    <div class="flex items-center gap-2">
                        <p class="font-mono text-sm font-medium text-gray-900 flex-1" id="iptvPassword">{{ str_repeat('•', 8) }}</p>
                        <button type="button" onclick="toggleIptvPassword()" class="text-purple-600 hover:text-purple-700">
                            <i class="fas fa-eye" id="iptvPasswordIcon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="bg-white/70 rounded-lg p-3">
                    <p class="text-xs text-gray-600 mb-1">حصر الـIP (اختياري)</p>
                    <input type="text" 
                           value="{{ $subscriber->iptv_allowed_ips ?? '' }}" 
                           placeholder="192.168.1.*, 10.0.0.0/24"
                           class="w-full px-3 py-2 bg-white border border-purple-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                           id="iptvAllowedIps">
                    <p class="text-xs text-gray-500 mt-1">افصل بين عدة IPs بفاصلة. استخدم * للنطاقات</p>
                </div>
                
                <button type="button" onclick="updateIptvIps()" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 rounded-lg text-sm font-medium">
                    <i class="fas fa-save ml-1"></i> حفظ إعدادات IP
                </button>
                
                <div class="bg-purple-100 rounded-lg p-3 mt-4">
                    <p class="text-xs text-purple-800 font-medium mb-2">رابط M3U:</p>
                    <p class="font-mono text-xs text-purple-900 break-all bg-white/70 p-2 rounded">
                        {{ url('/get_playlist.php') }}?username={{ $subscriber->iptvSubscription->username }}&password={{ $subscriber->iptvSubscription->password }}
                    </p>
                </div>
            </div>
            @else
            <div class="bg-yellow-100 border border-yellow-300 rounded-lg p-3">
                <p class="text-sm text-yellow-800">
                    <i class="fas fa-exclamation-triangle ml-1"></i>
                    IPTV مفعل ولكن لم يتم إنشاء الاشتراك بعد. قم بتحديث الصفحة.
                </p>
            </div>
            @endif
        </div>
        @endif

        <!-- الجلسات النشطة -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">الجلسات النشطة</h2>
            @if($subscriber->activeSessions->count() > 0)
            <div class="space-y-3">
                @foreach($subscriber->activeSessions as $session)
                <div class="bg-gray-50 rounded-lg p-3">
                    <div class="flex justify-between items-center">
                        <span class="font-mono text-sm">{{ $session->ip_address }}</span>
                        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        منذ {{ $session->started_at ? $session->started_at->diffForHumans() : '-' }}
                    </p>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-gray-500 text-center py-4">لا توجد جلسات نشطة</p>
            @endif
        </div>

        <!-- آخر الفواتير -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">آخر الفواتير</h2>
            @if($subscriber->invoices->count() > 0)
            <div class="space-y-2">
                @foreach($subscriber->invoices->take(5) as $invoice)
                <div class="flex justify-between items-center py-2 border-b last:border-0">
                    <div>
                        <p class="font-medium">{{ number_format($invoice->amount, 0) }} ر.ي</p>
                        <p class="text-xs text-gray-500">{{ $invoice->created_at->format('Y-m-d') }}</p>
                    </div>
                    @if($invoice->status === 'paid')
                        <span class="text-green-500"><i class="fas fa-check-circle"></i></span>
                    @else
                        <span class="text-yellow-500"><i class="fas fa-clock"></i></span>
                    @endif
                </div>
                @endforeach
            </div>
            @else
            <p class="text-gray-500 text-center py-4">لا توجد فواتير</p>
            @endif
        </div>

        <!-- ملاحظات -->
        @if($subscriber->notes)
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">ملاحظات</h2>
            <p class="text-gray-600 whitespace-pre-wrap">{{ $subscriber->notes }}</p>
        </div>
        @endif
    </div>
</div>

@php
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
}
@endphp

@push('scripts')
<script>
// Toggle IPTV
document.getElementById('iptvToggleForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('iptvToggleBtn');
    const btnText = document.getElementById('iptvBtnText');
    const originalText = btnText.textContent;
    
    btn.disabled = true;
    btnText.textContent = 'جاري التحديث...';
    
    try {
        const response = await fetch(this.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'حدث خطأ');
            btnText.textContent = originalText;
            btn.disabled = false;
        }
    } catch (error) {
        alert('حدث خطأ في الاتصال');
        btnText.textContent = originalText;
        btn.disabled = false;
    }
});

// Toggle IPTV password visibility
let iptvPasswordVisible = false;
const actualPassword = '{{ $subscriber->iptvSubscription->password ?? '' }}';

function toggleIptvPassword() {
    const passwordEl = document.getElementById('iptvPassword');
    const iconEl = document.getElementById('iptvPasswordIcon');
    
    iptvPasswordVisible = !iptvPasswordVisible;
    
    if (iptvPasswordVisible) {
        passwordEl.textContent = actualPassword;
        iconEl.className = 'fas fa-eye-slash';
    } else {
        passwordEl.textContent = '{{ str_repeat("•", 8) }}';
        iconEl.className = 'fas fa-eye';
    }
}

// Update IPTV allowed IPs
async function updateIptvIps() {
    const ipsInput = document.getElementById('iptvAllowedIps');
    const ips = ipsInput.value.trim();
    
    try {
        const response = await fetch('{{ route("subscribers.update", $subscriber) }}', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                iptv_allowed_ips: ips,
                _method: 'PUT'
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            alert('✅ تم حفظ إعدادات IP بنجاح');
        } else {
            alert('❌ ' + (data.message || 'حدث خطأ'));
        }
    } catch (error) {
        alert('❌ حدث خطأ في الاتصال');
    }
}
</script>
@endpush

@endsection
