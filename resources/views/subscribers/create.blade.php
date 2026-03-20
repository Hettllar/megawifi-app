@extends('layouts.app')

@section('title', 'إضافة مشترك')

@push('styles')
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="subscriberForm()" x-cloak>
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-gray-800 flex items-center gap-3">
                <span class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-user-plus text-white text-lg"></i>
                </span>
                إضافة مشترك جديد
            </h1>
            <p class="text-gray-500 text-sm mt-1 mr-14">أدخل بيانات المشترك الجديد</p>
        </div>
        <a href="{{ route('subscribers.index', ['type' => 'pppoe']) }}" 
           class="flex items-center gap-2 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-all duration-300">
            <i class="fas fa-arrow-right"></i>
            <span>العودة للقائمة</span>
        </a>
    </div>

    <form method="POST" action="{{ route('subscribers.store') }}" class="space-y-6">
        @csrf
        
        <!-- Step 1: Basic Settings -->
        <div class="bg-white rounded-2xl shadow-sm border-2 border-gray-100 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <span class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-cog"></i>
                    </span>
                    إعدادات الحساب
                </h2>
            </div>
            
            <div class="p-6 space-y-5">
                <!-- Router Selection -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-router text-blue-500 ml-1"></i>
                        الراوتر <span class="text-red-500">*</span>
                    </label>
                    <select name="router_id" x-model="routerId" @change="loadPlans()"
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 hover:bg-white @error('router_id') border-red-500 @enderror">
                        <option value="">اختر الراوتر...</option>
                        @foreach($routers as $router)
                            <option value="{{ $router->id }}" {{ old('router_id', $selectedRouter?->id) == $router->id ? 'selected' : '' }}>
                                {{ $router->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('router_id')
                        <p class="text-red-500 text-xs mt-1 flex items-center gap-1"><i class="fas fa-exclamation-circle"></i> {{ $message }}</p>
                    @enderror
                </div>
                
                <!-- Hidden Type Field - Default PPP for Broadband -->
                <input type="hidden" name="type" value="ppp">
                
                <!-- Username & Password -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-user text-green-500 ml-1"></i>
                            اسم المستخدم <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="username" value="{{ old('username') }}" required
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 hover:bg-white @error('username') border-red-500 @enderror"
                            placeholder="مثال: user123">
                        @error('username')
                            <p class="text-red-500 text-xs mt-1 flex items-center gap-1"><i class="fas fa-exclamation-circle"></i> {{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-key text-yellow-500 ml-1"></i>
                            كلمة المرور <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="text" id="password" name="password" value="{{ old('password') }}" required x-model="password"
                                class="w-full px-4 py-3 pl-24 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 hover:bg-white @error('password') border-red-500 @enderror">
                            <button type="button" @click="generatePassword()"
                                class="absolute left-2 top-1/2 -translate-y-1/2 px-3 py-1.5 bg-blue-100 hover:bg-blue-200 text-blue-600 rounded-lg text-sm font-medium transition">
                                <i class="fas fa-magic ml-1"></i> توليد
                            </button>
                        </div>
                        @error('password')
                            <p class="text-red-500 text-xs mt-1 flex items-center gap-1"><i class="fas fa-exclamation-circle"></i> {{ $message }}</p>
                        @enderror
                    </div>
                </div>
                
                <!-- Profile/Plan -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-tachometer-alt text-purple-500 ml-1"></i>
                        الباقة / البروفايل <span class="text-red-500">*</span>
                    </label>
                    <select id="profile" name="profile" required
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 hover:bg-white @error('profile') border-red-500 @enderror">
                        <option value="">اختر الباقة...</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->mikrotik_profile_name }}" {{ old('profile') == $plan->mikrotik_profile_name ? 'selected' : '' }}>
                                {{ $plan->name }} ({{ $plan->rate_limit }})
                            </option>
                        @endforeach
                    </select>
                    @error('profile')
                        <p class="text-red-500 text-xs mt-1 flex items-center gap-1"><i class="fas fa-exclamation-circle"></i> {{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>
        
        <!-- Step 2: Customer Info -->
        <div class="bg-white rounded-2xl shadow-sm border-2 border-gray-100 overflow-hidden">
            <div class="bg-gradient-to-r from-emerald-500 to-emerald-600 px-6 py-4">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <span class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-address-card"></i>
                    </span>
                    معلومات العميل
                    <span class="text-emerald-200 text-sm font-normal">(اختياري)</span>
                </h2>
            </div>
            
            <div class="p-6 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-user-tie text-gray-500 ml-1"></i>
                            الاسم الكامل
                        </label>
                        <input type="text" name="full_name" value="{{ old('full_name') }}"
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition bg-gray-50 hover:bg-white"
                            placeholder="الاسم الثلاثي">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-id-card text-gray-500 ml-1"></i>
                            الرقم الوطني
                        </label>
                        <input type="text" name="national_id" value="{{ old('national_id') }}"
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition bg-gray-50 hover:bg-white"
                            placeholder="رقم الهوية الوطنية">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-phone text-green-500 ml-1"></i>
                            رقم الهاتف
                        </label>
                        <input type="text" name="phone" value="{{ old('phone') }}"
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition bg-gray-50 hover:bg-white"
                            placeholder="مثال: 0912345678">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-envelope text-blue-500 ml-1"></i>
                            البريد الإلكتروني
                        </label>
                        <input type="email" name="email" value="{{ old('email') }}"
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition bg-gray-50 hover:bg-white"
                            placeholder="email@example.com">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-map-marker-alt text-red-500 ml-1"></i>
                        العنوان
                    </label>
                    <textarea name="address" rows="2"
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition bg-gray-50 hover:bg-white resize-none"
                        placeholder="المنطقة - الشارع - رقم المبنى">{{ old('address') }}</textarea>
                </div>
            </div>
        </div>
        
        <!-- Step 3: Subscription Settings -->
        <div class="bg-white rounded-2xl shadow-sm border-2 border-gray-100 overflow-hidden">
            <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-6 py-4">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <span class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-calendar-alt"></i>
                    </span>
                    إعدادات الاشتراك
                </h2>
            </div>
            
            <div class="p-6 space-y-5">
                <!-- Quick Duration Buttons -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-3">
                        <i class="fas fa-clock text-purple-500 ml-1"></i>
                        مدة الاشتراك
                    </label>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <button type="button" @click="setDuration(7)" 
                            class="px-4 py-2 border-2 rounded-xl transition font-medium"
                            :class="duration === 7 ? 'border-purple-500 bg-purple-50 text-purple-700' : 'border-gray-200 hover:border-purple-300'">
                            أسبوع
                        </button>
                        <button type="button" @click="setDuration(30)" 
                            class="px-4 py-2 border-2 rounded-xl transition font-medium"
                            :class="duration === 30 ? 'border-purple-500 bg-purple-50 text-purple-700' : 'border-gray-200 hover:border-purple-300'">
                            شهر
                        </button>
                        <button type="button" @click="setDuration(90)" 
                            class="px-4 py-2 border-2 rounded-xl transition font-medium"
                            :class="duration === 90 ? 'border-purple-500 bg-purple-50 text-purple-700' : 'border-gray-200 hover:border-purple-300'">
                            3 أشهر
                        </button>
                        <button type="button" @click="setDuration(180)" 
                            class="px-4 py-2 border-2 rounded-xl transition font-medium"
                            :class="duration === 180 ? 'border-purple-500 bg-purple-50 text-purple-700' : 'border-gray-200 hover:border-purple-300'">
                            6 أشهر
                        </button>
                        <button type="button" @click="setDuration(365)" 
                            class="px-4 py-2 border-2 rounded-xl transition font-medium"
                            :class="duration === 365 ? 'border-purple-500 bg-purple-50 text-purple-700' : 'border-gray-200 hover:border-purple-300'">
                            سنة
                        </button>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-calendar-check text-green-500 ml-1"></i>
                            تاريخ انتهاء الصلاحية
                        </label>
                        <input type="date" name="expiry_date" x-model="expiryDate"
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition bg-gray-50 hover:bg-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-database text-orange-500 ml-1"></i>
                            حد البيانات (GB)
                        </label>
                        <input type="number" name="data_limit" value="{{ old('data_limit') }}" min="0" step="1"
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition bg-gray-50 hover:bg-white"
                            placeholder="اتركه فارغاً لعدم وجود حد">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Step 4: Technical Settings (PPP Only) -->
        <div class="bg-white rounded-2xl shadow-sm border-2 border-gray-100 overflow-hidden" x-show="type === 'ppp'" x-collapse x-cloak>
            <div class="bg-gradient-to-r from-gray-600 to-gray-700 px-6 py-4">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <span class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-sliders-h"></i>
                    </span>
                    إعدادات تقنية متقدمة
                    <span class="text-gray-300 text-sm font-normal">(اختياري)</span>
                </h2>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-fingerprint text-gray-500 ml-1"></i>
                            Caller ID (MAC)
                        </label>
                        <input type="text" name="caller_id" value="{{ old('caller_id') }}"
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-gray-500 focus:border-gray-500 transition bg-gray-50 hover:bg-white font-mono text-sm"
                            placeholder="AA:BB:CC:DD:EE:FF">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-server text-gray-500 ml-1"></i>
                            Local Address
                        </label>
                        <input type="text" name="local_address" value="{{ old('local_address') }}"
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-gray-500 focus:border-gray-500 transition bg-gray-50 hover:bg-white font-mono text-sm"
                            placeholder="10.0.0.1">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-laptop text-gray-500 ml-1"></i>
                            Remote Address
                        </label>
                        <input type="text" name="remote_address" value="{{ old('remote_address') }}"
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-gray-500 focus:border-gray-500 transition bg-gray-50 hover:bg-white font-mono text-sm"
                            placeholder="10.0.0.100">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Notes -->
        <div class="bg-white rounded-2xl shadow-sm border-2 border-gray-100 overflow-hidden">
            <div class="p-6">
                <label class="block text-sm font-bold text-gray-700 mb-2">
                    <i class="fas fa-sticky-note text-yellow-500 ml-1"></i>
                    ملاحظات
                </label>
                <textarea name="comment" rows="2"
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 hover:bg-white resize-none"
                    placeholder="أي ملاحظات إضافية...">{{ old('comment') }}</textarea>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row justify-between gap-4 pt-4">
            <a href="{{ route('subscribers.index', ['type' => 'pppoe']) }}" 
               class="flex items-center justify-center gap-2 px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-all duration-300 font-medium">
                <i class="fas fa-times"></i>
                إلغاء
            </a>
            <button type="submit" 
                class="flex items-center justify-center gap-2 px-8 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white rounded-xl transition-all duration-300 shadow-lg hover:shadow-xl hover:scale-105 font-bold">
                <i class="fas fa-user-plus"></i>
                إضافة المشترك
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function subscriberForm() {
    return {
        routerId: '{{ old('router_id', $selectedRouter?->id) }}',
        type: '{{ old('type', 'ppp') }}',
        password: '{{ old('password') }}',
        duration: null,
        expiryDate: '{{ old('expiry_date') }}',
        
        generatePassword() {
            const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let pwd = '';
            for (let i = 0; i < 8; i++) {
                pwd += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            this.password = pwd;
        },
        
        setDuration(days) {
            this.duration = days;
            const date = new Date();
            date.setDate(date.getDate() + days);
            this.expiryDate = date.toISOString().split('T')[0];
        },
        
        loadPlans() {
            if (!this.routerId) return;
            
            fetch(`/routers/${this.routerId}/plans`)
                .then(response => response.json())
                .then(plans => {
                    const select = document.getElementById('profile');
                    select.innerHTML = '<option value="">اختر الباقة...</option>';
                    
                    plans.filter(p => p.type === this.type || this.type === 'usermanager')
                        .forEach(plan => {
                            const option = document.createElement('option');
                            option.value = plan.name;
                            option.textContent = `${plan.name} (${plan.rate_limit || '-'})`;
                            select.appendChild(option);
                        });
                });
        },
        
        init() {
            if (this.routerId) {
                this.loadPlans();
            }
        }
    }
}
</script>
@endpush
@endsection
