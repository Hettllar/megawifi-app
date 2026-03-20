<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1e40af">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="MegaWiFi">
    <title>تسجيل الدخول - MegaWiFi</title>

    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="192x192" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/icons/icon-152.png">
    <link rel="apple-touch-icon" sizes="144x144" href="/icons/icon-144.png">
    <link rel="icon" type="image/png" href="/icons/icon-192.png" sizes="192x192">
    <link rel="icon" type="image/png" href="/icons/icon-512.png" sizes="512x512">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="/css/tailwind.min.js"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        * { font-family: 'Tajawal', sans-serif; }
        @keyframes pulse-ring { 0% { transform: scale(0.9); opacity: 0.8; } 100% { transform: scale(1.5); opacity: 0; } }
        .pwa-pulse::before { content: ''; position: absolute; inset: -4px; border-radius: 9999px; background: inherit; animation: pulse-ring 1.5s infinite; z-index: -1; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-900 to-blue-700 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-white rounded-2xl mx-auto mb-4 flex items-center justify-center shadow-xl">
                <img src="/icons/icon-192.png" alt="MegaWiFi" class="w-16 h-16 rounded-xl">
            </div>
            <h1 class="text-4xl font-bold text-white mb-2">MegaWiFi</h1>
            <p class="text-blue-200">نظام إدارة الشبكات والمشتركين</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 text-center mb-6">تسجيل الدخول</h2>
            
            @if ($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="mb-4">
                    <label for="email" class="block text-gray-700 text-sm font-medium mb-2">البريد الإلكتروني</label>
                    <input type="email"
                           id="email"
                           name="email"
                           value="{{ old('email') }}"
                           required
                           autofocus
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                           placeholder="admin@example.com">
                </div>

                <div class="mb-6">
                    <label for="password" class="block text-gray-700 text-sm font-medium mb-2">كلمة المرور</label>
                    <input type="password"
                           id="password"
                           name="password"
                           required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                           placeholder="••••••••">
                </div>

                <div class="flex items-center mb-6">
                    <input type="checkbox" 
                           id="remember" 
                           name="remember"
                           class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <label for="remember" class="mr-2 text-sm text-gray-600">تذكرني</label>
                </div>

                <button type="submit"
                        class="w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white py-3 rounded-lg font-medium hover:from-blue-700 hover:to-blue-800 transition-all shadow-lg hover:shadow-xl">
                    <i class="fas fa-sign-in-alt ml-2"></i>
                    دخول
                </button>

            </form>

            <!-- App Download & Balance Check Links -->
            <div class="mt-6 pt-4 border-t border-gray-200 space-y-3">
                <a href="/megawifi.apk" class="flex items-center justify-center gap-2 px-4 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all shadow-md">
                    <i class="fab fa-android text-lg"></i>
                    <span class="font-medium">تحميل تطبيق الأندرويد</span>
                </a>
                <a href="{{ route('balance.index') }}" class="flex items-center justify-center gap-2 text-gray-600 hover:text-blue-600 transition-colors">
                    <i class="fas fa-search"></i>
                    <span>التحقق من رصيد الاشتراك</span>
                </a>
            </div>
        </div>

        <!-- Footer -->
        <p class="text-center text-blue-200 text-sm mt-6">
        </p>
    </div>

    <!-- PWA Install Button -->
    <div id="pwaInstallPrompt" class="hidden fixed z-50 bottom-6 left-4">
        <div class="relative">
            <button onclick="document.getElementById('pwaInstallPrompt').classList.add('hidden')" 
                    class="absolute -top-2 -right-2 w-6 h-6 bg-gray-800 text-white rounded-full text-xs flex items-center justify-center hover:bg-gray-700 z-10">
                <i class="fas fa-times"></i>
            </button>
            
            <div class="bg-white rounded-2xl shadow-2xl p-4 border border-gray-100 max-w-xs">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-mobile-alt text-white text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-800">تثبيت MegaWiFi</h3>
                        <p class="text-xs text-gray-500">تجربة أفضل كتطبيق</p>
                    </div>
                </div>
                
                <button id="pwaInstallBtn" 
                        class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-[1.02]">
                    <i class="fas fa-download"></i>
                    <span class="font-medium">تثبيت الآن</span>
                </button>
                
                <p class="text-[10px] text-gray-400 text-center mt-2">
                    <i class="fas fa-shield-alt"></i> آمن وبدون تحذيرات
                </p>
            </div>
        </div>
    </div>

    <!-- PWA Install Success Toast -->
    <div id="pwaInstallSuccess" class="hidden fixed z-50 top-4 left-1/2 -translate-x-1/2 px-6 py-3 bg-green-500 text-white rounded-xl shadow-xl">
        <div class="flex items-center gap-2">
            <i class="fas fa-check-circle"></i>
            <span>تم تثبيت التطبيق بنجاح!</span>
        </div>
    </div>

    <!-- Service Worker & PWA -->
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

        // Check if already installed
        if (window.matchMedia('(display-mode: standalone)').matches) {
            installPrompt.classList.add('hidden');
        }
    </script>
</body>
</html>
