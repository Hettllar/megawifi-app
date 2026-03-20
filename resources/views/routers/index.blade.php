@extends('layouts.app')

@section('title', 'الراوترات')

@section('content')
<div class="space-y-4 pb-20">
    <!-- Header -->
    <div class="bg-gradient-to-l from-purple-600 to-indigo-700 rounded-xl md:rounded-2xl p-4 md:p-6 text-white shadow-xl">
        <div class="flex flex-col gap-4">
            <div>
                <h1 class="text-lg md:text-2xl font-bold flex items-center gap-2">
                    <i class="fas fa-shield-alt"></i>
                    إدارة الراوترات - WireGuard VPN
                </h1>
                <p class="text-purple-200 mt-1 text-sm">اتصال آمن ومشفر بين السيرفر والراوترات</p>
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
                
                @can('create', App\Models\Router::class)
                <a href="{{ route('routers.create') }}" class="bg-white text-purple-700 hover:bg-purple-50 px-4 py-2 rounded-lg md:rounded-xl font-bold flex items-center justify-center gap-2 w-full md:w-auto">
                    <i class="fas fa-plus"></i>
                    <span>إضافة راوتر</span>
                </a>
                @endcan
            </div>
        </div>
    </div>

    <!-- Server WireGuard Info -->
    <div class="bg-white rounded-xl shadow p-3 md:p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3 border-r-4 border-purple-500">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 md:w-12 md:h-12 bg-purple-100 rounded-full flex items-center justify-center flex-shrink-0">
                <i class="fas fa-server text-purple-600 text-lg md:text-xl"></i>
            </div>
            <div>
                <h3 class="font-bold text-gray-800 text-sm md:text-base">سيرفر WireGuard</h3>
                <p class="text-xs md:text-sm text-gray-500 font-mono">{{ config('wireguard.endpoint', '104.207.66.159:51820') }}</p>
            </div>
        </div>
        <div class="flex items-center gap-4 text-xs md:text-sm mr-12 md:mr-0">
            <div>
                <span class="text-gray-500">الشبكة:</span>
                <span class="font-mono">10.0.0.0/24</span>
            </div>
            <div>
                <span class="text-gray-500">IP:</span>
                <span class="font-mono">10.0.0.1</span>
            </div>
        </div>
    </div>

    <!-- Routers List -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse($routers as $router)
        <div class="bg-white rounded-xl shadow-sm hover:shadow-lg transition overflow-hidden border {{ $router->status === 'online' ? 'border-green-200' : 'border-gray-200' }}">
            <!-- Header -->
            <div class="p-3 md:p-4 {{ $router->status === 'online' ? 'bg-green-500' : 'bg-gray-400' }} text-white">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 md:gap-3">
                        <i class="fas fa-router text-xl md:text-2xl"></i>
                        <div>
                            <h3 class="font-bold text-sm md:text-base">{{ $router->name }}</h3>
                            <p class="text-xs opacity-80">{{ $router->location ?? 'بدون موقع' }}</p>
                        </div>
                    </div>
                    <div class="text-left">
                        @if($router->wg_enabled)
                        <span class="bg-purple-600 text-xs px-2 py-1 rounded-full">
                            <i class="fas fa-shield-alt ml-1"></i>VPN
                        </span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Info -->
            <div class="p-4 space-y-3">
                <!-- Router Info -->
                @if($router->status === 'online')
                <div class="bg-gradient-to-l from-blue-50 to-indigo-50 rounded-lg p-3 space-y-2">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600"><i class="fas fa-microchip ml-1"></i>الموديل</span>
                        <span class="font-medium text-gray-800">{{ $router->board_name ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600"><i class="fas fa-code-branch ml-1"></i>الإصدار</span>
                        <span class="font-mono text-xs text-gray-800">{{ $router->router_os_version ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600"><i class="fas fa-clock ml-1"></i>التشغيل</span>
                        <span class="font-medium text-gray-800">{{ $router->uptime ? \Carbon\CarbonInterval::seconds($router->uptime)->cascade()->forHumans(['parts' => 2, 'short' => true]) : '-' }}</span>
                    </div>
                    @if($router->cpu_load !== null)
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600"><i class="fas fa-tachometer-alt ml-1"></i>CPU</span>
                        <span class="font-bold {{ $router->cpu_load > 80 ? 'text-red-600' : ($router->cpu_load > 50 ? 'text-yellow-600' : 'text-green-600') }}">{{ $router->cpu_load }}%</span>
                    </div>
                    @endif
                </div>
                @endif

                <!-- Public IP for Remote Access (WinBox) -->
                @if($router->public_ip)
                <div class="flex justify-between items-center p-2 bg-blue-50 rounded-lg cursor-pointer hover:bg-blue-100" 
                     onclick="copyToClipboard('{{ $router->public_ip }}:{{ $router->public_port ?? $router->api_port }}')" 
                     title="انقر للنسخ">
                    <span class="text-blue-700 text-sm font-medium">
                        <i class="fas fa-desktop ml-1"></i>WinBox
                    </span>
                    <span class="font-mono text-blue-900 font-bold text-sm flex items-center gap-1">
                        {{ $router->public_ip }}:{{ $router->public_port ?? $router->api_port }}
                        <i class="fas fa-copy text-blue-400 text-xs"></i>
                    </span>
                </div>
                @endif

                <!-- WireGuard IP -->
                @if($router->wg_enabled && $router->wg_client_ip)
                <div class="flex justify-between items-center p-2 bg-purple-50 rounded-lg">
                    <span class="text-purple-700 text-sm font-medium">
                        <i class="fas fa-network-wired ml-1"></i>WireGuard IP
                    </span>
                    <span class="font-mono text-purple-900 font-bold">{{ $router->wg_client_ip }}</span>
                </div>
                @else
                <div class="flex justify-between items-center p-2 bg-yellow-50 rounded-lg">
                    <span class="text-yellow-700 text-sm">
                        <i class="fas fa-exclamation-triangle ml-1"></i>WireGuard غير مفعل
                    </span>
                    <a href="{{ route('routers.show', $router) }}" class="text-purple-600 text-sm hover:underline">تفعيل</a>
                </div>
                @endif

                <!-- Stats -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-gray-50 rounded-lg p-3 text-center">
                        <p class="text-xl font-bold text-blue-600">{{ $router->subscribers_count ?? 0 }}</p>
                        <p class="text-xs text-gray-500">مشترك</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3 text-center">
                        <p class="text-xl font-bold text-green-600">{{ $router->active_sessions_count ?? 0 }}</p>
                        <p class="text-xs text-gray-500">متصل الآن</p>
                    </div>
                </div>

                <!-- Last Sync -->
                @if($router->last_seen)
                <p class="text-xs text-gray-400 text-center">
                    آخر اتصال: {{ $router->last_seen->diffForHumans() }}
                </p>
                @endif
            </div>

            <!-- Actions -->
            <div class="p-3 bg-gray-50 flex justify-between items-center border-t relative z-10">
                <a href="{{ route('routers.show', $router) }}" class="text-purple-600 hover:text-purple-800 font-medium text-sm">
                    <i class="fas fa-cog ml-1"></i>إدارة
                </a>
                <div class="flex gap-1">
                    <button onclick="openSyncSettings({{ $router->id }}, '{{ $router->name }}', {{ $router->sync_interval ?? 240 }}, '{{ $router->whatsapp_type ?? 'regular' }}')" class="p-2 text-amber-600 hover:bg-amber-100 rounded-lg active:bg-amber-200" title="إعدادات الراوتر">
                        <i class="fas fa-clock"></i>
                    </button>
                    <button onclick="testConnection({{ $router->id }})" class="p-2 text-green-600 hover:bg-green-100 rounded-lg active:bg-green-200" title="فحص الاتصال">
                        <i class="fas fa-plug"></i>
                    </button>
                    <button onclick="syncRouter({{ $router->id }})" class="p-2 text-blue-600 hover:bg-blue-100 rounded-lg active:bg-blue-200" title="مزامنة">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
        </div>
        @empty
        <div class="col-span-full bg-white rounded-xl p-8 md:p-12 text-center">
            <i class="fas fa-router text-gray-300 text-5xl md:text-6xl mb-4"></i>
            <h3 class="text-lg md:text-xl font-bold text-gray-700">لا توجد راوترات</h3>
            <p class="text-gray-500 mb-4 text-sm">ابدأ بإضافة راوتر جديد مع WireGuard VPN</p>
            @can('create', App\Models\Router::class)
            <a href="{{ route('routers.create') }}" class="inline-flex items-center gap-2 bg-purple-600 text-white px-5 py-2 rounded-xl hover:bg-purple-700">
                <i class="fas fa-plus"></i>
                إضافة راوتر
            </a>
            @endcan
        </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $routers->links() }}
    </div>
</div>

<!-- Sync Settings Modal -->
<div id="syncModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="p-6 border-b bg-gradient-to-l from-amber-500 to-orange-500 rounded-t-2xl">
            <h3 class="text-xl font-bold text-white flex items-center gap-2">
                <i class="fas fa-cog"></i>
                إعدادات الراوتر
            </h3>
            <p class="text-amber-100 text-sm mt-1" id="syncRouterName">راوتر</p>
        </div>
        <div class="p-6 space-y-4">
            <!-- Sync Interval -->
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">
                    <i class="fas fa-sync-alt ml-1 text-amber-500"></i>
                    فترة تحديث الاستهلاك (Toggle)
                </label>
                <select id="syncIntervalSelect" class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl text-gray-700 font-medium focus:border-amber-500 focus:ring-2 focus:ring-amber-200 transition-all">
                    <option value="60">كل ساعة</option>
                    <option value="120">كل ساعتين</option>
                    <option value="180">كل 3 ساعات</option>
                    <option value="240">كل 4 ساعات</option>
                    <option value="360">كل 6 ساعات</option>
                    <option value="480">كل 8 ساعات</option>
                    <option value="720">كل 12 ساعة</option>
                    <option value="1440">كل 24 ساعة (يومياً)</option>
                </select>
                <p class="text-xs text-gray-500 mt-2">
                    <i class="fas fa-info-circle ml-1"></i>
                    يتم تعطيل ثم تفعيل كل مشترك لتحديث بيانات الاستهلاك من الراوتر
                </p>
            </div>
            
            <!-- WhatsApp Type -->
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">
                    <i class="fab fa-whatsapp ml-1 text-green-500"></i>
                    تطبيق الواتساب للتذكيرات
                </label>
                <select id="whatsappTypeSelect" class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl text-gray-700 font-medium focus:border-green-500 focus:ring-2 focus:ring-green-200 transition-all">
                    <option value="regular">واتساب عادي</option>
                    <option value="business">واتساب بزنس</option>
                </select>
                <p class="text-xs text-gray-500 mt-2">
                    <i class="fas fa-info-circle ml-1"></i>
                    اختر التطبيق الذي سيتم فتحه عند إرسال تذكيرات الدفع
                </p>
            </div>
            
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                <p class="text-sm text-amber-800">
                    <i class="fas fa-exclamation-triangle ml-1 text-amber-500"></i>
                    <strong>ملاحظة:</strong> التحديث التلقائي يقطع اتصال المشتركين لحظياً (أقل من ثانية)
                </p>
            </div>
        </div>
        <div class="p-4 bg-gray-50 rounded-b-2xl flex gap-3">
            <button onclick="closeSyncModal()" class="flex-1 px-4 py-3 bg-gray-200 text-gray-700 rounded-xl font-bold hover:bg-gray-300 transition-all">
                إلغاء
            </button>
            <button onclick="saveSyncSettings()" id="saveSyncBtn" class="flex-1 px-4 py-3 bg-gradient-to-l from-amber-500 to-orange-500 text-white rounded-xl font-bold hover:from-amber-600 hover:to-orange-600 transition-all">
                <i class="fas fa-save ml-1"></i>
                حفظ
            </button>
        </div>
    </div>
</div>

<input type="hidden" id="currentRouterId" value="">

@push('scripts')
<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Show toast
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-4 left-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
        toast.innerHTML = '<i class="fas fa-check ml-2"></i>تم النسخ: ' + text;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2000);
    });
}

function testConnection(id) {
    const btn = event.currentTarget;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;
    
    fetch(`/routers/${id}/test`, {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
    })
    .then(r => r.json())
    .then(data => {
        alert(data.message);
        if(data.success) location.reload();
    })
    .finally(() => {
        btn.innerHTML = '<i class="fas fa-plug"></i>';
        btn.disabled = false;
    });
}

function syncRouter(id) {
    const btn = event.currentTarget;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;
    
    fetch(`/routers/${id}/sync`, {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
    })
    .then(r => r.json())
    .then(data => {
        alert(data.message);
        if(data.success) location.reload();
    })
    .finally(() => {
        btn.innerHTML = '<i class="fas fa-sync-alt"></i>';
        btn.disabled = false;
    });
}

// Sync Settings Modal Functions
function openSyncSettings(routerId, routerName, currentInterval, whatsappType) {
    document.getElementById('currentRouterId').value = routerId;
    document.getElementById('syncRouterName').textContent = routerName;
    document.getElementById('syncIntervalSelect').value = currentInterval || 240;
    document.getElementById('whatsappTypeSelect').value = whatsappType || 'regular';
    document.getElementById('syncModal').classList.remove('hidden');
    document.getElementById('syncModal').classList.add('flex');
}

function closeSyncModal() {
    document.getElementById('syncModal').classList.add('hidden');
    document.getElementById('syncModal').classList.remove('flex');
}

function saveSyncSettings() {
    const routerId = document.getElementById('currentRouterId').value;
    const interval = document.getElementById('syncIntervalSelect').value;
    const whatsappType = document.getElementById('whatsappTypeSelect').value;
    const btn = document.getElementById('saveSyncBtn');
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i> جاري الحفظ...';
    btn.disabled = true;
    
    fetch(`/routers/${routerId}/sync-settings`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ sync_interval: interval, whatsapp_type: whatsappType })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeSyncModal();
            // Show success toast
            const toast = document.createElement('div');
            toast.className = 'fixed bottom-4 left-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
            toast.innerHTML = '<i class="fas fa-check ml-2"></i>' + data.message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        } else {
            alert(data.message || 'حدث خطأ');
        }
    })
    .catch(err => {
        alert('خطأ في الاتصال');
    })
    .finally(() => {
        btn.innerHTML = '<i class="fas fa-save ml-1"></i> حفظ';
        btn.disabled = false;
    });
}

// Close modal on outside click
document.getElementById('syncModal').addEventListener('click', function(e) {
    if (e.target === this) closeSyncModal();
});
</script>
@endpush
@endsection
