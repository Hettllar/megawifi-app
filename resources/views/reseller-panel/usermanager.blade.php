@extends('layouts.app')

@section('title', 'تجديد اشتراكات UserManager')

@push('styles')
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush

@section('content')
<div class="space-y-6" x-data="renewForm()" x-cloak>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-gray-800 flex items-center gap-3">
                <span class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-700 rounded-xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-sync-alt text-white text-lg"></i>
                </span>
                تجديد اشتراكات UserManager
            </h1>
            <p class="text-gray-500 text-sm mt-1 mr-14">تجديد اشتراكات العملاء</p>
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
        <p class="text-amber-600">لم يتم تخصيص صلاحية تجديد اشتراكات UserManager لك على أي راوتر</p>
    </div>
    @else

    <!-- Reseller Balance Card -->
    <div class="bg-gradient-to-r from-green-500 to-emerald-600 rounded-xl p-4 shadow-lg">
        <div class="flex items-center justify-between text-white">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                    <i class="fas fa-wallet text-xl"></i>
                </div>
                <div>
                    <p class="text-white/80 text-sm">رصيدك الحالي</p>
                    <p class="text-2xl font-bold" x-text="Number(resellerBalance).toLocaleString() + ' ل.س'"></p>
                </div>
            </div>
            <i class="fas fa-coins text-4xl text-white/30"></i>
        </div>
    </div>

    <!-- Router Selection -->
    <div class="bg-white rounded-xl shadow-sm border p-4">
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <select name="router_id" onchange="this.form.submit()"
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition">
                    @foreach($routers as $router)
                        <option value="{{ $router->id }}" {{ $selectedRouter?->id == $router->id ? 'selected' : '' }}>
                            {{ $router->name }} ({{ $router->ip_address }})
                        </option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    @if($selectedRouter)
    <!-- Search Box -->
    <div class="bg-white rounded-xl shadow-sm border p-4">
        <div class="flex gap-3">
            <div class="flex-1 relative">
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" x-model="searchQuery" @input.debounce.300ms="searchSubscribers()" 
                    placeholder="🔍 بحث بالاسم أو اسم المستخدم أو رقم الهاتف..."
                    class="w-full pr-10 pl-4 py-3 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition text-right"
                    dir="rtl">
            </div>
        </div>
        
        <!-- Search Results -->
        <div x-show="searchResults.length > 0" class="mt-4 border rounded-xl overflow-hidden">
            <template x-for="sub in searchResults" :key="sub.id">
                <div class="p-4 hover:bg-purple-50 border-b last:border-0 cursor-pointer flex items-center justify-between transition" @click="selectSubscriber(sub)">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center">
                            <i class="fas fa-user text-purple-600"></i>
                        </div>
                        <div>
                            <p class="font-bold text-gray-800" x-text="sub.full_name || sub.username"></p>
                            <p class="text-xs text-gray-500" x-text="sub.username"></p>
                            <p class="text-xs text-blue-600 flex items-center gap-1" x-show="sub.phone">
                                <i class="fas fa-phone text-[10px]"></i>
                                <span x-text="sub.phone" dir="ltr"></span>
                            </p>
                        </div>
                    </div>
                    <div class="text-left">
                        <p class="text-sm text-gray-600" x-text="sub.profile"></p>
                        <p class="text-xs" :class="sub.status === 'active' ? 'text-green-600' : 'text-red-600'" x-text="sub.status === 'active' ? 'نشط' : 'منتهي'"></p>
                    </div>
                </div>
            </template>
        </div>
        
        <!-- No Results -->
        <div x-show="searchQuery.length >= 2 && searchResults.length === 0" class="mt-4 p-4 bg-gray-50 rounded-xl text-center text-gray-500">
            <i class="fas fa-search text-2xl mb-2 opacity-50"></i>
            <p>لا توجد نتائج للبحث</p>
        </div>
    </div>

    <!-- Renew Modal -->
    <div x-show="selectedSubscriber" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
         @click.self="loading = false; selectedSubscriber = null; errorMessage = ''; successMessage = ''">
        
        <div x-show="selectedSubscriber"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-90"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-90"
             class="bg-white rounded-2xl shadow-2xl border-2 border-purple-100 overflow-hidden w-full max-w-md max-h-[90vh] overflow-y-auto">
        <div class="bg-gradient-to-r from-purple-500 to-purple-700 px-6 py-4 sticky top-0">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <span class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-sync-alt"></i>
                    </span>
                    تجديد الاشتراك
                </h2>
                <button @click="loading = false; selectedSubscriber = null; errorMessage = ''; successMessage = ''" class="text-white/80 hover:text-white transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        <div class="p-6 space-y-5">
            <!-- Subscriber Info -->
            <div class="bg-purple-50 rounded-xl p-4">
                <h3 class="text-sm font-bold text-purple-700 mb-3">
                    <i class="fas fa-user ml-1"></i>
                    معلومات المشترك
                </h3>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <span class="text-gray-500">اسم المستخدم:</span>
                        <span class="font-bold text-gray-800 mr-1" x-text="selectedSubscriber?.username"></span>
                    </div>
                    <div>
                        <span class="text-gray-500">الاسم:</span>
                        <span class="font-bold text-gray-800 mr-1" x-text="selectedSubscriber?.full_name || '-'"></span>
                    </div>
                    <div>
                        <span class="text-gray-500">الباقة الحالية:</span>
                        <span class="font-bold text-gray-800 mr-1" x-text="selectedSubscriber?.profile || '-'"></span>
                    </div>
                    <div>
                        <span class="text-gray-500">حد البيانات:</span>
                        <span class="font-bold text-purple-700 mr-1" x-text="(selectedSubscriber?.data_limit_gb > 0 ? selectedSubscriber?.data_limit_gb + ' GB' : 'غير محدود')"></span>
                    </div>
                    <div>
                        <span class="text-gray-500">تاريخ الانتهاء:</span>
                        <span class="font-bold mr-1" :class="isExpired(selectedSubscriber?.expiration_date) ? 'text-red-600' : 'text-green-600'" x-text="selectedSubscriber?.expiration_date || '-'"></span>
                    </div>
                </div>
                
                <!-- Subscription Price -->
                <div x-show="parseFloat(selectedSubscriber?.subscription_price || 0) > 0" class="mt-3 pt-3 border-t border-purple-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">سعر الباقة (سيخصم من رصيدك):</span>
                        <span class="text-xl font-bold text-green-600" x-text="Number(selectedSubscriber?.subscription_price || 0).toLocaleString() + ' ل.س'"></span>
                    </div>
                    <div class="flex items-center justify-between" x-show="parseFloat(selectedSubscriber?.remaining_amount || 0) > 0">
                        <span class="text-gray-500">المبلغ المترتب على العميل:</span>
                        <span class="text-lg font-bold text-orange-600" x-text="Number(selectedSubscriber?.remaining_amount || 0).toLocaleString() + ' ل.س'"></span>
                    </div>
                </div>
                
                <!-- Warning: No price set -->
                <div x-show="!selectedSubscriber?.subscription_price || parseFloat(selectedSubscriber?.subscription_price || 0) <= 0" class="mt-3 pt-3 border-t border-red-200">
                    <div class="flex items-center gap-2 text-red-600">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span class="text-sm">لا يوجد سعر محدد للباقة. يرجى التواصل مع المدير.</span>
                    </div>
                </div>
            </div>

            <!-- Error/Success Messages -->
            <div x-show="errorMessage" x-transition class="bg-red-50 border border-red-200 rounded-xl p-4">
                <div class="flex items-center gap-2 text-red-600">
                    <i class="fas fa-exclamation-circle"></i>
                    <span x-text="errorMessage"></span>
                </div>
            </div>
            
            <div x-show="successMessage" x-transition class="bg-green-50 border border-green-200 rounded-xl p-4">
                <div class="flex items-center gap-2 text-green-600">
                    <i class="fas fa-check-circle"></i>
                    <span x-text="successMessage"></span>
                </div>
            </div>

            <!-- Info: سيتم استخدام نفس حد البيانات المحدد من قبل المدير -->
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                <div class="flex items-center gap-2 text-blue-700">
                    <i class="fas fa-info-circle"></i>
                    <span class="text-sm">سيتم التجديد بنفس الباقة وحد البيانات المحدد من قبل المدير مع تصفير الاستهلاك</span>
                </div>
            </div>

            <!-- Months Selection -->
                        <!-- Phone Number for SMS -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-sms text-blue-500 ml-1"></i>
                    رقم هاتف العميل (لإرسال إشعار SMS)
                </label>
                <div class="relative flex">
                    <span class="inline-flex items-center px-3 py-3 bg-blue-100 text-blue-700 font-bold border-2 border-l-0 border-gray-200 rounded-r-xl text-sm">
                        963+
                    </span>
                    <input type="tel" x-model="subscriberPhone" dir="ltr"
                        autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false"
                        class="flex-1 px-4 py-3 border-2 border-gray-200 rounded-l-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 text-left"
                        placeholder="912345678" maxlength="9">
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    <i class="fas fa-info-circle ml-1"></i>
                    سيتم إرسال إشعار التجديد للعميل عبر SMS (اختياري)
                </p>
            </div>
