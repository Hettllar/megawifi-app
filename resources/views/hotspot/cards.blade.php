@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 py-8" dir="rtl">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {{-- Header --}}
        <div class="mb-8">
            <div class="flex items-center gap-4 mb-4">
                <a href="{{ route('hotspot.index') }}" 
                   class="p-2 bg-white/10 hover:bg-white/20 rounded-xl transition-all duration-300">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-white">مولد بطاقات الهوتسبوت</h1>
                    <p class="text-purple-200 mt-1">إنشاء وطباعة بطاقات هوتسبوت جديدة</p>
                </div>
            </div>
        </div>

        {{-- Error Messages --}}
        @if($errors->any())
            <div class="mb-6 bg-red-500/20 border border-red-500/50 rounded-2xl p-4">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-red-200">{{ $errors->first() }}</span>
                </div>
            </div>
        @endif

        {{-- Main Form Card --}}
        <div class="bg-white/10 backdrop-blur-xl rounded-3xl border border-white/20 overflow-hidden">
            <form action="{{ route('hotspot.cards.generate') }}" method="POST" id="cardGeneratorForm">
                @csrf

                {{-- Router Selection --}}
                <div class="p-6 border-b border-white/10">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-2 bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-white">اختيار الراوتر</h2>
                    </div>

                    <select name="router_id" id="router_id" required
                            class="w-full bg-slate-800 border border-white/20 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-300">
                        <option value="" class="bg-slate-800 text-white">-- اختر الراوتر --</option>
                        @foreach($routers as $router)
                            <option value="{{ $router->id }}" class="bg-slate-800 text-white">
                                {{ $router->name }} ({{ $router->ip_address }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Card Settings --}}
                <div class="p-6 border-b border-white/10">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2 bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-white">إعدادات البطاقات</h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Number of Cards --}}
                        <div>
                            <label class="block text-purple-200 text-sm font-medium mb-2">عدد البطاقات</label>
                            <input type="number" name="count" value="75" min="1" max="500" required
                                   class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-300">
                            <p class="text-gray-400 text-xs mt-1">الحد الأقصى 500 بطاقة (75 بطاقة لكل صفحة)</p>
                        </div>

                        {{-- Profile --}}
                        <div x-data="{ profiles: [] }" x-init="
                            fetch('/hotspot/' + document.getElementById('router_id').value + '/profiles')
                                .then(r => r.json())
                                .then(data => profiles = data.profiles || []);
                            document.getElementById('router_id').addEventListener('change', function() {
                                fetch('/hotspot/' + this.value + '/profiles')
                                    .then(r => r.json())
                                    .then(data => profiles = data.profiles || []);
                            });
                        ">
                            <label class="block text-purple-200 text-sm font-medium mb-2">بروفايل الهوتسبوت</label>
                            <select name="profile" 
                                    class="w-full bg-slate-800 border border-white/20 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-300">
                                <option value="" class="bg-slate-800 text-white">-- اختر البروفايل --</option>
                                <template x-for="profile in profiles" :key="profile">
                                    <option :value="profile" x-text="profile" class="bg-slate-800 text-white"></option>
                                </template>
                            </select>
                        </div>

                        {{-- Data Limit (GB) --}}
                        <div class="md:col-span-2">
                            <label class="block text-purple-200 text-sm font-medium mb-2">حد الاستهلاك (جيجابايت) <span class="text-red-400">*</span></label>
                            <input type="number" name="data_limit_gb" id="data_limit_gb" value="" min="0.1" max="1000" step="any" placeholder="مثال: 5" required
                                   class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-300">
                            <p class="text-gray-400 text-xs mt-1">حدد حجم الاستهلاك بالجيجابايت</p>
                        </div>
                    </div>
                </div>

                {{-- Card Content Customization - Collapsible --}}
                <div class="border-b border-white/10" x-data="{ open: false }">
                    <button type="button" @click="open = !open" class="w-full p-6 flex items-center justify-between hover:bg-white/5 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-gradient-to-br from-cyan-500 to-blue-500 rounded-xl">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </div>
                            <div class="text-right">
                                <h2 class="text-xl font-bold text-white">محتوى البطاقة</h2>
                                <p class="text-purple-300 text-xs">اسم الشبكة، طول البيانات، أحجام الخطوط</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-green-400 text-xs" id="savedIndicator" style="display: none;">✓ محفوظ</span>
                            <svg class="w-5 h-5 text-white transition-transform duration-300" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </button>
                    
                    <div x-show="open" x-collapse class="px-6 pb-6">
                        {{-- Save/Load Template Buttons --}}
                        <div class="flex gap-2 mb-4">
                            <button type="button" onclick="saveCardTemplate()" class="flex-1 bg-green-600 hover:bg-green-500 text-white text-sm font-medium py-2 px-4 rounded-xl transition-colors flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                                </svg>
                                حفظ التنسيق
                            </button>
                            <button type="button" onclick="loadCardTemplate()" class="flex-1 bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium py-2 px-4 rounded-xl transition-colors flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                                تحميل التنسيق
                            </button>
                            <button type="button" onclick="clearCardTemplate()" class="bg-red-600/50 hover:bg-red-500 text-white text-sm font-medium py-2 px-3 rounded-xl transition-colors" title="مسح التنسيق المحفوظ">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- Username Length --}}
                            <div>
                                <label class="block text-purple-200 text-sm font-medium mb-2">طول اسم المستخدم (أرقام)</label>
                                <input type="number" name="username_length" id="username_length" value="6" min="3" max="12" required
                                       class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-300 template-field">
                                <p class="text-gray-400 text-xs mt-1">من 3 إلى 12 رقم</p>
                            </div>

                            {{-- Password Length --}}
                            <div>
                                <label class="block text-purple-200 text-sm font-medium mb-2">طول كلمة المرور (أرقام)</label>
                                <input type="number" name="password_length" id="password_length" value="3" min="3" max="12" required
                                       class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-300 template-field">
                                <p class="text-gray-400 text-xs mt-1">من 3 إلى 12 رقم</p>
                            </div>
                            
                            {{-- Network Name --}}
                            <div>
                                <label class="block text-purple-200 text-sm font-medium mb-2">اسم الشبكة (WiFi Name)</label>
                                <div class="flex gap-2">
                                    <input type="text" name="network_name" id="network_name" maxlength="30" placeholder="مثال: MegaWiFi"
                                           class="flex-1 bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-300 template-field">
                                    <input type="color" name="network_color" id="networkColor" value="#FFFFFF"
                                           class="w-12 h-12 rounded-xl cursor-pointer border-2 border-white/20 hover:border-purple-400 transition-colors template-field"
                                           title="لون اسم الشبكة">
                                </div>
                                <p class="text-gray-400 text-xs mt-1">يظهر في أعلى البطاقة (اختر اللون من المربع)</p>
                            </div>

                            {{-- Phone Number --}}
                            <div>
                                <label class="block text-purple-200 text-sm font-medium mb-2">رقم الهاتف / التواصل</label>
                                <input type="text" name="phone_number" id="phone_number" maxlength="20" placeholder="مثال: 0912345678"
                                       class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-300 template-field">
                                <p class="text-gray-400 text-xs mt-1">يظهر في أسفل البطاقة</p>
                            </div>

                            {{-- Show Data Limit --}}
                            <div class="md:col-span-2">
                                <label class="flex items-center gap-3 cursor-pointer group">
                                    <input type="checkbox" name="show_data_limit" id="show_data_limit" value="1" checked
                                           class="w-5 h-5 rounded border-white/20 bg-white/10 text-purple-500 focus:ring-purple-500 focus:ring-offset-0 template-field">
                                    <span class="text-white group-hover:text-purple-200 transition-colors">إظهار حجم الباقة على البطاقة (مثل: 10G)</span>
                                </label>
                            </div>
                        </div>
                        
                        {{-- Font Sizes Section --}}
                        <div class="mt-6 pt-6 border-t border-white/10">
                            <h3 class="text-white font-medium mb-4 flex items-center gap-2">
                                <svg class="w-4 h-4 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16"/>
                                </svg>
                                أحجام الخطوط
                            </h3>
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                                <div>
                                    <label class="block text-purple-200 text-xs font-medium mb-1">اسم الشبكة</label>
                                    <input type="number" name="font_network" id="font_network" value="8" min="6" max="24"
                                           class="w-full bg-white/10 border border-white/20 rounded-lg px-2 py-1.5 text-white text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent template-field">
                                </div>
                                <div>
                                    <label class="block text-purple-200 text-xs font-medium mb-1">حجم الباقة</label>
                                    <input type="number" name="font_data_limit" id="font_data_limit" value="10" min="6" max="28"
                                           class="w-full bg-white/10 border border-white/20 rounded-lg px-2 py-1.5 text-white text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent template-field">
                                </div>
                                <div>
                                    <label class="block text-purple-200 text-xs font-medium mb-1">اسم المستخدم</label>
                                    <input type="number" name="font_username" id="font_username" value="10" min="6" max="24"
                                           class="w-full bg-white/10 border border-white/20 rounded-lg px-2 py-1.5 text-white text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent template-field">
                                </div>
                                <div>
                                    <label class="block text-purple-200 text-xs font-medium mb-1">كلمة المرور</label>
                                    <input type="number" name="font_password" id="font_password" value="10" min="6" max="24"
                                           class="w-full bg-white/10 border border-white/20 rounded-lg px-2 py-1.5 text-white text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent template-field">
                                </div>
                                <div>
                                    <label class="block text-purple-200 text-xs font-medium mb-1">رقم الهاتف</label>
                                    <input type="number" name="font_phone" id="font_phone" value="6" min="4" max="18"
                                           class="w-full bg-white/10 border border-white/20 rounded-lg px-2 py-1.5 text-white text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent template-field">
                                </div>
                                <div>
                                    <label class="block text-purple-200 text-xs font-medium mb-1">التسميات</label>
                                    <input type="number" name="font_labels" id="font_labels" value="5" min="4" max="14"
                                           class="w-full bg-white/10 border border-white/20 rounded-lg px-2 py-1.5 text-white text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent template-field">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Design Settings --}}
                <div class="p-6 border-b border-white/10">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2 bg-gradient-to-br from-orange-500 to-red-500 rounded-xl">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-white">ألوان البطاقات</h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Card Color --}}
                        <div>
                            <label class="block text-purple-200 text-sm font-medium mb-2">لون خلفية البطاقة</label>
                            <div class="flex gap-3">
                                <input type="color" name="card_color" value="#4F46E5" id="cardColor"
                                       class="w-16 h-12 rounded-lg cursor-pointer border-2 border-white/20">
                                <input type="text" id="cardColorText" value="#4F46E5" 
                                       class="flex-1 bg-white/10 border border-white/20 rounded-xl px-4 py-2 text-white font-mono text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                       onchange="document.getElementById('cardColor').value = this.value">
                            </div>
                            
                            {{-- Preset Colors --}}
                            <div class="flex flex-wrap gap-2 mt-3">
                                @php
                                    $presetColors = [
                                        '#4F46E5' => 'أرجواني',
                                        '#0EA5E9' => 'أزرق',
                                        '#10B981' => 'أخضر',
                                        '#F59E0B' => 'برتقالي',
                                        '#EF4444' => 'أحمر',
                                        '#8B5CF6' => 'بنفسجي',
                                        '#EC4899' => 'وردي',
                                        '#1F2937' => 'رمادي داكن',
                                    ];
                                @endphp
                                @foreach($presetColors as $color => $name)
                                    <button type="button" onclick="setCardColor('{{ $color }}')"
                                            class="w-8 h-8 rounded-lg border-2 border-white/30 hover:border-white/60 transition-all duration-200 hover:scale-110"
                                            style="background-color: {{ $color }}"
                                            title="{{ $name }}"></button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Text Color --}}
                        <div>
                            <label class="block text-purple-200 text-sm font-medium mb-2">لون النص</label>
                            <div class="flex gap-3">
                                <input type="color" name="text_color" value="#FFFFFF" id="textColor"
                                       class="w-16 h-12 rounded-lg cursor-pointer border-2 border-white/20">
                                <input type="text" id="textColorText" value="#FFFFFF" 
                                       class="flex-1 bg-white/10 border border-white/20 rounded-xl px-4 py-2 text-white font-mono text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                       onchange="document.getElementById('textColor').value = this.value">
                            </div>
                            
                            <div class="flex gap-2 mt-3">
                                <button type="button" onclick="setTextColor('#FFFFFF')"
                                        class="px-4 py-2 bg-white text-gray-800 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">أبيض</button>
                                <button type="button" onclick="setTextColor('#000000')"
                                        class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-700 transition-colors border border-white/20">أسود</button>
                                <button type="button" onclick="setTextColor('#FCD34D')"
                                        class="px-4 py-2 bg-yellow-300 text-gray-800 rounded-lg text-sm font-medium hover:bg-yellow-400 transition-colors">ذهبي</button>
                            </div>
                        </div>
                    </div>

                    {{-- Preview Card --}}
                    <div class="mt-6">
                        <div class="flex items-center justify-between mb-3">
                            <label class="block text-purple-200 text-sm font-medium">معاينة البطاقة <span class="text-yellow-400">(اسحب العناصر لتحريكها)</span></label>
                            <button type="button" onclick="resetPositions()" class="text-xs bg-white/10 hover:bg-white/20 text-white px-3 py-1 rounded-lg transition-colors">
                                إعادة تعيين المواقع
                            </button>
                        </div>
                        <div class="flex justify-center">
                            {{-- Preview matches real card ratio: 36mm x 20mm horizontal (scaled up 8x for visibility) --}}
                            <div id="cardPreview" class="rounded-xl shadow-2xl relative overflow-hidden transition-all duration-300"
                                 style="background-color: #4F46E5; width: 288px; height: 160px;">
                                {{-- Decorative Pattern --}}
                                <div class="absolute top-0 right-0 w-20 h-20 bg-white/10 rounded-full -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>
                                <div class="absolute bottom-0 left-0 w-16 h-16 bg-white/10 rounded-full translate-y-1/2 -translate-x-1/2 pointer-events-none"></div>
                                
                                {{-- Draggable Elements --}}
                                <div id="dragNetworkName" class="draggable-item absolute cursor-move select-none hover:ring-2 hover:ring-yellow-400 rounded px-1"
                                     style="top: 5%; left: 50%; transform: translateX(-50%);">
                                    <p id="previewNetworkName" class="font-bold text-xs whitespace-nowrap" style="color: #FFFFFF;"></p>
                                </div>
                                
                                <div id="dragDataLimit" class="draggable-item absolute cursor-move select-none hover:ring-2 hover:ring-yellow-400 rounded px-1"
                                     style="top: 22%; left: 50%; transform: translateX(-50%);">
                                    <p id="previewDataLimit" class="font-bold text-sm whitespace-nowrap" style="color: #FFFFFF;"></p>
                                </div>
                                
                                <div id="dragUsername" class="draggable-item absolute cursor-move select-none hover:ring-2 hover:ring-yellow-400 rounded px-1"
                                     style="top: 42%; left: 30%; transform: translateX(-50%);">
                                    <p id="previewUsername" class="font-mono font-bold text-xs tracking-wider text-center" style="color: #FFFFFF;">12345678</p>
                                </div>
                                
                                <div id="dragPassword" class="draggable-item absolute cursor-move select-none hover:ring-2 hover:ring-yellow-400 rounded px-1"
                                     style="top: 42%; left: 70%; transform: translateX(-50%);">
                                    <p id="previewPassword" class="font-mono font-bold text-xs tracking-wider text-center" style="color: #FFD700;">123456</p>
                                </div>
                                
                                <div id="dragPhone" class="draggable-item absolute cursor-move select-none hover:ring-2 hover:ring-yellow-400 rounded px-1"
                                     style="top: 80%; left: 50%; transform: translateX(-50%);">
                                    <p id="previewPhone" class="text-xs opacity-80 whitespace-nowrap" style="color: #FFFFFF; font-size: 8px;"></p>
                                </div>
                            </div>
                        </div>
                        <p class="text-center text-gray-400 text-xs mt-2">💡 اضغط واسحب أي عنصر لتغيير موقعه على البطاقة</p>
                        <p class="text-center text-purple-300 text-xs mt-1">📐 الحجم الفعلي: 36mm × 20mm (80 بطاقة في الصفحة)</p>
                        
                        {{-- Save Position Button --}}
                        <div class="flex justify-center gap-2 mt-4">
                            <button type="button" onclick="saveCardTemplate()" class="bg-green-600 hover:bg-green-500 text-white text-sm font-bold py-2 px-6 rounded-xl transition-all shadow-lg hover:shadow-green-500/30 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                                </svg>
                                💾 حفظ التنسيق
                            </button>
                            <button type="button" onclick="clearCardTemplate()" class="bg-red-600/70 hover:bg-red-500 text-white text-sm font-medium py-2 px-4 rounded-xl transition-colors flex items-center gap-2" title="مسح التنسيق">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                مسح
                            </button>
                        </div>
                        <p id="saveStatus" class="text-center text-green-400 text-xs mt-2 hidden">✓ تم الحفظ</p>
                        
                        {{-- Hidden inputs for positions --}}
                        <input type="hidden" name="pos_network" id="pos_network" value="50,5">
                        <input type="hidden" name="pos_data_limit" id="pos_data_limit" value="50,22">
                        <input type="hidden" name="pos_username" id="pos_username" value="30,42">
                        <input type="hidden" name="pos_password" id="pos_password" value="70,42">
                        <input type="hidden" name="pos_phone" id="pos_phone" value="50,80">
                    </div>
                </div>

                {{-- Options --}}
                <div class="p-6 border-b border-white/10">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-2 bg-gradient-to-br from-green-500 to-emerald-500 rounded-xl">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-white">خيارات إضافية</h2>
                    </div>

                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input type="checkbox" name="add_to_router" value="1" checked
                               class="w-5 h-5 rounded border-white/20 bg-white/10 text-purple-500 focus:ring-purple-500 focus:ring-offset-0">
                        <span class="text-white group-hover:text-purple-200 transition-colors">إضافة البطاقات للراوتر تلقائياً</span>
                    </label>
                    <p class="text-gray-400 text-sm mt-2 mr-8">إذا تم تفعيل هذا الخيار، سيتم إضافة البطاقات مباشرة للراوتر. وإلا سيتم عرضها للطباعة فقط.</p>
                </div>

                {{-- Submit Button --}}
                <div class="p-6 bg-gradient-to-r from-purple-600/20 to-pink-600/20">
                    <div id="validationError" class="hidden mb-4 bg-red-500/20 border border-red-500/50 rounded-xl p-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span id="validationErrorText" class="text-red-200 text-sm"></span>
                        </div>
                    </div>
                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-500 hover:to-pink-500 text-white font-bold py-4 px-6 rounded-2xl transition-all duration-300 transform hover:scale-[1.02] shadow-lg hover:shadow-purple-500/25 flex items-center justify-center gap-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        <span>توليد وطباعة البطاقات</span>
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<script>
// Form validation before submit
document.getElementById('cardGeneratorForm').addEventListener('submit', function(e) {
    const routerSelect = document.getElementById('router_id');
    const countInput = document.querySelector('input[name="count"]');
    const errorDiv = document.getElementById('validationError');
    const errorText = document.getElementById('validationErrorText');
    
    let errors = [];
    
    // Check router selection
    if (!routerSelect.value || routerSelect.value === '') {
        errors.push('يجب اختيار الراوتر');
    }
    
    // Check count
    const count = parseInt(countInput.value);
    if (!count || count < 1) {
        errors.push('يجب تحديد عدد البطاقات (على الأقل 1)');
    } else if (count > 500) {
        errors.push('الحد الأقصى لعدد البطاقات هو 500');
    }
    
    // Check data limit
    const dataLimitInput = document.getElementById('data_limit_gb');
    const dataLimit = parseFloat(dataLimitInput.value);
    if (!dataLimit || dataLimit <= 0) {
        errors.push('يجب تحديد حد الاستهلاك (جيجابايت)');
    }
    
    // If there are errors, show them and prevent form submission
    if (errors.length > 0) {
        e.preventDefault();
        errorText.textContent = errors.join(' | ');
        errorDiv.classList.remove('hidden');
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }
    
    // Hide error if validation passed
    errorDiv.classList.add('hidden');
});

