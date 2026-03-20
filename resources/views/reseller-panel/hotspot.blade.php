@extends('layouts.app')

@section('title', 'إنشاء بطاقة هوتسبوت')

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
                إنشاء بطاقة هوتسبوت
            </h1>
            <p class="text-gray-500 text-sm mt-1 mr-14">إنشاء بطاقات WiFi جديدة للعملاء</p>
        </div>
        <a href="{{ route('reseller.dashboard') }}" 
           class="group flex items-center gap-2 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-all duration-300">
            <i class="fas fa-arrow-right"></i>
            <span class="font-medium">رجوع</span>
        </a>
    </div>

    @if($routers->isEmpty())
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-center">
        <i class="fas fa-exclamation-triangle text-amber-500 text-4xl mb-3"></i>
        <h3 class="text-lg font-bold text-amber-800">لا يوجد راوترات متاحة</h3>
        <p class="text-amber-600">لم يتم تخصيص صلاحية إنشاء بطاقات هوتسبوت لك على أي راوتر</p>
    </div>
    @else

    <!-- Success Message -->
    <div x-show="successMessage" x-transition class="bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-2xl p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center">
                <i class="fas fa-check text-white text-xl"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-green-800" x-text="successMessage"></h3>
                <p class="text-green-600 text-sm">يمكنك مشاركة البيانات مع العميل</p>
            </div>
        </div>
        
        <!-- Card Preview -->
        <div class="bg-white rounded-xl p-4 mb-4 border border-green-200">
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-gray-500">اسم المستخدم:</span>
                    <span class="font-bold text-gray-800 mr-2" x-text="createdCard.username"></span>
                </div>
                <div>
                    <span class="text-gray-500">كلمة المرور:</span>
                    <template x-if="canViewPassword">
                        <span class="font-bold text-gray-800 mr-2" x-text="createdCard.password"></span>
                    </template>
                    <template x-if="!canViewPassword">
                        <span class="font-bold text-gray-400 mr-2">••••••</span>
                    </template>
                </div>
                <div>
                    <span class="text-gray-500">حد البيانات:</span>
                    <span class="font-bold text-gray-800 mr-2" x-text="createdCard.data_limit + ' GB'"></span>
                </div>
            </div>
            <template x-if="!canViewPassword">
                <div class="mt-3 bg-amber-50 border border-amber-200 rounded-lg p-2 text-xs text-amber-700 flex items-center gap-2">
                    <i class="fas fa-info-circle"></i>
                    <span>كلمة المرور مخفية - يمكنك مشاركة البطاقة مع العميل مباشرة عبر الأزرار أدناه</span>
                </div>
            </template>
        </div>
        
        <!-- Share Buttons -->
        <div class="flex flex-col gap-3">
            <!-- Auto SMS Sent Indicator -->
            <template x-if="createdCard.sms_sent">
                <div class="w-full flex items-center justify-center gap-3 px-4 py-3 bg-gradient-to-r from-emerald-500 to-teal-500 text-white rounded-xl font-bold text-base shadow-lg">
                    <i class="fas fa-check-circle text-xl"></i>
                    تم إرسال SMS تلقائياً للمشترك عبر الراوتر ✅
                </div>
            </template>

            <!-- Send to Customer Button (if phone provided) -->
            <template x-if="createdCard.phone && createdCard.phone.trim()">
                <button type="button" @click="sendToCustomer()" 
                    class="w-full flex items-center justify-center gap-3 px-6 py-4 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white rounded-xl font-bold text-lg shadow-lg hover:shadow-xl transition-all duration-300">
                    <i class="fab fa-whatsapp text-2xl"></i>
                    إرسال للعميل مباشرة
                    <span class="text-sm bg-white/20 px-2 py-1 rounded-lg" x-text="'+' + createdCard.phone"></span>
                </button>
            </template>
            
            <!-- Action Buttons Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <!-- WhatsApp -->
                <button type="button" @click="shareWhatsApp()" 
                    class="flex flex-col items-center gap-2 px-4 py-4 bg-green-500 hover:bg-green-600 text-white rounded-xl font-medium transition shadow-md hover:shadow-lg">
                    <i class="fab fa-whatsapp text-2xl"></i>
                    <span class="text-sm">واتساب</span>
                </button>
                
                <!-- SMS -->
                <button type="button" @click="sendSMS()" 
                    class="flex flex-col items-center gap-2 px-4 py-4 bg-blue-500 hover:bg-blue-600 text-white rounded-xl font-medium transition shadow-md hover:shadow-lg">
                    <i class="fas fa-sms text-2xl"></i>
                    <span class="text-sm">SMS</span>
                </button>
                
                <!-- Copy -->
                <button type="button" @click="copyCardData()" 
                    class="flex flex-col items-center gap-2 px-4 py-4 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-medium transition shadow-md hover:shadow-lg">
                    <i class="fas fa-copy text-2xl"></i>
                    <span class="text-sm">نسخ</span>
                </button>
                
                <!-- Print -->
                <button type="button" @click="printCard()" 
                    class="flex flex-col items-center gap-2 px-4 py-4 bg-purple-500 hover:bg-purple-600 text-white rounded-xl font-medium transition shadow-md hover:shadow-lg">
                    <i class="fas fa-print text-2xl"></i>
                    <span class="text-sm">طباعة</span>
                </button>
            </div>
            
            <!-- New Card Button -->
            <button type="button" @click="resetForm()" 
                class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-bold transition shadow-md hover:shadow-lg">
                <i class="fas fa-plus"></i>
                إضافة بطاقة أخرى
            </button>
        </div>
    </div>

    <!-- Form -->
    <div x-show="!successMessage" class="bg-white rounded-2xl shadow-sm border-2 border-gray-100 overflow-hidden max-w-2xl mx-auto">
        <div class="bg-gradient-to-r from-orange-500 to-red-500 px-6 py-4">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <span class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-id-card"></i>
                </span>
                بيانات البطاقة
            </h2>
        </div>
        <div class="p-6 space-y-5">
            
            <!-- Error Message -->
            <div x-show="errorMessage" x-transition class="bg-red-50 border border-red-200 rounded-xl p-4">
                <div class="flex items-center gap-2 text-red-600">
                    <i class="fas fa-exclamation-circle"></i>
                    <span x-text="errorMessage"></span>
                </div>
            </div>

            <!-- Router Selection -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-server text-orange-500 ml-1"></i>
                    الراوتر <span class="text-red-500">*</span>
                </label>
                <select x-model="routerId" @change="loadProfiles()" required
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-orange-500 focus:ring-4 focus:ring-orange-100 transition-all duration-200">
                    <option value="">-- اختر الراوتر --</option>
                    @foreach($routers as $router)
                        <option value="{{ $router->id }}" data-price-per-gb="{{ $router->price_per_gb ?? 0 }}" {{ $selectedRouter?->id == $router->id ? 'selected' : '' }}>
                            {{ $router->name }} ({{ $router->ip_address }})
                        </option>
                    @endforeach
                </select>
                
                <!-- Show price per GB -->
                <div x-show="pricePerGb > 0" class="mt-2 bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-amber-700">
                            <i class="fas fa-coins ml-1"></i>
                            سعر الجيجا الواحدة:
                        </span>
                        <span class="font-bold text-amber-800" x-text="Number(pricePerGb).toLocaleString() + ' ل.س'"></span>
                    </div>
                </div>
            </div>

            <!-- Username -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-user text-orange-500 ml-1"></i>
                    اسم المستخدم <span class="text-red-500">*</span>
                </label>
                <div class="flex gap-2">
                    <input type="text" x-model="username" required minlength="3"
                        autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-form-type="other"
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
                <template x-if="canViewPassword">
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'" x-model="password" required minlength="3"
                            autocomplete="new-password" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-form-type="other"
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-orange-500 focus:ring-4 focus:ring-orange-100 transition-all duration-200 pl-12"
                            placeholder="كلمة المرور">
                        <button type="button" @click="showPassword = !showPassword" 
                            class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <i :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                        </button>
                    </div>
                </template>
                <template x-if="!canViewPassword">
                    <div>
                        <div class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl bg-gray-50 text-gray-400 flex items-center justify-between">
                            <span>••••••</span>
                            <span class="text-xs text-gray-400"><i class="fas fa-lock ml-1"></i> يتم توليدها تلقائياً</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle ml-1"></i>
                            سيتم توليد كلمة مرور عشوائية تلقائياً ولن تكون مرئية لك
                        </p>
                    </div>
                </template>
            </div>

            <!-- Profile -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-id-badge text-orange-500 ml-1"></i>
                    البروفايل
                </label>
                <select x-model="profile"
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-orange-500 focus:ring-4 focus:ring-orange-100 transition-all duration-200">
                    <option value="">-- اختر البروفايل --</option>
                    <template x-for="p in profiles" :key="p">
                        <option :value="p" x-text="p"></option>
                    </template>
                </select>
            </div>

            <!-- Data Limit (GB) -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-database text-orange-500 ml-1"></i>
                    حد البيانات (جيجابايت) <span class="text-red-500">*</span>
                </label>
                <input type="number" x-model="dataLimit" min="0.1" step="any" required
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-orange-500 focus:ring-4 focus:ring-orange-100 transition-all duration-200"
                    placeholder="مثال: 5">
                
                <!-- Quick Data Buttons -->
                <div class="flex flex-wrap gap-2 mt-3">
                    <button type="button" @click="dataLimit = 1" 
                        class="px-3 py-2 bg-orange-100 hover:bg-orange-200 text-orange-700 rounded-lg text-sm transition">
                        1 GB
                    </button>
                    <button type="button" @click="dataLimit = 2" 
                        class="px-3 py-2 bg-orange-100 hover:bg-orange-200 text-orange-700 rounded-lg text-sm transition">
                        2 GB
                    </button>
                    <button type="button" @click="dataLimit = 5" 
                        class="px-3 py-2 bg-orange-100 hover:bg-orange-200 text-orange-700 rounded-lg text-sm transition">
                        5 GB
                    </button>
                    <button type="button" @click="dataLimit = 10" 
                        class="px-3 py-2 bg-orange-100 hover:bg-orange-200 text-orange-700 rounded-lg text-sm transition">
                        10 GB
                    </button>
                    <button type="button" @click="dataLimit = 20" 
                        class="px-3 py-2 bg-orange-100 hover:bg-orange-200 text-orange-700 rounded-lg text-sm transition">
                        20 GB
                    </button>
                </div>
            </div>

            <!-- Phone Number for WhatsApp -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fab fa-whatsapp text-green-500 ml-1"></i>
                    رقم هاتف العميل (للمشاركة عبر واتساب)
                </label>
                <div class="relative flex">
                    <span class="inline-flex items-center px-4 py-3 bg-green-100 text-green-700 font-bold border-2 border-l-0 border-gray-200 rounded-r-xl">
                        963+
                    </span>
                    <input type="tel" x-model="phone" dir="ltr"
                        autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-lpignore="true" data-form-type="other"
                        class="flex-1 px-4 py-3 border-2 border-gray-200 rounded-l-xl focus:border-green-500 focus:ring-4 focus:ring-green-100 transition-all duration-200 text-left"
                        placeholder="912345678" maxlength="9">
                </div>
                <p class="text-xs text-gray-500 mt-2">
                    <i class="fas fa-info-circle ml-1"></i>
                    أدخل رقم الهاتف بدون رمز الدولة (اختياري - للإرسال المباشر عبر واتساب)
                </p>
            </div>

            <!-- Cost Preview -->
            <div x-show="pricePerGb > 0 && dataLimit > 0" class="bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-gray-600">
                        <i class="fas fa-calculator ml-1"></i>
                        تكلفة البطاقة:
                    </span>
                    <span class="text-2xl font-bold text-green-700" x-text="Number(pricePerGb * dataLimit).toLocaleString() + ' ل.س'"></span>
                </div>
                <div class="text-sm text-gray-500 flex items-center justify-between">
                    <span x-text="dataLimit + ' GB × ' + Number(pricePerGb).toLocaleString() + ' ل.س'"></span>
                    <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">سيُخصم من رصيدك</span>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-4 border-t border-gray-100">
                <button type="button" @click="createCard()" :disabled="loading"
                    class="w-full px-8 py-4 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white rounded-xl font-bold shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-[1.02] flex items-center justify-center gap-2 disabled:opacity-50">
                    <template x-if="loading">
                        <i class="fas fa-spinner fa-spin"></i>
                    </template>
                    <template x-if="!loading">
                        <i class="fas fa-plus"></i>
                    </template>
                    <span x-text="loading ? 'جاري الإنشاء...' : 'إنشاء البطاقة'"></span>
                </button>
            </div>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
