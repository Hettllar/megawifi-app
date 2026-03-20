@extends('layouts.app')

@section('title', 'إدارة الوكلاء')

@section('content')
<div class="mb-6 flex flex-col lg:flex-row lg:justify-between lg:items-center gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">إدارة الوكلاء والبائعين</h1>
        <p class="text-gray-600">قائمة الوكلاء وإدارة صلاحياتهم ورصيدهم</p>
    </div>
    <div class="flex flex-col sm:flex-row gap-3">
        <!-- Router Filter -->
        @if(isset($routers) && $routers->count() > 0)
        <form method="GET" class="flex gap-2">
            <select name="router_id" onchange="this.form.submit()" class="border rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 min-w-[200px]">
                <option value="">جميع الراوترات</option>
                @foreach($routers as $router)
                    <option value="{{ $router->id }}" {{ $selectedRouterId == $router->id ? 'selected' : '' }}>
                        {{ $router->name }}
                    </option>
                @endforeach
            </select>
        </form>
        @endif
        
        @if(auth()->user()->isSuperAdmin())
        <a href="{{ route('resellers.create') }}" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-center">
            <i class="fas fa-plus ml-1"></i> إضافة وكيل جديد
        </a>
        @endif
    </div>
</div>

@if(session('success'))
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
    {{ session('success') }}
</div>
@endif

<!-- إحصائيات سريعة -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                <i class="fas fa-users text-green-600 text-xl"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-800">{{ $resellers->total() }}</p>
                <p class="text-gray-500 text-sm">إجمالي الوكلاء</p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                <i class="fas fa-user-check text-blue-600 text-xl"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-800">{{ $resellers->where('is_active', true)->count() }}</p>
                <p class="text-gray-500 text-sm">وكلاء نشطين</p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                <i class="fas fa-users-cog text-purple-600 text-xl"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-800">{{ $resellers->sum('reseller_subscribers_count') }}</p>
                <p class="text-gray-500 text-sm">إجمالي المشتركين</p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                <i class="fas fa-wallet text-yellow-600 text-xl"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-800">{{ number_format($resellers->sum('balance'), 0) }}</p>
                <p class="text-gray-500 text-sm">إجمالي الأرصدة</p>
            </div>
        </div>
    </div>
</div>

<!-- جدول الوكلاء -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">الوكيل</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">الشركة</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">الرصيد</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">المشتركين</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">الراوترات</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">الحالة</th>
                    <th class="px-6 py-3 text-center text-sm font-medium text-gray-700">إجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($resellers as $reseller)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center ml-3">
                                <i class="fas fa-store text-green-600"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">{{ $reseller->name }}</p>
                                <p class="text-sm text-gray-500">{{ $reseller->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-gray-600">
                        {{ $reseller->company_name ?? '-' }}
                    </td>
                    <td class="px-6 py-4">
                        <span class="font-bold {{ $reseller->balance > 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ number_format($reseller->balance, 0) }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">
                            {{ $reseller->reseller_subscribers_count }}
                            @if($reseller->max_subscribers)
                                / {{ $reseller->max_subscribers }}
                            @endif
                        </span>
                    </td>
                    <td class="px-6 py-4 text-gray-600">
                        {{ $reseller->resellerPermissions->count() }} راوتر
                    </td>
                    <td class="px-6 py-4">
                        @if($reseller->is_active)
                            <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm">نشط</span>
                        @else
                            <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm">معطّل</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex justify-center gap-2">
                            <a href="{{ route('resellers.show', $reseller) }}" 
                               class="text-blue-600 hover:text-blue-800" title="عرض">
                                <i class="fas fa-eye"></i>
                            </a>
                            @if(auth()->user()->isSuperAdmin())
                            <a href="{{ route('resellers.permissions', $reseller) }}" 
                               class="text-purple-600 hover:text-purple-800" title="الصلاحيات">
                                <i class="fas fa-key"></i>
                            </a>
                            @endif
                            <a href="{{ route('resellers.deposit', $reseller) }}" 
                               class="text-green-600 hover:text-green-800" title="شحن الرصيد">
                                <i class="fas fa-wallet"></i>
                            </a>
                            @if(auth()->user()->isSuperAdmin())
                            <a href="{{ route('resellers.edit', $reseller) }}" 
                               class="text-yellow-600 hover:text-yellow-800" title="تعديل">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="{{ route('resellers.destroy', $reseller) }}" method="POST" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا الوكيل؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800" title="حذف">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <i class="fas fa-store text-4xl mb-2 opacity-50"></i>
                        <p>لا يوجد وكلاء</p>
                        @if(auth()->user()->isSuperAdmin())
                        <a href="{{ route('resellers.create') }}" class="text-blue-600 hover:underline mt-2 inline-block">
                            إضافة وكيل جديد
                        </a>
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    @if($resellers->hasPages())
    <div class="px-6 py-4 border-t">
        {{ $resellers->links() }}
    </div>
    @endif
</div>
@endsection