function setCardColor(color) {
    document.getElementById('cardColor').value = color;
    document.getElementById('cardColorText').value = color;
    document.getElementById('cardPreview').style.backgroundColor = color;
}

function setTextColor(color) {
    document.getElementById('textColor').value = color;
    document.getElementById('textColorText').value = color;
    updatePreviewTextColor(color);
}

function updatePreviewTextColor(color) {
    // Update text color for all elements except password (gold) and network name (custom color)
    const elements = ['previewUsername', 'previewDataLimit', 'previewPhone'];
    elements.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.color = color;
    });
    // Password always stays gold for contrast
    const passEl = document.getElementById('previewPassword');
    if (passEl) passEl.style.color = '#FFD700';
    // Network name has its own color
    const networkColor = document.getElementById('networkColor').value;
    const networkEl = document.getElementById('previewNetworkName');
    if (networkEl) networkEl.style.color = networkColor;
}

// Update preview when color inputs change
document.getElementById('cardColor').addEventListener('input', function() {
    document.getElementById('cardColorText').value = this.value;
    document.getElementById('cardPreview').style.backgroundColor = this.value;
});

document.getElementById('textColor').addEventListener('input', function() {
    document.getElementById('textColorText').value = this.value;
    updatePreviewTextColor(this.value);
});

