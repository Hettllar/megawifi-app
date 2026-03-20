@extends('layouts.app')

@section('title', 'بطاقة هوتسبوت')

@push('styles')
<style>
    @media print {
        body * {
            visibility: hidden;
        }
        #printArea, #printArea * {
            visibility: visible;
        }
        #printArea {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }
        .no-print {
            display: none !important;
        }
    }
    
    .wifi-card {
        background: linear-gradient(135deg, #f97316 0%, #dc2626 100%);
        border-radius: 16px;
        padding: 24px;
        color: white;
        max-width: 400px;
        margin: 0 auto;
        box-shadow: 0 10px 40px rgba(249, 115, 22, 0.3);
    }
    
    .wifi-card-header {
        text-align: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px dashed rgba(255,255,255,0.3);
    }
    
    .wifi-card-body {
        background: rgba(255,255,255,0.15);
        border-radius: 12px;
        padding: 20px;
    }
    
    .wifi-card-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px dashed rgba(255,255,255,0.2);
    }
    
    .wifi-card-row:last-child {
        border-bottom: none;
    }
    
    .wifi-card-label {
        font-size: 14px;
        opacity: 0.9;
    }
    
    .wifi-card-value {
        font-size: 18px;
        font-weight: bold;
        font-family: monospace;
        letter-spacing: 2px;
    }
</style>
@endpush

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 no-print">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-gray-800 flex items-center gap-3">
                <span class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-check text-white text-lg"></i>
                </span>
                تم إنشاء البطاقة بنجاح
            </h1>
            <p class="text-gray-500 text-sm mt-1 mr-14">يمكنك نسخ بيانات البطاقة</p>
        </div>
        <a href="{{ route('hotspot.create') }}" 
           class="group flex items-center gap-2 px-4 py-2.5 bg-orange-500 hover:bg-orange-600 text-white rounded-xl transition-all duration-300">
            <i class="fas fa-plus"></i>
            <span class="font-medium">إضافة بطاقة أخرى</span>
        </a>
    </div>

    <!-- Card Preview -->
    <div id="printArea">
        <div class="wifi-card">
            <div class="wifi-card-header">
                <div class="text-3xl mb-2">📶</div>
                <h2 class="text-xl font-bold">بطاقة WiFi</h2>
                <p class="text-sm opacity-80">MegaWiFi Network</p>
            </div>
            <div class="wifi-card-body">
                <div class="wifi-card-row">
                    <span class="wifi-card-label">👤 اسم المستخدم</span>
                    <span class="wifi-card-value" id="cardUsername">{{ $card['username'] }}</span>
                </div>
                <div class="wifi-card-row">
                    <span class="wifi-card-label">🔑 كلمة المرور</span>
                    <span class="wifi-card-value" id="cardPassword">{{ $card['password'] }}</span>
                </div>
                @if(!empty($card['profile']))
                <div class="wifi-card-row">
                    <span class="wifi-card-label">📋 البروفايل</span>
                    <span class="wifi-card-value text-base">{{ $card['profile'] }}</span>
                </div>
                @endif
                @if(!empty($card['data_limit']))
                <div class="wifi-card-row">
                    <span class="wifi-card-label">📊 حد البيانات</span>
                    <span class="wifi-card-value">{{ $card['data_limit'] }} GB</span>
                </div>
                @endif
            </div>
            <div class="text-center mt-4 text-sm opacity-70">
                ✨ شكراً لاستخدامكم خدماتنا
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-col sm:flex-row gap-3 justify-center max-w-lg mx-auto no-print">
        <!-- Copy Button -->
        <button type="button" id="copyBtn" onclick="copyCardData()"
            class="flex-1 flex items-center justify-center gap-2 px-6 py-4 bg-blue-500 hover:bg-blue-600 text-white rounded-xl font-medium transition-all duration-200 shadow-lg hover:shadow-xl">
            <i class="fas fa-copy text-xl"></i>
            <span>نسخ البيانات</span>
        </button>
    </div>

    <!-- Back to List -->
    <div class="text-center no-print">
        <a href="{{ route('hotspot.index') }}" class="text-gray-500 hover:text-gray-700 text-sm">
            <i class="fas fa-arrow-right ml-1"></i>
            العودة لقائمة البطاقات
        </a>
    </div>
</div>

@push('scripts')
<script>
function copyCardData() {
    const username = document.getElementById('cardUsername').textContent;
    const password = document.getElementById('cardPassword').textContent;
    const dataLimit = '{{ $card["data_limit"] ?? "" }}';
    
    let text = `🌐 بيانات اتصال WiFi\n\n👤 اسم المستخدم: ${username}\n🔑 كلمة المرور: ${password}`;
    if (dataLimit) {
        text += `\n📊 حد البيانات: ${dataLimit} GB`;
    }
    text += `\n\n✨ شكراً لاستخدامكم خدماتنا`;
    
    navigator.clipboard.writeText(text).then(() => {
        // Show success toast
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-4 left-1/2 -translate-x-1/2 bg-gray-800 text-white px-6 py-3 rounded-xl shadow-lg z-50 flex items-center gap-2';
        toast.innerHTML = '<i class="fas fa-check-circle text-green-400"></i> تم نسخ البيانات!';
        const btn = document.getElementById('copyBtn');
        btn.innerHTML = '<i class="fas fa-check text-xl"></i> <span>تم النسخ!</span>';
        btn.classList.remove('bg-blue-500', 'hover:bg-blue-600');
        btn.classList.add('bg-green-500');
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2000);
    });
}
</script>
@endpush
@endsection
