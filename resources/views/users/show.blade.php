@extends('layouts.app')

@section('title', 'عرض المستخدم - ' . $user->name)

@section('content')
<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">{{ $user->name }}</h1>
            <p class="text-gray-600">تفاصيل المستخدم</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('users.edit', $user) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                <i class="fas fa-edit ml-1"></i> تعديل
            </a>
            <a href="{{ route('users.index') }}" class="text-blue-600 hover:text-blue-800 px-4 py-2">
                <i class="fas fa-arrow-right ml-1"></i> رجوع
            </a>
        </div>
    </div>
</div>

<div class="grid md:grid-cols-3 gap-6">
    <!-- Main Info -->
    <div class="md:col-span-2 space-y-6">
        <!-- Basic Information -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-user text-blue-600"></i> معلومات أساسية
            </h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-500">الاسم</label>
                    <p class="font-medium text-gray-800">{{ $user->name }}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-500">البريد الإلكتروني</label>
                    <p class="font-medium text-gray-800">{{ $user->email }}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-500">الهاتف</label>
                    <p class="font-medium text-gray-800">{{ $user->phone ?? '-' }}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-500">الدور</label>
                    <p class="font-medium">
                        @if($user->role === 'super_admin')
                            <span class="px-3 py-1 rounded-full bg-purple-100 text-purple-800">مدير عام</span>
                        @elseif($user->role === 'admin')
                            <span class="px-3 py-1 rounded-full bg-blue-100 text-blue-800">مدير</span>
                        @elseif($user->role === 'reseller')
                            <span class="px-3 py-1 rounded-full bg-green-100 text-green-800">
                                <i class="fas fa-store ml-1"></i> وكيل/بائع
                            </span>
                        @elseif($user->role === 'operator')
                            <span class="px-3 py-1 rounded-full bg-yellow-100 text-yellow-800">مشغل</span>
                        @else
                            <span class="px-3 py-1 rounded-full bg-gray-100 text-gray-800">مشاهد</span>
                        @endif
                    </p>
                </div>
                <div>
                    <label class="text-sm text-gray-500">الحالة</label>
                    <p class="font-medium">
                        @if($user->is_active)
                            <span class="px-3 py-1 rounded-full bg-green-100 text-green-800">نشط</span>
                        @else
                            <span class="px-3 py-1 rounded-full bg-red-100 text-red-800">غير نشط</span>
                        @endif
                    </p>
                </div>
                <div>
                    <label class="text-sm text-gray-500">آخر تسجيل دخول</label>
                    <p class="font-medium text-gray-800">
                        {{ $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i') : 'لم يسجل دخول بعد' }}
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Assigned Routers -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-server text-green-600"></i> الراوترات المخصصة
            </h2>
            
            @if($user->role === 'super_admin')
                <div class="bg-purple-50 text-purple-700 p-4 rounded-lg">
                    <i class="fas fa-crown ml-2"></i>
                    المدير العام لديه صلاحية الوصول لجميع الراوترات
                </div>
            @elseif($user->routers->count() > 0)
                <div class="space-y-3">
                    @foreach($user->routers as $router)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-server text-blue-600"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">{{ $router->name }}</p>
                                <p class="text-sm text-gray-500">{{ $router->ip_address }}</p>
                            </div>
                        </div>
                        <span class="px-2 py-1 rounded text-xs {{ $router->is_online ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $router->is_online ? 'متصل' : 'غير متصل' }}
                        </span>
                    </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-server text-4xl mb-2 opacity-50"></i>
                    <p>لا توجد راوترات مخصصة لهذا المستخدم</p>
                </div>
            @endif
        </div>
        
        <!-- Activity Logs -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-history text-orange-600"></i> سجل النشاط
            </h2>
            
            @if($activityLogs->count() > 0)
                <div class="space-y-3">
                    @foreach($activityLogs as $log)
                    <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0 mt-1">
                            <i class="fas fa-clock text-blue-600 text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-gray-800">{{ $log->description }}</p>
                            <p class="text-sm text-gray-500 mt-1">
                                {{ $log->created_at->format('Y-m-d H:i') }}
                                @if($log->ip_address)
                                    <span class="mr-2">• {{ $log->ip_address }}</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-history text-4xl mb-2 opacity-50"></i>
                    <p>لا يوجد نشاط مسجل</p>
                </div>
            @endif
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="space-y-4">
        <!-- Quick Actions -->
        <div class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-medium text-gray-800 mb-3">إجراءات سريعة</h3>
            <div class="space-y-2">
                <a href="{{ route('users.edit', $user) }}" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm flex items-center justify-center">
                    <i class="fas fa-edit ml-1"></i> تعديل البيانات
                </a>
                @if($user->is_active)
                    <form action="{{ route('users.toggle-status', $user) }}" method="POST" onsubmit="return confirm('هل تريد تعطيل هذا المستخدم؟')">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="w-full bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg text-sm">
                            <i class="fas fa-ban ml-1"></i> تعطيل الحساب
                        </button>
                    </form>
                @else
                    <form action="{{ route('users.toggle-status', $user) }}" method="POST" onsubmit="return confirm('هل تريد تفعيل هذا المستخدم؟')">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm">
                            <i class="fas fa-check ml-1"></i> تفعيل الحساب
                        </button>
                    </form>
                @endif
                @if(auth()->id() !== $user->id)
                    <form action="{{ route('users.destroy', $user) }}" method="POST" onsubmit="return confirm('هل تريد حذف هذا المستخدم نهائياً؟')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm">
                            <i class="fas fa-trash ml-1"></i> حذف المستخدم
                        </button>
                    </form>
                @endif
            </div>
        </div>
        
        <!-- User Info Card -->
        <div class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-medium text-gray-800 mb-3">معلومات إضافية</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">تاريخ الإنشاء:</span>
                    <span class="font-medium">{{ $user->created_at->format('Y-m-d') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">آخر تحديث:</span>
                    <span class="font-medium">{{ $user->updated_at->format('Y-m-d') }}</span>
                </div>
                @if($user->last_login_ip)
                <div class="flex justify-between">
                    <span class="text-gray-500">آخر IP:</span>
                    <span class="font-medium">{{ $user->last_login_ip }}</span>
                </div>
                @endif
                <div class="flex justify-between">
                    <span class="text-gray-500">عدد الراوترات:</span>
                    <span class="font-medium">{{ $user->role === 'super_admin' ? 'الكل' : $user->routers->count() }}</span>
                </div>
            </div>
        </div>

        <!-- Reseller Info -->
        @if($user->role === 'reseller')
        <div class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-medium text-gray-800 mb-3">
                <i class="fas fa-store text-green-600 ml-1"></i>
                معلومات الوكيل
            </h3>
            <div class="space-y-3 text-sm">
                @if($user->company_name)
                <div class="flex justify-between">
                    <span class="text-gray-500">الشركة:</span>
                    <span class="font-medium">{{ $user->company_name }}</span>
                </div>
                @endif
                <div class="flex justify-between">
                    <span class="text-gray-500">نسبة العمولة:</span>
                    <span class="font-medium text-green-600">{{ $user->commission_rate }}%</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">الرصيد:</span>
                    <span class="font-bold text-blue-600">{{ number_format($user->balance, 0) }} د.ع</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">المشتركين:</span>
                    <span class="font-medium">
                        {{ $user->resellerSubscribers()->count() }}
                        @if($user->max_subscribers)
                            / {{ $user->max_subscribers }}
                        @endif
                    </span>
                </div>
                @if($user->address)
                <div class="pt-2 border-t">
                    <span class="text-gray-500 block mb-1">العنوان:</span>
                    <span class="font-medium text-gray-700">{{ $user->address }}</span>
                </div>
                @endif
            </div>
        </div>
        @endif
        
        <!-- Permissions -->
        @if($user->role !== 'super_admin' && $user->routers->count() > 0)
        <div class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-medium text-gray-800 mb-3">الصلاحيات</h3>
            @php
                $firstRouter = $user->routers->first();
                $pivot = $firstRouter ? $firstRouter->pivot : null;
            @endphp
            @if($pivot)
            <div class="space-y-2 text-sm">
                <div class="flex items-center gap-2">
                    <i class="fas {{ $pivot->can_add_users ? 'fa-check text-green-600' : 'fa-times text-red-600' }}"></i>
                    <span>إضافة مستخدمين</span>
                </div>
                <div class="flex items-center gap-2">
                    <i class="fas {{ $pivot->can_edit_users ? 'fa-check text-green-600' : 'fa-times text-red-600' }}"></i>
                    <span>تعديل مستخدمين</span>
                </div>
                <div class="flex items-center gap-2">
                    <i class="fas {{ $pivot->can_delete_users ? 'fa-check text-green-600' : 'fa-times text-red-600' }}"></i>
                    <span>حذف مستخدمين</span>
                </div>
                <div class="flex items-center gap-2">
                    <i class="fas {{ $pivot->can_view_reports ? 'fa-check text-green-600' : 'fa-times text-red-600' }}"></i>
                    <span>عرض التقارير</span>
                </div>
                <div class="flex items-center gap-2">
                    <i class="fas {{ $pivot->can_manage_hotspot ? 'fa-check text-green-600' : 'fa-times text-red-600' }}"></i>
                    <span>إدارة Hotspot</span>
                </div>
                <div class="flex items-center gap-2">
                    <i class="fas {{ $pivot->can_manage_ppp ? 'fa-check text-green-600' : 'fa-times text-red-600' }}"></i>
                    <span>إدارة PPP</span>
                </div>
            </div>
            @endif
        </div>
        @endif
    </div>
</div>
@endsection