// Update network name color in preview
document.getElementById('networkColor').addEventListener('input', function() {
    document.getElementById('previewNetworkName').style.color = this.value;
});

// Update preview username/password length
document.querySelector('input[name="username_length"]').addEventListener('input', function() {
    const length = parseInt(this.value) || 8;
    document.getElementById('previewUsername').textContent = '1'.repeat(Math.min(length, 12));
});

document.querySelector('input[name="password_length"]').addEventListener('input', function() {
    const length = parseInt(this.value) || 6;
    document.getElementById('previewPassword').textContent = '1'.repeat(Math.min(length, 12));
});

// Update preview for network name
document.getElementById('network_name').addEventListener('input', function() {
    document.getElementById('previewNetworkName').textContent = this.value;
});

// Update preview for phone number
document.getElementById('phone_number').addEventListener('input', function() {
    document.getElementById('previewPhone').textContent = this.value;
});

// Update preview for data limit
document.getElementById('data_limit_gb').addEventListener('input', function() {
    updateDataLimitPreview();
});

// Toggle data limit visibility
document.getElementById('show_data_limit').addEventListener('change', function() {
    updateDataLimitPreview();
});

// Update preview font sizes
function updateFontSize(inputId, previewId, unit = 'px') {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    if (input && preview) {
        input.addEventListener('input', function() {
            preview.style.fontSize = this.value + unit;
        });
    }
}

