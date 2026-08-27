<x-storefront.layout :title="'Notifications'">
    <div class="mx-auto max-w-4xl px-4 py-8 sm:py-10">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('storefront.buyer.orders.index') }}" class="text-sm text-slate-500">← My orders</a>
                <h1 class="mt-2 text-2xl font-extrabold text-slate-900">Notifications</h1>
                <p class="mt-1 text-sm text-slate-500">Updates about your orders and buyer account.</p>
            </div>
            @if($buyer->unreadNotifications()->exists())
                <form method="POST" action="{{ route('storefront.buyer.notifications.read-all') }}">@csrf
                    <button class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 sm:w-auto">Mark all read</button>
                </form>
            @endif
        </div>

        <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="divide-y divide-slate-100">
                @forelse($notifications as $notification)
                    @php($data = $notification->data)
                    <form method="POST" action="{{ route('storefront.buyer.notifications.read', $notification->id) }}">
                        @csrf
                        <button class="flex w-full items-start gap-3 p-4 text-left transition hover:bg-slate-50 sm:p-5 {{ $notification->read_at ? '' : 'bg-emerald-50/50' }}">
                            <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $notification->read_at ? 'bg-slate-300' : 'bg-emerald-600' }}"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-bold text-slate-900">{{ $data['title'] ?? 'Order update' }}</span>
                                <span class="mt-1 block text-sm leading-6 text-slate-600">{{ $data['message'] ?? '' }}</span>
                                <span class="mt-2 block text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
                            </span>
                            <span class="shrink-0 text-emerald-700">→</span>
                        </button>
                    </form>
                @empty
                    <div class="px-5 py-12 text-center text-sm text-slate-500">You have no notifications yet.</div>
                @endforelse
            </div>
        </div>

        <div class="mt-6">{{ $notifications->links() }}</div>
    </div>
</x-storefront.layout>
