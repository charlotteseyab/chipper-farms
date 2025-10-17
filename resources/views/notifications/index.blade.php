<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 mb-10">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-bold text-gray-900">Notifications</h1>
            <form action="{{ route('notifications.readAll') }}" method="POST">
                @csrf
                <button class="px-3 py-1.5 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">Mark all as read</button>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <ul class="divide-y divide-gray-200">
                @forelse($notifications as $notification)
                    @php $data = $notification->data; $isUnread = is_null($notification->read_at); @endphp
                    <li class="p-4" x-data="{ localRead: {{ $isUnread ? 'false' : 'true' }} }" :class="localRead ? '' : 'bg-gray-50'">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="text-sm text-gray-700">
                                    <span class="font-semibold">{{ ucfirst(str_replace('_',' ', $data['type'] ?? 'notification')) }}</span>
                                    • Pig <span class="font-medium">{{ $data['ear_tag'] ?? 'N/A' }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    @if(isset($data['days_since']))
                                        Last weighed {{ $data['days_since'] }} days ago
                                    @elseif(isset($data['last_weighed']))
                                        Last weighed on {{ \Carbon\Carbon::parse($data['last_weighed'])->format('M j, Y') }}
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                @if(isset($data['link']))
                                    <a href="{{ $data['link'] }}" class="px-3 py-1.5 text-xs rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Record Weight</a>
                                @endif
                                <form x-show="!localRead" x-on:submit.prevent="
                                        fetch('{{ route('notifications.read', $notification->id) }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }})
                                            .then(() => { localRead = true; })
                                    "
                                    method="POST">
                                    @csrf
                                    <button class="px-3 py-1.5 text-xs rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">Mark read</button>
                                </form>
                                <span x-show="localRead" class="px-3 py-1.5 text-xs rounded-lg bg-gray-100 text-gray-500">Read</span>
                            </div>
                        </div>
                        <div class="text-xs text-gray-400 mt-2">
                            {{ $notification->created_at->diffForHumans() }}
                        </div>
                    </li>
                @empty
                    <li class="p-10 text-center text-sm text-gray-500">No notifications</li>
                @endforelse
            </ul>
            
            {{-- PAGINATION MOVED HERE --}}
            @if ($notifications->hasPages())
                <div class="px-4 py-4 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing {{ $notifications->firstItem() }} to {{ $notifications->lastItem() }} of {{ $notifications->total() }} results
                        </div>
                        <div class="flex items-center space-x-2">
                            <x-simple-pagination :paginator="$notifications" />
                        </div>
                    </div>
                </div>
            @endif
            {{-- END PAGINATION --}}
        </div>

        {{-- The old, empty pagination div is removed --}}
    </div>
    <x-copyright-minimal />
</x-app-layout>