updateFontSize('font_network', 'previewNetworkName');
updateFontSize('font_data_limit', 'previewDataLimit');
updateFontSize('font_username', 'previewUsername');
updateFontSize('font_password', 'previewPassword');
updateFontSize('font_phone', 'previewPhone');

// Initialize preview on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize font sizes on preview
    initializeFontSizes();
    
    // Initialize drag and drop
    initDragAndDrop();
});

// Initialize all font sizes on preview
function initializeFontSizes() {
    const fontNetwork = document.getElementById('font_network').value;
    const fontDataLimit = document.getElementById('font_data_limit').value;
    const fontUsername = document.getElementById('font_username').value;
    const fontPassword = document.getElementById('font_password').value;
    const fontPhone = document.getElementById('font_phone').value;
    
    document.getElementById('previewNetworkName').style.fontSize = fontNetwork + 'px';
    document.getElementById('previewDataLimit').style.fontSize = fontDataLimit + 'px';
    document.getElementById('previewUsername').style.fontSize = fontUsername + 'px';
    document.getElementById('previewPassword').style.fontSize = fontPassword + 'px';
    document.getElementById('previewPhone').style.fontSize = fontPhone + 'px';
    
    // Update data limit preview
    updateDataLimitPreview();
}

// Update data limit in preview
function updateDataLimitPreview() {
    const dataLimit = parseFloat(document.getElementById('data_limit_gb').value);
    const showDataLimit = document.getElementById('show_data_limit').checked;
    if (showDataLimit && dataLimit > 0) {
        document.getElementById('previewDataLimit').textContent = dataLimit + 'G';
    } else {
        document.getElementById('previewDataLimit').textContent = '';
    }
}