<div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-calendar-plus text-purple-500 ml-1"></i>
                    مدة التجديد
                </label>
                <div class="grid grid-cols-4 gap-2">
                    <button type="button" @click="months = 1" 
                        :class="months === 1 ? 'bg-purple-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                        class="py-3 rounded-xl font-bold transition">
                        شهر
                    </button>
                    <button type="button" @click="months = 3" 
                        :class="months === 3 ? 'bg-purple-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                        class="py-3 rounded-xl font-bold transition">
                        3 أشهر
                    </button>
                    <button type="button" @click="months = 6" 
                        :class="months === 6 ? 'bg-purple-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                        class="py-3 rounded-xl font-bold transition">
                        6 أشهر
                    </button>
                    <button type="button" @click="months = 12" 
                        :class="months === 12 ? 'bg-purple-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                        class="py-3 rounded-xl font-bold transition">
                        سنة
                    </button>
                </div>
            </div>

            <!-- Balance Warning -->
            <div x-show="parseFloat(selectedSubscriber?.subscription_price || 0) > resellerBalance" x-transition class="bg-red-50 border border-red-200 rounded-xl p-4">
                <div class="flex items-center gap-2 text-red-600">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span class="text-sm">رصيدك غير كافي للتجديد! المطلوب: <span x-text="Number(selectedSubscriber?.subscription_price || 0).toLocaleString()"></span> ل.س - رصيدك: <span x-text="Number(resellerBalance).toLocaleString()"></span> ل.س</span>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="pt-4 border-t border-gray-100 flex gap-3">
                <button type="button" @click="renewSubscription()" :disabled="loading || parseFloat(selectedSubscriber?.subscription_price || 0) <= 0 || parseFloat(selectedSubscriber?.subscription_price || 0) > resellerBalance"
                    class="flex-1 px-6 py-4 bg-gradient-to-r from-purple-500 to-purple-700 hover:from-purple-600 hover:to-purple-800 text-white rounded-xl font-bold shadow-lg hover:shadow-xl transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                    <template x-if="loading">
                        <i class="fas fa-spinner fa-spin"></i>
                    </template>
                    <template x-if="!loading">
                        <i class="fas fa-sync-alt"></i>
                    </template>
                    <span x-text="loading ? 'جاري التجديد...' : 'تجديد الاشتراك'"></span>
                </button>
                <button type="button" @click="loading = false; selectedSubscriber = null; errorMessage = ''; successMessage = ''"
                    class="px-6 py-4 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl font-bold transition">
                    إلغاء
                </button>
            </div>
        </div>
        </div>
    </div>

    <!-- Subscribers List -->
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b">
            <h2 class="text-lg font-bold text-gray-800">
                <i class="fas fa-users text-purple-600 ml-2"></i>
                المشتركين ({{ $subscribers->count() }})
            </h2>
        </div>
        
        @if($subscribers->isEmpty())
            <div class="p-8 text-center text-gray-500">
                <i class="fas fa-users-slash text-4xl mb-3 opacity-50"></i>
                <p>لا يوجد مشتركين</p>
            </div>
        @else
            <div class="divide-y max-h-[600px] overflow-y-auto">
                @foreach($subscribers as $sub)
                @php
                    $isExpired = ($sub->expiration_date && $sub->expiration_date->isPast()) || 
                                 (strtolower($sub->profile) === '1k') || 
                                 (stripos($sub->profile, '1k') !== false);
                @endphp
                <div class="p-4 hover:bg-gray-50 flex items-center justify-between cursor-pointer" 
                    @click="selectSubscriber({{ json_encode(['id' => $sub->id, 'username' => $sub->username, 'full_name' => $sub->full_name, 'phone' => $sub->phone, 'profile' => $sub->profile, 'expiration_date' => $sub->expiration_date?->format('Y-m-d'), 'status' => $sub->status, 'subscription_price' => $sub->subscription_price, 'remaining_amount' => $sub->remaining_amount, 'is_paid' => $sub->is_paid, 'data_limit_gb' => $sub->data_limit_gb]) }})">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full {{ $isExpired ? 'bg-red-100' : 'bg-green-100' }} flex items-center justify-center">
                            <i class="fas fa-user {{ $isExpired ? 'text-red-600' : 'text-green-600' }}"></i>
                        </div>
                        <div>
                            <p class="font-bold text-gray-800">{{ $sub->full_name ?? $sub->username }}</p>
                            <p class="text-xs text-gray-500">{{ $sub->username }}</p>
                            @if($sub->phone)
                            <p class="text-xs text-blue-600 flex items-center gap-1 mt-0.5">
                                <i class="fas fa-phone text-[10px]"></i>
                                <span dir="ltr">{{ $sub->phone }}</span>
                            </p>
                            @endif
                        </div>
                    </div>
                    <div class="text-left">
                        @if($isExpired)
                            <p class="text-sm text-red-600 font-bold">منتهي</p>
                        @else
                            <p class="text-sm text-purple-600 font-medium">{{ $sub->profile }}</p>
                        @endif
                        <p class="text-xs {{ $isExpired ? 'text-red-600' : 'text-gray-500' }}">
                            {{ $sub->expiration_date ? $sub->expiration_date->format('Y-m-d') : '-' }}
                        </p>
                    </div>
                    <button class="px-4 py-2 {{ $isExpired ? 'bg-red-100 hover:bg-red-200 text-red-700' : 'bg-purple-100 hover:bg-purple-200 text-purple-700' }} rounded-lg text-sm font-medium transition">
                        <i class="fas fa-sync-alt ml-1"></i> تجديد
                    </button>
                </div>
                @endforeach
            </div>
        @endif
    </div>
    @endif
    @endif
