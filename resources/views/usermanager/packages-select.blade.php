@extends('layouts.app')

@section('title', 'اختيار الراوتر - إدارة الباقات')

@section('content')
<div class="mb-6">
    <div class="flex flex-wrap justify-between items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                <i class="fas fa-cubes text-orange-600 ml-2"></i>
                إدارة الباقات
            </h1>
            <p class="text-gray-600">اختر الراوتر للدخول إلى إدارة الباقات</p>
        </div>
        <a href="{{ route('usermanager.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition">
            <i class="fas fa-arrow-right ml-1"></i> رجوع
        </a>
    </div>
</div>

@if($routers->isEmpty())
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-8 text-center">
        <i class="fas fa-exclamation-triangle text-yellow-500 text-4xl mb-4"></i>
        <h3 class="text-lg font-bold text-yellow-700 mb-2">لا توجد راوترات</h3>
        <p class="text-yellow-600">لم يتم العثور على أي راوترات مرتبطة بحسابك</p>
    </div>
@else
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($routers as $router)
            <div class="bg-white rounded-xl shadow-md overflow-hidden border-2 {{ $router->is_online ? 'border-green-200 hover:border-green-400' : 'border-red-200 hover:border-red-400' }} transition-all duration-300 hover:shadow-lg">
                <!-- Header with status -->
                <div class="p-4 {{ $router->is_online ? 'bg-gradient-to-r from-green-500 to-emerald-600' : 'bg-gradient-to-r from-red-500 to-rose-600' }} text-white">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                                <i class="fas fa-server text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-lg">{{ $router->name }}</h3>
                                <p class="text-white/80 text-sm">{{ $router->ip_address }}</p>
                            </div>
                        </div>
                        <div class="text-left">
                            @if($router->is_online)
                                <span class="inline-flex items-center gap-1 bg-white/20 px-3 py-1 rounded-full text-sm">
                                    <span class="w-2 h-2 bg-white rounded-full animate-pulse"></span>
                                    متصل
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 bg-white/20 px-3 py-1 rounded-full text-sm">
                                    <span class="w-2 h-2 bg-white/50 rounded-full"></span>
                                    غير متصل
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
                
                <!-- Body -->
                <div class="p-4">
                    <div class="space-y-2 text-sm text-gray-600 mb-4">
                        @if($router->location)
                            <div class="flex items-center gap-2">
                                <i class="fas fa-map-marker-alt text-gray-400 w-5"></i>
                                <span>{{ $router->location }}</span>
                            </div>
                        @endif
                        @if($router->identity)
                            <div class="flex items-center gap-2">
                                <i class="fas fa-fingerprint text-gray-400 w-5"></i>
                                <span>{{ $router->identity }}</span>
                            </div>
                        @endif
                        <div class="flex items-center gap-2">
                            <i class="fas fa-plug text-gray-400 w-5"></i>
                            <span>المنفذ: {{ $router->api_port ?? 8728 }}</span>
                        </div>
                    </div>
                    
                    @if($router->is_online)
                        <a href="{{ route('usermanager.packages', $router) }}" 
                           class="w-full block text-center bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white py-3 rounded-xl font-bold transition-all duration-300 transform hover:scale-105 shadow-md hover:shadow-lg">
                            <i class="fas fa-cubes ml-2"></i>
                            إدارة الباقات
                        </a>
                    @else
                        <button disabled 
                                class="w-full bg-gray-300 text-gray-500 py-3 rounded-xl font-bold cursor-not-allowed">
                            <i class="fas fa-times-circle ml-2"></i>
                            الراوتر غير متصل
                        </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    
    <!-- Summary -->
    <div class="mt-6 bg-white rounded-xl shadow-md p-4">
        <div class="flex flex-wrap justify-center gap-6">
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-green-500 rounded-full"></div>
                <span class="text-gray-700">متصل: <strong class="text-green-600">{{ $routers->where('is_online', true)->count() }}</strong></span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-red-500 rounded-full"></div>
                <span class="text-gray-700">غير متصل: <strong class="text-red-600">{{ $routers->where('is_online', false)->count() }}</strong></span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-blue-500 rounded-full"></div>
                <span class="text-gray-700">الإجمالي: <strong class="text-blue-600">{{ $routers->count() }}</strong></span>
            </div>
        </div>
    </div>
@endif
@endsection
