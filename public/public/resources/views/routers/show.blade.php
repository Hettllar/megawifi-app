@extends('layouts.app')

@section('title', $router->name)

@section('content')
<div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-6 py-4 sm:py-6 space-y-4 sm:space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-xl shadow p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3 sm:gap-4">
                <div class="w-12 h-12 sm:w-16 sm:h-16 {{ $router->status === 'online' ? 'bg-green-100' : 'bg-gray-100' }} rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-router text-2xl sm:text-3xl {{ $router->status === 'online' ? 'text-green-600' : 'text-gray-400' }}"></i>
                </div>
                <div class="min-w-0">
                    <h1 class="text-lg sm:text-2xl font-bold text-gray-800 truncate">{{ $router->name }}</h1>
                    <p class="text-sm text-gray-500 truncate">{{ $router->location ?? 'بدون موقع' }}</p>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="w-2 h-2 rounded-full {{ $router->status === 'online' ? 'bg-green-500 animate-pulse' : 'bg-gray-400' }}"></span>
                        <span class="text-xs sm:text-sm {{ $router->status === 'online' ? 'text-green-600' : 'text-gray-500' }}">
                            {{ $router->status === 'online' ? 'متصل' : 'غير متصل' }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('sms.index', $router) }}" 
                   class="flex-1 sm:flex-none px-3 sm:px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 text-sm text-center"
                   onclick="console.log('SMS clicked')">
                    <i class="fas fa-comment-sms ml-1"></i>SMS
                </a>
                @if(auth()->user()->isSuperAdmin())
                <a href="{{ route('resellers.pricing', $router) }}" 
                   class="flex-1 sm:flex-none px-3 sm:px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 text-sm text-center">
                    <i class="fas fa-tags ml-1"></i>تسعير الوكلاء
                </a>
                @endif
                <a href="{{ route('routers.edit', $router) }}" class="flex-1 sm:flex-none px-3 sm:px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm text-center">
                    <i class="fas fa-edit ml-1"></i>تعديل
                </a>
                <a href="{{ route('routers.index') }}" class="flex-1 sm:flex-none px-3 sm:px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm text-center">
                    <i class="fas fa-arrow-right ml-1"></i>رجوع
                </a>
            </div>
        </div>
    </div>

    <!-- WireGuard Section -->
    <div class="bg-gradient-to-l from-purple-600 to-indigo-700 rounded-xl shadow-lg p-4 sm:p-6 text-white">
        <h2 class="text-lg sm:text-xl font-bold mb-4 flex items-center gap-2">
            <i class="fas fa-shield-alt"></i>
            اتصال WireGuard VPN
        </h2>

        @if($router->wg_enabled && $router->wg_public_key && $router->wg_public_key !== config('wireguard.server_public_key'))
            <!-- VPN Active -->
            <div class="bg-green-500/20 border border-green-400/30 rounded-xl p-3 sm:p-4 mb-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-check-circle text-xl sm:text-2xl text-green-400"></i>
                        <div>
                            <p class="font-bold text-sm sm:text-base">WireGuard مفعل ومتصل</p>
                            <p class="text-xs sm:text-sm text-purple-200">IP: <span class="font-mono">{{ $router->wg_client_ip }}</span></p>
                        </div>
                    </div>
                    <button onclick="testWireGuard()" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg text-sm w-full sm:w-auto">
                        <i class="fas fa-sync-alt ml-1"></i>فحص الاتصال
                    </button>
                </div>
            </div>
        @elseif($router->wg_enabled && $router->wg_public_key === config('wireguard.server_public_key'))
            <!-- Wrong Key Error -->
            <div class="bg-red-500/30 border border-red-400/50 rounded-xl p-3 sm:p-4 mb-4">
                <div class="flex items-start gap-3">
                    <i class="fas fa-exclamation-circle text-xl sm:text-2xl text-red-400 flex-shrink-0"></i>
                    <div>
                        <p class="font-bold text-red-200 text-sm sm:text-base">خطأ في المفتاح العام!</p>
                        <p class="text-xs sm:text-sm text-red-300">المفتاح المحفوظ هو مفتاح السيرفر وليس الراوتر.</p>
                    </div>
                </div>
            </div>
        @elseif($router->wg_enabled)
            <!-- Pending Setup -->
            <div class="bg-yellow-500/20 border border-yellow-400/30 rounded-xl p-3 sm:p-4 mb-4">
                <div class="flex items-start gap-3">
                    <i class="fas fa-exclamation-triangle text-xl sm:text-2xl text-yellow-400 flex-shrink-0"></i>
                    <div>
                        <p class="font-bold text-sm sm:text-base">بانتظار إعداد WireGuard</p>
                        <p class="text-xs sm:text-sm text-purple-200">نفذ السكريبت ثم ألصق المفتاح العام</p>
                    </div>
                </div>
            </div>
        @else
            <!-- Not Enabled -->
            <div class="bg-red-500/20 border border-red-400/30 rounded-xl p-3 sm:p-4 mb-4">
                <p class="font-bold text-sm sm:text-base"><i class="fas fa-times-circle ml-2"></i>WireGuard غير مفعل</p>
            </div>
        @endif

        @if($router->wg_enabled)
        <!-- Instructions -->
        @if(!$router->wg_public_key || $router->wg_public_key === config('wireguard.server_public_key'))
        <div class="bg-blue-500/20 border border-blue-400/30 rounded-xl p-3 sm:p-4 mb-4">
            <h4 class="font-bold text-blue-200 mb-2 text-sm sm:text-base"><i class="fas fa-list-ol ml-2"></i>خطوات الإعداد:</h4>
            <ol class="text-xs sm:text-sm text-blue-100 space-y-1 list-decimal list-inside mr-4">
                <li>انسخ السكريبت أدناه</li>
                <li>افتح Terminal في الراوتر</li>
                <li>ألصق السكريبت واضغط Enter</li>
                <li>انتظر ظهور المفتاح العام</li>
                <li><strong class="text-yellow-300">انسخ المفتاح</strong> وألصقه أدناه</li>
            </ol>
        </div>
        @endif

        <!-- Script Section -->
        <div class="mt-4">
            <div class="flex items-center justify-between mb-2">
                <h3 class="font-bold text-sm sm:text-base">سكريبت إعداد الراوتر:</h3>
                <button onclick="copyScript()" class="bg-white/20 hover:bg-white/30 px-3 py-1 rounded text-xs sm:text-sm">
                    <i class="fas fa-copy ml-1"></i>نسخ
                </button>
            </div>
            <div class="bg-black/30 rounded-lg p-3 sm:p-4 font-mono text-xs sm:text-sm overflow-x-auto max-h-48 sm:max-h-64 overflow-y-auto" id="wgScript">
