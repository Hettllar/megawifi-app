@extends('layouts.app')

@section('title', 'مجموعات UserManager')

@section('content')
<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">مجموعات UserManager</h1>
            <p class="text-gray-600">{{ $router->name }}</p>
        </div>
        <a href="{{ route('usermanager.index', ['router_id' => $router->id]) }}" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-right ml-1"></i> رجوع
        </a>
    </div>
</div>

<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
    @forelse($groups as $group)
    <div class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h3 class="text-lg font-bold text-gray-800">{{ $group['name'] ?? 'غير محدد' }}</h3>
                @if(isset($group['comment']))
                <p class="text-sm text-gray-500">{{ $group['comment'] }}</p>
                @endif
            </div>
            <div class="text-2xl">🏷️</div>
        </div>
        
        <div class="space-y-2 text-sm">
            <!-- Download Speed -->
            @if(isset($group['download-rate-limit']))
            <div class="flex items-center gap-2 text-green-600">
                <i class="fas fa-arrow-down w-5"></i>
                <span>{{ $group['download-rate-limit'] }}</span>
            </div>
            @endif
            
            <!-- Upload Speed -->
            @if(isset($group['upload-rate-limit']))
            <div class="flex items-center gap-2 text-blue-600">
                <i class="fas fa-arrow-up w-5"></i>
                <span>{{ $group['upload-rate-limit'] }}</span>
            </div>
            @endif
            
            <!-- Download Limit -->
            @if(isset($group['download-limit']))
            <div class="flex items-center gap-2 text-gray-600">
                <i class="fas fa-database w-5"></i>
                <span>تحميل: {{ $group['download-limit'] }}</span>
            </div>
            @endif
            
            <!-- Upload Limit -->
            @if(isset($group['upload-limit']))
            <div class="flex items-center gap-2 text-gray-600">
                <i class="fas fa-cloud-upload-alt w-5"></i>
                <span>رفع: {{ $group['upload-limit'] }}</span>
            </div>
            @endif
            
            <!-- Time Limit -->
            @if(isset($group['time-limit']))
            <div class="flex items-center gap-2 text-purple-600">
                <i class="fas fa-clock w-5"></i>
                <span>وقت: {{ $group['time-limit'] }}</span>
            </div>
            @endif
            
            <!-- Shared Users -->
            @if(isset($group['shared-users']))
            <div class="flex items-center gap-2 text-orange-600">
                <i class="fas fa-users w-5"></i>
                <span>{{ $group['shared-users'] }} مستخدمين متزامنين</span>
            </div>
            @endif
            
            <!-- Price -->
            @if(isset($group['price']))
            <div class="flex items-center gap-2 text-yellow-600">
                <i class="fas fa-tag w-5"></i>
                <span>{{ $group['price'] }} ل.س</span>
            </div>
            @endif
            
            <!-- Validity -->
            @if(isset($group['validity']))
            <div class="flex items-center gap-2 text-red-600">
                <i class="fas fa-calendar w-5"></i>
                <span>صلاحية: {{ $group['validity'] }}</span>
            </div>
            @endif
        </div>
        
        <!-- Limitations -->
        @php
            $limitations = [];
            if(isset($group['limitations'])) {
                $limitations = explode(',', $group['limitations']);
            }
        @endphp
        
        @if(count($limitations) > 0)
        <div class="mt-4 pt-4 border-t border-gray-200">
            <p class="text-xs font-semibold text-gray-500 mb-2">القيود:</p>
            <div class="flex flex-wrap gap-1">
                @foreach($limitations as $limitation)
                <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-700">
                    {{ trim($limitation) }}
                </span>
                @endforeach
            </div>
        </div>
        @endif
    </div>
    @empty
    <div class="col-span-full bg-white rounded-xl shadow-sm p-8 text-center text-gray-500">
        <i class="fas fa-folder-open text-4xl text-gray-300 mb-3"></i>
        <p>لا يوجد مجموعات محددة</p>
    </div>
    @endforelse
</div>
@endsection
