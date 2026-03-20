@extends('layouts.app')

@section('title', 'الدخول البعيد - WinBox')

@section('content')
<div class="max-w-6xl mx-auto p-4 sm:p-6">
    <!-- Header -->
    <div class="mb-6 sm:mb-8">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-gradient-to-br from-orange-500 to-red-600 rounded-xl flex items-center justify-center">
                <i class="fas fa-network-wired text-white text-lg sm:text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">الدخول البعيد - WinBox</h1>
                <p class="text-gray-500 text-sm sm:text-base">إدارة الراوترات عن بُعد بأمان</p>
            </div>
        </div>
    </div>

    <!-- Alert: WireGuard Status -->
    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded-r-lg">
        <div class="flex items-start gap-3">
            <i class="fas fa-info-circle text-blue-600 mt-1 flex-shrink-0"></i>
            <div>
                <p class="font-semibold text-blue-900">تنبيه أمان مهم</p>
                <p class="text-blue-700 text-sm mt-1">
                    🔒 استخدم اتصال <strong>WireGuard</strong> النشط لضمان الأمان والسرعة. WinBox متاح فقط عبر الشبكة الداخلية (10.0.0.0/24)
                </p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Routers List -->
        <div class="lg:col-span-2 space-y-4">
            <h2 class="text-xl font-bold text-gray-800 mb-4">الراوترات المتاحة</h2>
            
            @forelse($routers as $router)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-all p-5">
                <div class="flex items-start justify-between gap-4">
                    <!-- Router Info -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-2">
                            <h3 class="font-bold text-lg text-gray-800">{{ $router->name }}</h3>
                            @if($router->is_active)
                                <span class="px-2.5 py-1 bg-green-100 text-green-700 text-xs font-bold rounded-full">
                                    <i class="fas fa-check-circle ml-1"></i>نشط
                                </span>
                            @else
                                <span class="px-2.5 py-1 bg-gray-100 text-gray-700 text-xs font-bold rounded-full">
                                    <i class="fas fa-times-circle ml-1"></i>معطل
                                </span>
                            @endif
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm text-gray-600">
                            <div class="flex items-center gap-2">
                                <span class="text-gray-400">IP (Public):</span>
                                <code class="bg-gray-100 px-2 py-1 rounded font-mono text-xs">{{ $router->ip_address }}</code>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-400">IP (WireGuard):</span>
                                <code class="bg-gray-100 px-2 py-1 rounded font-mono text-xs">{{ $router->wg_client_ip ?? '—' }}</code>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-400">منفذ API:</span>
                                <code class="bg-gray-100 px-2 py-1 rounded font-mono text-xs">{{ $router->api_port ?? 8291 }}</code>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-400">المستخدم:</span>
                                <code class="bg-gray-100 px-2 py-1 rounded font-mono text-xs">{{ $router->api_username }}</code>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        @if($router->description)
                        <p class="text-gray-600 text-sm mt-3 italic">{{ $router->description }}</p>
                        @endif
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-col gap-2 flex-shrink-0">
                        <button onclick="copyToClipboard('{{ $router->wg_client_ip ?? $router->ip_address }}')" 
                                class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-medium transition"
                                title="نسخ العنوان">
                            <i class="fas fa-copy ml-1"></i>نسخ
                        </button>
                        <button onclick="openWinbox('{{ $router->wg_client_ip ?? $router->ip_address }}')" 
                                class="px-3 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-xs font-medium transition"
                                title="فتح WinBox">
                            <i class="fas fa-external-link-alt ml-1"></i>WinBox
                        </button>
                        <button onclick="openWebUI('{{ $router->wg_client_ip ?? $router->ip_address }}')" 
                                class="px-3 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-xs font-medium transition"
                                title="فتح WebUI">
                            <i class="fas fa-globe ml-1"></i>WebUI
                        </button>
                    </div>
                </div>

                <!-- Connection Details (expandable) -->
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <button onclick="toggleDetails(this)" class="text-sm text-blue-600 hover:text-blue-700 font-medium flex items-center gap-1">
                        <i class="fas fa-chevron-down transition-transform"></i>
                        تفاصيل الاتصال
                    </button>
                    <div class="hidden mt-3 bg-gray-50 p-3 rounded-lg space-y-2 text-xs">
                        <div class="flex items-start gap-2">
                            <span class="text-gray-600 font-medium min-w-fit">الطريقة 1:</span>
                            <div class="flex-1">
                                <p class="text-gray-700">فتح WinBox → أدخل <code class="bg-white px-1 rounded">{{ $router->wg_client_ip ?? $router->ip_address }}</code></p>
                            </div>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="text-gray-600 font-medium min-w-fit">الطريقة 2:</span>
                            <div class="flex-1">
                                <p class="text-gray-700">SSH Tunnel: <code class="bg-white px-1 rounded text-[10px]">ssh -L 8291:{{ $router->wg_client_ip ?? $router->ip_address }}:8291 root@104.207.66.159</code></p>
                            </div>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="text-gray-600 font-medium min-w-fit">الطريقة 3:</span>
                            <div class="flex-1">
                                <p class="text-gray-700">WebUI: <code class="bg-white px-1 rounded text-[10px]">http://{{ $router->wg_client_ip ?? $router->ip_address }}</code></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-r-lg">
                <p class="text-yellow-800">لا توجد راوترات متاحة</p>
            </div>
            @endforelse
        </div>

        <!-- Right: Instructions & Credentials -->
        <div class="space-y-6">
            <!-- Quick Start -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-rocket text-blue-500"></i>
                    البدء السريع
                </h3>
                <ol class="space-y-3 text-sm text-gray-700">
                    <li class="flex gap-2">
                        <span class="flex-shrink-0 w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs font-bold">1</span>
                        <span>تأكد من اتصال WireGuard النشط</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="flex-shrink-0 w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs font-bold">2</span>
                        <span>افتح تطبيق WinBox على جهازك</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="flex-shrink-0 w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs font-bold">3</span>
                        <span>اختر راوتر من القائمة أعلاه</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="flex-shrink-0 w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs font-bold">4</span>
                        <span>انسخ العنوان واضغط Connect</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="flex-shrink-0 w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs font-bold">5</span>
                        <span>أدخل بيانات الدخول (admin/wes)</span>
                    </li>
                </ol>
            </div>

            <!-- Credentials -->
            <div class="bg-gradient-to-br from-red-50 to-orange-50 rounded-xl shadow-sm border border-red-200 p-5">
                <h3 class="font-bold text-gray-800 mb-3 flex items-center gap-2">
                    <i class="fas fa-key text-red-500"></i>
                    بيانات الدخول
                </h3>
                <div class="space-y-3">
                    <div>
                        <p class="text-xs text-gray-600 font-medium">اسم المستخدم</p>
                        <div class="flex items-center gap-2 mt-1">
                            <code class="flex-1 bg-white px-3 py-2 rounded border border-red-200 text-sm font-mono">admin</code>
                            <button onclick="copyToClipboard('admin')" class="px-2 py-2 bg-red-500 hover:bg-red-600 text-white rounded transition">
                                <i class="fas fa-copy text-xs"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 font-medium">كلمة المرور</p>
                        <div class="flex items-center gap-2 mt-1">
                            <code class="flex-1 bg-white px-3 py-2 rounded border border-red-200 text-sm font-mono" id="passwordField">wes</code>
                            <button onclick="togglePassword()" class="px-2 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded transition">
                                <i class="fas fa-eye text-xs"></i>
                            </button>
                            <button onclick="copyToClipboard('wes')" class="px-2 py-2 bg-red-500 hover:bg-red-600 text-white rounded transition">
                                <i class="fas fa-copy text-xs"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Support -->
            <div class="bg-indigo-50 rounded-xl shadow-sm border border-indigo-200 p-5">
                <h3 class="font-bold text-gray-800 mb-3 flex items-center gap-2">
                    <i class="fas fa-circle-question text-indigo-500"></i>
                    المساعدة
                </h3>
                <div class="space-y-2 text-sm text-gray-700">
                    <p><strong>🔒 الأمان:</strong> استخدم WireGuard دائماً</p>
                    <p><strong>⚡ السرعة:</strong> اتصال سريع ومستقر</p>
                    <p><strong>📱 الهاتف:</strong> استخدم تطبيق WinBox</p>
                    <p><strong>❓ مشاكل:</strong> تحقق من WireGuard أولاً</p>
                </div>
            </div>

            <!-- Download -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h3 class="font-bold text-gray-800 mb-3">التحميلات</h3>
                <div class="space-y-2">
                    <a href="https://mikrotik.com/download" target="_blank" 
                       class="block px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium text-center transition">
                        <i class="fas fa-download ml-1"></i>WinBox
                    </a>
                    <a href="https://www.wireguard.com/install/" target="_blank" 
                       class="block px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white rounded-lg text-sm font-medium text-center transition">
                        <i class="fas fa-download ml-1"></i>WireGuard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script>