<pre class="text-green-300 whitespace-pre-wrap break-all sm:whitespace-pre sm:break-normal"># MegaWiFi WireGuard Setup - {{ $router->name }}
# IP: {{ $router->wg_client_ip }}

:do { /interface wireguard remove [find name=wg-megawifi] } on-error={}
:do { /ip address remove [find comment~"MegaWiFi"] } on-error={}
/interface wireguard add name=wg-megawifi listen-port=13231 comment="MegaWiFi VPN"
/ip address add address={{ $router->wg_client_ip }}/24 interface=wg-megawifi comment="MegaWiFi WG"
/interface wireguard peers add interface=wg-megawifi public-key="{{ config('wireguard.server_public_key') }}" endpoint-address={{ explode(':', config('wireguard.endpoint'))[0] }} endpoint-port={{ explode(':', config('wireguard.endpoint'))[1] ?? '51820' }} allowed-address=10.0.0.0/24 persistent-keepalive=25 comment="MegaWiFi Server"
/ip firewall filter add chain=input protocol=udp dst-port=13231 action=accept comment="WireGuard MegaWiFi" place-before=0
/ip firewall filter add chain=input src-address=10.0.0.0/24 action=accept comment="MegaWiFi Network" place-before=0
/ip service set api address=10.0.0.0/24 disabled=no
:delay 1s
:local myKey [/interface wireguard get wg-megawifi public-key]
:put "PUBLIC KEY:"
:put $myKey</pre>
            </div>
        </div>

        <!-- Public Key Input -->
        @if(!$router->wg_public_key || $router->wg_public_key === config('wireguard.server_public_key'))
        <div class="mt-4 bg-white/10 rounded-xl p-3 sm:p-4">
            @if($router->wg_public_key === config('wireguard.server_public_key'))
            <div class="bg-red-500/30 border border-red-400 rounded-lg p-2 sm:p-3 mb-3">
                <p class="text-red-200 font-bold text-xs sm:text-sm"><i class="fas fa-exclamation-circle ml-2"></i>خطأ: المفتاح المحفوظ هو مفتاح السيرفر!</p>
            </div>
            @endif
            <h3 class="font-bold mb-2 text-sm sm:text-base">أدخل المفتاح العام:</h3>
            <form action="{{ route('routers.wireguard.save-public-key', $router) }}" method="POST" id="pubKeyForm">
                @csrf
                <div class="flex flex-col sm:flex-row gap-2">
                    <input type="text" name="public_key" id="publicKeyInput" placeholder="المفتاح العام (44 حرف)" required
                           pattern="[A-Za-z0-9+/=]{44}" title="المفتاح يجب أن يكون 44 حرف"
                           class="flex-1 bg-white/20 border border-white/30 rounded-lg px-3 sm:px-4 py-2 text-white placeholder-purple-300 font-mono text-sm">
                    <button type="submit" class="bg-green-500 hover:bg-green-600 px-4 sm:px-6 py-2 rounded-lg font-bold text-sm">
                        <i class="fas fa-save ml-1"></i>حفظ
                    </button>
                </div>
            </form>
        </div>
        @endif
        @endif
    </div>

    <!-- WinBox Port Forwarding -->
    @if($router->wg_client_ip && $router->public_port)
    <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6 border-r-4 border-blue-500">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-desktop text-2xl text-blue-600"></i>
                </div>
                <div>
                    <h3 class="font-bold text-gray-800 text-sm sm:text-base">WinBox - دخول بعيد</h3>
                    <p class="text-sm text-gray-500 font-mono" id="winboxAddress">{{ $router->public_ip }}:{{ $router->public_port }}</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <span id="portStatus" class="px-3 py-2 rounded-lg text-sm font-bold bg-gray-100 text-gray-500">
                    <i class="fas fa-circle-notch fa-spin ml-1"></i>جاري الفحص...
                </span>
                <button onclick="openPort()" id="openPortBtn" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-bold">
                    <i class="fas fa-door-open ml-1"></i>فتح البورت
                </button>
                <button onclick="copyToClipboard('{{ $router->public_ip }}:{{ $router->public_port }}')" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm">
                    <i class="fas fa-copy ml-1"></i>نسخ
                </button>
            </div>
        </div>
    </div>
    @elseif(!$router->public_port)
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 sm:p-6">
        <div class="flex items-center gap-3">
            <i class="fas fa-exclamation-triangle text-yellow-500 text-xl"></i>
            <div>
                <p class="font-bold text-yellow-700 text-sm sm:text-base">بورت WinBox غير معين</p>
                <p class="text-xs sm:text-sm text-yellow-600">عدّل الراوتر وأضف بورت WinBox (Port Forwarding) لتفعيل الدخول البعيد</p>
            </div>
            <a href="{{ route('routers.edit', $router) }}" class="mr-auto px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg text-sm font-bold">
                <i class="fas fa-edit ml-1"></i>تعديل
            </a>
        </div>
    </div>
    @endif

    <!-- Quick Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
        <div class="bg-white rounded-xl shadow p-3 sm:p-4 text-center">
            <p class="text-2xl sm:text-3xl font-bold text-blue-600">{{ $router->subscribers_count ?? 0 }}</p>
            <p class="text-gray-500 text-xs sm:text-sm">مشترك</p>
        </div>
        <div class="bg-white rounded-xl shadow p-3 sm:p-4 text-center">
            <p class="text-2xl sm:text-3xl font-bold text-green-600">{{ $router->active_sessions_count ?? 0 }}</p>
            <p class="text-gray-500 text-xs sm:text-sm">متصل الآن</p>
        </div>
        <div class="bg-white rounded-xl shadow p-3 sm:p-4 text-center">
            <p class="text-2xl sm:text-3xl font-bold text-purple-600">{{ $router->servicePlans->count() ?? 0 }}</p>
            <p class="text-gray-500 text-xs sm:text-sm">باقة</p>
        </div>
        <div class="bg-white rounded-xl shadow p-3 sm:p-4 text-center">
            <p class="text-2xl sm:text-3xl font-bold text-gray-600">{{ $router->cpu_load ?? 0 }}%</p>
            <p class="text-gray-500 text-xs sm:text-sm">CPU</p>
        </div>
    </div>

    <!-- Actions -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
        <button onclick="syncRouter()" class="bg-blue-500 hover:bg-blue-600 text-white p-3 sm:p-4 rounded-xl font-bold text-sm sm:text-base">
            <i class="fas fa-sync-alt ml-1 sm:ml-2"></i>مزامنة
        </button>
        <button onclick="testConnection()" class="bg-green-500 hover:bg-green-600 text-white p-3 sm:p-4 rounded-xl font-bold text-sm sm:text-base">
            <i class="fas fa-plug ml-1 sm:ml-2"></i>فحص
        </button>
        <a href="{{ route('subscribers.create', ['router_id' => $router->id]) }}" class="bg-purple-500 hover:bg-purple-600 text-white p-3 sm:p-4 rounded-xl font-bold text-center text-sm sm:text-base">
            <i class="fas fa-user-plus ml-1 sm:ml-2"></i>مشترك
        </a>
        <a href="{{ route('plans.by-router', $router) }}" class="bg-orange-500 hover:bg-orange-600 text-white p-3 sm:p-4 rounded-xl font-bold text-center text-sm sm:text-base">
            <i class="fas fa-tags ml-1 sm:ml-2"></i>الباقات
        </a>
    </div>