</div>

@push('scripts')
<script>
function renewForm() {
    return {
        routerId: '{{ $selectedRouter?->id ?? '' }}',
        searchQuery: '',
        searchResults: [],
        selectedSubscriber: null,
        subscriberPhone: '',
        months: 1,
        loading: false,
        errorMessage: '',
        successMessage: '',
        resellerBalance: {{ $resellerBalance ?? 0 }},
        
        searchSubscribers() {
            if (!this.searchQuery || this.searchQuery.length < 2) {
                this.searchResults = [];
                return;
            }
            
            fetch('{{ route("reseller.usermanager.search") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    router_id: this.routerId,
                    search: this.searchQuery
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    this.searchResults = data.subscribers;
                    if (data.reseller_balance !== undefined) {
                        this.resellerBalance = data.reseller_balance;
                    }
                }
            });
        },
        
        selectSubscriber(sub) {
            this.loading = false;
            this.selectedSubscriber = sub;
            this.subscriberPhone = (sub.phone || '').replace(/^\+?963/, '');
            this.searchResults = [];
            this.searchQuery = '';
            this.errorMessage = '';
            this.successMessage = '';
        },
        
        isExpired(date) {
            if (!date) return true;
            return new Date(date) < new Date();
        },
        
        renewSubscription() {
            if (!this.selectedSubscriber) return;
            
            this.loading = true;
            this.errorMessage = '';
            this.successMessage = '';
            
            fetch('{{ route("reseller.usermanager.renew") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    subscriber_id: this.selectedSubscriber.id,
                    months: this.months,
                    phone: this.subscriberPhone
                })
            })
            .then(r => r.json())
            .then(data => {
                this.loading = false;
                if (data.success) {
                    this.successMessage = data.message;
                    if (data.new_balance !== undefined) {
                        this.resellerBalance = data.new_balance;
                    }
                    setTimeout(() => {
                        this.loading = false;
                        this.selectedSubscriber = null;
                        this.successMessage = '';
                    }, 2000);
                } else {
                    this.errorMessage = data.message || 'حدث خطأ';
                }
            })
            .catch(err => {
                this.loading = false;
                this.errorMessage = 'حدث خطأ في الاتصال';
            });
        }
    }
}
</script>
@endpush
@endsection