// ========== Drag and Drop System ==========
const elementPositions = {
    network: { x: 50, y: 5 },
    dataLimit: { x: 50, y: 22 },
    username: { x: 30, y: 42 },
    password: { x: 70, y: 42 },
    phone: { x: 50, y: 80 }
};

function initDragAndDrop() {
    const cardPreview = document.getElementById('cardPreview');
    const draggables = document.querySelectorAll('.draggable-item');
    
    draggables.forEach(draggable => {
        // Mouse events
        draggable.addEventListener('mousedown', startDrag);
        
        // Touch events for mobile
        draggable.addEventListener('touchstart', startDrag, { passive: false });
    });
}

let currentDraggable = null;
let offsetX = 0;
let offsetY = 0;

function startDrag(e) {
    e.preventDefault();
    currentDraggable = e.currentTarget;
    currentDraggable.style.zIndex = '100';
    currentDraggable.classList.add('ring-2', 'ring-yellow-400');
    
    const rect = currentDraggable.getBoundingClientRect();
    const clientX = e.type === 'touchstart' ? e.touches[0].clientX : e.clientX;
    const clientY = e.type === 'touchstart' ? e.touches[0].clientY : e.clientY;
    
    offsetX = clientX - rect.left;
    offsetY = clientY - rect.top;
    
    if (e.type === 'touchstart') {
        document.addEventListener('touchmove', drag, { passive: false });
        document.addEventListener('touchend', endDrag);
    } else {
        document.addEventListener('mousemove', drag);
        document.addEventListener('mouseup', endDrag);
    }
}

