@extends('layouts.app')

@section('title', 'القطع المتصلة')

@section('content')
<!-- Header with gradient -->
<div class="mb-6">
    <div class="bg-gradient-to-r from-cyan-600 via-blue-600 to-indigo-600 rounded-2xl p-6 text-white shadow-lg">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-white/20 backdrop-blur rounded-2xl flex items-center justify-center">
                    <i class="fas fa-network-wired text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold">القطع المتصلة</h1>
                    <p class="text-cyan-100 text-sm">مراقبة جميع الأجهزة المتصلة بالشبكة</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if($selectedRouterId)
                <div class="flex items-center gap-2 bg-white/10 backdrop-blur px-4 py-2 rounded-xl">
                    <i class="fas fa-clock text-cyan-200"></i>
                    <span class="text-sm">آخر تحديث: {{ now()->format('H:i:s') }}</span>
                </div>
                <!-- WireGuard VPN Button -->
                <button type="button" onclick="openWireGuardModal()" 
                        class="flex items-center gap-2 bg-white/20 hover:bg-white/30 backdrop-blur px-4 py-2 rounded-xl transition">
                    <i class="fas fa-shield-alt"></i>
                    <span class="text-sm hidden sm:inline">VPN للوصول عن بعد</span>
                    <span class="text-sm sm:hidden">VPN</span>
                </button>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Quick VPN Connect Section (Visible on Page) -->
@if($selectedRouterId)
<div id="quickVpnSection" class="mb-6 bg-gradient-to-br from-emerald-50 via-green-50 to-teal-50 border-2 border-green-300 rounded-2xl p-5 shadow-lg">
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg">
                <i class="fas fa-shield-alt text-white text-xl"></i>
            </div>
            <div>
                <h4 class="font-bold text-green-800 text-lg">اتصال VPN للوصول عن بعد</h4>
                <p class="text-green-600 text-sm">احصل على إعدادات VPN للوصول للقطع من أي مكان</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="getVPNConfig()" id="getVpnConfigBtn"
                    class="flex items-center gap-2 py-3 px-6 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-xl font-bold transition shadow-lg">
                <i class="fas fa-bolt"></i>
                <span>الحصول على إعدادات VPN</span>
            </button>
        </div>
    </div>
</div>
@endif

