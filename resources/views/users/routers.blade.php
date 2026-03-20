@extends('layouts.app')

@section('title', 'إدارة راوترات - ' . $user->name)

@section('content')
<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">إدارة راوترات {{ $user->name }}</h1>
            <p class="text-gray-600">تخصيص الراوترات التي يمكن للمستخدم الوصول إليها</p>
        </div>
        <a href="{{ route('users.show', $user) }}" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-right ml-1"></i> رجوع
        </a>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm p-6">
    <form action="{{ route('users.routers.update', $user) }}" method="POST">
        @csrf
        
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-gray-800">الراوترات المتاحة</h2>
                <div class="flex gap-2">
                    <button type="button" onclick="selectAll()" class="text-sm text-blue-600 hover:text-blue-800">
                        <i class="fas fa-check-double ml-1"></i> تحديد الكل
                    </button>
                    <button type="button" onclick="deselectAll()" class="text-sm text-red-600 hover:text-red-800">
                        <i class="fas fa-times ml-1"></i> إلغاء الكل
                    </button>
                </div>
            </div>
            
            @if($routers->isEmpty())
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-server text-4xl mb-2 opacity-50"></i>
                    <p>لا توجد راوترات مضافة</p>
                </div>
            @else
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($routers as $router)
                    <label class="flex items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer border-2 transition-colors {{ in_array($router->id, $assignedRouterIds) ? 'border-blue-500 bg-blue-50' : 'border-transparent' }}">
                        <input type="checkbox" name="routers[]" value="{{ $router->id }}"
                               {{ in_array($router->id, $assignedRouterIds) ? 'checked' : '' }}
                               class="router-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                               onchange="updateStyle(this)">
                        <div class="mr-3 flex-1">
                            <p class="font-medium text-gray-800">{{ $router->name }}</p>
                            <p class="text-sm text-gray-500">{{ $router->ip_address }}</p>
                        </div>
                        <span class="px-2 py-1 rounded text-xs {{ $router->is_online ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $router->is_online ? 'متصل' : 'غير متصل' }}
                        </span>
                    </label>
                    @endforeach
                </div>
            @endif
        </div>
        
        <div class="flex justify-end gap-3 pt-4 border-t">
            <a href="{{ route('users.show', $user) }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                إلغاء
            </a>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-save ml-1"></i> حفظ التغييرات
            </button>
        </div>
    </form>
</div>

<script>
function selectAll() {
    document.querySelectorAll('.router-checkbox').forEach(cb => {
        cb.checked = true;
        updateStyle(cb);
    });
}

function deselectAll() {
    document.querySelectorAll('.router-checkbox').forEach(cb => {
        cb.checked = false;
        updateStyle(cb);
    });
}

function updateStyle(checkbox) {
    const label = checkbox.closest('label');
    if (checkbox.checked) {
        label.classList.add('border-blue-500', 'bg-blue-50');
        label.classList.remove('border-transparent');
    } else {
        label.classList.remove('border-blue-500', 'bg-blue-50');
        label.classList.add('border-transparent');
    }
}
</script>
@endsection
