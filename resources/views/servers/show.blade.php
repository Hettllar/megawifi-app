@extends('layouts.app')

@section('title', 'تفاصيل: ' . $server->name)

@section('content')
<div class="space-y-4 pb-20 max-w-3xl mx-auto">

    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('servers.index') }}" class="hover:text-cyan-600">
            <i class="fas fa-desktop ml-1"></i>السيرفرات
        </a>
        <i class="fas fa-chevron-left text-xs"></i>
        <span class="text-gray-800 font-medium">{{ $server->name }}</span>
    </div>

    <!-- Header Card -->
    <div class="bg-gradient-to-l from-cyan-600 to-blue-700 rounded-xl p-5 text-white shadow-xl">
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-desktop text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold">{{ $server->name }}</h1>
                    <p class="text-cyan-200 text-sm mt-0.5">
                        <i class="fas fa-map-marker-alt ml-1"></i>{{ $server->location ?? 'بدون موقع' }}
                    </p>
                    @if($server->description)
                    <p class="text-cyan-100 text-xs mt-1">{{ $server->description }}</p>
                    @endif
                </div>
            </div>
            <div class="flex flex-col items-end gap-2">
                <span class="px-3 py-1 rounded-full text-sm font-bold
                    {{ $server->status === 'online'  ? 'bg-green-500'  :
                       ($server->status === 'offline' ? 'bg-red-500'   : 'bg-gray-500') }}">
                    @if($server->status === 'online')
                        <i class="fas fa-circle text-green-300 ml-1" style="font-size:0.5rem"></i>متصل
                    @elseif($server->status === 'offline')
                        <i class="fas fa-circle text-red-300 ml-1" style="font-size:0.5rem"></i>غير متصل
                    @else
                        <i class="fas fa-question-circle ml-1"></i>غير معروف
                    @endif
                </span>
                @if($server->last_seen)
                <p class="text-xs text-cyan-200">آخر اتصال: {{ $server->last_seen->diffForHumans() }}</p>
                @endif
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="flex flex-wrap gap-2 mt-4">
            <button id="testBtn" onclick="testConnection()"
                    class="bg-white/20 hover:bg-white/30 px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-1.5 transition">
                <i class="fas fa-plug"></i>اختبار الاتصال
            </button>
            <button id="portBtn" onclick="openPort()"
                    class="bg-white/20 hover:bg-white/30 px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-1.5 transition">
                <i class="fas fa-unlock-alt"></i>فتح بورت SSH
            </button>
            <button onclick="checkPort()"
                    class="bg-white/20 hover:bg-white/30 px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-1.5 transition">
                <i class="fas fa-search"></i>فحص البورت
            </button>
            <a href="{{ route('servers.edit', $server) }}"
               class="bg-white/20 hover:bg-white/30 px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-1.5 transition">
                <i class="fas fa-edit"></i>تعديل
            </a>
        </div>
    </div>

    <!-- SSH Access Info -->
    <div class="bg-white rounded-xl shadow p-5 space-y-3">
        <h2 class="text-base font-bold text-gray-800 border-b pb-2">
            <i class="fas fa-terminal text-green-500 ml-2"></i>
            معلومات الاتصال SSH
        </h2>

        <!-- Local SSH -->
        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
            <div>
                <p class="text-xs text-gray-500 mb-0.5">اتصال محلي (داخل الشبكة)</p>
                <p class="font-mono text-sm font-bold text-gray-800">
                    ssh -p {{ $server->ssh_port }} {{ $server->ssh_username }}@{{ $server->hostname }}
                </p>
            </div>
            <button onclick="copyText('ssh -p {{ $server->ssh_port }} {{ $server->ssh_username }}@{{ $server->hostname }}')"
                    class="text-gray-400 hover:text-gray-600 p-2 rounded-lg hover:bg-gray-200 flex-shrink-0">
                <i class="fas fa-copy"></i>
            </button>
        </div>

        <!-- External SSH -->
        @if($server->public_port)
        <div class="flex items-center justify-between p-3 bg-cyan-50 rounded-lg border border-cyan-200">
            <div>
                <p class="text-xs text-cyan-600 mb-0.5 font-medium">
                    <i class="fas fa-globe ml-1"></i>اتصال خارجي (من الإنترنت)
                </p>
                <p class="font-mono text-sm font-bold text-cyan-800">
                    ssh -p {{ $server->public_port }} {{ $server->ssh_username }}@{{ $server->public_host ?? 'syrianew.live' }}
                </p>
            </div>
            <button onclick="copyText('ssh -p {{ $server->public_port }} {{ $server->ssh_username }}@{{ $server->public_host ?? \'syrianew.live\' }}')"
                    class="text-cyan-400 hover:text-cyan-700 p-2 rounded-lg hover:bg-cyan-100 flex-shrink-0">
                <i class="fas fa-copy"></i>
            </button>
        </div>
        @else
        <div class="p-3 bg-yellow-50 rounded-lg border border-yellow-200 text-sm text-yellow-800">
            <i class="fas fa-exclamation-triangle ml-1"></i>
            لا يوجد بورت خارجي معين. انقر <strong>فتح بورت SSH</strong> أعلاه.
        </div>
        @endif
    </div>

    <!-- Server Details -->
    <div class="bg-white rounded-xl shadow p-5">
        <h2 class="text-base font-bold text-gray-800 border-b pb-2 mb-3">
            <i class="fas fa-info-circle text-blue-500 ml-2"></i>
            تفاصيل السيرفر
        </h2>
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-gray-500 text-xs mb-1">الاسم</p>
                <p class="font-bold text-gray-800">{{ $server->name }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-gray-500 text-xs mb-1">الموقع</p>
                <p class="font-bold text-gray-800">{{ $server->location ?? '-' }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-gray-500 text-xs mb-1">العنوان</p>
                <p class="font-mono font-bold text-gray-800">{{ $server->hostname }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-gray-500 text-xs mb-1">بورت SSH</p>
                <p class="font-mono font-bold text-gray-800">{{ $server->ssh_port }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-gray-500 text-xs mb-1">المستخدم</p>
                <p class="font-mono font-bold text-gray-800">{{ $server->ssh_username }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-gray-500 text-xs mb-1">البورت الخارجي</p>
                <p class="font-mono font-bold text-orange-600">{{ $server->public_port ?? '-' }}</p>
            </div>
        </div>

        @if($server->os_info)
        <div class="mt-3 bg-gray-900 text-green-400 rounded-lg p-3 font-mono text-xs overflow-x-auto">
            <p class="text-gray-500 mb-1"># معلومات النظام</p>
            {{ $server->os_info }}
        </div>
        @endif

        @if($server->last_error)
        <div class="mt-3 bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-800">
            <p class="font-bold mb-1"><i class="fas fa-exclamation-circle ml-1"></i>آخر خطأ:</p>
            <p class="font-mono text-xs">{{ $server->last_error }}</p>
            @if($server->last_error_at)
            <p class="text-xs text-red-500 mt-1">{{ $server->last_error_at->diffForHumans() }}</p>
            @endif
        </div>
        @endif
    </div>

    <!-- Port Forwarding Rule -->
    @if($server->public_port)
    <div class="bg-white rounded-xl shadow p-5">
        <h2 class="text-base font-bold text-gray-800 border-b pb-2 mb-3">
            <i class="fas fa-exchange-alt text-purple-500 ml-2"></i>
            قاعدة إعادة التوجيه (iptables)
        </h2>
        <div class="bg-gray-900 text-green-400 rounded-lg p-3 font-mono text-xs space-y-1 overflow-x-auto">
            <p class="text-gray-500"># DNAT rule</p>
            <p>iptables -t nat -A PREROUTING -p tcp --dport {{ $server->public_port }} -j DNAT \</p>
            <p>&nbsp;&nbsp;--to-destination {{ $server->hostname }}:{{ $server->ssh_port }}</p>
            <p class="text-gray-500 mt-2"># الاتصال من الخارج</p>
            <p>ssh -p {{ $server->public_port }} {{ $server->ssh_username }}@{{ $server->public_host ?? 'syrianew.live' }}</p>
        </div>
    </div>
    @endif

    <!-- Danger Zone -->
    <div class="bg-white rounded-xl shadow p-5 border border-red-200">
        <h2 class="text-base font-bold text-red-700 border-b border-red-100 pb-2 mb-3">
            <i class="fas fa-exclamation-triangle ml-2"></i>
            منطقة الخطر
        </h2>
        <form action="{{ route('servers.destroy', $server) }}" method="POST"
              onsubmit="return confirm('هل أنت متأكد من حذف هذا السيرفر؟ سيتم إغلاق البورت تلقائياً.')">
            @csrf @method('DELETE')
            <button type="submit"
                    class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm font-medium">
                <i class="fas fa-trash ml-1"></i>حذف السيرفر
            </button>
        </form>
    </div>
</div>

<!-- Toast -->
<div id="toast" class="fixed bottom-4 left-1/2 -translate-x-1/2 bg-gray-900 text-white px-5 py-3 rounded-xl shadow-2xl z-50 hidden text-sm flex items-center gap-2 max-w-sm">
    <i id="toastIcon" class="fas fa-check-circle text-green-400"></i>
    <span id="toastMsg"></span>
</div>

<script>
const serverId = {{ $server->id }};
const csrfToken = document.querySelector('meta[name=csrf-token]').content;

function showToast(msg, success = true) {
    const t = document.getElementById('toast');
    document.getElementById('toastMsg').textContent = msg;
    document.getElementById('toastIcon').className = success
        ? 'fas fa-check-circle text-green-400'
        : 'fas fa-times-circle text-red-400';
    t.classList.remove('hidden');
    t.classList.add('flex');
    setTimeout(() => { t.classList.add('hidden'); t.classList.remove('flex'); }, 4000);
}

function setLoading(btnId, loading) {
    const btn = document.getElementById(btnId);
    if (!btn) return;
    btn.disabled = loading;
    if (loading) {
        btn.dataset.orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري...';
    } else {
        btn.innerHTML = btn.dataset.orig;
    }
}

function testConnection() {
    setLoading('testBtn', true);
    fetch(`/servers/${serverId}/test`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken }
    })
    .then(r => r.json())
    .then(data => {
        setLoading('testBtn', false);
        showToast(data.message, data.success);
        if (data.success) setTimeout(() => location.reload(), 1800);
    })
    .catch(() => {
        setLoading('testBtn', false);
        showToast('حدث خطأ في الطلب', false);
    });
}

function openPort() {
    setLoading('portBtn', true);
    fetch(`/servers/${serverId}/open-port`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken }
    })
    .then(r => r.json())
    .then(data => {
        setLoading('portBtn', false);
        showToast(data.message, data.success);
    })
    .catch(() => {
        setLoading('portBtn', false);
        showToast('حدث خطأ في فتح البورت', false);
    });
}

function checkPort() {
    fetch(`/servers/${serverId}/check-port`)
    .then(r => r.json())
    .then(data => showToast(data.message + (data.port ? ` (${data.port})` : ''), data.open));
}

function copyText(text) {
    navigator.clipboard.writeText(text).then(() => showToast('تم النسخ'));
}
</script>
@endsection