function hotspotForm() {
    return {
        routerId: '{{ $selectedRouter?->id ?? '' }}',
        username: '',
        password: '',
        showPassword: false,
        profile: '',
        profiles: @json($profiles ?? []),
        dataLimit: '',
        phone: '',
        loading: false,
        errorMessage: '',
        successMessage: '',
        createdCard: {},
        pricePerGb: {{ $selectedRouter?->price_per_gb ?? 0 }},
        resellerBalance: {{ auth()->user()->balance ?? 0 }},
        canViewPassword: {{ ($canViewPassword ?? false) ? 'true' : 'false' }},
        
        init() {
            // Auto-generate password if reseller can't view it
            if (!this.canViewPassword) {
                this.autoGeneratePassword();
            }
            
            // Update price per GB when router changes
            this.$watch('routerId', (value) => {
                if (value) {
                    const select = document.querySelector('select[x-model="routerId"]');
                    const option = select.querySelector(`option[value="${value}"]`);
                    this.pricePerGb = parseFloat(option?.dataset?.pricePerGb || 0);
                } else {
                    this.pricePerGb = 0;
                }
            });
        },
        
        autoGeneratePassword() {
            let pass = '';
            for (let i = 0; i < 6; i++) {
                pass += Math.floor(Math.random() * 10);
            }
            this.password = pass;
        },
        
        loadProfiles() {
            if (!this.routerId) {
                this.profiles = [];
                return;
            }
            fetch('/reseller-panel/hotspot/' + this.routerId + '/profiles')
                .then(r => r.json())
                .then(data => {
                    this.profiles = data.profiles || [];
                })
                .catch(() => {
                    this.profiles = [];
                });
        },
        
        generateCredentials() {
            let user = '';
            for (let i = 0; i < 6; i++) {
                user += Math.floor(Math.random() * 10);
            }
            this.username = user;
            
            let pass = '';
            for (let i = 0; i < 6; i++) {
                pass += Math.floor(Math.random() * 10);
            }
            this.password = pass;
            if (this.canViewPassword) {
                this.showPassword = true;
            }
        },
        
        createCard() {
            if (!this.routerId || !this.username || !this.password || !this.dataLimit) {
                this.errorMessage = 'يرجى ملء جميع الحقول المطلوبة';
                return;
            }
            
            this.loading = true;
            this.errorMessage = '';
            
            fetch('{{ route("reseller.hotspot.create") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    router_id: this.routerId,
                    username: this.username,
                    password: this.password,
                    profile: this.profile,
                    data_limit_gb: this.dataLimit,
                    phone: this.phone
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    this.successMessage = data.message;
                    this.createdCard = {
                        username: this.username,
                        password: this.password,
                        data_limit: this.dataLimit,
                        phone: this.phone,
                        sms_sent: data.sms_sent || false
                    };
                    // Update balance if returned
                    if (data.new_balance !== undefined) {
                        this.resellerBalance = data.new_balance;
                    }
                } else {
                    this.errorMessage = data.message || 'حدث خطأ';
                }
            })
            .catch(err => {
                this.errorMessage = 'حدث خطأ في الاتصال';
            })
            .finally(() => {
                this.loading = false;
            });
        },
        
        shareWhatsApp() {
            const passText = this.canViewPassword ? this.createdCard.password : '••••••';
            const text = `🌐 بيانات اتصال WiFi\n\n👤 اسم المستخدم: ${this.createdCard.username}\n🔑 كلمة المرور: ${passText}\n📊 حد البيانات: ${this.createdCard.data_limit} GB\n\n✨ شكراً لاستخدامكم خدماتنا`;
            window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank');
        },
        
        sendToCustomer() {
            const passText = this.canViewPassword ? this.createdCard.password : '••••••';
            const text = `🌐 بيانات اتصال WiFi\n\n👤 اسم المستخدم: ${this.createdCard.username}\n🔑 كلمة المرور: ${passText}\n📊 حد البيانات: ${this.createdCard.data_limit} GB\n\n✨ شكراً لاستخدامكم خدماتنا`;
            const phone = '963' + this.createdCard.phone.replace(/[^0-9]/g, '');
            window.open('https://wa.me/' + phone + '?text=' + encodeURIComponent(text), '_blank');
        },
        
        copyCardData() {
            const passText = this.canViewPassword ? this.createdCard.password : '••••••';
            const text = `🌐 بيانات اتصال WiFi\n\n👤 اسم المستخدم: ${this.createdCard.username}\n🔑 كلمة المرور: ${passText}\n📊 حد البيانات: ${this.createdCard.data_limit} GB\n\n✨ شكراً لاستخدامكم خدماتنا`;
            navigator.clipboard.writeText(text).then(() => {
                alert('تم نسخ البيانات!');
            });
        },
        
        sendSMS() {
            const passText = this.canViewPassword ? this.createdCard.password : '••••••';
            const text = `بيانات WiFi - المستخدم: ${this.createdCard.username} - كلمة المرور: ${passText} - حد البيانات: ${this.createdCard.data_limit} GB`;
            // Check if phone provided
            if (this.createdCard.phone && this.createdCard.phone.trim()) {
                const phone = '963' + this.createdCard.phone.replace(/[^0-9]/g, '');
                window.open('sms:+' + phone + '?body=' + encodeURIComponent(text), '_blank');
            } else {
                window.open('sms:?body=' + encodeURIComponent(text), '_blank');
            }
        },
        
        printCard() {
            const passText = this.canViewPassword ? this.createdCard.password : '••••••';
            const printContent = `
                <html dir="rtl">
                <head>
                    <title>بطاقة WiFi</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        .card { border: 2px solid #f97316; border-radius: 15px; padding: 20px; max-width: 300px; margin: auto; }
                        .header { background: linear-gradient(135deg, #f97316, #ef4444); color: white; padding: 15px; border-radius: 10px; text-align: center; margin-bottom: 20px; }
                        .header h2 { margin: 0; font-size: 18px; }
                        .info { margin: 15px 0; }
                        .label { color: #666; font-size: 12px; margin-bottom: 5px; }
                        .value { font-size: 18px; font-weight: bold; color: #333; background: #f5f5f5; padding: 10px; border-radius: 8px; text-align: center; letter-spacing: 2px; }
                        .footer { text-align: center; margin-top: 20px; font-size: 11px; color: #999; }
                    </style>
                </head>
                <body>
                    <div class="card">
                        <div class="header">
                            <h2>🌐 بطاقة اتصال WiFi</h2>
                        </div>
                        <div class="info">
                            <div class="label">👤 اسم المستخدم</div>
                            <div class="value">${this.createdCard.username}</div>
                        </div>
                        <div class="info">
                            <div class="label">🔑 كلمة المرور</div>
                            <div class="value">${passText}</div>
                        </div>
                        <div class="info">
                            <div class="label">📊 حد البيانات</div>
                            <div class="value">${this.createdCard.data_limit} GB</div>
                        </div>
                        <div class="footer">✨ شكراً لاستخدامكم خدماتنا</div>
                    </div>
                </body>
                </html>
            `;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.print();
        },
        
        resetForm() {
            this.username = '';
            this.password = '';
            this.profile = '';
            this.dataLimit = '';
            this.phone = '';
            this.successMessage = '';
            this.createdCard = {};
            // Auto-generate password again if reseller can't view it
            if (!this.canViewPassword) {
                this.autoGeneratePassword();
            }
        }
    }
}
</script>
@endpush
@endsection
