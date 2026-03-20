{{-- PWA Install Button (Floating) --}}
<div id="pwaInstallPrompt" class="hidden fixed z-50 bottom-24 lg:bottom-6 left-4">
    <div class="relative">
        {{-- Close button --}}
        <button onclick="document.getElementById('pwaInstallPrompt').classList.add('hidden')" 
                class="absolute -top-2 -right-2 w-6 h-6 bg-gray-800 text-white rounded-full text-xs flex items-center justify-center hover:bg-gray-700 z-10">
            <i class="fas fa-times"></i>
        </button>
        
        {{-- Install card --}}
        <div class="bg-white rounded-2xl shadow-2xl p-4 border border-gray-100 max-w-xs">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-red-700 rounded-xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-mobile-alt text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="font-bold text-gray-800">تثبيت MegaWiFi</h3>
                    <p class="text-xs text-gray-500">تجربة أفضل كتطبيق</p>
                </div>
            </div>
            
            <button id="pwaInstallBtn" 
                    class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-[1.02]">
                <i class="fas fa-download"></i>
                <span class="font-medium">تثبيت الآن</span>
            </button>
            
            <p class="text-[10px] text-gray-400 text-center mt-2">
                <i class="fas fa-shield-alt"></i> آمن وبدون تحذيرات
            </p>
        </div>
    </div>
</div>

{{-- PWA Install Success Toast --}}
<div id="pwaInstallSuccess" class="hidden fixed z-50 top-4 left-1/2 -translate-x-1/2 px-6 py-3 bg-green-500 text-white rounded-xl shadow-xl">
    <div class="flex items-center gap-2">
        <i class="fas fa-check-circle"></i>
        <span>تم تثبيت التطبيق بنجاح!</span>
    </div>
</div>