function drag(e) {
    if (!currentDraggable) return;
    e.preventDefault();
    
    const cardPreview = document.getElementById('cardPreview');
    const cardRect = cardPreview.getBoundingClientRect();
    
    const clientX = e.type === 'touchmove' ? e.touches[0].clientX : e.clientX;
    const clientY = e.type === 'touchmove' ? e.touches[0].clientY : e.clientY;
    
    let newX = clientX - cardRect.left - offsetX;
    let newY = clientY - cardRect.top - offsetY;
    
    // Keep within bounds
    const elemRect = currentDraggable.getBoundingClientRect();
    const maxX = cardRect.width - elemRect.width;
    const maxY = cardRect.height - elemRect.height;
    
    newX = Math.max(0, Math.min(newX, maxX));
    newY = Math.max(0, Math.min(newY, maxY));
    
    currentDraggable.style.left = newX + 'px';
    currentDraggable.style.top = newY + 'px';
    currentDraggable.style.bottom = 'auto';
    currentDraggable.style.transform = 'none';
}

function endDrag(e) {
    if (!currentDraggable) return;
    
    currentDraggable.style.zIndex = '10';
    currentDraggable.classList.remove('ring-2', 'ring-yellow-400');
    
    // Save position
    savePosition(currentDraggable);
    
    currentDraggable = null;
    
    document.removeEventListener('mousemove', drag);
    document.removeEventListener('mouseup', endDrag);
    document.removeEventListener('touchmove', drag);
    document.removeEventListener('touchend', endDrag);
}

function savePosition(element) {
    const cardPreview = document.getElementById('cardPreview');
    const cardRect = cardPreview.getBoundingClientRect();
    const elemRect = element.getBoundingClientRect();
    
    // Calculate percentage position
    const xPercent = Math.round(((elemRect.left - cardRect.left + elemRect.width / 2) / cardRect.width) * 100);
    const yPercent = Math.round(((elemRect.top - cardRect.top + elemRect.height / 2) / cardRect.height) * 100);
    
    const id = element.id;
    let inputId = '';
    
    switch(id) {
        case 'dragNetworkName': inputId = 'pos_network'; elementPositions.network = { x: xPercent, y: yPercent }; break;
        case 'dragDataLimit': inputId = 'pos_data_limit'; elementPositions.dataLimit = { x: xPercent, y: yPercent }; break;
        case 'dragUsername': inputId = 'pos_username'; elementPositions.username = { x: xPercent, y: yPercent }; break;
        case 'dragPassword': inputId = 'pos_password'; elementPositions.password = { x: xPercent, y: yPercent }; break;
        case 'dragPhone': inputId = 'pos_phone'; elementPositions.phone = { x: xPercent, y: yPercent }; break;
    }
    
    if (inputId) {
        document.getElementById(inputId).value = xPercent + ',' + yPercent;
    }
}

