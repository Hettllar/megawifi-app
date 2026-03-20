@extends('layouts.app')

@section('title', 'ترحيل مستخدمي PPP إلى UserManager')

@push('styles')
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush

@section('content')
<div class="space-y-6" x-data="routerSelector()" x-cloak>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-gray-800 flex items-center gap-3">
                <span class="w-12 h-12 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-exchange-alt text-white text-lg"></i>
                </span>
                ترحيل مستخدمي PPP إلى UserManager
            </h1>
            <p class="text-gray-500 text-sm mt-1 mr-14">اختر الراوتر لترحيل مستخدمي PPP منه</p>
        </div>
        <a href="{{ route('usermanager.index') }}" 
           class="group flex items-center gap-2 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-all duration-300">
            <i class="fas fa-arrow-right"></i>
            <span class="font-medium">رجوع</span>
        </a>
    </div>

    <!-- Info Card -->
    <div class="bg-blue-50 border-2 border-blue-200 rounded-2xl p-6">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fas fa-info-circle text-white text-xl"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-blue-800 mb-2">كيفية الترحيل</h3>
                <ul class="text-blue-700 space-y-1 text-sm">
                    <li>1. اختر الراوتر الذي تريد ترحيل مستخدمي PPP منه</li>
                    <li>2. سيتم جلب قائمة مستخدمي PPP Secrets من الراوتر</li>
                    <li>3. حدد المستخدمين الذين تريد ترحيلهم</li>
                    <li>4. اختر المجموعة في UserManager وابدأ الترحيل</li>
                </ul>
            </div>
        </div>
    </div>

    @if($routers->isEmpty())
    <!-- No Routers -->
    <div class="bg-gray-50 border-2 border-gray-200 rounded-2xl p-8 text-center">
        <i class="fas fa-server text-gray-400 text-5xl mb-4"></i>
        <h3 class="text-lg font-bold text-gray-700 mb-2">لا توجد راوترات</h3>
        <p class="text-gray-500 mb-4">لم يتم العثور على راوترات مرتبطة بحسابك</p>
        <a href="{{ route('routers.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg">
            <i class="fas fa-plus"></i>
            إضافة راوتر
        </a>
    </div>
    @else
    <!-- Router Selection -->
    <div class="bg-white rounded-2xl shadow-sm border-2 border-gray-100 overflow-hidden">
        <div class="bg-gradient-to-r from-purple-500 to-indigo-600 px-6 py-4">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <span class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-server"></i>
                </span>
                اختر الراوتر
            </h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($routers as $router)
                <a href="{{ route('usermanager.migrate', $router) }}" 
                   class="group bg-gradient-to-br from-gray-50 to-white rounded-xl p-5 border-2 border-gray-200 hover:border-purple-400 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-start gap-4">
                        <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-router text-white text-xl"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-gray-800 group-hover:text-purple-600 transition-colors truncate">
                                {{ $router->name }}
                            </h3>
                            <p class="text-sm text-gray-500 mt-1">{{ $router->ip_address }}</p>
                            <div class="flex items-center gap-2 mt-2">
                                @if($router->is_connected ?? true)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-xs">
                                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                                    متصل
                                </span>
                                @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-red-100 text-red-700 rounded-full text-xs">
                                    <span class="w-1.5 h-1.5 bg-red-500 rounded-full"></span>
                                    غير متصل
                                </span>
                                @endif
                            </div>
                        </div>
                        <div class="text-gray-400 group-hover:text-purple-500 transition-colors">
                            <i class="fas fa-chevron-left"></i>
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
function routerSelector() {
    return {
        // No special logic needed for this page
    }
}
</script>
@endpush
@endsection
