@extends('layouts.app')

@section('title', 'إدارة المستخدمين')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">إدارة المستخدمين</h1>
        <p class="text-gray-600">مستخدمي النظام وصلاحياتهم</p>
    </div>
    <a href="{{ route('users.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
        <i class="fas fa-plus ml-1"></i> إضافة مستخدم
    </a>
</div>

@if(session('success'))
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
    {{ session('success') }}
</div>
@endif

@if(session('error'))
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
    {{ session('error') }}
</div>
@endif

<!-- جدول المستخدمين -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">#</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">المستخدم</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">البريد الإلكتروني</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">الدور</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">الراوترات</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">تاريخ الإنشاء</th>
                    <th class="px-6 py-3 text-right text-sm font-medium text-gray-700">الصلاحية</th>
                    <th class="px-6 py-3 text-center text-sm font-medium text-gray-700">إجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($users as $user)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-gray-500">{{ $user->id }}</td>
                    <td class="px-6 py-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center ml-3">
                                <span class="text-blue-600 font-bold">{{ mb_substr($user->name, 0, 1) }}</span>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">{{ $user->name }}</p>
                                @if($user->id === auth()->id())
                                    <span class="text-xs text-blue-600">(أنت)</span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-gray-600">{{ $user->email }}</td>
                    <td class="px-6 py-4">
                        @if($user->role === 'super_admin')
                            <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm">مدير عام</span>
                        @elseif($user->role === 'admin')
                            <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">مدير</span>
                        @elseif($user->role === 'reseller')
                            <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm">
                                <i class="fas fa-store ml-1"></i> وكيل/بائع
                            </span>
                        @elseif($user->role === 'operator')
                            <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">مشغّل</span>
                        @else
                            <span class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-sm">مشاهد</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        @if($user->role === 'super_admin')
                            <span class="text-green-600"><i class="fas fa-check-double ml-1"></i> جميع الراوترات</span>
                        @elseif($user->role === 'reseller')
                            <div class="text-sm">
                                <span class="text-green-600">{{ $user->resellerSubscribers()->count() }} مشترك</span>
                                @if($user->max_subscribers)
                                    <span class="text-gray-400">/ {{ $user->max_subscribers }}</span>
                                @endif
                            </div>
                        @else
                            <span class="text-gray-600">{{ $user->routers ? $user->routers->count() : 0 }} راوتر</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-gray-500">{{ $user->created_at->format('Y-m-d') }}</td>
                    <td class="px-6 py-4">
                        @if($user->expires_at)
                            @if(\Carbon\Carbon::parse($user->expires_at)->isPast())
                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs">منتهية</span>
                            @else
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">{{ (int)now()->diffInDays($user->expires_at, false) }} يوم</span>
                            @endif
                        @else
                            <span class="text-gray-400 text-sm">غير محدود</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex justify-center gap-2">
                            <a href="{{ route('users.edit', $user) }}" class="text-yellow-600 hover:text-yellow-800" title="تعديل">
                                <i class="fas fa-edit"></i>
                            </a>
                            @if($user->role !== 'super_admin')
                            <a href="{{ route('users.routers', $user) }}" class="text-blue-600 hover:text-blue-800" title="إدارة الراوترات">
                                <i class="fas fa-server"></i>
                            </a>
                            @endif
                            @if($user->id !== auth()->id())
                            <form action="{{ route('users.destroy', $user) }}" method="POST" class="inline"
                                  onsubmit="return confirm('هل أنت متأكد من حذف هذا المستخدم؟')">
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
                    <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                        <i class="fas fa-users text-4xl mb-2"></i>
                        <p>لا يوجد مستخدمين</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <div class="px-6 py-4 border-t">
        {{ $users->links() }}
    </div>
</div>
@endsection
