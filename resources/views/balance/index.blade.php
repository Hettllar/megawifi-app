<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1e40af">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>التحقق من الرصيد - MegaWiFi</title>
    
    <!-- PWA -->
    <link rel="manifest" href="/manifest-balance.json">
    <link rel="apple-touch-icon" sizes="192x192" href="/icons/balance-icon-192.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/balance-icon-192.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/icons/balance-icon-152.png">
    <link rel="apple-touch-icon" sizes="144x144" href="/icons/balance-icon-144.png">
    
    <!-- Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .animate-fade-in { animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .animate-bounce-slow { animation: bounce 2s infinite; }
        @keyframes bounce {
            0%, 100% { transform: translateY(-5%); }
            50% { transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-red-900 via-red-800 to-rose-900 min-h-screen">
    <div class="min-h-screen flex flex-col items-center justify-center p-4">
        <!-- Logo & Header -->
        <div class="text-center mb-8 animate-fade-in">
            <div class="w-24 h-24 bg-white/10 backdrop-blur-lg rounded-full flex items-center justify-center mx-auto mb-4 border border-white/20">
                <svg class="w-12 h-12 text-white animate-bounce-slow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/>
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">استعلام الرصيد</h1>
            <p class="text-white/70">التحقق من رصيد الاشتراك</p>
        </div>

        <!-- Main Card -->
        <div class="w-full max-w-md">
            <!-- Search Form -->
            <div id="searchForm" class="bg-white/10 backdrop-blur-lg rounded-2xl p-6 border border-white/20 shadow-2xl animate-fade-in">
                <div class="text-center mb-6">
                    <h2 class="text-xl font-semibold text-white">أدخل رقم هاتفك</h2>
                    <p class="text-white/60 text-sm mt-1">للتحقق من رصيدك وبيانات اشتراكك</p>
                </div>

                <form id="balanceForm" class="space-y-4">
                    <div>
                        <div class="relative">
                            <input 
                                type="tel" 
                                id="phone" 
                                name="phone" 
                                placeholder="09XXXXXXXX"
                                class="w-full bg-white/10 border border-white/30 rounded-xl px-4 py-4 text-white placeholder-white/50 text-center text-xl tracking-wider focus:outline-none focus:ring-2 focus:ring-white/50 focus:border-transparent transition-all"
                                required
                                dir="ltr"
                                autocomplete="tel"
                            >
                            <div class="absolute left-4 top-1/2 -translate-y-1/2">
                                <svg class="w-6 h-6 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <button type="submit" id="submitBtn" class="w-full bg-white text-red-900 font-semibold py-4 rounded-xl hover:bg-white/90 active:scale-[0.98] transition-all shadow-lg">
                        <span id="btnText">تحقق من الرصيد</span>
                        <span id="btnLoading" class="hidden">
                            <svg class="animate-spin h-5 w-5 inline-block" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            جاري البحث...
                        </span>
                    </button>
                </form>

                <!-- Error Message -->
                <div id="errorMessage" class="hidden mt-4 p-4 bg-red-500/20 border border-red-500/30 rounded-xl">
                    <p class="text-red-200 text-center text-sm" id="errorText"></p>
                </div>
            </div>

            <!-- Results Card -->
            <div id="resultsCard" class="hidden bg-white/10 backdrop-blur-lg rounded-2xl p-6 border border-white/20 shadow-2xl animate-fade-in">
                <!-- Back Button -->
                <button onclick="showSearchForm()" class="flex items-center gap-2 text-white/70 hover:text-white mb-4 transition-colors">
                    <svg class="w-5 h-5 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    بحث جديد
                </button>

                <!-- User Info -->
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-white" id="resultName">-</h3>
                    <p class="text-white/60 text-sm" id="resultUsername">-</p>
                    <span id="resultStatus" class="inline-block px-3 py-1 rounded-full text-xs font-medium mt-2">-</span>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-2 gap-3 mb-6">
                    <!-- Days Remaining -->
                    <div class="bg-white/10 rounded-xl p-4 text-center">
                        <svg class="w-6 h-6 text-amber-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p class="text-2xl font-bold text-white" id="resultDays">-</p>
                        <p class="text-white/60 text-xs">يوم متبقي</p>
                    </div>

                    <!-- Data Remaining -->
                    <div class="bg-white/10 rounded-xl p-4 text-center">
                        <svg class="w-6 h-6 text-cyan-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                        </svg>
                        <p class="text-2xl font-bold text-white" id="resultDataRemaining">-</p>
                        <p class="text-white/60 text-xs">جيجا متبقية</p>
                    </div>
                </div>

                <!-- Usage Progress -->
                <div class="bg-white/10 rounded-xl p-4 mb-4">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-white/70 text-sm">الاستهلاك</span>
                        <span class="text-white font-semibold text-sm"><span id="resultUsedGb">0</span> / <span id="resultLimitGb">0</span> GB</span>
                    </div>
                    <div class="w-full bg-white/20 rounded-full h-3 overflow-hidden">
                        <div id="resultProgressBar" class="h-full bg-gradient-to-r from-green-500 to-emerald-400 transition-all duration-500" style="width: 0%"></div>
                    </div>
                    <p class="text-white/50 text-xs text-center mt-2"><span id="resultPercent">0</span>% مستخدم</p>
                </div>

                <!-- Usage Details -->
                <div class="bg-white/10 rounded-xl p-4 mb-4">
                    <h4 class="text-white/80 text-sm font-medium mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        تفاصيل الاستهلاك
                    </h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-cyan-500/10 rounded-lg p-3 text-center">
                            <svg class="w-5 h-5 text-cyan-400 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                            </svg>
                            <p class="text-lg font-bold text-cyan-400" id="resultDownloadGb">0</p>
                            <p class="text-white/50 text-xs">تحميل (GB)</p>
                        </div>
                        <div class="bg-orange-500/10 rounded-lg p-3 text-center">
                            <svg class="w-5 h-5 text-orange-400 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                            </svg>
                            <p class="text-lg font-bold text-orange-400" id="resultUploadGb">0</p>
                            <p class="text-white/50 text-xs">رفع (GB)</p>
                        </div>
                    </div>
                    <div id="usageResetInfo" class="hidden mt-3 text-center text-xs text-white/50">
                        <svg class="w-3 h-3 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        آخر تصفير: <span id="resultResetAt">-</span>
                    </div>
                </div>

                <!-- Additional Info -->
                <div class="space-y-2">
                    <div class="flex justify-between items-center py-2 border-b border-white/10">
                        <span class="text-white/60 text-sm">الباقة</span>
                        <span class="text-white font-medium text-sm" id="resultProfile">-</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-white/10">
                        <span class="text-white/60 text-sm">تاريخ الانتهاء</span>
                        <span class="text-white font-medium text-sm" id="resultExpiry">-</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-white/10">
                        <span class="text-white/60 text-sm">حالة الدفع</span>
                        <span id="resultPaymentStatus" class="font-medium text-sm px-2 py-1 rounded-full">-</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-white/10">
                        <span class="text-white/60 text-sm">الراوتر</span>
                        <span class="text-white font-medium text-sm" id="resultRouter">-</span>
                    </div>
                    <div class="flex justify-between items-center py-2">
                        <span class="text-white/60 text-sm">آخر اتصال</span>
                        <span class="text-white font-medium text-sm" id="resultLastLogin">-</span>
                    </div>
                </div>

                <!-- IPTV Section -->
                <div id="iptvSection" class="mt-6 hidden">
                    <a href="/iptv" class="block w-full bg-gradient-to-r from-purple-600/20 to-indigo-600/20 backdrop-blur-sm border border-purple-500/30 rounded-xl p-4 text-white hover:from-purple-600/30 hover:to-indigo-600/30 transition-all duration-300 group">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-lg flex items-center justify-center shadow-lg shadow-purple-500/30">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
                                    </svg>
                                </div>
                                <div class="text-right">
                                    <div class="font-semibold text-lg">خدمة IPTV مجانية</div>
                                    <div class="text-xs text-purple-300/70">اضغط لمشاهدة القنوات المباشرة في المتصفح</div>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-purple-300 group-hover:translate-x-[-4px] transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </div>
                    </a>
                </div>

                <!-- Sessions Section -->
                <div id="sessionsSection" class="mt-6 hidden">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-white font-semibold flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.636 18.364a9 9 0 010-12.728m12.728 0a9 9 0 010 12.728m-9.9-2.829a5 5 0 010-7.07m7.072 0a5 5 0 010 7.07M13 12a1 1 0 11-2 0 1 1 0 012 0z"/>
                            </svg>
                            الجلسات النشطة
                        </h4>
                        <span class="bg-green-500/20 text-green-300 px-2 py-1 rounded-full text-xs" id="sessionsCount">0</span>
                    </div>
                    <div id="sessionsList" class="space-y-2 max-h-60 overflow-y-auto">
                        <!-- Sessions will be injected here -->
                    </div>
                    <div id="noSessions" class="hidden text-center py-4 text-white/50 text-sm">
                        <svg class="w-8 h-8 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728M5.636 18.364a9 9 0 010-12.728"/>
                        </svg>
                        لا توجد جلسات نشطة حالياً
                    </div>
                </div>

                <!-- Session History Section -->
                <div id="historySection" class="mt-6 hidden">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-white font-semibold flex items-center gap-2">
                            <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            سجل الجلسات السابقة
                        </h4>
                        <span class="bg-blue-500/20 text-blue-300 px-2 py-1 rounded-full text-xs" id="historyCount">0</span>
                    </div>
                    <div id="historyList" class="space-y-2 max-h-80 overflow-y-auto">
                        <!-- History will be injected here -->
                    </div>
                    <div id="noHistory" class="hidden text-center py-4 text-white/50 text-sm">
                        <svg class="w-8 h-8 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        لا يوجد سجل جلسات سابقة
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        const form = document.getElementById('balanceForm');
        const searchFormDiv = document.getElementById('searchForm');
        const resultsCard = document.getElementById('resultsCard');
        const errorMessage = document.getElementById('errorMessage');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const btnLoading = document.getElementById('btnLoading');

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const phone = document.getElementById('phone').value.trim();
            if (!phone) return;

            // Show loading
            btnText.classList.add('hidden');
            btnLoading.classList.remove('hidden');
            submitBtn.disabled = true;
            errorMessage.classList.add('hidden');

            try {
                const response = await fetch('{{ route("balance.check") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ phone }),
                });

                const result = await response.json();

                if (result.success) {
                    displayResults(result.data);
                } else {
                    showError(result.message || 'حدث خطأ أثناء البحث');
                }
            } catch (error) {
                showError('حدث خطأ في الاتصال. يرجى المحاولة مرة أخرى.');
                console.error(error);
            } finally {
                btnText.classList.remove('hidden');
                btnLoading.classList.add('hidden');
                submitBtn.disabled = false;
            }
        });

        function displayResults(data) {

            // Update UI
            document.getElementById('resultName').textContent = data.full_name || data.username;
            document.getElementById('resultUsername').textContent = '@' + data.username;
            
            // Status badge
            const statusEl = document.getElementById('resultStatus');
            statusEl.textContent = data.status_text;
            statusEl.className = 'inline-block px-3 py-1 rounded-full text-xs font-medium mt-2 ';
            if (data.status === 'active') {
                statusEl.className += 'bg-green-500/20 text-green-300';
            } else if (data.status === 'expired') {
                statusEl.className += 'bg-red-500/20 text-red-300';
            } else {
                statusEl.className += 'bg-yellow-500/20 text-yellow-300';
            }

            // Days remaining
            const daysEl = document.getElementById('resultDays');
            if (data.remaining_days !== null) {
                daysEl.textContent = data.remaining_days;
                daysEl.parentElement.classList.toggle('text-red-400', data.remaining_days <= 3);
            } else {
                daysEl.textContent = '∞';
            }

            // Data remaining
            const dataRemEl = document.getElementById('resultDataRemaining');
            if (data.remaining_gb !== null) {
                dataRemEl.textContent = data.remaining_gb;
            } else {
                dataRemEl.textContent = '∞';
            }

            // Usage
            document.getElementById('resultUsedGb').textContent = data.used_gb;
            document.getElementById('resultLimitGb').textContent = data.data_limit_gb || '∞';
            document.getElementById('resultPercent').textContent = data.usage_percent;
            
            // Usage details
            document.getElementById('resultDownloadGb').textContent = data.download_gb || '0';
            document.getElementById('resultUploadGb').textContent = data.upload_gb || '0';
            
            // Usage reset info
            if (data.usage_reset_at) {
                document.getElementById('usageResetInfo').classList.remove('hidden');
                document.getElementById('resultResetAt').textContent = data.usage_reset_at;
            } else {
                document.getElementById('usageResetInfo').classList.add('hidden');
            }
            
            // Progress bar
            const progressBar = document.getElementById('resultProgressBar');
            progressBar.style.width = data.usage_percent + '%';
            if (data.usage_percent > 80) {
                progressBar.className = 'h-full bg-gradient-to-r from-red-500 to-red-400 transition-all duration-500';
            } else if (data.usage_percent > 50) {
                progressBar.className = 'h-full bg-gradient-to-r from-yellow-500 to-orange-400 transition-all duration-500';
            } else {
                progressBar.className = 'h-full bg-gradient-to-r from-green-500 to-emerald-400 transition-all duration-500';
            }

            // Additional info
            document.getElementById('resultProfile').textContent = data.profile || '-';
            document.getElementById('resultExpiry').textContent = data.expiration_date || '-';
            document.getElementById('resultRouter').textContent = data.router || '-';
            document.getElementById('resultLastLogin').textContent = data.last_login || '-';
            
            // Payment status
            const paymentEl = document.getElementById('resultPaymentStatus');
            if (data.payment_status) {
                let paymentText = data.payment_status.text;
                if (data.payment_status.status === 'debt' && data.payment_status.amount) {
                    paymentText += ' (' + Number(data.payment_status.amount).toLocaleString() + ' ل.س)';
                }
                paymentEl.textContent = paymentText;
                paymentEl.className = 'font-medium text-sm px-2 py-1 rounded-full ';
                if (data.payment_status.color === 'green') {
                    paymentEl.className += 'bg-green-500/20 text-green-300';
                } else if (data.payment_status.color === 'orange') {
                    paymentEl.className += 'bg-orange-500/20 text-orange-300';
                } else {
                    paymentEl.className += 'bg-red-500/20 text-red-300';
                }
            } else {
                paymentEl.textContent = '-';
                paymentEl.className = 'font-medium text-sm px-2 py-1 rounded-full text-white/50';
            }

            // Sessions
            displaySessions(data.sessions || [], data.sessions_count || 0);
            
            // Session History
            displaySessionHistory(data.session_history || [], data.history_count || 0);

            // Show IPTV section if user has IPTV subscription
            const iptvSection = document.getElementById('iptvSection');
            if (data.iptv && data.iptv.has_subscription) {
                iptvSection.classList.remove('hidden');
            } else {
                iptvSection.classList.add('hidden');
            }

            // Show results
            searchFormDiv.classList.add('hidden');
            resultsCard.classList.remove('hidden');
        }

        function displaySessions(sessions, count) {
            const sessionsSection = document.getElementById('sessionsSection');
            const sessionsList = document.getElementById('sessionsList');
            const noSessions = document.getElementById('noSessions');
            const sessionsCount = document.getElementById('sessionsCount');
            
            sessionsSection.classList.remove('hidden');
            sessionsCount.textContent = count;
            
            if (sessions.length === 0) {
                sessionsList.classList.add('hidden');
                noSessions.classList.remove('hidden');
                return;
            }
            
            sessionsList.classList.remove('hidden');
            noSessions.classList.add('hidden');
            
            sessionsList.innerHTML = sessions.map(session => `
                <div class="bg-white/5 rounded-lg p-3 border border-white/10">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-green-400 text-xs font-medium flex items-center gap-1">
                            <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                            متصل
                        </span>
                        <span class="text-white/50 text-xs">${session.started_at}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <span class="text-white/50">IP:</span>
                            <span class="text-white mr-1" dir="ltr">${session.ip}</span>
                        </div>
                        <div>
                            <span class="text-white/50">المدة:</span>
                            <span class="text-white mr-1">${session.duration || '-'}</span>
                        </div>
                        <div>
                            <span class="text-white/50">تحميل:</span>
                            <span class="text-cyan-400 mr-1">${session.download} MB</span>
                        </div>
                        <div>
                            <span class="text-white/50">رفع:</span>
                            <span class="text-orange-400 mr-1">${session.upload} MB</span>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function displaySessionHistory(history, count) {
            const historySection = document.getElementById('historySection');
            const historyList = document.getElementById('historyList');
            const noHistory = document.getElementById('noHistory');
            const historyCount = document.getElementById('historyCount');
            
            historySection.classList.remove('hidden');
            historyCount.textContent = count;
            
            if (history.length === 0) {
                historyList.classList.add('hidden');
                noHistory.classList.remove('hidden');
                return;
            }
            
            historyList.classList.remove('hidden');
            noHistory.classList.add('hidden');
            
            historyList.innerHTML = history.map(session => `
                <div class="bg-white/5 rounded-lg p-3 border border-white/10">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-gray-400 text-xs font-medium flex items-center gap-1">
                            <span class="w-2 h-2 bg-gray-400 rounded-full"></span>
                            منتهية
                        </span>
                        <span class="text-white/50 text-xs">${session.ended_at}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <span class="text-white/50">IP:</span>
                            <span class="text-white mr-1" dir="ltr">${session.ip}</span>
                        </div>
                        <div>
                            <span class="text-white/50">المدة:</span>
                            <span class="text-white mr-1">${session.duration || '-'}</span>
                        </div>
                        <div>
                            <span class="text-white/50">تحميل:</span>
                            <span class="text-cyan-400 mr-1">${session.download} MB</span>
                        </div>
                        <div>
                            <span class="text-white/50">رفع:</span>
                            <span class="text-orange-400 mr-1">${session.upload} MB</span>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-t border-white/10 flex justify-between text-xs">
                        <span class="text-white/50">الإجمالي:</span>
                        <span class="text-purple-400 font-medium">${session.total_mb} MB</span>
                    </div>
                </div>
            `).join('');
        }

        function showError(message) {
            document.getElementById('errorText').textContent = message;
            errorMessage.classList.remove('hidden');
        }

        function showSearchForm() {
            resultsCard.classList.add('hidden');
            searchFormDiv.classList.remove('hidden');
        }

    </script>

    <!-- PWA Install Button (Floating) -->
    <div id="pwaInstallPrompt" class="hidden fixed z-50 bottom-6 left-4 animate-bounce">
        <button id="pwaInstallBtn" class="relative flex items-center gap-2 px-5 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-full shadow-xl shadow-blue-600/40 hover:shadow-blue-600/60 transition-all duration-300 hover:scale-105">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            <span class="font-medium">تثبيت التطبيق</span>
        </button>
    </div>

    <!-- PWA Install Success Toast -->
    <div id="pwaInstallSuccess" class="hidden fixed z-50 top-4 left-1/2 -translate-x-1/2 px-6 py-3 bg-green-500 text-white rounded-xl shadow-xl animate-fade-in">
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <span>تم تثبيت التطبيق بنجاح!</span>
        </div>
    </div>

    <!-- Service Worker Registration & PWA Install -->
    <script>
        // Service Worker Registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', async () => {
                try {
                    const registration = await navigator.serviceWorker.register('/sw-balance.js', { scope: '/check-balance' });
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
</body>
</html>