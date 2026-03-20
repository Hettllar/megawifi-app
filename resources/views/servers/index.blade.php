@extends('layouts.app')

@section('title', 'السيرفرات')

@section('content')
<div class="space-y-4 pb-20">
    <!-- Header -->
    <div class="bg-gradient-to-l from-cyan-600 to-blue-700 rounded-xl md:rounded-2xl p-4 md:p-6 text-white shadow-xl">
        <div class="flex flex-col gap-4">
            <div>
                <h1 class="text-lg md:text-2xl font-bold flex items-center gap-2">
                    <i class="fas fa-desktop"></i>
                    إدارة السيرفرات - وصول SSH خارجي
                </h1>
                <p class="text-cyan-200 mt-1 text-sm">وصول آمن عبر SSH من خارج الشبكة باستخدام إعادة توجيه المنافذ</p>
            </div>

            <div class="flex flex-wrap gap-2 md:gap-3">
                <div class="bg-white/20 rounded-lg md:rounded-xl px-3 md:px-4 py-2 text-center flex-1 min-w-[60px]">
                    <p class="text-xl md:text-2xl font-bold">{{ $stats['total'] ?? 0 }}</p>
                    <p class="text-xs">إجمالي</p>
                </div>
                <div class="bg-green-500/40 rounded-lg md:rounded-xl px-3 md:px-4 py-2 text-center flex-1 min-w-[60px]">
                    <p class="text-xl md:text-2xl font-bold">{{ $stats['online'] ?? 0 }}</p>
                    <p class="text-xs">متصل</p>
                </div>
                <div class="bg-red-500/40 rounded-lg md:rounded-xl px-3 md:px-4 py-2 text-center flex-1 min-w-[60px]">
                    <p class="text-xl md:text-2xl font-bold">{{ $stats['offline'] ?? 0 }}</p>
                    <p class="text-xs">غير متصل</p>
                </div>
                <a href="{{ route('servers.create') }}" class="bg-white text-cyan-700 hover:bg-cyan-50 px-4 py-2 rounded-lg md:rounded-xl font-bold flex items-center justify-center gap-2 w-full md:w-auto">
                    <i class="fas fa-plus"></i>
                    <span>إضافة سيرفر</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Search / Filter -->
    <form method="GET" class="bg-white rounded-xl shadow p-3 flex flex-col md:flex-row gap-2">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="بحث بالاسم أو العنوان أو الموقع..."
               class="flex-1 border rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-cyan-500">
        <select name="status" class="border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500">
            <option value="">كل الحالات</option>
            <option value="online"  {{ request('status') === 'online'  ? 'selected' : '' }}>متصل</option>
            <option value="offline" {{ request('status') === 'offline' ? 'selected' : '' }}>غير متصل</option>
            <option value="unknown" {{ request('status') === 'unknown' ? 'selected' : '' }}>غير معروف</option>
        </select>
        <button type="submit" class="bg-cyan-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-cyan-700">
            <i class="fas fa-search ml-1"></i>بحث
        </button>
        @if(request()->hasAny(['search','status']))
        <a href="{{ route('servers.index') }}" class="border px-4 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 text-center">
            إلغاء
        </a>
        @endif
    </form>

    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-800 rounded-xl p-3 flex items-center gap-2">
        <i class="fas fa-check-circle text-green-500"></i>
        {{ session('success') }}
    </div>
    @endif

    <!-- Servers Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse($servers as $server)
        <div class="bg-white rounded-xl shadow-sm hover:shadow-lg transition overflow-hidden border {{ $server->status === 'online' ? 'border-green-200' : ($server->status === 'offline' ? 'border-red-200' : 'border-gray-200') }}">
            <!-- Card Header -->
            <div class="p-3 md:p-4 text-white
                {{ $server->status === 'online'  ? 'bg-green-500'  :
                   ($server->status === 'offline' ? 'bg-red-500'   : 'bg-gray-400') }}">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-desktop text-xl md:text-2xl"></i>
                        <div>
                            <h3 class="font-bold text-sm md:text-base">{{ $server->name }}</h3>
                            <p class="text-xs opacity-80">{{ $server->location ?? 'بدون موقع' }}</p>
                        </div>
                    </div>
                    <div class="text-left text-xs opacity-90">
                        @if($server->status === 'online')
                            <span class="bg-white/30 px-2 py-1 rounded-full">
                                <i class="fas fa-circle text-green-300 ml-1" style="font-size:0.5rem"></i>متصل
                            </span>
                        @elseif($server->status === 'offline')
                            <span class="bg-white/30 px-2 py-1 rounded-full">
                                <i class="fas fa-circle text-red-300 ml-1" style="font-size:0.5rem"></i>غير متصل
                            </span>
                        @else
                            <span class="bg-white/30 px-2 py-1 rounded-full">
                                <i class="fas fa-question-circle ml-1"></i>غير معروف
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Card Body -->
            <div class="p-4 space-y-3">
                <!-- Hostname -->
                <div class="flex justify-between items-center p-2 bg-gray-50 rounded-lg text-sm">
                    <span class="text-gray-600"><i class="fas fa-network-wired ml-1"></i>العنوان</span>
                    <span class="font-mono text-gray-800 font-bold">{{ $server->hostname }}</span>
                </div>

                <!-- SSH -->
                <div class="flex justify-between items-center p-2 bg-blue-50 rounded-lg text-sm">
                    <span class="text-blue-700"><i class="fas fa-terminal ml-1"></i>SSH Port</span>
                    <span class="font-mono text-blue-900 font-bold">{{ $server->ssh_port }}</span>
                </div>

                <!-- External SSH Access -->
                @if($server->public_port)
                <div class="flex justify-between items-center p-2 bg-cyan-50 rounded-lg cursor-pointer hover:bg-cyan-100"
                     onclick="copySSH('{{ $server->public_host ?? 'syrianew.live' }}', {{ $server->public_port }})"
                     title="انقر لنسخ أمر SSH">
                    <span class="text-cyan-700 font-medium text-sm">
                        <i class="fas fa-globe ml-1"></i>SSH خارجي
                    </span>
                    <span class="font-mono text-cyan-900 font-bold text-sm flex items-center gap-1">
                        {{ $server->public_host ?? 'syrianew.live' }}:{{ $server->public_port }}
                        <i class="fas fa-copy text-cyan-400 text-xs"></i>
                    </span>
                </div>
                @endif

                <!-- OS Info -->
                @if($server->os_info)
                <div class="text-xs text-gray-500 truncate font-mono bg-gray-50 rounded p-2">
                    {{ $server->os_info }}
                </div>
                @endif

                <!-- Last Seen -->
                @if($server->last_seen)
                <p class="text-xs text-gray-400 text-center">
                    <i class="fas fa-clock ml-1"></i>آخر اتصال: {{ $server->last_seen->diffForHumans() }}
                </p>
                @endif
            </div>

            <!-- Card Actions -->
            <div class="p-3 bg-gray-50 flex justify-between items-center border-t">
                <a href="{{ route('servers.show', $server) }}" class="text-cyan-600 hover:text-cyan-800 font-medium text-sm">
                    <i class="fas fa-cog ml-1"></i>إدارة
                </a>
                <div class="flex gap-1">
                    <button onclick="testServerConnection({{ $server->id }}, this)"
                            class="p-2 text-green-600 hover:bg-green-100 rounded-lg" title="اختبار الاتصال">
                        <i class="fas fa-plug"></i>
                    </button>
                    <button onclick="openServerPort({{ $server->id }}, this)"
                            class="p-2 text-cyan-600 hover:bg-cyan-100 rounded-lg" title="فتح بورت SSH">
                        <i class="fas fa-unlock-alt"></i>
                    </button>
                    <a href="{{ route('servers.edit', $server) }}"
                       class="p-2 text-yellow-600 hover:bg-yellow-100 rounded-lg">
                        <i class="fas fa-edit"></i>
                    </a>
                    <form action="{{ route('servers.destroy', $server) }}" method="POST"
                          onsubmit="return confirm('هل أنت متأكد من حذف هذا السيرفر؟')">
                        @csrf @method('DELETE')
                        <button type="submit" class="p-2 text-red-500 hover:bg-red-100 rounded-lg">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @empty
        <div class="col-span-full bg-white rounded-xl shadow p-8 text-center text-gray-500">
            <i class="fas fa-desktop text-4xl text-gray-300 mb-3 block"></i>
            <p class="text-lg font-medium mb-1">لا يوجد سيرفرات بعد</p>
            <p class="text-sm mb-4">أضف سيرفرك الأول للبدء</p>
            <a href="{{ route('servers.create') }}" class="inline-flex items-center gap-2 bg-cyan-600 text-white px-4 py-2 rounded-lg hover:bg-cyan-700">
                <i class="fas fa-plus"></i>إضافة سيرفر
            </a>
        </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($servers->hasPages())
    <div class="mt-4">
        {{ $servers->links() }}
    </div>
    @endif
