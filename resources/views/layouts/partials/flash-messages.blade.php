@php
    $messages = [
        'success' => session('success') ?? session('status'),
        'error' => session('error'),
        'warning' => session('warning'),
        'info' => session('info'),
    ];

    $styles = [
        'success' => [
            'wrapper' => 'border-green-200 bg-green-50 text-green-900',
            'icon' => 'fa-solid fa-circle-check text-green-600',
            'title' => 'Success',
            'auto' => true,
        ],
        'error' => [
            'wrapper' => 'border-red-200 bg-red-50 text-red-900',
            'icon' => 'fa-solid fa-circle-exclamation text-red-600',
            'title' => 'Something needs attention',
            'auto' => false,
        ],
        'warning' => [
            'wrapper' => 'border-yellow-200 bg-yellow-50 text-yellow-900',
            'icon' => 'fa-solid fa-triangle-exclamation text-yellow-600',
            'title' => 'Please review',
            'auto' => false,
        ],
        'info' => [
            'wrapper' => 'border-blue-200 bg-blue-50 text-blue-900',
            'icon' => 'fa-solid fa-circle-info text-blue-600',
            'title' => 'For your information',
            'auto' => true,
        ],
    ];
@endphp

@if(collect($messages)->filter()->isNotEmpty() || $errors->any())
    <div class="mb-5 space-y-3">
        @foreach($messages as $type => $message)
            @continue(blank($message))
            <div data-flash-item data-auto-dismiss="{{ $styles[$type]['auto'] ? 'true' : 'false' }}" class="rounded-2xl border px-4 py-4 shadow-sm {{ $styles[$type]['wrapper'] }}">
                <div class="flex items-start gap-3">
                    <i class="{{ $styles[$type]['icon'] }} mt-0.5"></i>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold">{{ $styles[$type]['title'] }}</p>
                        <p class="mt-1 text-sm leading-6">{{ $message }}</p>
                    </div>
                    <button type="button" data-flash-dismiss class="rounded-xl p-1.5 text-inherit/70 transition hover:bg-white/50 hover:text-inherit" aria-label="Dismiss message">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
        @endforeach

        @if($errors->any())
            <div data-flash-item class="rounded-2xl border border-red-200 bg-red-50 px-4 py-4 text-red-900 shadow-sm">
                <div class="flex items-start gap-3">
                    <i class="fa-solid fa-circle-exclamation mt-0.5 text-red-600"></i>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold">Please correct the following</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm leading-6">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <button type="button" data-flash-dismiss class="rounded-xl p-1.5 text-red-700/70 transition hover:bg-white/50 hover:text-red-900" aria-label="Dismiss validation errors">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
        @endif
    </div>
@endif