<!-- WireGuard VPN Modal -->
<div id="wireGuardModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden items-center justify-center p-4" onclick="if(event.target === this) closeWireGuardModal()">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 p-6 rounded-t-2xl">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                        <i class="fas fa-shield-alt text-2xl text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white">WireGuard VPN</h3>
                        <p class="text-purple-200 text-sm">الوصول للقطع من خارج الشبكة</p>
                    </div>
                </div>
                <button onclick="closeWireGuardModal()" class="w-10 h-10 bg-white/20 hover:bg-white/30 rounded-xl flex items-center justify-center transition">
                    <i class="fas fa-times text-white"></i>
                </button>
            </div>
        </div>
        
        <!-- Modal Body -->
        <div class="p-6" x-data="wireGuardApp()">
            <!-- App VPN Quick Connect (Mobile Only) -->
            <div id="appVpnSection" class="mb-6 bg-gradient-to-br from-emerald-50 via-green-50 to-teal-50 border-2 border-green-300 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg">
                            <i class="fas fa-shield-alt text-white text-xl"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-green-800 text-lg">اتصال VPN سريع</h4>
                            <p class="text-green-600 text-sm">اتصل بضغطة واحدة للوصول عن بعد</p>
                        </div>
                    </div>
                    <div id="vpnStatusBadge" class="px-4 py-2 rounded-full text-sm font-bold bg-gray-200 text-gray-600 shadow-inner">
                        <i class="fas fa-circle text-xs ml-1"></i>
                        غير متصل
                    </div>
                </div>
                
                <!-- Saved Connection -->
                <div id="savedConnectionSection" class="mb-2 p-2 bg-white/70 rounded-xl border border-green-200" style="display: none;">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-bookmark text-green-600"></i>
                            <span class="text-sm text-gray-700">الاتصال المحفوظ:</span>
                            <span id="savedRouterName" class="font-semibold text-green-700"></span>
                        </div>
                        <button onclick="clearSavedVPN()" class="text-xs text-red-500 hover:text-red-700">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Quick Connect Buttons -->
                <div class="grid grid-cols-1 gap-2">
                    <button onclick="quickConnectVPN()" id="quickConnectBtn" 
                            class="flex items-center justify-center gap-2 py-4 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-xl font-bold text-lg transition shadow-lg">
                        <i class="fas fa-bolt"></i>
                        <span>اتصال سريع</span>
                    </button>
                    
                    <div class="grid grid-cols-2 gap-2">
                        <button onclick="disconnectAppVPN()" id="disconnectVpnBtn"
                                class="flex items-center justify-center gap-2 py-3 bg-red-500 hover:bg-red-600 text-white rounded-xl font-medium transition" style="display: none;">
                            <i class="fas fa-power-off"></i>
                            <span>قطع الاتصال</span>
                        </button>
                        <button onclick="showVPNSettings()" 
                                class="flex items-center justify-center gap-2 py-3 bg-white hover:bg-gray-50 text-gray-700 rounded-xl font-medium transition border border-gray-200">
                            <i class="fas fa-cog"></i>
                            <span>إعدادات VPN</span>
                        </button>
                    </div>
                </div>
                
                <!-- VPN Settings (Hidden by default) -->
                <div id="vpnSettingsPanel" class="mt-4 p-4 bg-white rounded-xl border border-gray-200 hidden">
                    <h5 class="font-semibold text-gray-700 mb-3">
                        <i class="fas fa-sliders-h text-green-600 ml-2"></i>
                        إعدادات الاتصال
                    </h5>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">الراوتر</label>
                            <select id="vpnRouterSelect" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                                @foreach($routers as $router)
                                <option value="{{ $router->id }}" data-ip="{{ $router->wg_client_ip ?? $router->ip_address }}" data-name="{{ $router->name }}">
                                    {{ $router->name }} ({{ $router->wg_client_ip ?? $router->ip_address }})
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="saveAndConnectVPN()" class="flex-1 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition">
                                <i class="fas fa-save ml-1"></i>
                                حفظ والاتصال
                            </button>
                            <button onclick="hideVPNSettings()" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition">
                                إلغاء
                            </button>
                        </div>
                    </div>
                </div>
                
                <p class="mt-3 text-xs text-green-600/70 text-center">
                    <i class="fas fa-lock ml-1"></i>
                    اتصال آمن ومشفر عبر WireGuard VPN
                </p>
            </div>
            
            <!-- Status Check -->
            <div class="mb-6">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="font-semibold text-gray-800">حالة WireGuard على الراوتر</h4>
                    <button @click="checkStatus()" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm transition">
                        <i class="fas fa-sync-alt" :class="checking && 'fa-spin'"></i>
                        فحص
                    </button>
                </div>
                
                <!-- Status Display -->
                <div x-show="status !== null" class="rounded-xl p-4" :class="status === 'active' ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200'">
                    <template x-if="status === 'active'">
                        <div class="flex items-center gap-2">
                            <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-check text-white"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-green-800">WireGuard مفعّل ✓</p>
                                <p class="text-green-600 text-sm">Interface: <span x-text="wgInterface"></span></p>
                            </div>
                        </div>
                    </template>
                    <template x-if="status === 'inactive'">
                        <div class="flex items-center gap-2">
                            <div class="w-10 h-10 bg-yellow-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-exclamation text-white"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-yellow-800">WireGuard غير مفعّل</p>
                                <p class="text-yellow-600 text-sm">يجب إعداده أولاً</p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            
            <!-- Setup Section -->
            <div x-show="status === 'inactive'" class="mb-6 bg-gray-50 rounded-xl p-5">
                <h4 class="font-semibold text-gray-800 mb-3">
                    <i class="fas fa-cog text-purple-500 ml-2"></i>
                    إعداد WireGuard
                </h4>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">عنوان الشبكة الافتراضية</label>
                        <input type="text" x-model="setupConfig.address" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="10.10.10.1/24">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">المنفذ (Port)</label>
                        <input type="number" x-model="setupConfig.port" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="51820">
                    </div>
                    <button @click="setupWireGuard()" :disabled="setting" 
                            class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-medium transition disabled:opacity-50">
                        <i class="fas fa-magic ml-2" :class="setting && 'fa-spin'"></i>
                        <span x-text="setting ? 'جاري الإعداد...' : 'إعداد WireGuard تلقائياً'"></span>
                    </button>
                </div>
            </div>
            
            <!-- Peer Configuration -->
            <div x-show="status === 'active'" class="space-y-4">
                <div class="bg-purple-50 rounded-xl p-5 border border-purple-200">
                    <h4 class="font-semibold text-purple-800 mb-3">
                        <i class="fas fa-mobile-alt ml-2"></i>
                        إعداد جهازك للاتصال
                    </h4>
                    
                    <!-- Add Peer Form -->
                    <div class="space-y-3 mb-2">
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">اسم الجهاز (Peer)</label>
                            <input type="text" x-model="newPeer.name" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="my-phone">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">IP للجهاز</label>
                            <input type="text" x-model="newPeer.ip" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="10.10.10.2/32">
                        </div>
                        <button @click="addPeer()" :disabled="addingPeer"
                                class="w-full py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition disabled:opacity-50">
                            <i class="fas fa-plus ml-2" :class="addingPeer && 'fa-spin'"></i>
                            <span x-text="addingPeer ? 'جاري الإضافة...' : 'إضافة جهاز وتوليد QR'"></span>
                        </button>
                    </div>
                </div>
                
                <!-- Generated Config & QR -->
                <div x-show="generatedConfig" class="bg-gray-900 rounded-xl p-5 text-white">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-semibold">
                            <i class="fas fa-qrcode ml-2 text-purple-400"></i>
                            إعدادات الاتصال
                        </h4>
                        <button @click="copyConfig()" class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm transition">
                            <i class="fas fa-copy ml-1"></i>
                            نسخ
                        </button>
                    </div>
                    
                    <!-- QR Code -->
                    <div class="flex justify-center mb-2">
                        <div id="qrcode" class="bg-white p-4 rounded-xl"></div>
                    </div>
                    
                    <!-- Config Text -->
                    <pre class="text-xs bg-gray-800 rounded-lg p-4 overflow-x-auto whitespace-pre-wrap break-all" x-text="generatedConfig"></pre>
                    
                    <p class="text-gray-400 text-xs mt-3 text-center">
                        <i class="fas fa-info-circle ml-1"></i>
                        امسح QR Code من تطبيق WireGuard على هاتفك
                    </p>
                </div>
                
                <!-- Existing Peers -->
                <div x-show="peers.length > 0" class="bg-gray-50 rounded-xl p-5">
                    <h4 class="font-semibold text-gray-800 mb-3">
                        <i class="fas fa-users text-gray-500 ml-2"></i>
                        الأجهزة المتصلة (<span x-text="peers.length"></span>)
                    </h4>
                    <div class="space-y-2">
                        <template x-for="peer in peers" :key="peer.id">
                            <div class="flex items-center justify-between bg-white rounded-lg p-2 border">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-mobile-alt text-purple-600 text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-800 text-sm" x-text="peer.comment || 'Peer'"></p>
                                        <p class="text-gray-500 text-xs" x-text="peer['allowed-address']"></p>
                                    </div>
                                </div>
                                <button @click="removePeer(peer['.id'])" class="w-8 h-8 bg-red-100 hover:bg-red-200 text-red-600 rounded-lg flex items-center justify-center transition">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            
            <!-- Help Section -->
            <div class="mt-6 bg-blue-50 rounded-xl p-4 border border-blue-200">
                <h5 class="font-semibold text-blue-800 mb-2">
                    <i class="fas fa-lightbulb ml-2 text-yellow-500"></i>
                    كيفية الاستخدام
                </h5>
                <ol class="text-blue-700 text-sm space-y-1 list-decimal list-inside">
                    <li>ثبّت تطبيق WireGuard على هاتفك</li>
                    <li>أضف جهازك وولّد QR Code</li>
                    <li>امسح QR من التطبيق</li>
                    <li>فعّل الاتصال وستصل لأي جهاز داخل الشبكة</li>
                </ol>
                <div class="flex gap-2 mt-3">
                    <a href="https://apps.apple.com/app/wireguard/id1441195209" target="_blank" class="flex-1 py-2 bg-black text-white rounded-lg text-center text-xs">
                        <i class="fab fa-apple ml-1"></i> App Store
                    </a>
                    <a href="https://play.google.com/store/apps/details?id=com.wireguard.android" target="_blank" class="flex-1 py-2 bg-green-600 text-white rounded-lg text-center text-xs">
                        <i class="fab fa-google-play ml-1"></i> Play Store
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-2xl shadow-sm p-5 mb-5 border border-gray-100" x-data="{ showAdvanced: false }">
    <form method="GET" action="{{ route('devices.index') }}" class="space-y-3">
        <!-- الصف الأول: البحث الأساسي -->
        <div class="flex flex-col sm:flex-row gap-2">
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-500 mb-1">🔍 بحث سريع</label>
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}" 
                           placeholder="ابحث بـ IP, MAC, اسم المستخدم, Hostname..."
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-cyan-500 text-sm bg-white"
                           id="searchInput">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                </div>
            </div>
            <div class="sm:w-48">
                <label class="block text-xs font-medium text-gray-500 mb-1">الراوتر</label>
                <select name="router_id" onchange="this.form.submit()" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-cyan-500 text-sm bg-white">
                    <option value="">-- اختر الراوتر --</option>
                    @foreach($routers as $router)
                        <option value="{{ $router->id }}" {{ $selectedRouterId == $router->id ? 'selected' : '' }}>
                            {{ $router->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="sm:w-40">
                <label class="block text-xs font-medium text-gray-500 mb-1">النوع</label>
                <select name="type" onchange="this.form.submit()" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-cyan-500 text-sm bg-white">
                    <option value="all" {{ request('type', 'all') == 'all' ? 'selected' : '' }}>الكل</option>
                    <option value="dhcp" {{ request('type') == 'dhcp' ? 'selected' : '' }}>DHCP</option>
                    <option value="arp" {{ request('type') == 'arp' ? 'selected' : '' }}>ARP</option>
                    <option value="pppoe" {{ request('type') == 'pppoe' ? 'selected' : '' }}>PPPoE</option>
                    <option value="hotspot" {{ request('type') == 'hotspot' ? 'selected' : '' }}>Hotspot</option>
                    <option value="wireless" {{ request('type') == 'wireless' ? 'selected' : '' }}>Wireless</option>
                    <option value="remote" {{ request('type') == 'remote' ? 'selected' : '' }}>Remote</option>
                </select>
            </div>
        </div>
        
        <!-- زر البحث المتقدم -->
        <div class="flex items-center justify-between">
            <button type="button" @click="showAdvanced = !showAdvanced" class="text-sm text-cyan-600 hover:text-cyan-700 flex items-center gap-1">
                <i class="fas fa-sliders-h"></i>
                <span x-text="showAdvanced ? 'إخفاء البحث المتقدم' : 'بحث متقدم'"></span>
                <i class="fas fa-chevron-down text-xs transition-transform" :class="showAdvanced ? 'rotate-180' : ''"></i>
            </button>
            <div class="flex gap-2">
                @if(request('search') || request('ip') || request('mac'))
                <a href="{{ route('devices.index', ['router_id' => $selectedRouterId]) }}" class="px-3 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition text-sm">
                    <i class="fas fa-times ml-1"></i> مسح
                </a>
                @endif
                <button type="submit" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 transition text-sm flex items-center gap-2">
                    <i class="fas fa-search"></i>
                    بحث
                </button>
                @if($selectedRouterId)
                <button type="button" onclick="refreshDevices()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-2">
                    <i class="fas fa-sync-alt" id="refreshIcon"></i>
                    تحديث
                </button>
                @endif
            </div>
        </div>
        
        <!-- البحث المتقدم -->
        <div x-show="showAdvanced" x-collapse class="pt-3 border-t border-gray-100 mt-3">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">بحث بـ IP</label>
                    <input type="text" name="ip" value="{{ request('ip') }}" placeholder="192.168.1.x"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-cyan-500 text-sm font-mono" dir="ltr">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">بحث بـ MAC</label>
                    <input type="text" name="mac" value="{{ request('mac') }}" placeholder="AA:BB:CC:DD:EE:FF"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-cyan-500 text-sm font-mono" dir="ltr">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">بحث باسم المستخدم</label>
                    <input type="text" name="username" value="{{ request('username') }}" placeholder="username"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-cyan-500 text-sm">
                </div>
            </div>
        </div>
    </form>
</div>

@if($error)
<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-2 flex items-center gap-2">
    <i class="fas fa-exclamation-circle"></i>
    <span>{{ $error }}</span>
</div>
@endif

@if(!$selectedRouterId)
<div class="bg-white rounded-xl shadow-sm border p-8 text-center">
    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-2">
        <i class="fas fa-server text-gray-400 text-3xl"></i>
    </div>
    <h3 class="text-lg font-semibold text-gray-700 mb-2">اختر راوتر للعرض</h3>
    <p class="text-gray-500 text-sm">يرجى اختيار راوتر من القائمة أعلاه لعرض القطع المتصلة</p>
</div>
@elseif(count($devices) === 0)
<div class="bg-white rounded-xl shadow-sm border p-8 text-center">
    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-2">
        <i class="fas fa-laptop text-gray-400 text-3xl"></i>
    </div>
    <h3 class="text-lg font-semibold text-gray-700 mb-2">لا توجد قطع متصلة</h3>
    <p class="text-gray-500 text-sm">لا توجد أجهزة متصلة حالياً على هذا الراوتر</p>
</div>
@else

<!-- Stats -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-2 mb-5">
    @php
        $dhcpCount = collect($devices)->where('type', 'dhcp')->count();
        $arpCount = collect($devices)->where('type', 'arp')->count();
        $pppoeCount = collect($devices)->where('type', 'pppoe')->count();
        $hotspotCount = collect($devices)->where('type', 'hotspot')->count();
        $wirelessCount = collect($devices)->where('type', 'wireless')->count();
        $remoteCount = collect($devices)->where('type', 'remote')->count();
    @endphp
    <div class="bg-gradient-to-br from-cyan-500 to-cyan-600 rounded-2xl p-4 text-white shadow-lg">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-3xl font-bold">{{ count($devices) }}</div>
                <div class="text-cyan-100 text-xs">إجمالي القطع</div>
            </div>
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-laptop text-xl"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm hover:shadow-md transition">
        <div class="flex items-center gap-2">
            <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-network-wired text-green-600"></i>
            </div>
            <div>
                <div class="text-2xl font-bold text-green-600">{{ $dhcpCount }}</div>
                <div class="text-gray-500 text-xs">DHCP</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm hover:shadow-md transition">
        <div class="flex items-center gap-2">
            <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-list text-blue-600"></i>
            </div>
            <div>
                <div class="text-2xl font-bold text-blue-600">{{ $arpCount }}</div>
                <div class="text-gray-500 text-xs">ARP</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm hover:shadow-md transition">
        <div class="flex items-center gap-2">
            <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-broadcast-tower text-purple-600"></i>
            </div>
            <div>
                <div class="text-2xl font-bold text-purple-600">{{ $pppoeCount }}</div>
                <div class="text-gray-500 text-xs">PPPoE</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm hover:shadow-md transition">
        <div class="flex items-center gap-2">
            <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-wifi text-amber-600"></i>
            </div>
            <div>
                <div class="text-2xl font-bold text-amber-600">{{ $hotspotCount }}</div>
                <div class="text-gray-500 text-xs">Hotspot</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm hover:shadow-md transition">
        <div class="flex items-center gap-2">
            <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-signal text-indigo-600"></i>
            </div>
            <div>
                <div class="text-2xl font-bold text-indigo-600">{{ $wirelessCount }}</div>
                <div class="text-gray-500 text-xs">Wireless</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm hover:shadow-md transition">
        <div class="flex items-center gap-2">
            <div class="w-10 h-10 bg-rose-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-satellite-dish text-rose-600"></i>
            </div>
            <div>
                <div class="text-2xl font-bold text-rose-600">{{ $remoteCount }}</div>
                <div class="text-gray-500 text-xs">Remote</div>
            </div>
        </div>
    </div>
</div>

<!-- Devices Table - Desktop -->
<div class="hidden md:block bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b">
        <h3 class="font-semibold text-gray-700 flex items-center gap-2">
            <i class="fas fa-table text-cyan-500"></i>
            قائمة الأجهزة المتصلة
            <span class="bg-cyan-100 text-cyan-700 px-2 py-0.5 rounded-full text-xs mr-2">{{ count($devices) }} جهاز</span>
        </h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50/50">
                <tr>
                    <th class="px-5 py-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">النوع</th>
                    <th class="px-5 py-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">عنوان IP</th>
                    <th class="px-5 py-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">عنوان MAC</th>
                    <th class="px-5 py-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">المعلومات</th>
                    <th class="px-5 py-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">الحالة</th>
                    <th class="px-5 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">إجراءات</th>
                </tr>
            </thead>
            <tbody id="devicesTable" class="divide-y divide-gray-100">
                @foreach($devices as $device)
                <tr class="hover:bg-cyan-50/50 transition group">
                    <td class="px-5 py-4">
                        @if($device['type'] === 'dhcp')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-100 text-green-700 rounded-lg text-xs font-medium">
                                <i class="fas fa-network-wired"></i> DHCP
                            </span>
                        @elseif($device['type'] === 'arp')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-100 text-blue-700 rounded-lg text-xs font-medium">
                                <i class="fas fa-list"></i> ARP
                            </span>
                        @elseif($device['type'] === 'pppoe')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-purple-100 text-purple-700 rounded-lg text-xs font-medium">
                                <i class="fas fa-broadcast-tower"></i> PPPoE
                            </span>
                        @elseif($device['type'] === 'hotspot')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-100 text-amber-700 rounded-lg text-xs font-medium">
                                <i class="fas fa-wifi"></i> Hotspot
                            </span>
                        @elseif($device['type'] === 'wireless')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-100 text-indigo-700 rounded-lg text-xs font-medium">
                                <i class="fas fa-signal"></i> Wireless
                            </span>
                        @elseif($device['type'] === 'remote')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-rose-100 text-rose-700 rounded-lg text-xs font-medium">
                                <i class="fas fa-satellite-dish"></i> Remote
                            </span>
                        @endif
                    </td>
                    <td class="px-5 py-4">
                        <div class="space-y-1">
                            @if(isset($device['ip']) && $device['ip'] !== '-')
                                <a href="http://{{ $device['ip'] }}" target="_blank" 
                                   class="inline-flex items-center gap-2 px-3 py-1.5 bg-cyan-50 text-cyan-700 rounded-lg hover:bg-cyan-100 transition font-mono text-sm group">
                                    <i class="fas fa-globe text-cyan-500"></i>
                                    {{ $device['ip'] }}
                                    <i class="fas fa-external-link-alt text-xs opacity-0 group-hover:opacity-100 transition"></i>
                                </a>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                            @if(isset($device['remote_ip']) && $device['remote_ip'] !== '-')
                                <a href="http://{{ $device['remote_ip'] }}" target="_blank" 
                                   class="inline-flex items-center gap-2 px-2.5 py-1 bg-rose-50 text-rose-600 rounded-lg hover:bg-rose-100 transition font-mono text-xs">
                                    <i class="fas fa-satellite-dish text-rose-400"></i>
                                    {{ $device['remote_ip'] }}
                                    <span class="text-rose-400 text-[10px]">remote</span>
                                </a>
                            @endif
                        </div>
                    </td>
                    <td class="px-5 py-4">
                        <span class="font-mono text-sm text-gray-600 bg-gray-100 px-2 py-1 rounded">{{ $device['mac'] ?? '-' }}</span>
                    </td>
                    <td class="px-5 py-4 text-sm text-gray-600">
                        @if($device['type'] === 'dhcp')
                            <div class="space-y-1">
                                <div class="flex items-center gap-2"><i class="fas fa-desktop text-gray-400 w-4"></i> {{ $device['hostname'] ?? '-' }}</div>
                                <div class="text-xs text-gray-400"><i class="fas fa-server w-4"></i> {{ $device['server'] ?? '-' }}</div>
                            </div>
                        @elseif($device['type'] === 'arp')
                            <div class="flex items-center gap-2"><i class="fas fa-plug text-gray-400 w-4"></i> {{ $device['interface'] ?? '-' }}</div>
                        @elseif($device['type'] === 'pppoe')
                            <div class="space-y-1">
                                <div class="flex items-center gap-2"><i class="fas fa-user text-gray-400 w-4"></i> {{ $device['username'] ?? '-' }}</div>
                                <div class="text-xs text-gray-400"><i class="fas fa-clock w-4"></i> {{ $device['uptime'] ?? '-' }}</div>
                                @if(isset($device['remote_ip']) && $device['remote_ip'] !== '-')
                                <div class="flex items-center gap-2 text-xs text-rose-500"><i class="fas fa-satellite-dish w-4"></i> Remote: {{ $device['remote_ip'] }}</div>
                                @endif
                            </div>
                        @elseif($device['type'] === 'hotspot')
                            <div class="space-y-1">
                                <div class="flex items-center gap-2"><i class="fas fa-user text-gray-400 w-4"></i> {{ $device['username'] ?? '-' }}</div>
                                <div class="text-xs text-gray-400"><i class="fas fa-clock w-4"></i> {{ $device['uptime'] ?? '-' }}</div>
                            </div>
                        @elseif($device['type'] === 'wireless')
                            <div class="space-y-1">
                                <div class="flex items-center gap-2"><i class="fas fa-signal text-gray-400 w-4"></i> {{ $device['signal'] ?? '-' }}</div>
                                <div class="text-xs text-gray-400"><i class="fas fa-plug w-4"></i> {{ $device['interface'] ?? '-' }}</div>
                            </div>
                        @elseif($device['type'] === 'remote')
                            <div class="space-y-1">
                                <div class="flex items-center gap-2"><i class="fas fa-user text-rose-400 w-4"></i> {{ $device['username'] ?? '-' }}</div>
                                <div class="text-xs text-gray-400"><i class="fas fa-clock w-4"></i> {{ $device['uptime'] ?? '-' }}</div>
                                <div class="text-xs text-rose-500"><i class="fas fa-satellite-dish w-4"></i> {{ $device['interface'] ?? '-' }}</div>
                            </div>
                        @endif
                    </td>
                    <td class="px-5 py-4">
                        @if(isset($device['status']))
                            @if($device['status'] === 'bound' || $device['status'] === 'complete')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-green-100 text-green-700 rounded-lg text-xs font-medium">
                                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                                    متصل
                                </span>
                            @elseif($device['status'] === 'waiting')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-100 text-amber-700 rounded-lg text-xs font-medium">
                                    <span class="w-2 h-2 bg-amber-500 rounded-full"></span>
                                    انتظار
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-gray-100 text-gray-600 rounded-lg text-xs font-medium">
                                    <span class="w-2 h-2 bg-gray-400 rounded-full"></span>
                                    {{ $device['status'] }}
                                </span>
                            @endif
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-green-100 text-green-700 rounded-lg text-xs font-medium">
                                <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                                نشط
                            </span>
                        @endif
                    </td>
                    <td class="px-5 py-4 text-center">
                        @if(isset($device['ip']) && $device['ip'] !== '-')
                        <div class="flex items-center justify-center gap-1 opacity-0 group-hover:opacity-100 transition">
                            <a href="http://{{ $device['ip'] }}" target="_blank" 
                               class="w-8 h-8 bg-cyan-100 hover:bg-cyan-200 text-cyan-600 rounded-lg flex items-center justify-center transition" title="فتح في المتصفح">
                                <i class="fas fa-external-link-alt text-xs"></i>
                            </a>
                            <button onclick="copyToClipboard('{{ $device['ip'] }}')" 
                                    class="w-8 h-8 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg flex items-center justify-center transition" title="نسخ IP">
                                <i class="fas fa-copy text-xs"></i>
                            </button>
                        </div>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- Devices List - Mobile -->
<div class="md:hidden space-y-3" id="devicesList">
    @foreach($devices as $device)
    <div class="bg-white rounded-xl shadow-sm border p-4">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                @if($device['type'] === 'dhcp')
                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-network-wired text-green-600 text-sm"></i>
                    </div>
                    <span class="text-xs font-medium text-green-700 bg-green-50 px-2 py-1 rounded">DHCP</span>
                @elseif($device['type'] === 'arp')
                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-list text-blue-600 text-sm"></i>
                    </div>
                    <span class="text-xs font-medium text-blue-700 bg-blue-50 px-2 py-1 rounded">ARP</span>
                @elseif($device['type'] === 'pppoe')
                    <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-broadcast-tower text-purple-600 text-sm"></i>
                    </div>
                    <span class="text-xs font-medium text-purple-700 bg-purple-50 px-2 py-1 rounded-lg">PPPoE</span>
                @elseif($device['type'] === 'hotspot')
                    <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-wifi text-amber-600"></i>
                    </div>
                    <span class="text-xs font-medium text-amber-700 bg-amber-50 px-2 py-1 rounded-lg">Hotspot</span>
                @elseif($device['type'] === 'wireless')
                    <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-signal text-indigo-600"></i>
                    </div>
                    <span class="text-xs font-medium text-indigo-700 bg-indigo-50 px-2 py-1 rounded-lg">Wireless</span>
                @elseif($device['type'] === 'remote')
                    <div class="w-10 h-10 bg-rose-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-satellite-dish text-rose-600"></i>
                    </div>
                    <span class="text-xs font-medium text-rose-700 bg-rose-50 px-2 py-1 rounded-lg">Remote</span>
                @endif
            </div>
            @if(isset($device['status']) && ($device['status'] === 'bound' || $device['status'] === 'complete'))
                <span class="flex items-center gap-1 px-2 py-1 bg-green-100 text-green-700 rounded-lg text-xs">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                    متصل
                </span>
            @else
                <span class="flex items-center gap-1 px-2 py-1 bg-green-100 text-green-700 rounded-lg text-xs">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                    نشط
                </span>
            @endif
        </div>
        
        <div class="space-y-2 text-sm">
            <!-- IP Address with link -->
            <div class="flex justify-between items-center bg-cyan-50 rounded-lg p-2.5">
                <span class="text-cyan-700 flex items-center gap-1 font-medium"><i class="fas fa-globe text-sm"></i> IP:</span>
                @if(isset($device['ip']) && $device['ip'] !== '-')
                    <div class="flex items-center gap-2">
                        <a href="http://{{ $device['ip'] }}" target="_blank" 
                           class="font-mono text-cyan-700 font-bold flex items-center gap-1 bg-white px-3 py-1 rounded-lg shadow-sm">
                            {{ $device['ip'] }}
                            <i class="fas fa-external-link-alt text-xs"></i>
                        </a>
                        <button onclick="copyToClipboard('{{ $device['ip'] }}')" class="w-8 h-8 bg-white hover:bg-gray-100 rounded-lg flex items-center justify-center shadow-sm">
                            <i class="fas fa-copy text-sm text-gray-600"></i>
                        </button>
                    </div>
                @else
                    <span class="font-mono text-gray-400">-</span>
                @endif
            </div>
            
            <!-- MAC Address -->
            <div class="flex justify-between items-center bg-gray-50 rounded-lg p-2.5">
                <span class="text-gray-600 flex items-center gap-1 font-medium"><i class="fas fa-fingerprint text-sm"></i> MAC:</span>
                <span class="font-mono text-gray-700 text-sm bg-white px-3 py-1 rounded-lg">{{ $device['mac'] ?? '-' }}</span>
            </div>
            
            <!-- Hostname - Always show for DHCP -->
            @if($device['type'] === 'dhcp')
            <div class="flex justify-between items-center bg-green-50 rounded-lg p-2.5">
                <span class="text-green-700 flex items-center gap-1 font-medium"><i class="fas fa-desktop text-sm"></i> اسم الجهاز:</span>
                <span class="text-green-800 font-medium bg-white px-3 py-1 rounded-lg">{{ $device['hostname'] ?? 'غير معروف' }}</span>
            </div>
            @endif
            
            <!-- Username for PPPoE/Hotspot -->
            @if(in_array($device['type'], ['pppoe', 'hotspot', 'remote']) && isset($device['username']))
            <div class="flex justify-between items-center bg-purple-50 rounded-lg p-2.5">
                <span class="text-purple-700 flex items-center gap-1 font-medium"><i class="fas fa-user text-sm"></i> المستخدم:</span>
                <span class="text-purple-800 font-medium bg-white px-3 py-1 rounded-lg">{{ $device['username'] }}</span>
            </div>
            @endif
            
            <!-- Remote IP for PPPoE/Remote -->
            @if(isset($device['remote_ip']) && $device['remote_ip'] !== '-')
            <div class="flex justify-between items-center bg-rose-50 rounded-lg p-2.5">
                <span class="text-rose-700 flex items-center gap-1 font-medium"><i class="fas fa-satellite-dish text-sm"></i> Remote IP:</span>
                <div class="flex items-center gap-2">
                    <a href="http://{{ $device['remote_ip'] }}" target="_blank" 
                       class="font-mono text-rose-700 font-bold flex items-center gap-1 bg-white px-3 py-1 rounded-lg shadow-sm">
                        {{ $device['remote_ip'] }}
                        <i class="fas fa-external-link-alt text-xs"></i>
                    </a>
                    <button onclick="copyToClipboard('{{ $device['remote_ip'] }}')" class="w-8 h-8 bg-white hover:bg-gray-100 rounded-lg flex items-center justify-center shadow-sm">
                        <i class="fas fa-copy text-sm text-gray-600"></i>
                    </button>
                </div>
            </div>
            @endif
            
            <!-- Interface for ARP -->
            @if($device['type'] === 'arp' && isset($device['interface']))
            <div class="flex justify-between items-center bg-blue-50 rounded-lg p-2.5">
                <span class="text-blue-700 flex items-center gap-1 font-medium"><i class="fas fa-plug text-sm"></i> Interface:</span>
                <span class="text-blue-800 font-medium bg-white px-3 py-1 rounded-lg">{{ $device['interface'] }}</span>
            </div>
            @endif
            
            <!-- Uptime for PPPoE/Hotspot/Remote -->
            @if(in_array($device['type'], ['pppoe', 'hotspot', 'remote']) && isset($device['uptime']) && $device['uptime'] !== '-')
            <div class="flex justify-between items-center bg-amber-50 rounded-lg p-2.5">
                <span class="text-amber-700 flex items-center gap-1 font-medium"><i class="fas fa-clock text-sm"></i> مدة الاتصال:</span>
                <span class="text-amber-800 font-medium bg-white px-3 py-1 rounded-lg">{{ $device['uptime'] }}</span>
            </div>
            @endif
            
            <!-- Signal for Wireless -->
            @if($device['type'] === 'wireless' && isset($device['signal']))
            <div class="flex justify-between items-center bg-indigo-50 rounded-lg p-2.5">
                <span class="text-indigo-700 flex items-center gap-1 font-medium"><i class="fas fa-signal text-sm"></i> قوة الإشارة:</span>
                <span class="text-indigo-800 font-medium bg-white px-3 py-1 rounded-lg">{{ $device['signal'] }}</span>
            </div>
            @if(isset($device['interface']))
            <div class="flex justify-between items-center bg-indigo-50/50 rounded-lg p-2.5">
                <span class="text-indigo-600 flex items-center gap-1 font-medium"><i class="fas fa-wifi text-sm"></i> Interface:</span>
                <span class="text-indigo-700 bg-white px-3 py-1 rounded-lg">{{ $device['interface'] }}</span>
            </div>
            @endif
            @endif
        </div>
        
        <!-- Quick Actions -->
        @if(isset($device['ip']) && $device['ip'] !== '-')
        <div class="mt-3 pt-3 border-t border-gray-100 flex gap-2">
            <a href="http://{{ $device['ip'] }}" target="_blank" 
               class="flex-1 py-2 bg-cyan-50 hover:bg-cyan-100 text-cyan-700 rounded-lg text-center text-sm font-medium transition flex items-center justify-center gap-2">
                <i class="fas fa-external-link-alt"></i>
                فتح في المتصفح
            </a>
            <button onclick="copyToClipboard('{{ $device['ip'] }}')" 
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium transition">
                <i class="fas fa-copy"></i>
            </button>
        </div>
        @endif
    </div>
    @endforeach
</div>
@endif

@endsection

@push('scripts')
<script>
// Copy to clipboard function
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Show toast notification
        showToast('تم نسخ ' + text + ' بنجاح!');
    }).catch(err => {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showToast('تم نسخ ' + text + ' بنجاح!');
    });
}