</div>

<!-- Toast Notification -->
<div id="toast" class="fixed bottom-4 left-1/2 -translate-x-1/2 bg-gray-900 text-white px-5 py-3 rounded-xl shadow-2xl z-50 hidden text-sm flex items-center gap-2 max-w-sm">
    <i id="toastIcon" class="fas fa-check-circle text-green-400"></i>
    <span id="toastMsg"></span>
</div>

<script>
function showToast(msg, success = true) {
    const t = document.getElementById('toast');
    document.getElementById('toastMsg').textContent = msg;
    document.getElementById('toastIcon').className = success
        ? 'fas fa-check-circle text-green-400'
        : 'fas fa-times-circle text-red-400';
    t.classList.remove('hidden');
    t.classList.add('flex');
    setTimeout(() => { t.classList.add('hidden'); t.classList.remove('flex'); }, 3500);
}

function copySSH(host, port) {
    const cmd = `ssh -p ${port} root@${host}`;
    navigator.clipboard.writeText(cmd).then(() => showToast('تم نسخ أمر SSH: ' + cmd));
}

function testServerConnection(id, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    fetch(`/servers/${id}/test`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-plug"></i>';
        showToast(data.message, data.success);
        if (data.success) setTimeout(() => location.reload(), 1500);
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-plug"></i>';
        showToast('حدث خطأ في الطلب', false);
    });
}

function openServerPort(id, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    fetch(`/servers/${id}/open-port`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-unlock-alt"></i>';
        showToast(data.message, data.success);
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-unlock-alt"></i>';
        showToast('حدث خطأ في فتح البورت', false);
    });
}
</script>
@endsection
