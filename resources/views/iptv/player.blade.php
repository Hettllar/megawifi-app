<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0f172a">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="MegaWiFi TV">
    <title>IPTV - MegaWiFi</title>

    <link rel="manifest" href="/manifest-iptv.json">
    <link rel="apple-touch-icon" sizes="192x192" href="/icons/iptv-icon-192.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/iptv-icon-192.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/icons/iptv-icon-152.png">
    <link rel="apple-touch-icon" sizes="144x144" href="/icons/iptv-icon-144.png">
    <link rel="apple-touch-icon" sizes="128x128" href="/icons/iptv-icon-128.png">
    <link rel="icon" type="image/svg+xml" href="/icons/iptv-icon.svg">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>

    <style>
        body { font-family: 'Cairo', sans-serif; }
        .channel-card { transition: all 0.3s ease; }
        .channel-card:hover { transform: translateY(-4px); }
        .channel-card.active { ring: 2px solid #8b5cf6; }
        .player-container { aspect-ratio: 16/9; }
        .category-icon { width: 40px; height: 40px; }
        @keyframes pulse-glow { 0%, 100% { box-shadow: 0 0 10px rgba(139,92,246,0.3); } 50% { box-shadow: 0 0 25px rgba(139,92,246,0.6); } }
        .live-badge { animation: pulse-glow 2s ease-in-out infinite; }
        .glass { background: rgba(255,255,255,0.05); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.1); }
        #videoPlayer { background: #000; }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 min-h-screen text-white">

    <!-- Login Section -->
    <div id="loginSection" class="min-h-screen flex flex-col items-center justify-center p-4">
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-gradient-to-br from-purple-600 to-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-purple-500/30">
                <i class="fas fa-tv text-3xl text-white"></i>
            </div>
            <h1 class="text-3xl font-bold bg-gradient-to-r from-purple-400 to-indigo-400 bg-clip-text text-transparent">MegaWiFi TV</h1>
            <p class="text-slate-400 mt-2">شاهد القنوات مباشرة في المتصفح</p>
        </div>

        <div class="w-full max-w-sm glass rounded-2xl p-6">
            <form id="loginForm" class="space-y-4">
                <div>
                    <label class="block text-sm text-slate-300 mb-2">رقم الهاتف</label>
                    <div class="relative">
                        <i class="fas fa-phone absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="tel" id="phoneInput" name="phone" placeholder="09XXXXXXXX"
                               class="w-full bg-white/10 border border-white/20 rounded-xl py-3 pr-10 pl-4 text-white placeholder-slate-500 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500 transition-all"
                               required maxlength="15" inputmode="numeric">
                    </div>
                </div>

                <button type="submit" id="loginBtn"
                        class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-bold py-3 rounded-xl transition-all duration-300 shadow-lg shadow-purple-500/30 hover:shadow-purple-500/50">
                    <span id="loginBtnText"><i class="fas fa-play-circle ml-2"></i> مشاهدة القنوات</span>
                    <span id="loginBtnLoading" class="hidden"><i class="fas fa-spinner fa-spin ml-2"></i> جاري التحقق...</span>
                </button>

                <div id="loginError" class="hidden text-center text-red-400 text-sm bg-red-500/10 rounded-lg p-3"></div>
            </form>
        </div>
    </div>

    <!-- Player Section -->
    <div id="playerSection" class="hidden min-h-screen flex flex-col">
        <!-- Header -->
        <div class="glass px-4 py-3 flex items-center justify-between sticky top-0 z-50">
            <div class="flex items-center gap-3">
                <button onclick="goBack()" class="w-9 h-9 bg-white/10 rounded-lg flex items-center justify-center hover:bg-white/20 transition-colors">
                    <i class="fas fa-arrow-right text-sm"></i>
                </button>
                <div>
                    <h2 class="text-sm font-bold">MegaWiFi TV</h2>
                    <p class="text-xs text-slate-400" id="subscriberName"></p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="live-badge px-2 py-0.5 bg-red-600 rounded text-xs font-bold flex items-center gap-1">
                    <span class="w-1.5 h-1.5 bg-white rounded-full"></span> LIVE
                </span>
            </div>
        </div>

        <!-- Video Player -->
        <div class="player-container w-full bg-black relative" id="playerContainer">
            <video id="videoPlayer" class="w-full h-full" controls playsinline autoplay>
                <p class="text-white text-center p-8">المتصفح لا يدعم تشغيل الفيديو</p>
            </video>
            <!-- Channel Info Overlay -->
            <div id="channelOverlay" class="absolute top-4 right-4 glass rounded-lg px-3 py-2 flex items-center gap-2 opacity-0 transition-opacity duration-500">
                <img id="overlayLogo" class="w-8 h-8 rounded" src="" alt="">
                <span id="overlayName" class="text-sm font-bold"></span>
            </div>
            <!-- No Channel Selected -->
            <div id="noChannelMsg" class="absolute inset-0 flex flex-col items-center justify-center bg-slate-900/90">
                <i class="fas fa-satellite-dish text-5xl text-slate-600 mb-4"></i>
                <p class="text-slate-400 text-lg">اختر قناة للمشاهدة</p>
            </div>
        </div>

        <!-- Channels List -->
        <div class="flex-1 overflow-y-auto p-4 pb-20 scrollbar-hide" id="channelsContainer">
            <!-- Categories will be injected here -->
        </div>
    </div>

    <script>
    let hls = null;
    let currentPhone = null;
    let allChannels = {};

    // Login Form
    document.getElementById('loginForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const phone = document.getElementById('phoneInput').value.trim();
        if (!phone) return;

        currentPhone = phone;
        const btnText = document.getElementById('loginBtnText');
        const btnLoading = document.getElementById('loginBtnLoading');
        const loginBtn = document.getElementById('loginBtn');
        const errorDiv = document.getElementById('loginError');

        btnText.classList.add('hidden');
        btnLoading.classList.remove('hidden');
        loginBtn.disabled = true;
        errorDiv.classList.add('hidden');

        try {
            const response = await fetch('{{ route("iptv.channels") }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: new URLSearchParams({ phone }),
            });

            var lastResponse = await response.text();
            console.log('Raw response:', lastResponse.substring(0, 200));
            const result = JSON.parse(lastResponse);

            if (result.success) {
                allChannels = result.channels;
                document.getElementById('subscriberName').textContent = result.subscriber;
                renderChannels(result.channels);
                document.getElementById('loginSection').classList.add('hidden');
                document.getElementById('playerSection').classList.remove('hidden');
                // Save phone in session
                sessionStorage.setItem('iptv_phone', phone);
            } else {
                errorDiv.textContent = result.message || 'حدث خطأ';
                errorDiv.classList.remove('hidden');
            }
        } catch (error) {
            errorDiv.textContent = 'حدث خطأ في الاتصال. يرجى المحاولة مرة أخرى.';
            errorDiv.classList.remove('hidden');
            console.error('IPTV Error:', error);
        } finally {
            btnText.classList.remove('hidden');
            btnLoading.classList.add('hidden');
            loginBtn.disabled = false;
        }
    });

    function renderChannels(channels) {
        const container = document.getElementById('channelsContainer');
        const categoryIcons = {
            'sports': 'fas fa-futbol',
            'news': 'fas fa-newspaper',
            'religious': 'fas fa-mosque',
            'entertainment': 'fas fa-film',
            'movies': 'fas fa-video',
            'kids': 'fas fa-child',
            'drama': 'fas fa-theater-masks',
        };
        const categoryColors = {
            'sports': 'from-green-600 to-emerald-600',
            'news': 'from-blue-600 to-cyan-600',
            'religious': 'from-amber-600 to-yellow-600',
            'entertainment': 'from-pink-600 to-rose-600',
            'movies': 'from-red-600 to-orange-600',
            'kids': 'from-purple-500 to-fuchsia-500',
            'drama': 'from-rose-600 to-pink-600',
        };

        let html = '';
        for (const [catKey, catData] of Object.entries(channels)) {
            const icon = categoryIcons[catKey] || 'fas fa-tv';
            const color = categoryColors[catKey] || 'from-purple-600 to-indigo-600';

            html += `
            <div class="mb-6">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-9 h-9 bg-gradient-to-br ${color} rounded-lg flex items-center justify-center shadow-lg">
                        <i class="${icon} text-white text-sm"></i>
                    </div>
                    <h3 class="text-base font-bold text-white">${catData.name}</h3>
                    <span class="text-xs text-slate-500">${catData.channels.length} قناة</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">`;

            for (const ch of catData.channels) {
                const logoHtml = ch.logo && !ch.logo.includes('imgur.com')
                    ? `<img src="${ch.logo}" alt="${ch.name}" class="w-12 h-12 rounded-lg object-contain bg-white/10 p-1" onerror="this.src='';this.className='hidden'">`
                    : `<div class="w-12 h-12 rounded-lg bg-gradient-to-br ${color} flex items-center justify-center"><i class="${icon} text-white text-lg"></i></div>`;

                html += `
                    <div class="channel-card glass rounded-xl p-3 cursor-pointer hover:bg-white/10 group"
                         onclick="playChannel(${ch.id}, '${ch.stream_url.replace(/'/g, "\\'")}', '${ch.name.replace(/'/g, "\\'")}', '${(ch.logo || '').replace(/'/g, "\\'")}', ${ch.is_hls}, '${ch.format || 'hls'}')"
                         id="channel-${ch.id}">
                        <div class="flex flex-col items-center text-center gap-2">
                            ${logoHtml}
                            <span class="text-xs font-medium text-slate-300 group-hover:text-white transition-colors leading-tight">${ch.name}</span>
                        </div>
                    </div>`;
            }

            html += `</div></div>`;
        }

        container.innerHTML = html;
    }

    function playChannel(channelId, streamUrl, channelName, logoUrl, isHls, format) {
        const video = document.getElementById('videoPlayer');
        const noMsg = document.getElementById('noChannelMsg');
        const overlay = document.getElementById('channelOverlay');
        const overlayName = document.getElementById('overlayName');
        const overlayLogo = document.getElementById('overlayLogo');

        // Update active state
        document.querySelectorAll('.channel-card').forEach(el => {
            el.classList.remove('ring-2', 'ring-purple-500', 'bg-purple-500/20');
        });
        const activeCard = document.getElementById(`channel-${channelId}`);
        if (activeCard) {
            activeCard.classList.add('ring-2', 'ring-purple-500', 'bg-purple-500/20');
        }

        // Hide no channel message
        noMsg.classList.add('hidden');

        // Show channel overlay
        overlayName.textContent = channelName;
        if (logoUrl && !logoUrl.includes('imgur.com')) {
            overlayLogo.src = logoUrl;
            overlayLogo.classList.remove('hidden');
        } else {
            overlayLogo.classList.add('hidden');
        }
        overlay.classList.remove('opacity-0');
        overlay.classList.add('opacity-100');
        setTimeout(() => {
            overlay.classList.remove('opacity-100');
            overlay.classList.add('opacity-0');
        }, 3000);

        // Stop current playback
        if (hls) {
            hls.destroy();
            hls = null;
        }
        video.pause();
        video.src = '';

        // Scroll to player
        document.getElementById('playerContainer').scrollIntoView({ behavior: 'smooth' });

        // Play the channel
        if (format === 'ts' || streamUrl.includes('stream-proxy')) {
            // Direct MPEG-TS stream - use video src directly
            video.src = streamUrl;
            video.play().catch(e => console.log('Auto-play blocked:', e));
        } else if (isHls || streamUrl.includes('.m3u8')) {
            if (Hls.isSupported()) {
                hls = new Hls({
                    enableWorker: true,
                    lowLatencyMode: true,
                });
                hls.loadSource(streamUrl);
                hls.attachMedia(video);
                hls.on(Hls.Events.MANIFEST_PARSED, () => {
                    video.play().catch(e => console.log('Auto-play blocked:', e));
                });
                hls.on(Hls.Events.ERROR, (event, data) => {
                    if (data.fatal) {
                        console.error('HLS Error:', data);
                        if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
                            hls.startLoad();
                        } else {
                            showPlayerError('حدث خطأ في تحميل القناة');
                        }
                    }
                });
            } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                // Safari native HLS
                video.src = streamUrl;
                video.play().catch(e => console.log('Auto-play blocked:', e));
            } else {
                showPlayerError('المتصفح لا يدعم تشغيل هذا النوع من البث');
            }
        } else {
            // Direct stream URL (Xtream)
            video.src = streamUrl;
            video.play().catch(e => console.log('Auto-play blocked:', e));
        }
    }

    function showPlayerError(msg) {
        const noMsg = document.getElementById('noChannelMsg');
        noMsg.innerHTML = `<i class="fas fa-exclamation-triangle text-5xl text-red-500 mb-4"></i><p class="text-red-400 text-lg">${msg}</p><p class="text-slate-500 text-sm mt-2">جرب قناة أخرى</p>`;
        noMsg.classList.remove('hidden');
    }

    function goBack() {
        if (hls) { hls.destroy(); hls = null; }
        const video = document.getElementById('videoPlayer');
        video.pause();
        video.src = '';
        document.getElementById('playerSection').classList.add('hidden');
        document.getElementById('loginSection').classList.remove('hidden');
    }

    // Check stored session
    window.addEventListener('DOMContentLoaded', () => {
        const savedPhone = sessionStorage.getItem('iptv_phone');
        if (savedPhone) {
            document.getElementById('phoneInput').value = savedPhone;
        }
    });
    </script>

    <!-- PWA Install Prompt -->
    <div id="pwaInstallPrompt" class="hidden fixed z-50 bottom-6 left-4 animate-bounce">
        <button id="pwaInstallBtn" class="relative flex items-center gap-2 px-5 py-3 bg-gradient-to-r from-purple-600 to-indigo-700 text-white rounded-full shadow-xl shadow-purple-600/40 hover:shadow-purple-600/60 transition-all duration-300 hover:scale-105">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            <span class="font-medium">تثبيت التطبيق</span>
        </button>
    </div>

    <script>
    // Service Worker Registration
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw-iptv.js?v=13', { scope: '/iptv' })
            .then(reg => console.log('[IPTV] SW registered:', reg.scope))
            .catch(err => console.log('[IPTV] SW registration failed:', err));
    }

    // PWA Install Prompt
    let deferredPrompt;
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        document.getElementById('pwaInstallPrompt').classList.remove('hidden');
    });

    document.getElementById('pwaInstallBtn').addEventListener('click', async () => {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        console.log('[IPTV] Install outcome:', outcome);
        deferredPrompt = null;
        document.getElementById('pwaInstallPrompt').classList.add('hidden');
    });

    window.addEventListener('appinstalled', () => {
        document.getElementById('pwaInstallPrompt').classList.add('hidden');
        deferredPrompt = null;
        console.log('[IPTV] App installed');
    });
    </script>
</body>
</html>
