@extends('layouts.app')

@section('title', 'التقارير')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">التقارير والإحصائيات</h1>
    <p class="text-gray-600">عرض تقارير مفصلة عن النظام</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
    <!-- Subscribers Report -->
    <a href="{{ route('reports.subscribers') }}" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-lg transition border-r-4 border-blue-500">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-800">تقرير المشتركين</h3>
                <p class="text-gray-500 text-sm mt-1">إحصائيات تفصيلية عن المشتركين</p>
            </div>
            <div class="bg-blue-100 rounded-full p-3">
                <i class="fas fa-users text-2xl text-blue-600"></i>
            </div>
        </div>
    </a>

    <!-- Revenue Report -->
    <a href="{{ route('reports.revenue') }}" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-lg transition border-r-4 border-green-500">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-800">تقرير الإيرادات</h3>
                <p class="text-gray-500 text-sm mt-1">الإيرادات والفواتير</p>
            </div>
            <div class="bg-green-100 rounded-full p-3">
                <i class="fas fa-dollar-sign text-2xl text-green-600"></i>
            </div>
        </div>
    </a>

    <!-- Sessions Report -->
    <a href="{{ route('reports.sessions') }}" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-lg transition border-r-4 border-yellow-500">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-800">تقرير الجلسات</h3>
                <p class="text-gray-500 text-sm mt-1">الاتصالات واستهلاك الترافيك</p>
            </div>
            <div class="bg-yellow-100 rounded-full p-3">
                <i class="fas fa-chart-line text-2xl text-yellow-600"></i>
            </div>
        </div>
    </a>

    <!-- Expiring Subscribers -->
    <a href="{{ route('reports.expiring') }}" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-lg transition border-r-4 border-red-500">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-800">المنتهية صلاحيتهم</h3>
                <p class="text-gray-500 text-sm mt-1">المشتركين قرب انتهاء الصلاحية</p>
            </div>
            <div class="bg-red-100 rounded-full p-3">
                <i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>
            </div>
        </div>
    </a>
</div>

<!-- Quick Stats -->
<div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Export Section -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">
            <i class="fas fa-download ml-2 text-blue-500"></i>
            تصدير البيانات
        </h3>
        <div class="space-y-3">
            <a href="{{ route('reports.export', ['type' => 'subscribers']) }}" 
               class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                <div class="flex items-center">
                    <i class="fas fa-file-csv text-green-600 ml-3"></i>
                    <span>تصدير المشتركين (CSV)</span>
                </div>
                <i class="fas fa-arrow-left text-gray-400"></i>
            </a>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">
            <i class="fas fa-clock ml-2 text-purple-500"></i>
            نصائح
        </h3>
        <ul class="space-y-3 text-gray-600">
            <li class="flex items-start">
                <i class="fas fa-lightbulb text-yellow-500 mt-1 ml-2"></i>
                <span>راجع تقرير المنتهية صلاحيتهم بشكل دوري للتواصل مع المشتركين</span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-lightbulb text-yellow-500 mt-1 ml-2"></i>
                <span>استخدم تقرير الإيرادات لتتبع الأداء المالي</span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-lightbulb text-yellow-500 mt-1 ml-2"></i>
                <span>تابع تقرير الجلسات لمعرفة أوقات الذروة</span>
            </li>
        </ul>
    </div>
</div>
@endsection
