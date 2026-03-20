@extends('layouts.app')

@section('title', 'الجلسات النشطة')

@section('content')
<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">الجلسات النشطة - UserManager</h1>
            <p class="text-gray-600">{{ $router->name }}</p>
        </div>
        <a href="{{ route('usermanager.index', ['router_id' => $router->id]) }}" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-right ml-1"></i> رجوع
        </a>
    </div>
</div>

<!-- Session Stats -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border-r-4 border-blue-500">
        <p class="text-3xl font-bold text-blue-600">{{ count($sessions) }}</p>
        <p class="text-gray-500 text-sm">جلسات نشطة</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border-r-4 border-green-500">
        @php
            $totalDownload = array_sum(array_column($sessions, 'download'));
        @endphp
        <p class="text-3xl font-bold text-green-600">{{ number_format($totalDownload / 1048576, 2) }}</p>
        <p class="text-gray-500 text-sm">MB تحميل</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border-r-4 border-purple-500">
        @php
            $totalUpload = array_sum(array_column($sessions, 'upload'));
        @endphp
        <p class="text-3xl font-bold text-purple-600">{{ number_format($totalUpload / 1048576, 2) }}</p>
        <p class="text-gray-500 text-sm">MB رفع</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border-r-4 border-orange-500">
        @php
            $totalUptime = array_sum(array_column($sessions, 'uptime'));
        @endphp
        <p class="text-3xl font-bold text-orange-600">{{ gmdate('H:i', $totalUptime) }}</p>
        <p class="text-gray-500 text-sm">إجمالي الوقت</p>
    </div>
</div>

<!-- Sessions Table -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    @if(count($sessions) > 0)
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">المستخدم</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">IP</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">MAC</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">الوقت</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">التحميل</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">الرفع</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">إجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach($sessions as $session)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800">
                        {{ $session['username'] ?? 'غير معروف' }}
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        {{ $session['ip'] ?? '-' }}
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500 font-mono">
                        {{ $session['mac'] ?? '-' }}
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        @if(isset($session['uptime']))
                            @php
                                $hours = floor($session['uptime'] / 3600);
                                $minutes = floor(($session['uptime'] % 3600) / 60);
                            @endphp
                            <span class="text-orange-600 font-medium">
                                {{ $hours }}h {{ $minutes }}m
                            </span>
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm">
                        @if(isset($session['download']))
                            @php
                                $downloadMB = $session['download'] / 1048576;
                            @endphp
                            <span class="text-green-600 font-medium">
                                ↓ {{ number_format($downloadMB, 2) }} MB
                            </span>
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm">
                        @if(isset($session['upload']))
                            @php
                                $uploadMB = $session['upload'] / 1048576;
                            @endphp
                            <span class="text-blue-600 font-medium">
                                ↑ {{ number_format($uploadMB, 2) }} MB
                            </span>
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if(isset($session['.id']))
                        <button onclick="disconnectSession('{{ $session['.id'] }}')" 
                                class="text-red-600 hover:text-red-800 text-sm" 
                                title="قطع الاتصال">
                            <i class="fas fa-times-circle"></i> قطع
                        </button>
                        @else
                        -
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="p-8 text-center text-gray-500">
        <i class="fas fa-plug text-4xl text-gray-300 mb-3"></i>
        <p>لا يوجد جلسات نشطة حالياً</p>
    </div>
    @endif
</div>

<script>
function disconnectSession(sessionId) {
    if (!confirm('هل تريد قطع اتصال هذه الجلسة؟')) return;
    
    fetch(`/usermanager/{{ $router->id }}/sessions/${sessionId}/disconnect`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) location.reload();
    })
    .catch(() => alert('حدث خطأ أثناء قطع الاتصال'));
}
</script>
@endsection