// Toast notification
function showToast(message, type = 'success') {
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) existingToast.remove();
    
    const toast = document.createElement('div');
    const bgColor = type === 'error' ? 'bg-red-600' : type === 'info' ? 'bg-blue-600' : 'bg-green-600';
    const icon = type === 'error' ? 'fa-times-circle' : type === 'info' ? 'fa-info-circle' : 'fa-check-circle';
    toast.className = `toast-notification fixed left-1/2 -translate-x-1/2 ${bgColor} text-white px-4 py-3 rounded-xl shadow-lg z-[100] animate-fade-in flex items-center gap-2`;
    toast.style.bottom = 'calc(80px + env(safe-area-inset-bottom, 0px))';
    toast.innerHTML = `<i class="fas ${icon}"></i><span>${message}</span>`;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('opacity-0', 'transition-opacity');
        setTimeout(() => toast.remove(), 300);
    }, 2500);
}

function refreshDevices() {
    const icon = document.getElementById('refreshIcon');
    const btn = icon.parentElement;
    icon.classList.add('fa-spin');
    btn.disabled = true;
    
    const routerId = '{{ $selectedRouterId }}';
    const type = '{{ request('type', 'all') }}';
    
    showToast('جاري التحديث...', 'info');
    
    fetch(`/devices/refresh?router_id=${routerId}&type=${type}`, {
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(`تم تحديث ${data.count || 0} جهاز بنجاح`);
            // Soft reload after short delay
            setTimeout(() => {
                window.location.reload();
            }, 500);
        } else {
            showToast(data.message || 'فشل التحديث', 'error');
        }
    })
    .catch(err => {
        showToast('حدث خطأ في التحديث', 'error');
    })
    .finally(() => {
        icon.classList.remove('fa-spin');
        btn.disabled = false;
    });
}