function resetPositions() {
    const defaults = {
        dragNetworkName: { top: '5%', left: '50%', transform: 'translateX(-50%)', bottom: 'auto' },
        dragDataLimit: { top: '22%', left: '50%', transform: 'translateX(-50%)', bottom: 'auto' },
        dragUsername: { top: '42%', left: '30%', transform: 'translateX(-50%)', bottom: 'auto' },
        dragPassword: { top: '42%', left: '70%', transform: 'translateX(-50%)', bottom: 'auto' },
        dragPhone: { top: '80%', left: '50%', transform: 'translateX(-50%)', bottom: 'auto' }
    };
    
    for (const [id, pos] of Object.entries(defaults)) {
        const el = document.getElementById(id);
        if (el) {
            el.style.top = pos.top;
            el.style.left = pos.left;
            el.style.transform = pos.transform;
            el.style.bottom = pos.bottom;
        }
    }
    
    // Reset hidden inputs
    document.getElementById('pos_network').value = '50,5';
    document.getElementById('pos_data_limit').value = '50,22';
    document.getElementById('pos_username').value = '30,42';
    document.getElementById('pos_password').value = '70,42';
    document.getElementById('pos_phone').value = '50,80';
    
    // Reset positions object
    elementPositions.network = { x: 50, y: 5 };
    elementPositions.dataLimit = { x: 50, y: 22 };
    elementPositions.username = { x: 30, y: 42 };
    elementPositions.password = { x: 70, y: 42 };
    elementPositions.phone = { x: 50, y: 80 };
}

// Apply saved positions to preview elements
function applyPositionsToPreview() {
    const positionMap = {
        'pos_network': { element: 'dragNetworkName', posKey: 'network' },
        'pos_data_limit': { element: 'dragDataLimit', posKey: 'dataLimit' },
        'pos_username': { element: 'dragUsername', posKey: 'username' },
        'pos_password': { element: 'dragPassword', posKey: 'password' },
        'pos_phone': { element: 'dragPhone', posKey: 'phone' }
    };
    
    for (const [inputId, config] of Object.entries(positionMap)) {
        const input = document.getElementById(inputId);
        const element = document.getElementById(config.element);
        
        if (input && element && input.value) {
            const [x, y] = input.value.split(',').map(Number);
            if (!isNaN(x) && !isNaN(y)) {
                element.style.left = x + '%';
                element.style.top = y + '%';
                element.style.transform = 'translateX(-50%)';
                element.style.bottom = 'auto';
                
                // Update elementPositions object
                elementPositions[config.posKey] = { x, y };
            }
        }
    }
}

// ========== Template Save/Load System ==========
const TEMPLATE_KEY = 'hotspot_card_template';

function saveCardTemplate() {
    const template = {
        // Text fields
        username_length: document.getElementById('username_length').value,
        password_length: document.getElementById('password_length').value,
        network_name: document.getElementById('network_name').value,
        phone_number: document.getElementById('phone_number').value,
        data_limit_gb: document.getElementById('data_limit_gb').value,
        show_data_limit: document.getElementById('show_data_limit').checked,
        
        // Font sizes
        font_network: document.getElementById('font_network').value,
        font_data_limit: document.getElementById('font_data_limit').value,
        font_username: document.getElementById('font_username').value,
        font_password: document.getElementById('font_password').value,
        font_phone: document.getElementById('font_phone').value,
        font_labels: document.getElementById('font_labels').value,
        
        // Colors
        card_color: document.getElementById('cardColor').value,
        text_color: document.getElementById('textColor').value,
        network_color: document.getElementById('networkColor').value,
        
        // Positions
        pos_network: document.getElementById('pos_network').value,
        pos_data_limit: document.getElementById('pos_data_limit').value,
        pos_username: document.getElementById('pos_username').value,
        pos_password: document.getElementById('pos_password').value,
        pos_phone: document.getElementById('pos_phone').value,
        
        // Timestamp
        saved_at: new Date().toISOString()
    };
    
    localStorage.setItem(TEMPLATE_KEY, JSON.stringify(template));
    
    // Show saved indicator near preview
    const saveStatus = document.getElementById('saveStatus');
    if (saveStatus) {
        saveStatus.classList.remove('hidden');
        setTimeout(() => {
            saveStatus.classList.add('hidden');
        }, 3000);
    }
    
    // Show saved indicator in content section
    const indicator = document.getElementById('savedIndicator');
    if (indicator) {
        indicator.style.display = 'inline';
        setTimeout(() => {
            indicator.style.display = 'none';
        }, 3000);
    }
    
    // Show notification
    showNotification('✓ تم حفظ التنسيق بنجاح', 'success');
}

