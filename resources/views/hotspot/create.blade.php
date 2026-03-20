@extends('layouts.app')

@section('title', 'إضافة بطاقة هوتسبوت')

@push('styles')
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush

@section('content')
<div class="space-y-6" x-data="hotspotForm()" x-cloak>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-gray-800 flex items-center gap-3">
                <span class="w-12 h-12 bg-gradient-to-br from-orange-500 to-red-600 rounded-xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-wifi text-white text-lg"></i>
                </span>
                إضافة بطاقة هوتسبوت
            </h1>
            <p class="text-gray-500 text-sm mt-1 mr-14">إنشاء بطاقة هوتسبوت جديدة</p>
        </div>
        <a href="{{ route('hotspot.index') }}" 
           class="group flex items-center gap-2 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-all duration-300">
            <i class="fas fa-arrow-right"></i>
            <span class="font-medium">رجوع</span>
        </a>
    </div>

    <!-- Success Message with WhatsApp Share -->
    @if(session('success') && session('card_data'))
        <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-2xl p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center">
                    <i class="fas fa-check text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-green-800">{{ session('success') }}</h3>
                    <p class="text-green-600 text-sm">يمكنك نسخ بيانات البطاقة</p>
                </div>
            </div>
            
            <!-- Card Preview -->
            <div class="bg-white rounded-xl p-4 mb-4 border border-green-200">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">اسم المستخدم:</span>
                        <span class="font-bold text-gray-800 mr-2" id="savedUsername">{{ session('card_data.username') }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">كلمة المرور:</span>
                        <span class="font-bold text-gray-800 mr-2" id="savedPassword">{{ session('card_data.password') }}</span>
                    </div>
                    @if(session('card_data.profile'))
                    <div>
                        <span class="text-gray-500">البروفايل:</span>
                        <span class="font-bold text-gray-800 mr-2">{{ session('card_data.profile') }}</span>
                    </div>
                    @endif
                    @if(session('card_data.data_limit'))
                    <div>
                        <span class="text-gray-500">حد البيانات:</span>
                        <span class="font-bold text-gray-800 mr-2">{{ session('card_data.data_limit') }} GB</span>
                    </div>
                    @endif
                </div>
            </div>
            
            <!-- Copy Button -->
            <div class="flex flex-col sm:flex-row gap-3">
                <button type="button" id="copyBtn" onclick="copyCardData()"
                    class="flex-1 flex items-center justify-center gap-2 px-6 py-3 bg-blue-500 hover:bg-blue-600 text-white rounded-xl font-medium transition-all duration-200 shadow-md">
                    <i class="fas fa-copy text-lg"></i>
                    نسخ البيانات
                </button>
                <a href="{{ route('hotspot.create') }}"
                   class="flex items-center justify-center gap-2 px-6 py-3 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-medium transition-all duration-200 text-center">
                    <i class="fas fa-plus"></i>
                    إضافة بطاقة أخرى
                </a>
            </div>
        </div>
        
        <script>
            function copyCardData() {
                const text = `🌐 بيانات اتصال WiFi\n\n👤 اسم المستخدم: {{ session('card_data.username') }}\n🔑 كلمة المرور: {{ session('card_data.password') }}{{ session('card_data.data_limit') ? '\n📊 حد البيانات: ' . session('card_data.data_limit') . ' GB' : '' }}\n\n✨ شكراً لاستخدامكم خدماتنا`;
                navigator.clipboard.writeText(text).then(() => {
                    const btn = document.getElementById('copyBtn');
                    const orig = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-check text-lg"></i> تم النسخ!';
                    btn.classList.remove('bg-blue-500', 'hover:bg-blue-600');
                    btn.classList.add('bg-green-500');
                    setTimeout(() => {
                        btn.innerHTML = orig;
                        btn.classList.remove('bg-green-500');
                        btn.classList.add('bg-blue-500', 'hover:bg-blue-600');
                    }, 2000);
                });
            }
        </script>
    @endif

    <form action="{{ route('hotspot.store') }}" method="POST">
        @csrf
        
        <!-- Simple Card -->
        <div class="bg-white rounded-2xl shadow-sm border-2 border-gray-100 overflow-hidden max-w-2xl mx-auto">
            <div class="bg-gradient-to-r from-orange-500 to-red-500 px-6 py-4">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <span class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-id-card"></i>
                    </span>
                    بيانات البطاقة
                </h2>
            </div>
            <div class="p-6 space-y-5">
                
                @if($errors->any())
                    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                        <div class="flex items-center gap-2 text-red-600">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>{{ $errors->first() }}</span>
                        </div>
                    </div>
                @endif

                <!-- Router Selection -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-server text-orange-500 ml-1"></i>
                        الراوتر <span class="text-red-500">*</span>
                    </label>
                    <select name="router_id" x-model="routerId" @change="loadProfiles()" required
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-orange-500 focus:ring-4 focus:ring-orange-100 transition-all duration-200">
                        <option value="">-- اختر الراوتر --</option>
                        @foreach($routers as $router)
                            <option value="{{ $router->id }}">
                                {{ $router->name }} ({{ $router->ip_address }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Username -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-user text-orange-500 ml-1"></i>
                        اسم المستخدم <span class="text-red-500">*</span>
                    </label>
                    <div class="flex gap-2">
                        <input type="text" name="username" x-model="username" required minlength="3" autocomplete="off"
                            class="flex-1 px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-orange-500 focus:ring-4 focus:ring-orange-100 transition-all duration-200"
                            placeholder="مثال: 123456">
                        <button type="button" @click="generateCredentials()" 
                            class="px-4 py-3 bg-orange-100 hover:bg-orange-200 text-orange-600 rounded-xl transition-all duration-200"
                            title="توليد تلقائي">
                            <i class="fas fa-magic"></i>
                        </button>
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-lock text-orange-500 ml-1"></i>
                        كلمة المرور <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'" name="password" x-model="password" required minlength="3" autocomplete="new-password"
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-orange-500 focus:ring-4 focus:ring-orange-100 transition-all duration-200 pl-12"
                            placeholder="كلمة المرور">
                        <button type="button" @click="showPassword = !showPassword" 
                            class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <i :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                        </button>
                    </div>
                </div>

                <!-- Profile -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-id-badge text-orange-500 ml-1"></i>
                        البروفايل
                    </label>
                    <select name="profile" 
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-orange-500 focus:ring-4 focus:ring-orange-100 transition-all duration-200">
                        <option value="">-- اختر البروفايل --</option>
                        <template x-for="profile in profiles" :key="profile">
                            <option :value="profile" x-text="profile"></option>
                        </template>
                    </select>
                </div>

                <!-- Data Limit (GB) -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-database text-orange-500 ml-1"></i>
                        حد البيانات (جيجابايت) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="data_limit_gb" min="0.1" step="any" required
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-orange-500 focus:ring-4 focus:ring-orange-100 transition-all duration-200"
                        placeholder="مثال: 5">
                    
                    <!-- Quick Data Buttons -->
                    <div class="flex flex-wrap gap-2 mt-3">
                        <button type="button" onclick="document.querySelector('[name=data_limit_gb]').value='1'" 
                            class="px-3 py-2 bg-orange-100 hover:bg-orange-200 text-orange-700 rounded-lg text-sm transition">
                            1 GB
                        </button>
                        <button type="button" onclick="document.querySelector('[name=data_limit_gb]').value='2'" 
                            class="px-3 py-2 bg-orange-100 hover:bg-orange-200 text-orange-700 rounded-lg text-sm transition">
                            2 GB
                        </button>
                        <button type="button" onclick="document.querySelector('[name=data_limit_gb]').value='5'" 
                            class="px-3 py-2 bg-orange-100 hover:bg-orange-200 text-orange-700 rounded-lg text-sm transition">
                            5 GB
                        </button>
                        <button type="button" onclick="document.querySelector('[name=data_limit_gb]').value='10'" 
                            class="px-3 py-2 bg-orange-100 hover:bg-orange-200 text-orange-700 rounded-lg text-sm transition">
                            10 GB
                        </button>
                        <button type="button" onclick="document.querySelector('[name=data_limit_gb]').value='20'" 
                            class="px-3 py-2 bg-orange-100 hover:bg-orange-200 text-orange-700 rounded-lg text-sm transition">
                            20 GB
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-4 border-t border-gray-100">
                    <button type="submit" 
                        class="w-full px-8 py-4 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white rounded-xl font-bold shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-[1.02] flex items-center justify-center gap-2">
                        <i class="fas fa-plus"></i>
                        إضافة البطاقة
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function hotspotForm() {
    return {
        routerId: '',
        username: '',
        password: '',
        showPassword: false,
        profiles: [],
        
        loadProfiles() {
            if (!this.routerId) {
                this.profiles = [];
                return;
            }
            fetch('/hotspot/' + this.routerId + '/profiles')
                .then(r => r.json())
                .then(data => {
                    this.profiles = data.profiles || [];
                })
                .catch(() => {
                    this.profiles = [];
                });
        },
        
        generateCredentials() {
            // Generate random username (6 digits)
            let user = '';
            for (let i = 0; i < 6; i++) {
                user += Math.floor(Math.random() * 10);
            }
            this.username = user;
            
            // Generate random password (3 digits)
            let pass = '';
            for (let i = 0; i < 3; i++) {
                pass += Math.floor(Math.random() * 10);
            }
            this.password = pass;
            this.showPassword = true;
        }
    }
}
</script>
@endpush
@endsection