// WireGuard Modal Functions
function openWireGuardModal() {
    const modal = document.getElementById('wireGuardModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.classList.add('overflow-hidden');
}

function closeWireGuardModal() {
    const modal = document.getElementById('wireGuardModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.classList.remove('overflow-hidden');
}

// WireGuard Alpine.js App
function wireGuardApp() {
    return {
        status: null,
        checking: false,
        setting: false,
        addingPeer: false,
        wgInterface: '',
        serverPublicKey: '',
        serverEndpoint: '',
        peers: [],
        generatedConfig: '',
        setupConfig: {
            address: '10.10.10.1/24',
            port: 51820
        },
        newPeer: {
            name: '',
            ip: '10.10.10.2/32'
        },
        
        init() {
            this.checkStatus();
        },
        
        async checkStatus() {
            this.checking = true;
            try {
                const response = await fetch('/wireguard/status?router_id={{ $selectedRouterId }}', {
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();
                this.status = data.active ? 'active' : 'inactive';
                if (data.active) {
                    this.wgInterface = data.interface || 'wireguard1';
                    this.serverPublicKey = data.public_key || '';
                    this.serverEndpoint = data.endpoint || '';
                    this.peers = data.peers || [];
                }
            } catch (e) {
                console.error('Error checking WireGuard status:', e);
                this.status = 'inactive';
            }
            this.checking = false;
        },
        
        async setupWireGuard() {
            this.setting = true;
            try {
                const response = await fetch('/wireguard/setup', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        router_id: '{{ $selectedRouterId }}',
                        address: this.setupConfig.address,
                        port: this.setupConfig.port
                    })
                });
                const data = await response.json();
                if (data.success) {
                    showToast('تم إعداد WireGuard بنجاح!');
                    this.checkStatus();
                } else {
                    alert(data.message || 'فشل الإعداد');
                }
            } catch (e) {
                console.error('Error setting up WireGuard:', e);
                alert('حدث خطأ في إعداد WireGuard');
            }
            this.setting = false;
        },
        
        async addPeer() {
            if (!this.newPeer.name) {
                alert('أدخل اسم الجهاز');
                return;
            }
            this.addingPeer = true;
            try {
                const response = await fetch('/wireguard/add-peer', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        router_id: '{{ $selectedRouterId }}',
                        name: this.newPeer.name,
                        ip: this.newPeer.ip
                    })
                });
                const data = await response.json();
                if (data.success) {
                    this.generatedConfig = data.config;
                    // Generate QR Code
                    this.generateQR(data.config);
                    // Increment IP for next peer
                    const parts = this.newPeer.ip.split('.');
                    const lastOctet = parseInt(parts[3].split('/')[0]) + 1;
                    this.newPeer.ip = parts[0] + '.' + parts[1] + '.' + parts[2] + '.' + lastOctet + '/32';
                    this.newPeer.name = '';
                    // Refresh peers list
                    this.checkStatus();
                    showToast('تم إضافة الجهاز بنجاح!');
                } else {
                    alert(data.message || 'فشل إضافة الجهاز');
                }
            } catch (e) {
                console.error('Error adding peer:', e);
                alert('حدث خطأ في إضافة الجهاز');
            }
            this.addingPeer = false;
        },
        
        generateQR(text) {
            const qrContainer = document.getElementById('qrcode');
            qrContainer.innerHTML = '';
            new QRCode(qrContainer, {
                text: text,
                width: 200,
                height: 200,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        },
        
        copyConfig() {
            navigator.clipboard.writeText(this.generatedConfig).then(() => {
                showToast('تم نسخ الإعدادات!');
            }).catch(() => {
                // Fallback
                const textArea = document.createElement('textarea');
                textArea.value = this.generatedConfig;
                textArea.style.position = 'fixed';
                textArea.style.opacity = '0';
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showToast('تم نسخ الإعدادات!');
            });
        },
        
        async removePeer(peerId) {
            if (!confirm('هل تريد حذف هذا الجهاز؟')) return;
            try {
                const response = await fetch('/wireguard/remove-peer', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        router_id: '{{ $selectedRouterId }}',
                        peer_id: peerId
                    })
                });
                const data = await response.json();
                if (data.success) {
                    showToast('تم حذف الجهاز');
                    this.checkStatus();
                } else {
                    alert(data.message || 'فشل الحذف');
                }
            } catch (e) {
                console.error('Error removing peer:', e);
                alert('حدث خطأ في حذف الجهاز');
            }
        }
    };
}

