@extends('layouts.app')

@section('title', 'النسخ الاحتياطية')

@push('styles')
<style>
    .backup-card { transition: all 0.3s ease; }
    .backup-card:hover { transform: translateY(-2px); box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
    .expand-btn { cursor: pointer; }
    [x-cloak] { display: none !important; }
</style>
@endpush

@section('content')
<div class="space-y-4 pb-20">
    <!-- Header -->
    <div class="bg-gradient-to-l from-emerald-600 to-teal-700 rounded-xl md:rounded-2xl p-4 md:p-6 text-white shadow-xl">
        <div class="flex flex-col gap-4">
            <div>
                <h1 class="text-lg md:text-2xl font-bold flex items-center gap-2">
                    <i class="fas fa-shield-alt"></i>
                    النسخ الاحتياطية للراوترات
                </h1>
                <p class="text-emerald-200 mt-1 text-sm">إدارة وتحميل واستعادة النسخ الاحتياطية لجميع الراوترات</p>
            </div>

            <div class="flex flex-wrap gap-2 md:gap-3">
                <div class="bg-white/20 rounded-lg md:rounded-xl px-3 md:px-4 py-2 text-center flex-1 min-w-[60px]">
                    <p class="text-xl md:text-2xl font-bold">{{ count($backups) }}</p>
                    <p class="text-xs">راوتر</p>
                </div>
                <div class="bg-green-500/40 rounded-lg md:rounded-xl px-3 md:px-4 py-2 text-center flex-1 min-w-[60px]">
                    <p class="text-xl md:text-2xl font-bold">{{ $totalFiles }}</p>
                    <p class="text-xs">نسخة</p>
                </div>
                <div class="bg-blue-500/40 rounded-lg md:rounded-xl px-3 md:px-4 py-2 text-center flex-1 min-w-[60px]">
                    <p class="text-xl md:text-2xl font-bold">{{ number_format($totalSize / 1024 / 1024, 1) }} MB</p>
                    <p class="text-xs">الحجم الكلي</p>
                </div>

                <form action="{{ route('admin.backups.backupAll') }}" method="POST" class="w-full md:w-auto" onsubmit="return confirm('هل تريد بدء النسخ الاحتياطي لجميع الراوترات؟')">
                    @csrf
                    <button type="submit" class="bg-white text-teal-700 hover:bg-teal-50 px-4 py-2 rounded-lg md:rounded-xl font-bold flex items-center justify-center gap-2 w-full">
                        <i class="fas fa-download"></i>
                        <span>نسخ احتياطي للكل</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl flex items-center gap-2">
        <i class="fas fa-check-circle text-green-500"></i>
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl flex items-center gap-2">
        <i class="fas fa-exclamation-circle text-red-500"></i>
        {{ session('error') }}
    </div>
    @endif

    <!-- Routers Backup Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach($backups as $item)
        <div class="backup-card bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden" x-data="{ open: false }">
            <!-- Router Header -->
            <div class="p-3 md:p-4 {{ $item['count'] > 0 ? 'bg-emerald-500' : 'bg-gray-400' }} text-white">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 md:gap-3">
                        <i class="fas fa-router text-xl md:text-2xl"></i>
                        <div>
                            <h3 class="font-bold text-sm md:text-base">{{ $item['router']->name }}</h3>
                            <p class="text-xs opacity-80">{{ $item['router']->location ?? $item['router']->wg_client_ip }}</p>
                        </div>
                    </div>
                    <div class="text-left">
                        <span class="bg-white/20 px-2 py-1 rounded-lg text-xs font-bold">
                            {{ $item['count'] }} {{ $item['count'] == 1 ? 'نسخة' : 'نسخ' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Info -->
            <div class="p-3 md:p-4">
                @if($item['latest'])
                <div class="flex items-center gap-2 text-sm text-gray-600 mb-2">
                    <i class="fas fa-clock text-gray-400"></i>
                    <span>آخر نسخة: {{ \Carbon\Carbon::parse($item['latest']['date'])->diffForHumans() }}</span>
                </div>
                @if($item['latest']['has_backup'])
                <div class="flex items-center gap-2 text-sm text-gray-600 mb-3">
                    <i class="fas fa-hdd text-gray-400"></i>
                    <span>الحجم: {{ number_format($item['latest']['size'] / 1024, 0) }} KB</span>
                </div>
                @endif
                @else
                <div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
                    <i class="fas fa-info-circle"></i>
                    <span>لا توجد نسخ احتياطية</span>
                </div>
                @endif

                <!-- Actions -->
                <div class="flex gap-2 mb-2">
                    <form action="{{ route('admin.backups.create', $item['router']->id) }}" method="POST" class="flex-1" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='<i class=\'fas fa-spinner fa-spin\'></i> جاري...'">
                        @csrf
                        <button type="submit" class="w-full bg-teal-50 text-teal-700 hover:bg-teal-100 px-3 py-2 rounded-lg text-sm font-bold flex items-center justify-center gap-1 transition">
                            <i class="fas fa-plus-circle"></i>
                            نسخة جديدة
                        </button>
                    </form>
                    @if($item['count'] > 0)
                    <button @click="open = !open" class="expand-btn bg-gray-50 text-gray-600 hover:bg-gray-100 px-3 py-2 rounded-lg text-sm font-bold flex items-center gap-1 transition">
                        <i class="fas" :class="open ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                        التفاصيل
                    </button>
                    @endif
                </div>

                <!-- Expanded Files List -->
                <div x-show="open" x-cloak x-transition class="border-t border-gray-100 pt-3 mt-2 space-y-2 max-h-60 overflow-y-auto">
                    @foreach($item['files'] as $file)
                    <div class="bg-gray-50 rounded-lg p-2 text-xs">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-gray-700">
                                <i class="fas fa-file-archive text-teal-500 ml-1"></i>
                                {{ \Carbon\Carbon::parse($file['date'])->format('Y-m-d H:i') }}
                            </span>
                            <span class="text-gray-400">{{ number_format($file['size'] / 1024, 0) }} KB</span>
                        </div>
                        @if(isset($file['info']['created_by']))
                        <div class="text-gray-400 mb-1">بواسطة: {{ $file['info']['created_by'] }}</div>
                        @endif
                        <div class="flex gap-1 mt-1">
                            @if($file['has_backup'])
                            <a href="{{ route('admin.backups.download', [$item['router']->id, $file['filename']]) }}" class="bg-blue-50 text-blue-600 hover:bg-blue-100 px-2 py-1 rounded text-xs font-bold flex items-center gap-1 transition">
                                <i class="fas fa-download"></i> تحميل
                            </a>
                            <form action="{{ route('admin.backups.restore', [$item['router']->id, $file['filename']]) }}" method="POST" onsubmit="return confirm('⚠️ هل أنت متأكد؟ سيتم استعادة هذه النسخة وإعادة تشغيل الراوتر!')">
                                @csrf
                                <button class="bg-amber-50 text-amber-600 hover:bg-amber-100 px-2 py-1 rounded text-xs font-bold flex items-center gap-1 transition">
                                    <i class="fas fa-undo"></i> استعادة
                                </button>
                            </form>
                            @else
                            <span class="bg-yellow-50 text-yellow-600 px-2 py-1 rounded text-xs">
                                <i class="fas fa-exclamation-triangle"></i> ملف غير متوفر
                            </span>
                            @endif
                            <form action="{{ route('admin.backups.delete', [$item['router']->id, $file['filename']]) }}" method="POST" onsubmit="return confirm('حذف هذه النسخة الاحتياطية؟')">
                                @csrf
                                @method('DELETE')
                                <button class="bg-red-50 text-red-600 hover:bg-red-100 px-2 py-1 rounded text-xs font-bold flex items-center gap-1 transition">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection
