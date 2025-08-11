<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Favicon -->
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="icon" type="image/png" sizes="32x32" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🐷</text></svg>">
        <link rel="apple-touch-icon" sizes="180x180" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🐷</text></svg>">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Alpine.js -->
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

        <!-- Custom Scrollbar Styles -->
        <style>
            /* Custom scrollbar for sidebars */
            .scrollbar-thin {
                scrollbar-width: thin;
            }

            .scrollbar-thin::-webkit-scrollbar {
                width: 6px;
            }

            .scrollbar-thin::-webkit-scrollbar-track {
                background: #1C6C51;
                border-radius: 3px;
            }

            .scrollbar-thin::-webkit-scrollbar-thumb {
                background: #218c6a;
                border-radius: 3px;
            }

            .scrollbar-thin::-webkit-scrollbar-thumb:hover {
                background: #2da776;
            }

            /* Ensure smooth scrolling */
            .scrollbar-thin {
                scroll-behavior: smooth;
            }
        </style>

        <!-- Styles -->
        @livewireStyles
    </head>
    <body class="font-sans antialiased">
        <x-banner />

        <div class="min-h-screen bg-gray-100">
            @livewire('navigation-menu')

            <div class="flex min-h-screen pt-16">
                @php $user = Auth::user(); @endphp
                @if($user && $user->isAdmin())
                    <x-sidebar-admin />
                @elseif($user && $user->isManager())
                    <x-sidebar-manager />
                @elseif($user && $user->isSuperuser())
                    <x-sidebar-superuser />
                @elseif($user && $user->isDataEntry())
                    <x-sidebar-dataentry />
                @endif

                <div class="flex-1 ml-72 mt-4">
                    <!-- Page Heading -->
                    @if (isset($header))
                        <header class="bg-white shadow">
                            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                                {{ $header }}
                            </div>
                        </header>
                    @endif

                    <!-- Page Content -->
                    <main>
                        <!-- Global Flash Messages -->
                        <div class="flash-messages" x-data="{
                            init() {
                                // Add keyboard support for closing messages
                                document.addEventListener('keydown', (e) => {
                                    if (e.key === 'Escape') {
                                        // Close all visible messages
                                        this.$el.querySelectorAll('[x-data]').forEach(el => {
                                            if (el.__x && el.__x.$data.show) {
                                                el.__x.$data.show = false;
                                            }
                                        });
                                    }
                                });
                            }
                        }">
                            <!-- Close All Button (shows when multiple messages exist) -->
                            @php
                                $messageCount = collect(['success', 'error', 'warning', 'info'])->filter(fn($type) => session($type))->count();
                                $messageCount += $errors->any() ? 1 : 0;
                            @endphp

                            @if($messageCount > 1)
                                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
                                    <div class="flex justify-end">
                                        <button @click="$el.closest('.flash-messages').querySelectorAll('[x-data]').forEach(el => { if (el.__x && el.__x.$data.show) el.__x.$data.show = false; })"
                                                class="inline-flex items-center px-3 py-1 text-xs font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-md transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                                            <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                            Close All Messages
                                        </button>
                                    </div>
                                </div>
                            @endif

                            @if(session('success'))
                                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
                                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 shadow-sm"
                                         x-data="{ show: true }"
                                         x-show="show"
                                         x-transition:enter="transition ease-out duration-300"
                                         x-transition:enter-start="opacity-0 transform scale-95"
                                         x-transition:enter-end="opacity-100 transform scale-100"
                                         x-transition:leave="transition ease-in duration-200"
                                         x-transition:leave-start="opacity-100 transform scale-100"
                                         x-transition:leave-end="opacity-0 transform scale-95">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <svg class="h-5 w-5 text-green-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                                <span class="text-green-800 font-medium">{{ session('success') }}</span>
                                            </div>
                                            <button @click="show = false"
                                                    class="ml-4 flex-shrink-0 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-full p-1 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                                                    aria-label="Close message">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if(session('error'))
                                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
                                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 shadow-sm"
                                         x-data="{ show: true }"
                                         x-show="show"
                                         x-transition:enter="transition ease-out duration-300"
                                         x-transition:enter-start="opacity-0 transform scale-95"
                                         x-transition:enter-end="opacity-100 transform scale-100"
                                         x-transition:leave="transition ease-in duration-200"
                                         x-transition:leave-start="opacity-100 transform scale-100"
                                         x-transition:leave-end="opacity-0 transform scale-95">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <svg class="h-5 w-5 text-red-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                <span class="text-red-800 font-medium">{{ session('error') }}</span>
                                            </div>
                                            <button @click="show = false"
                                                    class="ml-4 flex-shrink-0 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-full p-1 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                                    aria-label="Close message">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if(session('warning'))
                                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
                                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 shadow-sm"
                                         x-data="{ show: true }"
                                         x-show="show"
                                         x-transition:enter="transition ease-out duration-300"
                                         x-transition:enter-start="opacity-0 transform scale-95"
                                         x-transition:enter-end="opacity-100 transform scale-100"
                                         x-transition:leave="transition ease-in duration-200"
                                         x-transition:leave-start="opacity-100 transform scale-100"
                                         x-transition:leave-end="opacity-0 transform scale-95">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <svg class="h-5 w-5 text-yellow-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                                </svg>
                                                <span class="text-yellow-800 font-medium">{{ session('warning') }}</span>
                                            </div>
                                            <button @click="show = false"
                                                    class="ml-4 flex-shrink-0 text-yellow-600 hover:text-yellow-800 hover:bg-yellow-100 rounded-full p-1 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2"
                                                    aria-label="Close message">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if(session('info'))
                                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
                                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 shadow-sm"
                                         x-data="{ show: true }"
                                         x-show="show"
                                         x-transition:enter="transition ease-out duration-300"
                                         x-transition:enter-start="opacity-0 transform scale-95"
                                         x-transition:enter-end="opacity-100 transform scale-100"
                                         x-transition:leave="transition ease-in duration-200"
                                         x-transition:leave-start="opacity-100 transform scale-100"
                                         x-transition:leave-end="opacity-0 transform scale-95">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <svg class="h-5 w-5 text-blue-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                <span class="text-blue-800 font-medium">{{ session('info') }}</span>
                                            </div>
                                            <button @click="show = false"
                                                    class="ml-4 flex-shrink-0 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-full p-1 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                                    aria-label="Close message">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($errors->any())
                                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
                                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 shadow-sm"
                                         x-data="{ show: true }"
                                         x-show="show"
                                         x-transition:enter="transition ease-out duration-300"
                                         x-transition:enter-start="opacity-0 transform scale-95"
                                         x-transition:enter-end="opacity-100 transform scale-100"
                                         x-transition:leave="transition ease-in duration-200"
                                         x-transition:leave-start="opacity-100 transform scale-100"
                                         x-transition:leave-end="opacity-0 transform scale-95">
                                        <div class="flex items-start justify-between">
                                            <div class="flex items-start">
                                                <svg class="h-5 w-5 text-red-600 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                <div class="flex-1">
                                                    <h3 class="text-red-800 font-medium mb-2">Please correct the following errors:</h3>
                                                    <ul class="list-disc list-inside text-red-700 space-y-1">
                                                        @foreach($errors->all() as $error)
                                                            <li class="text-sm">{{ $error }}</li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            </div>
                                            <button @click="show = false"
                                                    class="ml-4 flex-shrink-0 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-full p-1 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                                    aria-label="Close error messages">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{ $slot }}
                    </main>
                </div>
            </div>
        </div>

        @stack('modals')

        @livewireScripts
    </body>
</html>