// ========================================
// App VPN Functions (Capacitor Plugin)
// ========================================

const VPN_STORAGE_KEY = 'megawifi_vpn_config';

// Check if running in Capacitor app
function isCapacitorApp() {
    return window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform();
}

// Get VPN Config from server (main function for quick connect)
async function getVPNConfig() {
    const btn = document.getElementById('getVpnConfigBtn');
    if (btn) {
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التحضير...';
        btn.disabled = true;
    }
    
    try {
        const response = await fetch('/vpn/config', {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const data = await response.json();
        
        if (data.error) {
            showToast('خطأ: ' + data.error, 'error');
            return;
        }
        
        if (data.success && data.config) {
            // If in Capacitor app, try to open WireGuard directly
            const plugin = getVPNPlugin();
            if (plugin && isCapacitorApp()) {
                try {
                    const result = await plugin.openWithConfig({
                        config: data.config,
                        name: 'MegaWiFi-VPN'
                    });
                    
                    if (result.needsInstall) {
                        showToast('يتم فتح متجر Play لتثبيت WireGuard...', 'info');
                    } else if (result.success) {
                        showToast('✓ يتم فتح WireGuard - فعّل الاتصال من هناك', 'success');
                    }
                    return;
                } catch (pluginError) {
                    console.log('Plugin error, showing modal:', pluginError);
                }
            }
            
            // Fallback: Show QR code modal
            showVPNConfigModal(data.config, data.qr_data);
        }
    } catch (e) {
        console.error('VPN config error:', e);
        showToast('فشل جلب إعدادات VPN', 'error');
    } finally {
        if (btn) {
            btn.innerHTML = '<i class="fas fa-bolt"></i> <span>الحصول على إعدادات VPN</span>';
            btn.disabled = false;
        }
    }
}

// Show VPN config modal with QR code
function showVPNConfigModal(config, qrData) {
    const existingModal = document.getElementById('vpnConfigModal');
    if (existingModal) existingModal.remove();
    
    const modal = document.createElement('div');
    modal.id = 'vpnConfigModal';
    modal.className = 'fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4';
    modal.onclick = function(e) { if (e.target === modal) closeVPNConfigModal(); };
    modal.innerHTML = `
        <div class="bg-white rounded-2xl max-w-md w-full max-h-[90vh] overflow-auto">
            <div class="p-5 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-t-2xl">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-lg">إعدادات VPN الخاصة بك</h3>
                    <button onclick="closeVPNConfigModal()" class="text-white/80 hover:text-white">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <p class="text-green-100 text-sm mt-1">امسح QR Code في تطبيق WireGuard</p>
            </div>
            <div class="p-5">
                <div id="vpnQRCode" class="flex justify-center mb-2 bg-white p-4 rounded-xl border"></div>
                <div class="mb-2">
                    <label class="block text-sm text-gray-500 mb-2">أو انسخ الإعدادات:</label>
                    <textarea id="vpnConfigText" readonly class="w-full h-40 p-2 bg-gray-100 rounded-lg text-xs font-mono text-gray-700 border" dir="ltr">${config}</textarea>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <button onclick="copyVPNConfig()" class="py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium transition">
                        <i class="fas fa-copy ml-1"></i> نسخ الإعدادات
                    </button>
                    <a href="/vpn/download" class="py-3 bg-green-600 hover:bg-green-700 text-white rounded-xl font-medium transition text-center block">
                        <i class="fas fa-download ml-1"></i> تحميل الملف
                    </a>
                </div>
                <div class="mt-4 p-2 bg-yellow-50 rounded-xl border border-yellow-200">
                    <p class="text-yellow-800 text-sm">
                        <i class="fas fa-lightbulb ml-1 text-yellow-600"></i>
                        <strong>الخطوات:</strong><br>
                        1. ثبت تطبيق <a href="https://play.google.com/store/apps/details?id=com.wireguard.android" target="_blank" class="text-blue-600 underline">WireGuard</a><br>
                        2. امسح QR Code أو انسخ الإعدادات<br>
                        3. فعّل الاتصال من التطبيق
                    </p>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    
    // Generate QR code
    setTimeout(() => {
        if (typeof QRCode !== 'undefined') {
            const qrContainer = document.getElementById('vpnQRCode');
            qrContainer.innerHTML = '';
            new QRCode(qrContainer, {
                text: qrData,
                width: 200,
                height: 200
            });
        }
    }, 100);
}

function closeVPNConfigModal() {
    const modal = document.getElementById('vpnConfigModal');
    if (modal) modal.remove();
}

function copyVPNConfig() {
    const text = document.getElementById('vpnConfigText');
    text.select();
    text.setSelectionRange(0, 99999);
    document.execCommand('copy');
    showToast('✓ تم نسخ الإعدادات', 'success');
}

// Show/hide app VPN section - Show on both web and app
document.addEventListener('DOMContentLoaded', function() {
    const appVpnSection = document.getElementById('appVpnSection');
    if (appVpnSection) {
        // Show VPN section for all users (web and app)
        appVpnSection.style.display = 'block';
        loadSavedVPNConfig();
        if (isCapacitorApp()) {
            checkAppVPNStatus();
        }
    }
});

// Get WireGuard VPN plugin
function getVPNPlugin() {
    if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.WireGuardVPN) {
        return window.Capacitor.Plugins.WireGuardVPN;
    }
    return null;
}

// Load saved VPN config
function loadSavedVPNConfig() {
    try {
        const saved = localStorage.getItem(VPN_STORAGE_KEY);
        if (saved) {
            const config = JSON.parse(saved);
            const section = document.getElementById('savedConnectionSection');
            const routerName = document.getElementById('savedRouterName');
            if (section && config.routerName) {
                section.style.display = 'block';
                routerName.textContent = config.routerName;
            }
            return config;
        }
    } catch (e) {
        console.error('Error loading VPN config:', e);
    }
    return null;
}

// Save VPN config
function saveVPNConfig(config) {
    try {
        localStorage.setItem(VPN_STORAGE_KEY, JSON.stringify(config));
        loadSavedVPNConfig();
    } catch (e) {
        console.error('Error saving VPN config:', e);
    }
}

// Clear saved VPN
function clearSavedVPN() {
    localStorage.removeItem(VPN_STORAGE_KEY);
    const section = document.getElementById('savedConnectionSection');
    if (section) section.style.display = 'none';
    showToast('تم حذف الاتصال المحفوظ', 'info');
}

// Show VPN settings panel
function showVPNSettings() {
    document.getElementById('vpnSettingsPanel').classList.remove('hidden');
}

// Hide VPN settings panel
function hideVPNSettings() {
    document.getElementById('vpnSettingsPanel').classList.add('hidden');
}

// Save and connect VPN
async function saveAndConnectVPN() {
    const select = document.getElementById('vpnRouterSelect');
    const option = select.options[select.selectedIndex];
    
    const config = {
        routerId: select.value,
        routerName: option.dataset.name,
        routerIP: option.dataset.ip,
        serverAddress: '152.53.128.114',
        port: 51820
    };
    
    saveVPNConfig(config);
    hideVPNSettings();
    await quickConnectVPN();
}

// Quick connect VPN - Fetches config from API and opens WireGuard app
async function quickConnectVPN() {
    const btn = document.getElementById('quickConnectBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التحضير...';
    btn.disabled = true;
    
    try {
        // Fetch VPN config from server API
        const response = await fetch('/vpn/config', {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const data = await response.json();
        
        if (data.error) {
            showToast('خطأ: ' + data.error, 'error');
            return;
        }
        
        if (data.success && data.config) {
            // Save config locally
            saveVPNConfig({
                config: data.config,
                clientIP: data.client_ip,
                serverEndpoint: data.server_endpoint
            });
            
            // Try to open WireGuard app with config
            const plugin = getVPNPlugin();
            if (plugin) {
                try {
                    const result = await plugin.importConfig({
                        config: data.config,
                        name: 'MegaWiFi VPN'
                    });
                    if (result.success) {
                        showToast('✓ تم استيراد الإعدادات - افتح WireGuard للاتصال');
                    }
                } catch (pluginError) {
                    // Fallback: Show config for manual copy
                    showVPNConfigModal(data.config, data.qr_data);
                }
            } else {
                // Not in app - show config modal
                showVPNConfigModal(data.config, data.qr_data);
            }
        }
    } catch (e) {
        console.error('VPN config error:', e);
        showToast('فشل جلب إعدادات VPN', 'error');
    } finally {
        btn.innerHTML = '<i class="fas fa-bolt"></i> <span>اتصال سريع</span>';
        btn.disabled = false;
    }
}

// Show VPN config modal with QR code
function showVPNConfigModal(config, qrData) {
    // Create modal
    const modal = document.createElement('div');
    modal.id = 'vpnConfigModal';
    modal.className = 'fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4';
    modal.innerHTML = `
        <div class="bg-white rounded-2xl max-w-md w-full max-h-[90vh] overflow-auto">
            <div class="p-5 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-t-2xl">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-lg">إعدادات VPN</h3>
                    <button onclick="closeVPNConfigModal()" class="text-white/80 hover:text-white">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <p class="text-green-100 text-sm mt-1">امسح QR Code في تطبيق WireGuard</p>
            </div>
            <div class="p-5">
                <div id="vpnQRCode" class="flex justify-center mb-2 bg-white p-4 rounded-xl"></div>
                <div class="mb-2">
                    <label class="block text-sm text-gray-500 mb-2">أو انسخ الإعدادات:</label>
                    <textarea id="vpnConfigText" readonly class="w-full h-32 p-2 bg-gray-100 rounded-lg text-xs font-mono text-gray-700 border">${config}</textarea>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <button onclick="copyVPNConfig()" class="py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium transition">
                        <i class="fas fa-copy ml-1"></i> نسخ
                    </button>
                    <a href="/vpn/download" class="py-3 bg-green-600 hover:bg-green-700 text-white rounded-xl font-medium transition text-center">
                        <i class="fas fa-download ml-1"></i> تحميل
                    </a>
                </div>
                <div class="mt-4 p-2 bg-yellow-50 rounded-xl border border-yellow-200">
                    <p class="text-yellow-800 text-sm">
                        <i class="fas fa-info-circle ml-1"></i>
                        ثبت تطبيق <a href="https://play.google.com/store/apps/details?id=com.wireguard.android" target="_blank" class="text-blue-600 underline">WireGuard</a> ثم امسح الكود
                    </p>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    
    // Generate QR code
    if (typeof QRCode !== 'undefined') {
        new QRCode(document.getElementById('vpnQRCode'), {
            text: qrData,
            width: 200,
            height: 200
        });
    }
}

function closeVPNConfigModal() {
    const modal = document.getElementById('vpnConfigModal');
    if (modal) modal.remove();
}

function copyVPNConfig() {
    const text = document.getElementById('vpnConfigText');
    text.select();
    document.execCommand('copy');
    showToast('✓ تم نسخ الإعدادات');
}

// Disconnect from VPN
async function disconnectAppVPN() {
    const plugin = getVPNPlugin();
    if (!plugin) return;
    
    const btn = document.getElementById('disconnectVpnBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري قطع الاتصال...';
    btn.disabled = true;
    
    try {
        await plugin.disconnect();
        showToast('تم قطع الاتصال', 'info');
        updateVPNUI(false);
    } catch (e) {
        console.error('VPN disconnect error:', e);
        showToast('خطأ في قطع الاتصال', 'error');
    } finally {
        btn.innerHTML = '<i class="fas fa-power-off"></i> <span>قطع الاتصال</span>';
        btn.disabled = false;
    }
}

// Check VPN status
async function checkAppVPNStatus() {
    const plugin = getVPNPlugin();
    if (!plugin) return;
    
    try {
        const result = await plugin.getStatus();
        updateVPNUI(result.connected);
    } catch (e) {
        console.error('VPN status error:', e);
        updateVPNUI(false);
    }
}

// Update UI based on VPN status
function updateVPNUI(connected) {
    const quickConnectBtn = document.getElementById('quickConnectBtn');
    const disconnectBtn = document.getElementById('disconnectVpnBtn');
    const statusBadge = document.getElementById('vpnStatusBadge');
    
    if (connected) {
        if (quickConnectBtn) quickConnectBtn.style.display = 'none';
        if (disconnectBtn) {
            disconnectBtn.style.display = 'flex';
            disconnectBtn.innerHTML = '<i class="fas fa-power-off"></i> <span>قطع الاتصال</span>';
            disconnectBtn.disabled = false;
        }
        if (statusBadge) {
            statusBadge.className = 'px-4 py-2 rounded-full text-sm font-bold bg-green-100 text-green-700 shadow-inner';
            statusBadge.innerHTML = '<i class="fas fa-check-circle text-xs ml-1"></i> متصل';
        }
    } else {
        if (quickConnectBtn) {
            quickConnectBtn.style.display = 'flex';
            quickConnectBtn.innerHTML = '<i class="fas fa-bolt"></i> <span>اتصال سريع</span>';
            quickConnectBtn.disabled = false;
        }
        if (disconnectBtn) disconnectBtn.style.display = 'none';
        if (statusBadge) {
            statusBadge.className = 'px-4 py-2 rounded-full text-sm font-bold bg-gray-200 text-gray-600 shadow-inner';
            statusBadge.innerHTML = '<i class="fas fa-circle text-xs ml-1"></i> غير متصل';
        }
    }
}
</script>
<!-- QRCode.js library -->
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<style>
    @keyframes fadeIn { from { opacity: 0; transform: translate(-50%, 10px); } to { opacity: 1; transform: translate(-50%, 0); } }
    .animate-fade-in { animation: fadeIn 0.3s ease-out; }
    /* Fix for mobile bottom bar */
    .safe-area-bottom { padding-bottom: env(safe-area-inset-bottom, 20px); }
</style>
@endpush