function copyToClipboard(text) {
    const elem = document.createElement('textarea');
    elem.value = text;
    document.body.appendChild(elem);
    elem.select();
    document.execCommand('copy');
    document.body.removeChild(elem);
    
    // Show toast
    showToast('تم النسخ: ' + text);
}

function togglePassword() {
    const field = document.getElementById('passwordField');
    if (field.textContent === 'wes') {
        field.textContent = '••••';
    } else {
        field.textContent = 'wes';
    }
}

function toggleDetails(button) {
    const details = button.nextElementSibling;
    details.classList.toggle('hidden');
    button.querySelector('i').style.transform = details.classList.contains('hidden') ? 'rotate(0)' : 'rotate(180deg)';
}

function openWinbox(ip) {
    // Copy to clipboard and show instruction
    copyToClipboard(ip);
    showToast('🔗 تم نسخ: ' + ip + '\nافتح WinBox واضغط Ctrl+V');
}

function openWebUI(ip) {
    const url = 'http://' + ip;
    window.open(url, '_blank');
}

function showToast(message) {
    const toast = document.createElement('div');
    toast.className = 'fixed bottom-4 right-4 bg-gray-800 text-white px-4 py-3 rounded-lg shadow-lg text-sm z-50';
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Auto-detect paste
document.addEventListener('DOMContentLoaded', function() {
    // Check WireGuard connection
    const alert = document.createElement('div');
    // Connection status will be added here
});
</script>

<style>
@media (prefers-reduced-motion: no-preference) {
    * {
        transition: all 0.2s ease-out;
    }
}

code {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
}
</style>
@endsection
