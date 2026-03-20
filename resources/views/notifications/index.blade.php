@extends('layouts.app')

@section('title', 'الإشعارات')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-gray-800 flex items-center gap-3">
                <span class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-bell text-white text-lg"></i>
                </span>
                الإشعارات
            </h1>
            <p class="text-gray-500 text-sm mt-1 mr-14">جميع الإشعارات والتنبيهات</p>
        </div>
        <div class="flex gap-2">
            @if($unreadCount > 0)
            <button onclick="markAllAsRead()" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition">
                <i class="fas fa-check-double ml-1"></i>
                تحديد الكل كمقروء ({{ $unreadCount }})
            </button>
            @endif
            <button onclick="clearRead()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium transition">
                <i class="fas fa-trash ml-1"></i>
                حذف المقروءة
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-4 shadow-sm border">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-bell text-blue-600"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800">{{ $notifications->total() }}</p>
                    <p class="text-xs text-gray-500">إجمالي الإشعارات</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm border">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-exclamation-circle text-red-600"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-red-600">{{ $unreadCount }}</p>
                    <p class="text-xs text-gray-500">غير مقروءة</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm border">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-sync-alt text-purple-600"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800">{{ $notifications->where('type', 'renewal')->count() }}</p>
                    <p class="text-xs text-gray-500">تجديدات</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm border">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-money-bill-wave text-green-600"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800">{{ $notifications->where('type', 'payment')->count() }}</p>
                    <p class="text-xs text-gray-500">دفعات</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications List -->
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        @forelse($notifications as $notification)
        <div id="notification-{{ $notification->id }}" class="p-4 border-b last:border-0 hover:bg-gray-50 transition {{ $notification->is_read ? '' : 'bg-blue-50' }}">
            <div class="flex items-start gap-4">
                <!-- Icon -->
                <div class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0
                    @switch($notification->color)
                        @case('purple') bg-purple-100 @break
                        @case('green') bg-green-100 @break
                        @case('red') bg-red-100 @break
                        @case('orange') bg-orange-100 @break
                        @default bg-blue-100
                    @endswitch">
                    <i class="fas {{ $notification->icon }}
                        @switch($notification->color)
                            @case('purple') text-purple-600 @break
                            @case('green') text-green-600 @break
                            @case('red') text-red-600 @break
                            @case('orange') text-orange-600 @break
                            @default text-blue-600
                        @endswitch text-lg"></i>
                </div>
                
                <!-- Content -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h3 class="font-bold text-gray-800">{{ $notification->title }}</h3>
                            <p class="text-gray-600 mt-1">{{ $notification->message }}</p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            @if(!$notification->is_read)
                            <span class="w-3 h-3 bg-blue-500 rounded-full animate-pulse"></span>
                            @endif
                            <button onclick="deleteNotification({{ $notification->id }})" class="text-gray-400 hover:text-red-500 transition">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Details -->
                    @if($notification->data)
                    <div class="mt-2 flex flex-wrap gap-2">
                        @if(isset($notification->data['reseller_name']))
                        <span class="px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs">
                            <i class="fas fa-user ml-1"></i>{{ $notification->data['reseller_name'] }}
                        </span>
                        @endif
                        @if(isset($notification->data['subscriber_username']))
                        <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">
                            <i class="fas fa-user-circle ml-1"></i>{{ $notification->data['subscriber_username'] }}
                        </span>
                        @endif
                        @if(isset($notification->data['amount']))
                        <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs">
                            <i class="fas fa-money-bill ml-1"></i>{{ number_format($notification->data['amount'], 0) }} ل.س
                        </span>
                        @endif
                    </div>
                    @endif
                    
                    <!-- Time -->
                    <p class="text-xs text-gray-400 mt-2">
                        <i class="fas fa-clock ml-1"></i>
                        {{ $notification->created_at->diffForHumans() }}
                        @if($notification->is_read && $notification->read_at)
                        <span class="mr-2">• قُرئ {{ $notification->read_at->diffForHumans() }}</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>
        @empty
        <div class="p-12 text-center text-gray-400">
            <i class="fas fa-bell-slash text-5xl mb-4"></i>
            <p class="text-lg">لا توجد إشعارات</p>
        </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($notifications->hasPages())
    <div class="mt-4">
        {{ $notifications->links() }}
    </div>
    @endif
</div>

@push('scripts')
<script>
function markAllAsRead() {
    fetch('{{ route("notifications.read-all") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        }
    }).then(() => location.reload());
}

function clearRead() {
    if (!confirm('هل تريد حذف جميع الإشعارات المقروءة؟')) return;
    
    fetch('{{ route("notifications.clear-read") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        }
    }).then(() => location.reload());
}

function deleteNotification(id) {
    fetch('/notifications/' + id, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        }
    }).then(() => {
        document.getElementById('notification-' + id).remove();
    });
}
</script>
@endpush
@endsection
