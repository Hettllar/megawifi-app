@extends('layouts.app')

@section('title', 'إعدادات المزامنة')

@section('content')
<div class="max-w-4xl mx-auto" x-data="syncSettings()">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center gap-3 mb-2">
            <div class="p-3 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl shadow-lg">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">إعدادات المزامنة</h1>
                <p class="text-gray-500">التحكم في المزامنة التلقائية للراوترات</p>
            </div>
        </div>
    </div>

    <!-- Main Card -->
    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
        <!-- Status Banner -->
        <div class="p-6 border-b" :class="settings.auto_sync_enabled ? 'bg-gradient-to-r from-green-50 to-emerald-50 border-green-100' : 'bg-gradient-to-r from-red-50 to-orange-50 border-red-100'">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-xl" :class="settings.auto_sync_enabled ? 'bg-green-100' : 'bg-red-100'">
                        <svg x-show="settings.auto_sync_enabled" class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <svg x-show="!settings.auto_sync_enabled" class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold" :class="settings.auto_sync_enabled ? 'text-green-800' : 'text-red-800'">
                            <span x-text="settings.auto_sync_enabled ? 'المزامنة التلقائية مُفعّلة' : 'المزامنة التلقائية مُعطّلة'"></span>
                        </h3>
                        <p class="text-sm" :class="settings.auto_sync_enabled ? 'text-green-600' : 'text-red-600'">
                            <span x-show="settings.auto_sync_enabled">يتم مزامنة البيانات تلقائياً كل <span x-text="settings.sync_interval"></span> دقيقة</span>
                            <span x-show="!settings.auto_sync_enabled">لن يتم مزامنة البيانات تلقائياً</span>
                        </p>
                    </div>
                </div>
                <button @click="toggleSync()" 
                        class="px-6 py-3 rounded-xl font-bold text-white shadow-lg transition-all duration-300 transform hover:scale-105"
                        :class="settings.auto_sync_enabled ? 'bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700' : 'bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700'"
                        :disabled="loading">
                    <span x-show="!loading" x-text="settings.auto_sync_enabled ? 'إيقاف المزامنة' : 'تفعيل المزامنة'"></span>
                    <span x-show="loading" class="flex items-center gap-2">
                        <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        جاري...
                    </span>
                </button>
            </div>
        </div>

        <!-- Settings Form -->
        <div class="p-6 space-y-6">
            <!-- Sync Interval -->
            <div class="bg-gray-50 rounded-xl p-5 border border-gray-200">
                <div class="flex items-start gap-4">
                    <div class="p-3 bg-blue-100 rounded-lg">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <label class="block text-lg font-bold text-gray-800 mb-1">فترة مزامنة الترافيك</label>
                        <p class="text-sm text-gray-500 mb-4">المدة بين كل عملية مزامنة لبيانات الاستهلاك والجلسات</p>
                        <select x-model="settings.sync_interval" 
                                class="w-full md:w-64 px-4 py-3 bg-white border-2 border-gray-200 rounded-xl text-gray-700 font-medium focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                            <option value="1">كل دقيقة</option>
                            <option value="2">كل دقيقتين</option>
                            <option value="3">كل 3 دقائق</option>
                            <option value="5">كل 5 دقائق</option>
                            <option value="10">كل 10 دقائق</option>
                            <option value="15">كل 15 دقيقة</option>
                            <option value="30">كل 30 دقيقة</option>
                            <option value="60">كل ساعة</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Full Sync Interval -->
            <div class="bg-gray-50 rounded-xl p-5 border border-gray-200">
                <div class="flex items-start gap-4">
                    <div class="p-3 bg-indigo-100 rounded-lg">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <label class="block text-lg font-bold text-gray-800 mb-1">فترة المزامنة الكاملة</label>
                        <p class="text-sm text-gray-500 mb-4">المدة بين كل عملية مزامنة كاملة للمشتركين والخطط من الراوتر</p>
                        <select x-model="settings.full_sync_interval" 
                                class="w-full md:w-64 px-4 py-3 bg-white border-2 border-gray-200 rounded-xl text-gray-700 font-medium focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                            <option value="15">كل 15 دقيقة</option>
                            <option value="30">كل 30 دقيقة</option>
                            <option value="60">كل ساعة</option>
                            <option value="120">كل ساعتين</option>
                            <option value="180">كل 3 ساعات</option>
                            <option value="360">كل 6 ساعات</option>
                            <option value="720">كل 12 ساعة</option>
                            <option value="1440">كل 24 ساعة</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Toggle Refresh Section -->
            <div class="bg-gradient-to-r from-amber-50 to-orange-50 rounded-xl p-5 border border-amber-200">
                <div class="flex items-start gap-4">
                    <div class="p-3 bg-amber-100 rounded-lg">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-lg font-bold text-gray-800">تحديث الاستهلاك بـ Toggle</label>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" x-model="settings.toggle_refresh_enabled" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-amber-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                            </label>
                        </div>
                        <p class="text-sm text-amber-700 mb-4">
                            <i class="fas fa-info-circle ml-1"></i>
                            يقوم بتعطيل ثم تفعيل كل مشترك لتحديث بيانات الاستهلاك بدقة من الراوتر
                            <br><span class="text-amber-600 font-medium">⚠️ تحذير: سيقطع اتصال المشتركين لثانية واحدة</span>
                        </p>
                        <select x-model="settings.toggle_refresh_interval" 
                                :disabled="!settings.toggle_refresh_enabled"
                                class="w-full md:w-64 px-4 py-3 bg-white border-2 border-amber-200 rounded-xl text-gray-700 font-medium focus:border-amber-500 focus:ring-2 focus:ring-amber-200 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                            <option value="60">كل ساعة</option>
                            <option value="120">كل ساعتين</option>
                            <option value="180">كل 3 ساعات</option>
                            <option value="360">كل 6 ساعات</option>
                            <option value="720">كل 12 ساعة</option>
                            <option value="1440">كل 24 ساعة (يومياً)</option>
                        </select>
                        
                        <!-- Last Toggle Time -->
                        <div x-show="settings.toggle_refresh_enabled" class="mt-4 p-3 bg-white rounded-lg border border-amber-100">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-600">آخر تحديث Toggle:</span>
                                <span class="font-medium text-amber-700" x-text="settings.last_toggle_refresh || 'لم يتم بعد'"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="flex justify-end pt-4">
                <button @click="saveSettings()" 
                        class="px-8 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-bold rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-300 flex items-center gap-2"
                        :disabled="saving">
                    <svg x-show="!saving" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <svg x-show="saving" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-text="saving ? 'جاري الحفظ...' : 'حفظ الإعدادات'"></span>
                </button>
            </div>
        </div>

        <!-- Info Section -->
        <div class="p-6 bg-gradient-to-r from-blue-50 to-indigo-50 border-t border-blue-100">
            <div class="flex items-start gap-3">
                <svg class="w-6 h-6 text-blue-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="text-sm text-blue-800">
                    <p class="font-bold mb-1">ملاحظات هامة:</p>
                    <ul class="list-disc list-inside space-y-1 text-blue-700">
                        <li>مزامنة الترافيك تجمع بيانات الاستهلاك والجلسات النشطة</li>
                        <li>المزامنة الكاملة تحدّث قائمة المشتركين والخطط من الراوتر</li>
                        <li>فترات مزامنة أقصر = بيانات أدق لكن حمل أكبر على الشبكة</li>
                        <li>يُنصح بفترة 5 دقائق للترافيك وساعة للمزامنة الكاملة</li>
                        <li><strong class="text-amber-700">تحديث Toggle:</strong> يُحدّث الاستهلاك بدقة عالية لكنه يقطع اتصال المشتركين لحظياً</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function syncSettings() {
    return {
        settings: {
            auto_sync_enabled: {{ $settings['auto_sync_enabled'] ? 'true' : 'false' }},
            sync_interval: '{{ $settings['sync_interval'] }}',
            full_sync_interval: '{{ $settings['full_sync_interval'] }}',
            toggle_refresh_enabled: {{ ($settings['toggle_refresh_enabled'] ?? false) ? 'true' : 'false' }},
            toggle_refresh_interval: '{{ $settings['toggle_refresh_interval'] ?? '1440' }}',
            last_toggle_refresh: '{{ $settings['last_toggle_refresh'] ?? '' }}'
        },
        loading: false,
        saving: false,

        toggleSync() {
            this.loading = true;
            fetch('{{ route('settings.sync.toggle') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(res => res.json())
            .then(data => {
                this.settings.auto_sync_enabled = data.enabled;
                this.showNotification(data.message, data.enabled ? 'success' : 'warning');
            })
            .catch(err => {
                this.showNotification('حدث خطأ أثناء تغيير الحالة', 'error');
            })
            .finally(() => {
                this.loading = false;
            });
        },

        saveSettings() {
            this.saving = true;
            fetch('{{ route('settings.sync.update') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    auto_sync_enabled: this.settings.auto_sync_enabled ? 'true' : 'false',
                    sync_interval: this.settings.sync_interval,
                    full_sync_interval: this.settings.full_sync_interval,
                    toggle_refresh_enabled: this.settings.toggle_refresh_enabled ? 'true' : 'false',
                    toggle_refresh_interval: this.settings.toggle_refresh_interval
                })
            })
            .then(res => res.json())
            .then(data => {
                this.showNotification(data.message, 'success');
            })
            .catch(err => {
                this.showNotification('حدث خطأ أثناء حفظ الإعدادات', 'error');
            })
            .finally(() => {
                this.saving = false;
            });
        },

        showNotification(message, type) {
            // Simple notification
            const colors = {
                success: 'bg-green-500',
                warning: 'bg-yellow-500',
                error: 'bg-red-500'
            };
            
            const notification = document.createElement('div');
            notification.className = `fixed top-4 left-1/2 transform -translate-x-1/2 ${colors[type]} text-white px-6 py-3 rounded-xl shadow-lg z-50 transition-all duration-300`;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
    }
}
</script>
@endsection
