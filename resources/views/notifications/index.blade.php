<x-app-layout>
    @php
        $user = auth()->user();
        $unreadCount = $user->unreadNotifications()->count();
        $totalCount = $user->notifications()->count();
        $pageModules = $notifications->getCollection()
            ->map(fn ($notification) => $notification->data['module'] ?? null)
            ->filter()->unique()->count();
    @endphp

    <div class="mx-auto w-full max-w-6xl py-3 sm:py-6">
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-950 via-emerald-900 to-emerald-700 px-5 py-7 text-white shadow-xl sm:px-8 sm:py-9">
            <div class="pointer-events-none absolute -right-16 -top-20 h-56 w-56 rounded-full bg-white/10 blur-2xl"></div>
            <div class="pointer-events-none absolute -bottom-24 left-1/3 h-48 w-48 rounded-full bg-emerald-300/10 blur-2xl"></div>

            <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <div class="mb-3 flex items-center gap-3">
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/20">
                            <x-ui-icon name="notification" class="h-6 w-6" />
                        </span>
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-emerald-100">Notification centre</p>
                    </div>
                    <h1 class="text-2xl font-black tracking-tight sm:text-4xl">Updates that need your attention</h1>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-emerald-100 sm:text-base">A private activity feed for your farm and the modules you are allowed to access.</p>
                </div>

                @if($unreadCount > 0)
                    <form action="{{ route('notifications.readAll') }}" method="POST">
                        @csrf
                        <button class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-emerald-900 shadow-lg transition hover:-translate-y-0.5 hover:bg-emerald-50 sm:w-auto">
                            <i class="fa-solid fa-check-double"></i>
                            Mark all as read
                        </button>
                    </form>
                @endif
            </div>

            <div class="relative mt-7 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-100">Unread</p>
                    <p class="mt-1 text-2xl font-black">{{ number_format($unreadCount) }}</p>
                </div>
                <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-100">All notifications</p>
                    <p class="mt-1 text-2xl font-black">{{ number_format($totalCount) }}</p>
                </div>
                <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-100">Modules on this page</p>
                    <p class="mt-1 text-2xl font-black">{{ number_format($pageModules) }}</p>
                </div>
            </div>
        </section>

        <div class="mt-7 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-extrabold text-slate-900 sm:text-xl">Recent activity</h2>
                <p class="mt-1 text-sm text-slate-500">Newest updates appear first.</p>
            </div>
            @if($unreadCount === 0 && $totalCount > 0)
                <span class="rounded-full bg-emerald-100 px-3 py-1.5 text-xs font-bold text-emerald-700">All caught up</span>
            @endif
        </div>

        <div class="mt-4 space-y-3">
            @forelse($notifications as $notification)
                @php
                    $data = $notification->data;
                    $type = $data['type'] ?? class_basename($notification->type);
                    $title = $data['title'] ?? ucfirst(str_replace(['_', '.'], ' ', $type));
                    $message = $data['message'] ?? $data['body'] ?? null;
                    $module = $data['module'] ?? match(true) {
                        str_contains($type, 'weight') => 'Weight management',
                        str_contains($type, 'gestation'), str_contains($type, 'weaning') => 'Breeding and lifecycle',
                        str_contains($type, 'quote'), str_contains($type, 'storefront') => 'Storefront',
                        str_contains($type, 'feed'), str_contains($type, 'purchase') => 'Feed and procurement',
                        default => 'Farm operations',
                    };
                    $severity = $data['severity'] ?? $data['color'] ?? 'info';
                    [$iconTone, $badgeTone, $icon] = match($severity) {
                        'high', 'critical', 'red', 'error' => ['bg-red-100 text-red-700', 'bg-red-50 text-red-700 ring-red-200', 'fa-triangle-exclamation'],
                        'medium', 'warning', 'yellow', 'orange' => ['bg-amber-100 text-amber-700', 'bg-amber-50 text-amber-700 ring-amber-200', 'fa-circle-exclamation'],
                        'success', 'green' => ['bg-emerald-100 text-emerald-700', 'bg-emerald-50 text-emerald-700 ring-emerald-200', 'fa-circle-check'],
                        default => ['bg-blue-100 text-blue-700', 'bg-blue-50 text-blue-700 ring-blue-200', 'fa-bell'],
                    };
                    $link = filled($data['link'] ?? null) ? $data['link'] : null;
                @endphp

                <article class="relative overflow-hidden rounded-2xl border bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:p-5 {{ $notification->read_at ? 'border-slate-200' : 'border-emerald-200 ring-1 ring-emerald-100' }}">
                    @if(!$notification->read_at)<span class="absolute inset-y-0 left-0 w-1 bg-emerald-500"></span>@endif
                    <div class="flex items-start gap-3 sm:gap-4">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ $iconTone }}">
                            <i class="fa-solid {{ $icon }}"></i>
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-base font-extrabold text-slate-900">{{ $title }}</h3>
                                        @if(!$notification->read_at)
                                            <span class="rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-white">New</span>
                                        @endif
                                    </div>
                                    <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset {{ $badgeTone }}">{{ $module }}</span>
                                </div>
                                <div class="shrink-0 text-left sm:text-right">
                                    <time class="block text-xs font-semibold text-slate-500" datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</time>
                                    <span class="mt-1 block text-[11px] text-slate-400">{{ $notification->created_at->format('d M Y, g:i A') }}</span>
                                </div>
                            </div>

                            @if($message)
                                <p class="mt-3 max-w-4xl text-sm leading-6 text-slate-600">{{ $message }}</p>
                            @endif

                            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                                @if($link)
                                    <a href="{{ $link }}" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-800">
                                        {{ $data['action_text'] ?? 'View details' }} <i class="fa-solid fa-arrow-right text-xs"></i>
                                    </a>
                                @endif
                                @if(!$notification->read_at)
                                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                        @csrf
                                        <button class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-600 transition hover:bg-slate-50 sm:w-auto">
                                            <i class="fa-solid fa-check text-xs"></i> Mark as read
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-5 py-16 text-center shadow-sm">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700"><i class="fa-solid fa-check-double text-2xl"></i></div>
                    <h3 class="mt-5 text-lg font-extrabold text-slate-800">You’re all caught up</h3>
                    <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-slate-500">Relevant farm activity, approvals and reminders will appear here when there is something to review.</p>
                </div>
            @endforelse
        </div>

        @if($notifications->hasPages())
            <div class="mt-7 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">{{ $notifications->links() }}</div>
        @endif
    </div>
</x-app-layout>
