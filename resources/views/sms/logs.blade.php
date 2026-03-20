@extends('layouts.app')

@section('title', 'سجل الرسائل - ' . $router->name)

@section('content')
<div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-6 py-4 sm:py-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
        <div>
            <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-list text-indigo-500"></i> سجل الرسائل
            </h1>
            <p class="text-sm text-gray-500 mt-1">{{ $router->name }}</p>
        </div>
        <a href="{{ route('sms.index', $router) }}" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">
            <i class="fas fa-arrow-right ml-1"></i> رجوع
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border-r-4 border-green-500 text-green-700 p-3 rounded-lg mb-4 text-sm">
            <i class="fas fa-check-circle ml-2"></i>{{ session('success') }}
        </div>
    @endif

    @if(isset($logStats))
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
        <div class="bg-blue-50 rounded-lg p-3 text-center">
            <div class="text-2xl font-bold text-blue-600">{{ $logStats['total'] }}</div>
            <div class="text-xs text-gray-600">الإجمالي</div>
        </div>
        <div class="bg-green-50 rounded-lg p-3 text-center">
            <div class="text-2xl font-bold text-green-600">{{ $logStats['sent'] }}</div>
            <div class="text-xs text-gray-600">تم إرسالها</div>
        </div>
        <div class="bg-red-50 rounded-lg p-3 text-center">
            <div class="text-2xl font-bold text-red-600">{{ $logStats['failed'] }}</div>
            <div class="text-xs text-gray-600">فشلت</div>
        </div>
        <div class="bg-purple-50 rounded-lg p-3 text-center">
            <div class="text-2xl font-bold text-purple-600">{{ ($logStats['by_type']['manual'] ?? 0) + ($logStats['by_type']['welcome'] ?? 0) }}</div>
            <div class="text-xs text-gray-600">يدوي + ترحيب</div>
        </div>
    </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
        <form method="GET" class="flex flex-wrap gap-2 items-end">
            <div>
                <label class="text-xs text-gray-500">الحالة</label>
                <select name="status" class="px-2 py-1.5 border rounded text-sm">
                    <option value="">الكل</option>
                    <option value="sent" {{ request('status') == 'sent' ? 'selected' : '' }}>تم الإرسال</option>
                    <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>فشل</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>قيد الانتظار</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500">النوع</label>
                <select name="type" class="px-2 py-1.5 border rounded text-sm">
                    <option value="">الكل</option>
                    <option value="manual" {{ request('type') == 'manual' ? 'selected' : '' }}>يدوي</option>
                    <option value="reminder" {{ request('type') == 'reminder' ? 'selected' : '' }}>تذكير</option>
                    <option value="welcome" {{ request('type') == 'welcome' ? 'selected' : '' }}>ترحيب</option>
                    <option value="renewal" {{ request('type') == 'renewal' ? 'selected' : '' }}>تجديد</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500">من</label>
                <input type="date" name="from" value="{{ request('from') }}" class="px-2 py-1.5 border rounded text-sm">
            </div>
            <div>
                <label class="text-xs text-gray-500">إلى</label>
                <input type="date" name="to" value="{{ request('to') }}" class="px-2 py-1.5 border rounded text-sm">
            </div>
            <div>
                <label class="text-xs text-gray-500">بحث</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="رقم هاتف..." class="px-2 py-1.5 border rounded text-sm" dir="ltr">
            </div>
            <button type="submit" class="px-3 py-1.5 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                <i class="fas fa-search"></i>
            </button>
            <a href="{{ route('sms.logs', $router) }}" class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300">مسح</a>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-right">الرقم</th>
                        <th class="px-3 py-2 text-right">المشترك</th>
                        <th class="px-3 py-2 text-right">الرسالة</th>
                        <th class="px-3 py-2 text-center">النوع</th>
                        <th class="px-3 py-2 text-center">الحالة</th>
                        <th class="px-3 py-2 text-right">التاريخ</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($logs as $log)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 font-mono text-xs" dir="ltr">{{ $log->phone_number }}</td>
                        <td class="px-3 py-2 text-xs">{{ $log->subscriber ? ($log->subscriber->full_name ?: $log->subscriber->username) : '-' }}</td>
                        <td class="px-3 py-2 text-xs max-w-[200px] truncate" title="{{ $log->message }}">{{ Str::limit($log->message, 40) }}</td>
                        <td class="px-3 py-2 text-center">
                            <span class="text-[10px] px-1.5 py-0.5 rounded {{ $log->type === 'welcome' ? 'bg-green-100 text-green-700' : ($log->type === 'reminder' ? 'bg-orange-100 text-orange-700' : ($log->type === 'renewal' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700')) }}">{{ $log->type_text }}</span>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <span class="text-[10px] px-1.5 py-0.5 rounded-full {{ $log->getStatusBadgeClass() }}">{{ $log->status_text }}</span>
                            @if($log->error_message)
                                <div class="text-[10px] text-red-500 mt-0.5">{{ Str::limit($log->error_message, 30) }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-500">{{ $log->created_at->format('m/d H:i') }}</td>
                        <td class="px-3 py-2">
                            <form action="{{ route('sms.logs.delete', [$router, $log]) }}" method="POST" onsubmit="return confirm('حذف؟')">
                                @csrf @method('DELETE')
                                <button class="text-red-400 hover:text-red-600"><i class="fas fa-trash text-xs"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-3 py-8 text-center text-gray-400">لا توجد سجلات</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3 border-t">{{ $logs->appends(request()->query())->links() }}</div>
    </div>
</div>
@endsection
