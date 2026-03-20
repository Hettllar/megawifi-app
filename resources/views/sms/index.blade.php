@extends('layouts.app')

@section('title', 'SMS مركزي - ' . $router->name)

@section('content')
<div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-6 py-4 sm:py-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4 sm:mb-6">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-sms text-green-500"></i>
                نظام SMS المركزي
            </h1>
            <p class="text-sm text-gray-500 mt-1">{{ $router->name }} - الإرسال عبر مودم TEST</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('sms.logs', $router) }}" class="inline-flex items-center px-3 py-2 bg-indigo-100 text-indigo-700 rounded-lg hover:bg-indigo-200 text-sm">
                <i class="fas fa-list ml-1"></i> السجلات
            </a>
            <a href="{{ route('routers.show', $router) }}" class="inline-flex items-center px-3 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">
                <i class="fas fa-arrow-right ml-1"></i> رجوع
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border-r-4 border-green-500 text-green-700 p-3 rounded-lg mb-4 text-sm">
            <i class="fas fa-check-circle ml-2"></i>{{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border-r-4 border-red-500 text-red-700 p-3 rounded-lg mb-4 text-sm">
            <i class="fas fa-exclamation-circle ml-2"></i>{{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3 mb-4">
        <div class="bg-gradient-to-br {{ ($gateway_status['connected'] ?? false) ? 'from-green-500 to-green-600' : 'from-red-500 to-red-600' }} rounded-xl p-3 text-white">
            <div class="text-xs opacity-90">بوابة SMS</div>
            <div class="text-lg font-bold mt-1">
                @if($gateway_status['connected'] ?? false)
                    <i class="fas fa-check-circle"></i> متصل
                @else
                    <i class="fas fa-times-circle"></i> غير متصل
                @endif
            </div>
            <div class="text-xs mt-1 opacity-80">{{ $gateway_status['device_name'] ?? 'مودم HiLink' }}</div>
        </div>
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-3 text-white">
            <div class="text-xs opacity-90">الرسائل المرسلة اليوم</div>
            <div class="text-2xl font-bold mt-1">{{ $stats['today']['total'] }}</div>
            <div class="text-xs mt-1 opacity-80">
                <span class="text-green-200">ناجح {{ $stats['today']['sent'] }}</span>
                <span class="text-red-200 mr-1">فشل {{ $stats['today']['failed'] }}</span>
            </div>
        </div>
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-3 text-white">
            <div class="text-xs opacity-90">الاسبوع</div>
            <div class="text-2xl font-bold mt-1">{{ $stats['week']['total'] }}</div>
            <div class="text-xs mt-1 opacity-80">
                <span class="text-green-200">ناجح {{ $stats['week']['sent'] }}</span>
                <span class="text-red-200 mr-1">فشل {{ $stats['week']['failed'] }}</span>
            </div>
        </div>
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-3 text-white">
            <div class="text-xs opacity-90">المتبقي اليوم</div>
            <div class="text-2xl font-bold mt-1">{{ $globalStats['today_remaining'] }}</div>
            <div class="text-xs mt-1 opacity-80">من {{ $globalStats['daily_limit'] }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 space-y-4">

            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 border-b">
                    <h3 class="font-semibold text-gray-800 flex items-center gap-2 text-sm">
                        <i class="fas fa-paper-plane text-green-500"></i> رسالة اختبارية
                    </h3>
                </div>
                <div class="p-4">
                    <form action="{{ route('sms.test', $router) }}" method="POST">
                        @csrf
                        <div class="flex gap-2 mb-2">
                            <input type="text" name="phone" required placeholder="0939XXXXXXX" dir="ltr"
                                class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm">
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium">
                                <i class="fas fa-paper-plane ml-1"></i> إرسال
                            </button>
                        </div>
                        <input type="text" name="message" placeholder="نص الرسالة (اختياري)" dir="rtl"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                    </form>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 border-b">
                    <h3 class="font-semibold text-gray-800 flex items-center gap-2 text-sm">
                        <i class="fas fa-user text-blue-500"></i> إرسال يدوي لمشترك
                    </h3>
                </div>
                <div class="p-4">
                    @if($subscribers->count() > 0)
                    <form id="manualSmsForm" method="POST">
                        @csrf
                        <select id="subscriberSelect" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm mb-2"
                            onchange="updateManualFormAction(this)">
                            <option value="">-- اختر مشترك --</option>
                            @foreach($subscribers as $sub)
                                <option value="{{ $sub->id }}" data-phone="{{ $sub->phone }}">
                                    {{ $sub->full_name ?: $sub->username }} ({{ $sub->phone }})
                                </option>
                            @endforeach
                        </select>
                        <textarea name="message" rows="2" required placeholder="اكتب رسالتك..." dir="rtl"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm mb-2 resize-none"></textarea>
                        <button type="submit" id="manualSendBtn" disabled
                            class="w-full py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium disabled:opacity-50">
                            <i class="fas fa-paper-plane ml-1"></i> إرسال للمشترك
                        </button>
                    </form>
                    @else
                        <p class="text-sm text-gray-500 text-center py-4">لا يوجد مشتركين بأرقام هواتف</p>
                    @endif
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 border-b">
                    <h3 class="font-semibold text-gray-800 flex items-center gap-2 text-sm">
                        <i class="fas fa-users text-purple-500"></i> إرسال جماعي
                    </h3>
                </div>
                <div class="p-4">
                    @if($subscribers->count() > 0)
                    <form action="{{ route('sms.send-bulk', $router) }}" method="POST"
                        onsubmit="return confirm('إرسال لـ ' + document.querySelectorAll('input[name=\'subscribers[]\']:checked').length + ' مشترك؟')">
                        @csrf
                        <div class="max-h-40 overflow-y-auto border border-gray-200 rounded-lg p-2 mb-2">
                            <label class="flex items-center gap-2 text-sm p-1 border-b mb-1 pb-1">
                                <input type="checkbox" id="selectAll" onchange="toggleAll(this)" class="rounded">
                                <span class="font-medium">تحديد الكل ({{ $subscribers->count() }})</span>
                            </label>
                            @foreach($subscribers as $sub)
                            <label class="flex items-center gap-2 text-sm p-1 hover:bg-gray-50">
                                <input type="checkbox" name="subscribers[]" value="{{ $sub->id }}" class="rounded sub-check">
                                <span>{{ $sub->full_name ?: $sub->username }}</span>
                                <span class="text-xs text-gray-400 mr-auto" dir="ltr">{{ $sub->phone }}</span>
                            </label>
                            @endforeach
                        </div>
                        <textarea name="message" rows="2" required placeholder="نص الرسالة الجماعية..." dir="rtl"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm mb-2 resize-none"></textarea>
                        <button type="submit" class="w-full py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm font-medium">
                            <i class="fas fa-paper-plane ml-1"></i> إرسال جماعي
                        </button>
                    </form>
                    @else
                        <p class="text-sm text-gray-500 text-center py-4">لا يوجد مشتركين بأرقام هواتف</p>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 border-b">
                        <h3 class="font-semibold text-gray-800 flex items-center gap-2 text-sm">
                            <i class="fas fa-broadcast-tower text-indigo-500"></i> إرسال للجميع
                        </h3>
                    </div>
                    <div class="p-4">
                        <form action="{{ route('sms.send-all', $router) }}" method="POST"
                            onsubmit="return confirm('إرسال لجميع المشتركين؟')">
                            @csrf
                            <textarea name="message" rows="2" required placeholder="رسالة لجميع المشتركين..." dir="rtl"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm mb-2 resize-none"></textarea>
                            <button type="submit" class="w-full py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">
                                <i class="fas fa-paper-plane ml-1"></i> إرسال للجميع
                            </button>
                        </form>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 border-b">
                        <h3 class="font-semibold text-gray-800 flex items-center gap-2 text-sm">
                            <i class="fas fa-bell text-orange-500"></i> التذكيرات
                        </h3>
                    </div>
                    <div class="p-4">
                        <p class="text-sm text-gray-600 mb-2">إرسال لمن تنتهي اشتراكاتهم خلال <strong>{{ $settings->reminder_days_before }}</strong> يوم</p>
                        <form action="{{ route('sms.reminders', $router) }}" method="POST" onsubmit="return confirm('إرسال التذكيرات؟')">
                            @csrf
                            <button type="submit" class="w-full py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 text-sm font-medium {{ !$settings->is_enabled ? 'opacity-50 cursor-not-allowed' : '' }}"
                                {{ !$settings->is_enabled ? 'disabled' : '' }}>
                                <i class="fas fa-bell ml-1"></i> إرسال التذكيرات
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 border-b">
                    <h3 class="font-semibold text-gray-800 flex items-center gap-2 text-sm">
                        <i class="fas fa-cog text-blue-500"></i> الإعدادات
                    </h3>
                </div>
                <div class="p-4">
                    <form action="{{ route('sms.settings.update', $router) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg mb-3">
                            <span class="font-medium text-gray-700 text-sm">تفعيل SMS</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="is_enabled" value="1" {{ $settings->is_enabled ? 'checked' : '' }} class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-blue-100 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:-translate-x-full peer-checked:bg-blue-600 after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg mb-3">
                            <span class="font-medium text-gray-700 text-sm">رسالة ترحيب للمشتركين الجدد</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="welcome_enabled" value="1" {{ $settings->welcome_enabled ? 'checked' : '' }} class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-green-100 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:-translate-x-full peer-checked:bg-green-600 after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">كود الدولة</label>
                                <input type="text" name="country_code" value="{{ $settings->country_code }}" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" placeholder="+963">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">التذكير قبل (يوم)</label>
                                <input type="number" name="reminder_days_before" value="{{ $settings->reminder_days_before }}" min="1" max="30" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">وقت الإرسال</label>
                                <input type="time" name="send_time" value="{{ $settings->send_time }}" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                            </div>
                            <div class="flex items-end gap-2">
                                <label class="flex items-center gap-1 text-sm">
                                    <input type="checkbox" name="send_on_expiry" value="1" {{ $settings->send_on_expiry ? 'checked' : '' }} class="rounded">
                                    يوم الانتهاء
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="block text-xs font-medium text-gray-600 mb-1">نص رسالة التذكير</label>
                            <textarea name="reminder_message" rows="2" dir="rtl" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm resize-none">{{ $settings->reminder_message }}</textarea>
                            <div class="flex flex-wrap gap-1 mt-1">
                                @foreach(['{name}','{username}','{date}','{days}','{service}'] as $var)
                                <span class="text-xs bg-gray-100 px-2 py-0.5 rounded cursor-pointer hover:bg-gray-200" onclick="insertVar('reminder_message','{{ $var }}')">{{ $var }}</span>
                                @endforeach
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="block text-xs font-medium text-gray-600 mb-1">نص رسالة الترحيب</label>
                            <textarea name="welcome_message" rows="2" dir="rtl" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm resize-none">{{ $settings->welcome_message ?? \App\Models\SmsSettings::getDefaultWelcomeMessage() }}</textarea>
                            <div class="flex flex-wrap gap-1 mt-1">
                                @foreach(['{name}','{username}','{service}','{password}','{router}'] as $var)
                                <span class="text-xs bg-gray-100 px-2 py-0.5 rounded cursor-pointer hover:bg-gray-200" onclick="insertVar('welcome_message','{{ $var }}')">{{ $var }}</span>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex items-center gap-2 mb-3">
                            <input type="checkbox" name="send_after_expiry" value="1" {{ $settings->send_after_expiry ? 'checked' : '' }} class="rounded" id="afterCheck"
                                onchange="document.getElementById('afterDays').classList.toggle('hidden', !this.checked)">
                            <label for="afterCheck" class="text-sm">إرسال بعد الانتهاء بـ</label>
                            <input type="number" name="after_expiry_days" id="afterDays" value="{{ $settings->after_expiry_days ?? 1 }}" min="1" max="7"
                                class="w-14 px-2 py-1 border border-gray-200 rounded text-sm {{ $settings->send_after_expiry ? '' : 'hidden' }}">
                            <span class="text-sm text-gray-500">يوم</span>
                        </div>

                        <button type="submit" class="w-full py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                            <i class="fas fa-save ml-1"></i> حفظ الإعدادات
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm overflow-hidden sticky top-4">
                <div class="px-4 py-3 bg-gray-50 border-b flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800 flex items-center gap-2 text-sm">
                        <i class="fas fa-history text-gray-500"></i> آخر الرسائل
                    </h3>
                    <a href="{{ route('sms.logs', $router) }}" class="text-xs text-blue-600 hover:underline">عرض الكل</a>
                </div>
                <div class="divide-y divide-gray-100 max-h-[600px] overflow-y-auto">
                    @forelse($logs->take(20) as $log)
                    <div class="p-3 hover:bg-gray-50">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1">
                                    <span class="font-mono text-xs text-gray-800" dir="ltr">{{ $log->phone_number }}</span>
                                    <span class="text-[10px] px-1 py-0.5 rounded {{ $log->type === 'welcome' ? 'bg-green-100 text-green-700' : ($log->type === 'reminder' ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-600') }}">{{ $log->type_text }}</span>
                                </div>
                                <p class="text-xs text-gray-500 truncate mt-0.5">{{ Str::limit($log->message, 35) }}</p>
                            </div>
                            <div class="flex flex-col items-end">
                                <span class="text-[10px] px-1.5 py-0.5 rounded-full {{ $log->getStatusBadgeClass() }}">{{ $log->status_text }}</span>
                                <span class="text-[10px] text-gray-400 mt-0.5">{{ $log->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="p-8 text-center text-gray-400">
                        <i class="fas fa-inbox text-3xl mb-2"></i>
                        <p class="text-sm">لا توجد رسائل</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateManualFormAction(select) {
    const form = document.getElementById('manualSmsForm');
    const btn = document.getElementById('manualSendBtn');
    if (select.value) {
        form.action = '{{ url("routers/{$router->id}/sms/subscriber") }}/' + select.value;
        btn.disabled = false;
    } else {
        btn.disabled = true;
    }
}
function toggleAll(el) {
    document.querySelectorAll('.sub-check').forEach(c => c.checked = el.checked);
}
function insertVar(field, v) {
    const ta = document.querySelector('textarea[name="'+field+'"]');
    const s = ta.selectionStart, e = ta.selectionEnd;
    ta.value = ta.value.substring(0,s) + v + ta.value.substring(e);
    ta.focus();
    ta.selectionStart = ta.selectionEnd = s + v.length;
}
</script>
@endsection
