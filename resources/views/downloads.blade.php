@extends('layouts.app')

@section('title', 'تحميل التطبيقات')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-lg lg:text-xl font-bold text-gray-800 flex items-center gap-2">
                <span class="w-8 h-8 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-download text-white text-sm"></i>
                </span>
                تحميل التطبيقات
            </h1>
            <p class="text-xs text-gray-500 mt-1">حمّل تطبيقات MegaWiFi الرسمية</p>
        </div>
    </div>

    <!-- Apps Grid -->
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
        
        <!-- Android App -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <div class="bg-gradient-to-br from-green-500 to-green-600 p-6 text-center">
                <div class="w-20 h-20 mx-auto bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur">
                    <i class="fab fa-android text-4xl text-white"></i>
                </div>
            </div>
            <div class="p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-2">Android</h3>
                <p class="text-gray-500 text-sm mb-4">تطبيق MegaWiFi لأجهزة أندرويد</p>
                
                <div class="flex items-center gap-2 mb-4">
                    <span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded-full">v1.0.0</span>
                    <span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded-full">4 MB</span>
                </div>
                
                <a href="/downloads/MegaWiFi-v1.0.apk" 
                   class="w-full flex items-center justify-center gap-2 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold py-3 px-4 rounded-xl hover:from-green-600 hover:to-green-700 transition-all duration-300">
                    <i class="fas fa-download"></i>
                    تحميل APK
                </a>
            </div>
        </div>

        <!-- Windows App -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 p-6 text-center">
                <div class="w-20 h-20 mx-auto bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur">
                    <i class="fab fa-windows text-4xl text-white"></i>
                </div>
            </div>
            <div class="p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-2">Windows</h3>
                <p class="text-gray-500 text-sm mb-4">تطبيق MegaWiFi لنظام ويندوز</p>
                
                <div class="flex items-center gap-2 mb-4">
                    <span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded-full">v1.0.0</span>
                    <span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded-full">92 MB</span>
                    <span class="bg-blue-100 text-blue-600 text-xs px-2 py-1 rounded-full">محمول</span>
                </div>
                
                <a href="/downloads/MegaWiFi-Portable.exe" 
                   class="w-full flex items-center justify-center gap-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-semibold py-3 px-4 rounded-xl hover:from-blue-600 hover:to-blue-700 transition-all duration-300">
                    <i class="fas fa-download"></i>
                    تحميل EXE
                </a>
            </div>
        </div>

        <!-- iOS Coming Soon -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden opacity-60">
            <div class="bg-gradient-to-br from-gray-400 to-gray-500 p-6 text-center">
                <div class="w-20 h-20 mx-auto bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur">
                    <i class="fab fa-apple text-4xl text-white"></i>
                </div>
            </div>
            <div class="p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-2">iOS</h3>
                <p class="text-gray-500 text-sm mb-4">تطبيق MegaWiFi لأجهزة آيفون</p>
                
                <div class="flex items-center gap-2 mb-4">
                    <span class="bg-orange-100 text-orange-600 text-xs px-2 py-1 rounded-full">قريباً</span>
                </div>
                
                <button disabled class="w-full flex items-center justify-center gap-2 bg-gray-300 text-gray-500 font-semibold py-3 px-4 rounded-xl cursor-not-allowed">
                    <i class="fas fa-clock"></i>
                    قريباً
                </button>
            </div>
        </div>
    </div>

    <!-- Installation Instructions -->
    <div class="grid md:grid-cols-2 gap-6 mt-8">
        <!-- Android Instructions -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fab fa-android text-green-500"></i>
                تعليمات تثبيت Android
            </h3>
            <ol class="space-y-3 text-gray-600 text-sm">
                <li class="flex items-start gap-3">
                    <span class="w-6 h-6 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">1</span>
                    <span>حمّل ملف APK من الرابط أعلاه</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="w-6 h-6 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">2</span>
                    <span>اذهب إلى الإعدادات > الأمان > اسمح بتثبيت التطبيقات من مصادر غير معروفة</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="w-6 h-6 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">3</span>
                    <span>افتح ملف APK واضغط "تثبيت"</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="w-6 h-6 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">4</span>
                    <span>افتح التطبيق وسجّل الدخول ببيانات حسابك</span>
                </li>
            </ol>
        </div>

        <!-- Windows Instructions -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fab fa-windows text-blue-500"></i>
                تعليمات تثبيت Windows
            </h3>
            <ol class="space-y-3 text-gray-600 text-sm">
                <li class="flex items-start gap-3">
                    <span class="w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">1</span>
                    <span>حمّل ملف EXE (النسخة المحمولة)</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">2</span>
                    <span>قد تظهر رسالة تحذير من Windows - اضغط "مزيد من المعلومات"</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">3</span>
                    <span>اضغط "تشغيل على أي حال"</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">4</span>
                    <span>لا يحتاج تثبيت - يعمل مباشرة!</span>
                </li>
            </ol>
        </div>
    </div>
</div>
@endsection