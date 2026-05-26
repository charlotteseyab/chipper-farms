<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-10">

        <!-- Notification List Container -->
        <div class="bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden">
             <!-- Header and Controls -->
        <div class="flex items-center justify-between mb-6 pt-4">
            <!-- <h1 class="text-3xl font-extrabold text-gray-900">Farm Notifications</h1> -->
            <form action="{{ route('notifications.readAll') }}" method="POST">
                @csrf
                <button class="px-4 py-2 ml-2 text-sm rounded-full bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium transition duration-150 shadow-sm flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Mark all as read
                </button>
            </form>
        </div>
            <ul class="divide-y divide-gray-100">
                @forelse($notifications as $notification)
                    @php 
                        $data = $notification->data; 
                        $isUnread = is_null($notification->read_at); 
                        $type = $data['type'] ?? 'notification';

                        // Icon and color mapping for clarity
                        $iconClass = 'text-gray-500';
                        $iconPath = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.427 6 9.125 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v2a3 3 0 11-6 0v-2m6 0H9"/>';
                        
                        if (Str::contains($type, ['birth', 'farrowing'])) {
                            $iconPath = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6m-6 0h-2M15 5a2 2 0 012 2v3M9 5a2 2 0 002 2v3m-2-5a2 2 0 012-2M12 11v1"/>'; // Piglet icon (abstract)
                            $iconClass = 'text-pink-500';
                        } elseif ($type === 'feed_purchase_request') {
                            $iconPath = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>'; // Shopping cart icon
                            $iconClass = 'text-green-600';
                        } elseif (Str::contains($type, 'inventory')) {
                            $iconPath = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10h16V7M4 7h16M4 7L12 3l8 4"/>'; // Box/Stock icon
                            $iconClass = 'text-blue-500';
                        } elseif (Str::contains($type, 'weigh')) {
                            $iconPath = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10l-2 3M14 10a5 5 0 100-8 5 5 0 000 8zM14 10v3M10 17l-2 3m2-3h4m0-7v3M10 17a5 5 0 10-8 0 5 5 0 008 0z"/>'; // Scale/Weight icon
                            $iconClass = 'text-yellow-600';
                        }
                    @endphp

                    <li class="p-4 sm:p-6 transition duration-200" 
                        x-data="{ localRead: {{ $isUnread ? 'false' : 'true' }} }" 
                        :class="localRead ? 'hover:bg-gray-50' : 'bg-indigo-50 hover:bg-indigo-100'"
                    >
                        <div class="flex items-start">
                            <!-- Icon -->
                            <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center mr-4 
                                        {{ $isUnread ? 'bg-white border-2 border-indigo-300' : 'bg-gray-100' }}">
                                <svg class="w-5 h-5 {{ $iconClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    {!! $iconPath !!}
                                </svg>
                            </div>

                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <!-- Main Title -->
                                        <div class="text-base font-semibold text-gray-900 truncate">
                                            @if(isset($data['title']))
                                                {{ $data['title'] }}
                                            @else
                                                {{ ucfirst(str_replace('_',' ', $type)) }} Alert
                                            @endif
                                            <span class="text-sm font-medium text-indigo-600 ml-1" x-show="!localRead">• New</span>
                                        </div>
                                        
                                        <!-- Subtitle/Context -->
                                        <div class="text-sm text-gray-600 mt-1">
                                            @if($type === 'feed_purchase_request')
                                                {{-- Purchase Request Notifications --}}
                                                @if(isset($data['body']))
                                                    {{ $data['body'] }}
                                                @elseif(isset($data['item_name']))
                                                    <span class="font-medium text-gray-800">{{ $data['title'] ?? 'Purchase Request' }}</span>
                                                    @if(isset($data['item_name']))
                                                        <br><span class="text-gray-600">{{ $data['item_name'] }}</span>
                                                        @if(isset($data['quantity']))
                                                            <span class="text-gray-500">({{ $data['quantity'] }})</span>
                                                        @endif
                                                    @endif
                                                    @if(isset($data['cost']))
                                                        <br><span class="text-gray-500">Cost: {{ $data['cost'] }}</span>
                                                    @endif
                                                @endif
                                            @elseif(isset($data['ear_tag']))
                                                {{-- Pig/Weight Notifications --}}
                                                Pig: <a href="{{ route('pigs.show', $data['pig_id']) ?? '#' }}" class="font-medium text-gray-800 hover:text-indigo-600">{{ $data['ear_tag'] }}</a>
                                                @if(isset($data['days_since']) || isset($data['last_weighed']))
                                                    <span class="ml-2 text-yellow-600">
                                                        <span class="font-semibold">Weight Check Needed:</span> 
                                                        @if(isset($data['days_since']))
                                                            Last weighed {{ $data['days_since'] }} days ago
                                                        @elseif(isset($data['last_weighed']))
                                                            Since {{ \Carbon\Carbon::parse($data['last_weighed'])->format('M j, Y') }}
                                                        @endif
                                                    </span>
                                                @endif
                                            @elseif(isset($data['item_name']) && $type !== 'feed_purchase_request')
                                                {{-- Inventory Low (but not purchase requests) --}}
                                                Inventory Low: <span class="font-medium text-red-600">{{ $data['item_name'] }}</span> (Only {{ $data['quantity'] ?? 'N/A' }} left)
                                            @elseif(isset($data['feed_name']))
                                                {{-- Feed Stock Notifications --}}
                                                Feed Stock: <span class="font-medium text-orange-600">{{ $data['feed_name'] }}</span> (Stock: {{ $data['current_stock'] ?? 'N/A' }} kg)
                                            @elseif(isset($data['body']))
                                                {{-- Generic body text --}}
                                                {{ $data['body'] }}
                                            @elseif(isset($data['title']))
                                                {{-- Fallback to title --}}
                                                {{ $data['title'] }}
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <div class="flex-shrink-0 flex items-center gap-3 ml-4">
                                        @if(isset($data['link']))
                                            <a href="{{ $data['link'] }}" class="px-3 py-1.5 text-xs rounded-full bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition shadow-md">
                                                {{ $data['action_text'] ?? 'View Record' }}
                                            </a>
                                        @endif
                                        
                                        <!-- Mark Read Button -->
                                        <form x-show="!localRead" x-on:submit.prevent="
                                                fetch('{{ route('notifications.read', $notification->id) }}', { 
                                                    method: 'POST', 
                                                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                                                })
                                                .then(response => { if(response.ok) localRead = true; })
                                            "
                                            method="POST">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 text-xs rounded-full bg-white border border-gray-300 text-gray-700 hover:bg-gray-100 transition shadow-sm">
                                                Mark Read
                                            </button>
                                        </form>

                                        <!-- Read Status -->
                                        <span x-show="localRead" class="px-3 py-1.5 text-xs rounded-full bg-gray-100 text-gray-500 font-medium">Read</span>
                                    </div>
                                </div>
                                
                                <!-- Timestamp -->
                                <div class="text-xs text-gray-400 mt-2">
                                    <span class="font-medium">Received:</span> {{ $notification->created_at->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="p-12 text-center">
                        <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900">All caught up!</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            You have no new notifications.
                        </p>
                    </li>
                @endforelse
            </ul>
            
            <!-- Pagination -->
            @if ($notifications->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-600">
                            Showing <span class="font-semibold">{{ $notifications->firstItem() }}</span> to <span class="font-semibold">{{ $notifications->lastItem() }}</span> of <span class="font-semibold">{{ $notifications->total() }}</span> results
                        </div>
                        <div class="flex items-center space-x-2">
                            <x-simple-pagination :paginator="$notifications" />
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>