function loadCardTemplate(showMessage = true) {
    const saved = localStorage.getItem(TEMPLATE_KEY);
    if (!saved) {
        if (showMessage) {
            showNotification('لا يوجد تنسيق محفوظ', 'warning');
        }
        return;
    }
    
    try {
        const template = JSON.parse(saved);
        
        // Text fields
        if (template.username_length) document.getElementById('username_length').value = template.username_length;
        if (template.password_length) document.getElementById('password_length').value = template.password_length;
        if (template.network_name) document.getElementById('network_name').value = template.network_name;
        if (template.phone_number) document.getElementById('phone_number').value = template.phone_number;
        if (template.data_limit_gb) document.getElementById('data_limit_gb').value = template.data_limit_gb;
        if (typeof template.show_data_limit !== 'undefined') document.getElementById('show_data_limit').checked = template.show_data_limit;
        
        // Font sizes
        if (template.font_network) document.getElementById('font_network').value = template.font_network;
        if (template.font_data_limit) document.getElementById('font_data_limit').value = template.font_data_limit;
        if (template.font_username) document.getElementById('font_username').value = template.font_username;
        if (template.font_password) document.getElementById('font_password').value = template.font_password;
        if (template.font_phone) document.getElementById('font_phone').value = template.font_phone;
        if (template.font_labels) document.getElementById('font_labels').value = template.font_labels;
        
        // Colors
        if (template.card_color) {
            document.getElementById('cardColor').value = template.card_color;
            document.getElementById('cardColorText').value = template.card_color;
            document.getElementById('cardPreview').style.backgroundColor = template.card_color;
        }
        if (template.text_color) {
            document.getElementById('textColor').value = template.text_color;
            document.getElementById('textColorText').value = template.text_color;
            updatePreviewTextColor(template.text_color);
        }
        if (template.network_color) {
            document.getElementById('networkColor').value = template.network_color;
            document.getElementById('previewNetworkName').style.color = template.network_color;
        }
        
        // Positions
        if (template.pos_network) document.getElementById('pos_network').value = template.pos_network;
        if (template.pos_data_limit) document.getElementById('pos_data_limit').value = template.pos_data_limit;
        if (template.pos_username) document.getElementById('pos_username').value = template.pos_username;
        if (template.pos_password) document.getElementById('pos_password').value = template.pos_password;
        if (template.pos_phone) document.getElementById('pos_phone').value = template.pos_phone;
        
        // Apply positions to preview elements
        applyPositionsToPreview();
        
        // Trigger preview updates
        document.getElementById('network_name').dispatchEvent(new Event('input'));
        document.getElementById('phone_number').dispatchEvent(new Event('input'));
        document.getElementById('data_limit_gb').dispatchEvent(new Event('input'));
        document.getElementById('username_length').dispatchEvent(new Event('input'));
        document.getElementById('password_length').dispatchEvent(new Event('input'));
        
        // Re-initialize font sizes
        initializeFontSizes();
        
        if (showMessage) {
            showNotification('✓ تم تحميل التنسيق المحفوظ', 'success');
        }
    } catch (e) {
        if (showMessage) {
            showNotification('خطأ في تحميل التنسيق', 'error');
        }
        console.error('Error loading template:', e);
    }
}

function clearCardTemplate() {
    if (confirm('هل أنت متأكد من حذف التنسيق المحفوظ؟')) {
        localStorage.removeItem(TEMPLATE_KEY);
        showNotification('تم حذف التنسيق المحفوظ', 'info');
    }
}

function showNotification(message, type = 'info') {
    // Remove existing notification
    const existing = document.getElementById('templateNotification');
    if (existing) existing.remove();
    
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-blue-500'
    };
    
    const notification = document.createElement('div');
    notification.id = 'templateNotification';
    notification.className = `fixed top-20 left-1/2 transform -translate-x-1/2 ${colors[type]} text-white px-6 py-3 rounded-xl shadow-lg z-50 transition-all duration-300`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 2500);
}

// Auto-load template on page load if exists
document.addEventListener('DOMContentLoaded', function() {
    const saved = localStorage.getItem(TEMPLATE_KEY);
    if (saved) {
        // Auto-load the saved template
        loadCardTemplate(false);
        
        // Show indicator that template is loaded
        const indicator = document.getElementById('savedIndicator');
        indicator.textContent = '✓ تم تحميل التنسيق';
        indicator.style.display = 'inline';
        indicator.classList.remove('text-green-400');
        indicator.classList.add('text-cyan-400');
    }
});
</script>
@endsection
