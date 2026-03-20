@extends('layouts.app')

@section('title', 'تعديل بطاقة هوتسبوت')

@push('styles')
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush

@section('content')
<div class="space-y-6" x-data="hotspotEditForm()" x-cloak>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-gray-800 flex items-center gap-3">
                <span class="w-12 h-12 bg-gradient-to-br from-orange-500 to-red-600 rounded-xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-edit text-white text-lg"></i>
                </span>
                تعديل بطاقة هوتسبوت
            </h1>
            <p class="text-gray-500 text-sm mt-1 mr-14">تعديل بيانات البطاقة: {{ $hotspot->username }}</p>
        </div>
        <a href="{{ route('hotspot.index') }}" 
           class="group flex items-center gap-2 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-all duration-300">
            <i class="fas fa-arrow-right"></i>
            <span class="font-medium">رجوع</span>
        </a>
    </div>

    <form action="{{ route('hotspot.update', $hotspot) }}" method="POST">
        @csrf
        @method('PUT')
        
        <!-- Simple Card -->
        <div class="bg-white rounded-2xl shadow-sm border-2 border-gray-100 overflow-hidden max-w-2xl mx-auto">
            <div class="bg-gradient-to-r from-orange-500 to-red-500 px-6 py-4">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <span class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-id-card"></i>
                    </span>
                    تعديل بيانات البطاقة
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

                @if(session('error'))
                    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                        <div class="flex items-center gap-2 text-red-600">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>{{ session('error') }}</span>
                        </div>
                    </div>
                @endif

                <!-- Current Info -->
                <div class="bg-gray-50 rounded-xl p-4 space-y-2">
                    <h3 class="text-sm font-bold text-gray-700 mb-3">
                        <i class="fas fa-info-circle text-blue-500 ml-1"></i>
                        معلومات البطاقة الحالية
                    </h3>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <span class="text-gray-500">اسم المستخدم:</span>
                            <span class="font-bold text-gray-800 mr-1">{{ $hotspot->username }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">الراوتر:</span>
                            <span class="font-bold text-gray-800 mr-1">{{ $hotspot->router->name ?? 'غير معروف' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">البروفايل الحالي:</span>
                            <span class="font-bold text-gray-800 mr-1">{{ $hotspot->profile ?? 'غير محدد' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">الحالة:</span>
                            <span class="px-2 py-1 rounded text-xs font-bold {{ $hotspot->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $hotspot->status === 'active' ? 'نشط' : 'غير نشط' }}
                            </span>
                        </div>
                        @if($hotspot->limit_bytes_total)
                        <div>
                            <span class="text-gray-500">حد البيانات:</span>
                            <span class="font-bold text-gray-800 mr-1">{{ number_format($hotspot->limit_bytes_total / 1073741824, 2) }} GB</span>
                        </div>
                        <div>
                            <span class="text-gray-500">المستهلك:</span>
                            <span class="font-bold text-gray-800 mr-1">{{ number_format($hotspot->total_bytes / 1073741824, 2) }} GB</span>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- New Password -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-lock text-orange-500 ml-1"></i>
                        كلمة المرور الجديدة
                    </label>
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'" name="password" x-model="password"
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-orange-500 focus:ring-4 focus:ring-orange-100 transition-all duration-200 pl-12"
                            placeholder="اتركه فارغاً للإبقاء على كلمة المرور الحالية">
                        <button type="button" @click="showPassword = !showPassword" 
                            class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <i :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-info-circle ml-1"></i>
                        اتركه فارغاً إذا لم ترغب بتغيير كلمة المرور
                    </p>
                </div>

                <!-- Profile -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-id-badge text-orange-500 ml-1"></i>
                        البروفايل
                    </label>
                    <select name="profile" x-model="profile"
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-orange-500 focus:ring-4 focus:ring-orange-100 transition-all duration-200">
                        <option value="">-- اختر البروفايل --</option>
                        <template x-for="p in profiles" :key="p">
                            <option :value="p" x-text="p" :selected="p === '{{ $hotspot->profile }}'"></option>
                        </template>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-info-circle ml-1"></i>
                        البروفايل الحالي: {{ $hotspot->profile ?? 'غير محدد' }}
                    </p>
                </div>

                <!-- Full Name -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-user text-orange-500 ml-1"></i>
                        الاسم الكامل
                    </label>
                    <input type="text" name="full_name" value="{{ old('full_name', $hotspot->full_name) }}"
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-orange-500 focus:ring-4 focus:ring-orange-100 transition-all duration-200"
                        placeholder="اسم العميل (اختياري)">
                </div>

                <!-- Phone -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-phone text-orange-500 ml-1"></i>
                        رقم الهاتف
                        <i class="fab fa-whatsapp text-green-500 mr-1"></i>
                    </label>
                    <input type="tel" name="phone" value="{{ old('phone', $hotspot->phone) }}"
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-orange-500 focus:ring-4 focus:ring-orange-100 transition-all duration-200"
                        placeholder="مثال: 0912345678">
                    <p class="text-xs text-green-600 mt-1">
                        <i class="fab fa-whatsapp ml-1"></i>
                        أضف رقم الهاتف لتفعيل إشعارات الواتساب وإمكانية تفقد الرصيد
                    </p>
                </div>

                <!-- Balance Check Link -->
                @if($hotspot->phone)
                <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-chart-pie text-white"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-bold text-green-800">رابط تفقد الرصيد</p>
                            <p class="text-xs text-green-600">يمكن للعميل تفقد رصيده من هذا الرابط</p>
                        </div>
                        <a href="https://megawifi.site/check-balance" target="_blank" 
                            class="px-3 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition">
                            <i class="fas fa-external-link-alt ml-1"></i>
                            فتح
                        </a>
                    </div>
                </div>
                @endif

                <!-- Submit Button -->
                <div class="pt-4 border-t border-gray-100 flex flex-col sm:flex-row gap-3">
                    <button type="submit" 
                        class="flex-1 px-8 py-4 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white rounded-xl font-bold shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-[1.02] flex items-center justify-center gap-2">
                        <i class="fas fa-save"></i>
                        حفظ التغييرات
                    </button>
                    <a href="{{ route('hotspot.index') }}" 
                        class="px-8 py-4 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl font-bold transition-all duration-200 flex items-center justify-center gap-2">
                        <i class="fas fa-times"></i>
                        إلغاء
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function hotspotEditForm() {
    return {
        password: '',
        showPassword: false,
        profile: '{{ $hotspot->profile ?? '' }}',
        profiles: [],
        
        init() {
            this.loadProfiles();
        },
        
        loadProfiles() {
            fetch('/hotspot/{{ $hotspot->router_id }}/profiles')
                .then(r => r.json())
                .then(data => {
                    this.profiles = data.profiles || [];
                })
                .catch(() => {
                    this.profiles = [];
                });
        }
    }
}
</script>
@endpush
@endsection
