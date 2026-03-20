<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#991b1b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="MegaWiFi">
    <title>@yield('title', 'MegaWiFi') - لوحة التحكم</title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="192x192" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/icons/icon-152.png">
    <link rel="apple-touch-icon" sizes="144x144" href="/icons/icon-144.png">
    <link rel="icon" type="image/png" href="/icons/icon-192.png" sizes="192x192">
    <link rel="icon" type="image/png" href="/icons/icon-512.png" sizes="512x512">
    
    @livewireStyles
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap');
        * { font-family: 'Tajawal', sans-serif; }
        [x-cloak] { display: none !important; }
        /* Hide scrollbar for sidebar */
        .sidebar-nav::-webkit-scrollbar { display: none; }
        .sidebar-nav { -ms-overflow-style: none; scrollbar-width: none; }
        /* PWA Install Button Animation */
        @keyframes pulse-ring { 0% { transform: scale(0.9); opacity: 0.8; } 100% { transform: scale(1.5); opacity: 0; } }
        .pwa-pulse::before { content: ''; position: absolute; inset: -4px; border-radius: 9999px; background: inherit; animation: pulse-ring 1.5s infinite; z-index: -1; }
    </style>
</head>
<body class="bg-gray-100" x-data="{ moreMenuOpen: false }">
    <div class="min-h-screen flex">
        
        <!-- Sidebar - Desktop Only -->
        <aside class="hidden lg:flex lg:flex-shrink-0">
            <div class="w-72 bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900 text-white flex flex-col h-screen fixed right-0 top-0 shadow-2xl shadow-slate-900/50 border-l border-slate-700/50">
                <!-- Logo -->
                <div class="p-5 border-b border-slate-700/50 bg-slate-800/50">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-cyan-400 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/30">
                            <i class="fas fa-wifi text-xl text-white"></i>
                        </div>
                        <div>
                            <h1 class="font-bold text-xl bg-gradient-to-r from-white to-cyan-200 bg-clip-text text-transparent">MegaWiFi</h1>
                            <p class="text-xs text-slate-400">لوحة التحكم</p>
                        </div>
                    </div>
                </div>
                
                <!-- Navigation -->
                <nav class="flex-1 overflow-y-auto p-4 space-y-1 sidebar-nav">
                    @if(auth()->user()->isReseller())
                    {{-- قائمة الوكيل فقط --}}
                    <p class="text-xs text-emerald-400/70 font-medium px-3 mb-2 uppercase tracking-wider">لوحة الوكيل</p>
                    
                    <a href="{{ route('reseller.dashboard') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group {{ request()->routeIs('reseller.dashboard') ? 'bg-gradient-to-r from-blue-500/20 to-cyan-500/10 text-white shadow-lg border border-blue-500/30' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white' }}">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center {{ request()->routeIs('reseller.dashboard') ? 'bg-blue-500/30' : 'bg-slate-700/70 group-hover:bg-slate-600/70' }}">
                            <i class="fas fa-tachometer-alt {{ request()->routeIs('reseller.dashboard') ? 'text-blue-400' : 'text-slate-400 group-hover:text-white' }}"></i>
                        </div>
                        <span class="font-medium">لوحة التحكم</span>
                    </a>
                    
                    <a href="{{ route('reseller.hotspot') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group {{ request()->routeIs('reseller.hotspot*') ? 'bg-gradient-to-r from-orange-500/20 to-amber-500/10 text-white shadow-lg border border-orange-500/30' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white' }}">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center {{ request()->routeIs('reseller.hotspot*') ? 'bg-orange-500/30' : 'bg-slate-700/70 group-hover:bg-slate-600/70' }}">
                            <i class="fas fa-wifi {{ request()->routeIs('reseller.hotspot*') ? 'text-orange-400' : 'text-slate-400 group-hover:text-white' }}"></i>
                        </div>
                        <span class="font-medium">إنشاء هوتسبوت</span>
                    </a>
                    
                    <a href="{{ route('reseller.usermanager') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group {{ request()->routeIs('reseller.usermanager*') ? 'bg-gradient-to-r from-purple-500/20 to-violet-500/10 text-white shadow-lg border border-purple-500/30' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white' }}">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center {{ request()->routeIs('reseller.usermanager*') ? 'bg-purple-500/30' : 'bg-slate-700/70 group-hover:bg-slate-600/70' }}">
                            <i class="fas fa-sync-alt {{ request()->routeIs('reseller.usermanager*') ? 'text-purple-400' : 'text-slate-400 group-hover:text-white' }}"></i>
                        </div>
                        <span class="font-medium">تجديد اشتراكات</span>
                    </a>
                    
                    @else
                    {{-- القائمة العادية للمدراء --}}
                    <p class="text-xs text-cyan-400/70 font-medium px-3 mb-2 uppercase tracking-wider">القائمة الرئيسية</p>
                    
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group {{ request()->routeIs('dashboard') ? 'bg-gradient-to-r from-blue-500/20 to-cyan-500/10 text-white shadow-lg border border-blue-500/30' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white' }}">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center {{ request()->routeIs('dashboard') ? 'bg-blue-500/30' : 'bg-slate-700/70 group-hover:bg-slate-600/70' }}">
                            <i class="fas fa-home {{ request()->routeIs('dashboard') ? 'text-blue-400' : 'text-slate-400 group-hover:text-white' }}"></i>
                        </div>
                        <span class="font-medium">الرئيسية</span>
                    </a>
                    
                    <a href="{{ route('routers.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group {{ request()->routeIs('routers.*') ? 'bg-gradient-to-r from-emerald-500/20 to-green-500/10 text-white shadow-lg border border-emerald-500/30' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white' }}">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center {{ request()->routeIs('routers.*') ? 'bg-emerald-500/30' : 'bg-slate-700/70 group-hover:bg-slate-600/70' }}">
                            <i class="fas fa-server {{ request()->routeIs('routers.*') ? 'text-emerald-400' : 'text-slate-400 group-hover:text-white' }}"></i>
                        </div>
                        <span class="font-medium">الراوترات</span>
                    </a>

                    <a href="{{ route('servers.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group {{ request()->routeIs('servers.*') ? 'bg-gradient-to-r from-cyan-500/20 to-sky-500/10 text-white shadow-lg border border-cyan-500/30' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white' }}">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center {{ request()->routeIs('servers.*') ? 'bg-cyan-500/30' : 'bg-slate-700/70 group-hover:bg-slate-600/70' }}">
                            <i class="fas fa-desktop {{ request()->routeIs('servers.*') ? 'text-cyan-400' : 'text-slate-400 group-hover:text-white' }}"></i>
                        </div>
                        <span class="font-medium">السيرفرات</span>
                    </a>

                    <p class="text-xs text-cyan-400/70 font-medium px-3 mt-5 mb-2 uppercase tracking-wider">المشتركين</p>
                    
                    <a href="{{ route('usermanager.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group {{ request()->routeIs('usermanager.*') ? 'bg-gradient-to-r from-purple-500/20 to-violet-500/10 text-white shadow-lg border border-purple-500/30' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white' }}">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center {{ request()->routeIs('usermanager.*') ? 'bg-purple-500/30' : 'bg-slate-700/70 group-hover:bg-slate-600/70' }}">
                            <i class="fas fa-users-cog {{ request()->routeIs('usermanager.*') ? 'text-purple-400' : 'text-slate-400 group-hover:text-white' }}"></i>
                        </div>
                        <span class="font-medium">يوزرمانجر</span>
                    </a>
                    
                    <a href="{{ route('hotspot.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group {{ request()->routeIs('hotspot.*') ? 'bg-gradient-to-r from-orange-500/20 to-amber-500/10 text-white shadow-lg border border-orange-500/30' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white' }}">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center {{ request()->routeIs('hotspot.*') ? 'bg-orange-500/30' : 'bg-slate-700/70 group-hover:bg-slate-600/70' }}">
                            <i class="fas fa-wifi {{ request()->routeIs('hotspot.*') ? 'text-orange-400' : 'text-slate-400 group-hover:text-white' }}"></i>
                        </div>
                        <span class="font-medium">هوتسبوت</span>
                    </a>
                    
                    <a href="{{ route('subscribers.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group {{ request()->routeIs('subscribers.*') ? 'bg-gradient-to-r from-cyan-500/20 to-sky-500/10 text-white shadow-lg border border-cyan-500/30' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white' }}">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center {{ request()->routeIs('subscribers.*') ? 'bg-cyan-500/30' : 'bg-slate-700/70 group-hover:bg-slate-600/70' }}">
                            <i class="fas fa-broadcast-tower {{ request()->routeIs('subscribers.*') ? 'text-cyan-400' : 'text-slate-400 group-hover:text-white' }}"></i>
                        </div>
                        <span class="font-medium">برودباند (PPPoE)</span>
                    </a>

                    <p class="text-xs text-cyan-400/70 font-medium px-3 mt-5 mb-2 uppercase tracking-wider">الإدارة</p>
                    
                    <a href="{{ route('devices.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group {{ request()->routeIs('devices.*') ? 'bg-gradient-to-r from-amber-500/20 to-yellow-500/10 text-white shadow-lg border border-amber-500/30' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white' }}">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center {{ request()->routeIs('devices.*') ? 'bg-amber-500/30' : 'bg-slate-700/70 group-hover:bg-slate-600/70' }}">
                            <i class="fas fa-laptop {{ request()->routeIs('devices.*') ? 'text-amber-400' : 'text-slate-400 group-hover:text-white' }}"></i>
                        </div>
                        <span class="font-medium">القطع المتصلة</span>
                    </a>
                    
                    @if(auth()->user()->role === 'super_admin')
                    <a href="{{ route('users.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group {{ request()->routeIs('users.*') ? 'bg-gradient-to-r from-rose-500/20 to-pink-500/10 text-white shadow-lg border border-rose-500/30' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white' }}">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center {{ request()->routeIs('users.*') ? 'bg-rose-500/30' : 'bg-slate-700/70 group-hover:bg-slate-600/70' }}">
                            <i class="fas fa-user-shield {{ request()->routeIs('users.*') ? 'text-rose-400' : 'text-slate-400 group-hover:text-white' }}"></i>
                        </div>
                        <span class="font-medium">المستخدمين</span>
                    </a>
                    @endif
                    
                    @if(in_array(auth()->user()->role, ['super_admin', 'admin']))
                    <a href="{{ route('resellers.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group {{ request()->routeIs('resellers.*') ? 'bg-gradient-to-r from-indigo-500/20 to-blue-500/10 text-white shadow-lg border border-indigo-500/30' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white' }}">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center {{ request()->routeIs('resellers.*') ? 'bg-indigo-500/30' : 'bg-slate-700/70 group-hover:bg-slate-600/70' }}">
                            <i class="fas fa-store {{ request()->routeIs('resellers.*') ? 'text-indigo-400' : 'text-slate-400 group-hover:text-white' }}"></i>
                        </div>
                        <span class="font-medium">الوكلاء</span>
                    </a>
                    @endif
                    
                    @if(auth()->user()->role === 'super_admin')
                    <a href="{{ route('notifications.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group {{ request()->routeIs('notifications.*') ? 'bg-gradient-to-r from-red-500/20 to-orange-500/10 text-white shadow-lg border border-red-500/30' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white' }}" x-data="{ unread: 0 }" x-init="fetch('/notifications/unread').then(r => r.json()).then(d => unread = d.unread_count)">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center {{ request()->routeIs('notifications.*') ? 'bg-red-500/30' : 'bg-slate-700/70 group-hover:bg-slate-600/70' }} relative">
                            <i class="fas fa-bell {{ request()->routeIs('notifications.*') ? 'text-red-400' : 'text-slate-400 group-hover:text-white' }}"></i>
                            <span x-show="unread > 0" x-text="unread > 9 ? '9+' : unread" class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center"></span>
                        </div>
                        <span class="font-medium">الإشعارات</span>
                    </a>

                    
                    <a href="{{ route('admin.backups') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group {{ request()->routeIs('admin.backups*') ? 'bg-gradient-to-r from-emerald-500/20 to-teal-500/10 text-white shadow-lg border border-emerald-500/30' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white' }}">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center {{ request()->routeIs('admin.backups*') ? 'bg-emerald-500/30' : 'bg-slate-700/70 group-hover:bg-slate-600/70' }}">
                            <i class="fas fa-database {{ request()->routeIs('admin.backups*') ? 'text-emerald-400' : 'text-slate-400 group-hover:text-white' }}"></i>
                        </div>
                        <span class="font-medium">النسخ الاحتياطية</span>
                    </a>

                    <p class="text-xs text-cyan-400/70 font-medium px-3 mt-5 mb-2 uppercase tracking-wider">الإعدادات</p>
                    
                    <a href="{{ route('settings.sync') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group {{ request()->routeIs('settings.sync*') ? 'bg-gradient-to-r from-sky-500/20 to-blue-500/10 text-white shadow-lg border border-sky-500/30' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white' }}">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center {{ request()->routeIs('settings.sync*') ? 'bg-sky-500/30' : 'bg-slate-700/70 group-hover:bg-slate-600/70' }}">
                            <i class="fas fa-clock {{ request()->routeIs('settings.sync*') ? 'text-sky-400' : 'text-slate-400 group-hover:text-white' }}"></i>
                        </div>
                        <span class="font-medium">إعدادات المزامنة</span>
                    </a>
                    @endif
                    @endif
                </nav>
                
                <!-- User Info -->
                <div class="p-4 border-t border-slate-700/50 bg-slate-800/50">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-11 h-11 bg-gradient-to-br from-blue-500 to-cyan-400 rounded-full flex items-center justify-center shadow-lg shadow-blue-500/30">
                            <i class="fas fa-user text-white"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-white truncate">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-slate-400 truncate">{{ auth()->user()->email }}</p>
                        </div>
                    </div>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-700/70 hover:bg-red-500/80 text-slate-200 hover:text-white rounded-xl transition-all duration-200 border border-slate-600/50 hover:border-red-500/50 group">
                            <i class="fas fa-sign-out-alt group-hover:text-white"></i>
                            <span>تسجيل الخروج</span>
                        </button>
                    </form>
                </div>
            </div>
        </aside>
        <!-- Main Content -->
        <main class="flex-1 lg:mr-72 pb-24 lg:pb-6 overflow-x-hidden">
            <!-- Header - Mobile -->
            <header class="lg:hidden bg-white/80 backdrop-blur-lg shadow-sm sticky top-0 z-40 border-b border-gray-100">
                <div class="flex items-center justify-between px-4 py-3">
                    <div class="flex items-center gap-2">
                        <div class="w-9 h-9 bg-gradient-to-br from-blue-500 to-cyan-400 rounded-xl flex items-center justify-center shadow-md shadow-blue-500/30">
                            <i class="fas fa-wifi text-white text-sm"></i>
                        </div>
                        <span class="font-bold text-transparent bg-gradient-to-r from-blue-600 to-cyan-500 bg-clip-text text-lg">MegaWiFi</span>
                    </div>
                    <div class="flex items-center gap-3">
                        @if(!auth()->user()->isReseller())
                        <!-- Notifications Bell -->
                        <div x-data="notificationBell()" class="relative">
                            <button @click="toggleNotifications()" class="relative p-2 text-gray-600 hover:text-blue-600 transition">
                                <i class="fas fa-bell text-lg"></i>
                                <span x-show="unreadCount > 0" x-text="unreadCount > 9 ? '9+' : unreadCount" 
                                    class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center animate-pulse"></span>
                            </button>
                            <!-- Modal Backdrop for Mobile -->
                            <div x-show="showDropdown" x-cloak
                                @click="showDropdown = false"
                                class="fixed inset-0 bg-black/50 z-40 lg:hidden"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0"
                                x-transition:enter-end="opacity-100"></div>
                            <!-- Dropdown - Centered on Mobile -->
                            <div x-show="showDropdown" x-cloak
                                @click.away="showDropdown = false"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 transform scale-95"
                                x-transition:enter-end="opacity-100 transform scale-100"
                                class="fixed left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-[90vw] max-w-sm lg:absolute lg:left-auto lg:top-auto lg:right-0 lg:translate-x-0 lg:translate-y-0 lg:mt-2 lg:w-80 bg-white rounded-xl shadow-2xl border overflow-hidden z-50"
                                style="max-height: 70vh; overflow-y: auto;">
                                <div class="p-3 bg-gray-50 border-b flex items-center justify-between sticky top-0">
                                    <span class="font-bold text-gray-800">الإشعارات</span>
                                    <div class="flex items-center gap-3">
                                        <button @click="markAllAsRead()" class="text-xs text-blue-600 hover:underline">تحديد الكل كمقروء</button>
                                        <button @click="showDropdown = false" class="text-gray-400 hover:text-gray-600 lg:hidden">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                <template x-for="n in notifications" :key="n.id">
                                    <div :class="n.is_read ? 'bg-white' : 'bg-blue-50'" class="p-3 border-b hover:bg-gray-50 cursor-pointer" @click="markAsRead(n.id)">
                                        <div class="flex gap-3">
                                            <div :class="'w-10 h-10 rounded-full flex items-center justify-center bg-' + n.color + '-100'">
                                                <i :class="'fas ' + n.icon + ' text-' + n.color + '-600'"></i>
                                            </div>
                                            <div class="flex-1">
                                                <p class="font-semibold text-gray-800 text-sm" x-text="n.title"></p>
                                                <p class="text-xs text-gray-500 mt-1" x-text="n.message"></p>
                                                <p class="text-xs text-gray-400 mt-1" x-text="timeAgo(n.created_at)"></p>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="notifications.length === 0">
                                    <div class="p-6 text-center text-gray-400">
                                        <i class="fas fa-bell-slash text-3xl mb-2"></i>
                                        <p>لا توجد إشعارات</p>
                                    </div>
                                </template>
                                <a href="{{ route('notifications.index') }}" class="block p-3 text-center text-blue-600 hover:bg-gray-50 font-medium text-sm border-t">
                                    عرض جميع الإشعارات
                                </a>
                            </div>
                        </div>
                        @endif
                        <span class="text-sm text-gray-600 font-medium">{{ auth()->user()->name }}</span>
                        <div class="w-9 h-9 bg-gradient-to-br from-blue-500 to-purple-500 rounded-full flex items-center justify-center shadow-md">
                            <i class="fas fa-user text-white text-xs"></i>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="p-4 lg:p-6">
                {{-- Flash Messages --}}
                @if(session('error'))
                    <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg flex items-center gap-3">
                        <i class="fas fa-exclamation-circle text-xl"></i>
                        <span>{{ session('error') }}</span>
                        <button onclick="this.parentElement.remove()" class="mr-auto text-red-500 hover:text-red-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                @endif
                @if(session('success'))
                    <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg flex items-center gap-3">
                        <i class="fas fa-check-circle text-xl"></i>
                        <span>{{ session('success') }}</span>
                        <button onclick="this.parentElement.remove()" class="mr-auto text-green-500 hover:text-green-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                @endif
                @if(session('warning'))
                    <div class="mb-4 p-4 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded-lg flex items-center gap-3">
                        <i class="fas fa-exclamation-triangle text-xl"></i>
                        <span>{{ session('warning') }}</span>
                        <button onclick="this.parentElement.remove()" class="mr-auto text-yellow-500 hover:text-yellow-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                @endif
                
                @yield('content')
            </div>
        </main>
    </div>

    <!-- More Menu Popup - Mobile Only -->
    <div x-show="moreMenuOpen" 
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="lg:hidden fixed inset-0 z-50"
         @click="moreMenuOpen = false">
        
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
        
        <!-- Menu Panel -->
        <div x-show="moreMenuOpen"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-full"
             x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0"
             x-transition:leave-end="translate-y-full"
             @click.stop
             class="absolute bottom-20 left-3 right-3 bg-white rounded-3xl shadow-2xl max-h-[70vh] overflow-hidden">
            
            <!-- Handle -->
            <div class="flex justify-center pt-3 pb-1">
                <div class="w-10 h-1 bg-gray-300 rounded-full"></div>
            </div>
            
            <!-- Menu Header -->
            <div class="px-5 pb-3">
                <h3 class="text-lg font-bold text-gray-800">القائمة</h3>
            </div>
            
            <!-- Menu Items -->
            <div class="p-4 pt-0 grid grid-cols-4 gap-3">
                <a href="{{ route('routers.index') }}" class="flex flex-col items-center gap-2 p-3 rounded-2xl bg-gradient-to-br from-blue-50 to-blue-100/50 hover:from-blue-100 hover:to-blue-200/50 transition-all duration-200" @click="moreMenuOpen = false">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/30">
                        <i class="fas fa-server text-white"></i>
                    </div>
                    <span class="text-[11px] font-medium text-gray-700">الراوترات</span>
                </a>
                
                <a href="{{ route('devices.index') }}" class="flex flex-col items-center gap-2 p-3 rounded-2xl bg-gradient-to-br from-cyan-50 to-cyan-100/50 hover:from-cyan-100 hover:to-cyan-200/50 transition-all duration-200" @click="moreMenuOpen = false">
                    <div class="w-12 h-12 bg-gradient-to-br from-cyan-500 to-cyan-600 rounded-xl flex items-center justify-center shadow-lg shadow-cyan-500/30">
                        <i class="fas fa-laptop text-white"></i>
                    </div>
                    <span class="text-[11px] font-medium text-gray-700">القطع المتصلة</span>
                </a>
                
                @if(auth()->user()->role === 'super_admin')
                <a href="{{ route('users.index') }}" class="flex flex-col items-center gap-2 p-3 rounded-2xl bg-gradient-to-br from-indigo-50 to-indigo-100/50 hover:from-indigo-100 hover:to-indigo-200/50 transition-all duration-200" @click="moreMenuOpen = false">
                    <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-500/30">
                        <i class="fas fa-user-shield text-white"></i>
                    </div>
                    <span class="text-[11px] font-medium text-gray-700">المستخدمين</span>
                </a>
                @endif
                
                @if(in_array(auth()->user()->role, ['super_admin', 'admin']))
                <a href="{{ route('resellers.index') }}" class="flex flex-col items-center gap-2 p-3 rounded-2xl bg-gradient-to-br from-purple-50 to-purple-100/50 hover:from-purple-100 hover:to-purple-200/50 transition-all duration-200" @click="moreMenuOpen = false">
                    <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg shadow-purple-500/30">
                        <i class="fas fa-store text-white"></i>
                    </div>
                    <span class="text-[11px] font-medium text-gray-700">الوكلاء</span>
                </a>
                @endif
                
                <form action="{{ route('logout') }}" method="POST" class="contents">
                    @csrf
                    <button type="submit" class="flex flex-col items-center gap-2 p-3 rounded-2xl bg-gradient-to-br from-red-50 to-red-100/50 hover:from-red-100 hover:to-red-200/50 transition-all duration-200">
                        <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-red-600 rounded-xl flex items-center justify-center shadow-lg shadow-red-500/30">
                            <i class="fas fa-sign-out-alt text-white"></i>
                        </div>
                        <span class="text-[11px] font-medium text-gray-700">خروج</span>
                    </button>
                </form>
            </div>
            
            <!-- User Info -->
            <div class="px-5 py-4 border-t bg-gradient-to-r from-slate-50 to-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 bg-gradient-to-br from-blue-500 to-purple-500 rounded-full flex items-center justify-center shadow-md">
                        <i class="fas fa-user text-white"></i>
                    </div>
                    <div class="flex-1">
                        <p class="font-semibold text-gray-800">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-gray-500">{{ auth()->user()->email }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation - Mobile Only -->
    <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50">
        <div class="flex justify-around items-center py-1.5 px-2">
            @if(auth()->user()->isReseller())
            {{-- قائمة الوكيل للموبايل --}}
            <a href="{{ route('reseller.dashboard') }}" class="flex flex-col items-center gap-0.5 py-2 px-3 rounded-xl transition-all duration-200 {{ request()->routeIs('reseller.dashboard') ? 'bg-green-50' : '' }}">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center {{ request()->routeIs('reseller.dashboard') ? 'bg-gradient-to-br from-green-500 to-green-600 shadow-lg shadow-green-500/30' : 'bg-gray-100' }}">
                    <i class="fas fa-home {{ request()->routeIs('reseller.dashboard') ? 'text-white' : 'text-gray-500' }}"></i>
                </div>
                <span class="text-[10px] font-medium {{ request()->routeIs('reseller.dashboard') ? 'text-green-600' : 'text-gray-500' }}">الرئيسية</span>
            </a>
            <a href="{{ route('reseller.hotspot') }}" class="flex flex-col items-center gap-0.5 py-2 px-3 rounded-xl transition-all duration-200 {{ request()->routeIs('reseller.hotspot*') ? 'bg-orange-50' : '' }}">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center {{ request()->routeIs('reseller.hotspot*') ? 'bg-gradient-to-br from-orange-500 to-orange-600 shadow-lg shadow-orange-500/30' : 'bg-gray-100' }}">
                    <i class="fas fa-wifi {{ request()->routeIs('reseller.hotspot*') ? 'text-white' : 'text-gray-500' }}"></i>
                </div>
                <span class="text-[10px] font-medium {{ request()->routeIs('reseller.hotspot*') ? 'text-orange-600' : 'text-gray-500' }}">هوتسبوت</span>
            </a>
            <a href="{{ route('reseller.usermanager') }}" class="flex flex-col items-center gap-0.5 py-2 px-3 rounded-xl transition-all duration-200 {{ request()->routeIs('reseller.usermanager*') ? 'bg-purple-50' : '' }}">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center {{ request()->routeIs('reseller.usermanager*') ? 'bg-gradient-to-br from-purple-500 to-purple-600 shadow-lg shadow-purple-500/30' : 'bg-gray-100' }}">
                    <i class="fas fa-sync-alt {{ request()->routeIs('reseller.usermanager*') ? 'text-white' : 'text-gray-500' }}"></i>
                </div>
                <span class="text-[10px] font-medium {{ request()->routeIs('reseller.usermanager*') ? 'text-purple-600' : 'text-gray-500' }}">تجديد</span>
            </a>
            <form action="{{ route('logout') }}" method="POST" class="flex flex-col items-center gap-0.5 py-2 px-3">
                @csrf
                <button type="submit" class="flex flex-col items-center gap-0.5">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-gray-100">
                        <i class="fas fa-sign-out-alt text-gray-500"></i>
                    </div>
                    <span class="text-[10px] font-medium text-gray-500">خروج</span>
                </button>
            </form>
            @else
            {{-- قائمة الإدارة للموبايل --}}
            <a href="{{ route('dashboard') }}" class="flex flex-col items-center gap-0.5 py-2 px-3 rounded-xl transition-all duration-200 {{ request()->routeIs('dashboard') ? 'bg-blue-50' : '' }}">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center {{ request()->routeIs('dashboard') ? 'bg-gradient-to-br from-blue-500 to-blue-600 shadow-lg shadow-blue-500/30' : 'bg-gray-100' }}">
                    <i class="fas fa-home {{ request()->routeIs('dashboard') ? 'text-white' : 'text-gray-500' }}"></i>
                </div>
                <span class="text-[10px] font-medium {{ request()->routeIs('dashboard') ? 'text-blue-600' : 'text-gray-500' }}">الرئيسية</span>
            </a>
            <a href="{{ route('usermanager.index') }}" class="flex flex-col items-center gap-0.5 py-2 px-3 rounded-xl transition-all duration-200 {{ request()->routeIs('usermanager.*') ? 'bg-purple-50' : '' }}">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center {{ request()->routeIs('usermanager.*') ? 'bg-gradient-to-br from-purple-500 to-purple-600 shadow-lg shadow-purple-500/30' : 'bg-gray-100' }}">
                    <i class="fas fa-users-cog {{ request()->routeIs('usermanager.*') ? 'text-white' : 'text-gray-500' }}"></i>
                </div>
                <span class="text-[10px] font-medium {{ request()->routeIs('usermanager.*') ? 'text-purple-600' : 'text-gray-500' }}">يوزرمانجر</span>
            </a>
            <a href="{{ route('hotspot.index') }}" class="flex flex-col items-center gap-0.5 py-2 px-3 rounded-xl transition-all duration-200 {{ request()->routeIs('hotspot.*') ? 'bg-amber-50' : '' }}">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center {{ request()->routeIs('hotspot.*') ? 'bg-gradient-to-br from-amber-500 to-amber-600 shadow-lg shadow-amber-500/30' : 'bg-gray-100' }}">
                    <i class="fas fa-wifi {{ request()->routeIs('hotspot.*') ? 'text-white' : 'text-gray-500' }}"></i>
                </div>
                <span class="text-[10px] font-medium {{ request()->routeIs('hotspot.*') ? 'text-amber-600' : 'text-gray-500' }}">هوتسبوت</span>
            </a>
            <a href="{{ route('devices.index') }}" class="flex flex-col items-center gap-0.5 py-2 px-3 rounded-xl transition-all duration-200 {{ request()->routeIs('devices.*') ? 'bg-cyan-50' : '' }}">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center {{ request()->routeIs('devices.*') ? 'bg-gradient-to-br from-cyan-500 to-cyan-600 shadow-lg shadow-cyan-500/30' : 'bg-gray-100' }}">
                    <i class="fas fa-laptop {{ request()->routeIs('devices.*') ? 'text-white' : 'text-gray-500' }}"></i>
                </div>
                <span class="text-[10px] font-medium {{ request()->routeIs('devices.*') ? 'text-cyan-600' : 'text-gray-500' }}">القطع</span>
            </a>
            <button @click="moreMenuOpen = !moreMenuOpen" class="flex flex-col items-center gap-0.5 py-2 px-3 rounded-xl transition-all duration-200" :class="moreMenuOpen ? 'bg-indigo-50' : ''">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center transition-all duration-200" :class="moreMenuOpen ? 'bg-gradient-to-br from-indigo-500 to-indigo-600 shadow-lg shadow-indigo-500/30' : 'bg-gray-100'">
                    <i class="fas fa-th-large" :class="moreMenuOpen ? 'text-white' : 'text-gray-500'"></i>
                </div>
                <span class="text-[10px] font-medium" :class="moreMenuOpen ? 'text-indigo-600' : 'text-gray-500'">المزيد</span>
            </button>
            @endif
        </div>
    </nav>

    @stack('scripts')
    @livewireScripts

    <!-- PWA Install Button (Floating) -->
    <div id="pwaInstallPrompt" class="hidden fixed z-50 bottom-24 lg:bottom-6 left-4 animate-bounce">
        <button id="pwaInstallBtn" class="relative flex items-center gap-2 px-5 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-full shadow-xl shadow-red-600/40 hover:shadow-red-600/60 transition-all duration-300 hover:scale-105 pwa-pulse">
            <i class="fas fa-download text-lg"></i>
            <span class="font-medium">تثبيت التطبيق</span>
        </button>
    </div>

    <!-- PWA Install Success Toast -->
    <div id="pwaInstallSuccess" class="hidden fixed z-50 top-4 left-1/2 -translate-x-1/2 px-6 py-3 bg-green-500 text-white rounded-xl shadow-xl animate-fade-in">
        <div class="flex items-center gap-2">
            <i class="fas fa-check-circle"></i>
            <span>تم تثبيت التطبيق بنجاح!</span>
        </div>
    </div>

    <!-- Service Worker Registration & PWA Install -->
    <script>
        // Service Worker Registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', async () => {
                try {
                    const registration = await navigator.serviceWorker.register('/sw.js');
                    console.log('SW registered:', registration.scope);
                } catch (error) {
                    console.log('SW registration failed:', error);
                }
            });
        }

        // PWA Install Prompt
        let deferredPrompt;
        const installPrompt = document.getElementById('pwaInstallPrompt');
        const installBtn = document.getElementById('pwaInstallBtn');
        const installSuccess = document.getElementById('pwaInstallSuccess');

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            installPrompt.classList.remove('hidden');
        });

        installBtn.addEventListener('click', async () => {
            if (!deferredPrompt) return;
            
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            
            if (outcome === 'accepted') {
                installPrompt.classList.add('hidden');
                installSuccess.classList.remove('hidden');
                setTimeout(() => {
                    installSuccess.classList.add('hidden');
                }, 3000);
            }
            deferredPrompt = null;
        });

        window.addEventListener('appinstalled', () => {
            installPrompt.classList.add('hidden');
            deferredPrompt = null;
        });

        // Check if already installed (standalone mode)
        if (window.matchMedia('(display-mode: standalone)').matches) {
            installPrompt.classList.add('hidden');
        }
    </script>

    <!-- Notifications Script -->
    @if(!auth()->user()->isReseller())
    <script>
        function notificationBell() {
            return {
                showDropdown: false,
                notifications: [],
                unreadCount: 0,
                
                init() {
                    this.fetchNotifications();
                    // تحديث كل 30 ثانية
                    setInterval(() => this.fetchNotifications(), 30000);
                },
                
                toggleNotifications() {
                    this.showDropdown = !this.showDropdown;
                    if (this.showDropdown) {
                        this.fetchNotifications();
                    }
                },
                
                fetchNotifications() {
                    fetch('{{ route("notifications.unread") }}')
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                this.notifications = data.notifications;
                                this.unreadCount = data.unread_count;
                            }
                        });
                },
                
                markAsRead(id) {
                    fetch('/notifications/' + id + '/read', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        }
                    }).then(() => this.fetchNotifications());
                },
                
                markAllAsRead() {
                    fetch('{{ route("notifications.read-all") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        }
                    }).then(() => this.fetchNotifications());
                },
                
                timeAgo(dateString) {
                    const date = new Date(dateString);
                    const now = new Date();
                    const seconds = Math.floor((now - date) / 1000);
                    
                    if (seconds < 60) return 'الآن';
                    if (seconds < 3600) return Math.floor(seconds / 60) + ' دقيقة';
                    if (seconds < 86400) return Math.floor(seconds / 3600) + ' ساعة';
                    return Math.floor(seconds / 86400) + ' يوم';
                }
            };
        }
    </script>
    @endif
</body>
</html>