</div>

@push('scripts')
<script>
function copyScript() {
    const text = document.getElementById('wgScript').innerText;
    navigator.clipboard.writeText(text).then(() => alert('تم النسخ!'));
}

// Handle public key form submission via AJAX
document.addEventListener('DOMContentLoaded', function() {
    const pubKeyForm = document.getElementById('pubKeyForm');
    if (pubKeyForm) {
        pubKeyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;
            
            fetch(this.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    public_key: formData.get('public_key')
                })
            })
            .then(r => r.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    location.reload();
                }
            })
            .catch(err => {
                alert('حدث خطأ في الاتصال');
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        });
    }
});

function testConnection() {
    fetch('/routers/{{ $router->id }}/test', {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
    }).then(r => r.json()).then(data => {
        alert(data.message);
        if(data.success) location.reload();
    });
}

function testWireGuard() {
    fetch('/routers/{{ $router->id }}/wireguard/test', {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
    }).then(r => r.json()).then(data => {
        alert(data.message + (data.latency ? ` (${data.latency}ms)` : ''));
        if(data.success) location.reload();
    });
}

function syncRouter() {
    fetch('/routers/{{ $router->id }}/sync', {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
    }).then(r => r.json()).then(data => {
        alert(data.message);
        if(data.success) location.reload();
    });
}

// WinBox Port Management
function checkPortStatus() {
    fetch('/routers/{{ $router->id }}/check-port', {
        headers: {'Accept': 'application/json'}
    }).then(r => r.json()).then(data => {
        const statusEl = document.getElementById('portStatus');
        const openBtn = document.getElementById('openPortBtn');
        if (!statusEl) return;
        
        if (data.open) {
            statusEl.className = 'px-3 py-2 rounded-lg text-sm font-bold bg-green-100 text-green-700';
            statusEl.innerHTML = '<i class="fas fa-check-circle ml-1"></i>البورت مفتوح';
            if (openBtn) {
                openBtn.innerHTML = '<i class="fas fa-sync-alt ml-1"></i>إعادة فتح';
                openBtn.className = 'px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg text-sm font-bold';
            }
        } else {
            statusEl.className = 'px-3 py-2 rounded-lg text-sm font-bold bg-red-100 text-red-700';
            statusEl.innerHTML = '<i class="fas fa-times-circle ml-1"></i>البورت مغلق';
            if (openBtn) {
                openBtn.innerHTML = '<i class="fas fa-door-open ml-1"></i>فتح البورت';
                openBtn.className = 'px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-bold';
            }
        }
    }).catch(() => {
        const statusEl = document.getElementById('portStatus');
        if (statusEl) {
            statusEl.className = 'px-3 py-2 rounded-lg text-sm font-bold bg-gray-100 text-gray-500';
            statusEl.innerHTML = '<i class="fas fa-question-circle ml-1"></i>غير معروف';
        }
    });
}

function openPort() {
    const btn = document.getElementById('openPortBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i>جاري الفتح...';
    }
    
    fetch('/routers/{{ $router->id }}/open-port', {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
    }).then(r => r.json()).then(data => {
        alert(data.message);
        if (data.success) {
            checkPortStatus();
        }
    }).catch(err => {
        alert('حدث خطأ في الاتصال');
    }).finally(() => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-door-open ml-1"></i>فتح البورت';
        }
    });
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('تم النسخ: ' + text);
    });
}

// Check port status on page load
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('portStatus')) {
        checkPortStatus();
    }
});
</script>
@endpush
@endsection
