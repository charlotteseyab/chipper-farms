<x-app-layout>
    <main class="min-h-screen bg-slate-50/70 py-2">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- <div class="mb-6">
                <div class="rounded-3xl bg-gradient-to-r from-[#1C6C51] via-[#238b67] to-[#2aa578] p-6 md:p-8 text-white shadow-lg">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-sm font-medium text-white/80 mb-2">System Configuration</p>
                            <h1 class="text-2xl md:text-3xl font-bold tracking-tight">Email Settings</h1>
                            <p class="mt-2 text-sm md:text-base text-white/85 max-w-3xl">
                                Configure your mail server, test delivery, and manage email templates used across the system.
                            </p>
                        </div>

                        <div class="rounded-2xl bg-white/10 backdrop-blur-sm px-5 py-4 border border-white/15">
                            <p class="text-xs uppercase tracking-[0.18em] text-white/70">Settings Area</p>
                            <p class="mt-1 text-lg font-semibold">Mail Configuration</p>
                        </div>
                    </div>
                </div>
            </div> -->

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-slate-50/70">
                    <nav class="flex flex-wrap gap-x-6 gap-y-2 px-6 py-4" aria-label="Tabs">
                        @if(auth()->user()->isSuperuser() || auth()->user()->isAdmin())
                            <a href="{{ route('settings.general') }}"
                               class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('settings.general*') ? 'bg-green-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                General Settings
                            </a>
                        @endif

                        @if(auth()->user()->isSuperuser())
                            <a href="{{ route('settings.users') }}"
                               class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('settings.users*') ? 'bg-green-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                Account Settings
                            </a>
                            @endif

                            <a href="{{ route('settings.setup') }}"
                               class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('settings.setup*') ? 'bg-green-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                Breeding Cycle
                            </a>

                        <a href="{{ route('settings.email') }}"
                           class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('settings.email*') ? 'bg-green-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                            Email Settings
                        </a>
                    </nav>
                </div>

                <div class="p-6 md:p-8">
                    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/80">
                            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                <div class="flex items-start gap-4">
                                    <div class="shrink-0 rounded-2xl bg-blue-100 p-3">
                                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-slate-900">Email Configuration</h3>
                                        <p class="text-sm text-slate-500 mt-1">Set up server connection details and maintain email-related preferences.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Inner Tab Navigation -->
                        <div class="px-6 pt-6">
                            <div class="inline-flex rounded-2xl bg-slate-100 p-1 border border-slate-200">
                                <button onclick="switchTab('server')" id="tab-server"
                                        class="rounded-xl px-4 py-2.5 text-sm font-semibold transition bg-white text-green-700 shadow-sm">
                                    Server Settings
                                </button>
                                <button onclick="switchTab('templates')" id="tab-templates"
                                        class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:text-slate-900 transition">
                                    Email Templates
                                </button>
                            </div>
                        </div>

                        <div class="p-6">
                            <!-- Server Settings Tab -->
                            <div id="tab-content-server" class="tab-content">
                                <form action="{{ route('settings.email.update') }}" method="POST" class="space-y-8">
                                    @csrf
                                    @method('PUT')

                                    <section class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                                        <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/80">
                                            <h4 class="text-base font-semibold text-slate-900">Mail Server Settings</h4>
                                            <p class="text-sm text-slate-500 mt-1">Configure your SMTP or mail transport details.</p>
                                        </div>

                                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                                            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                                <label class="block text-sm font-medium text-slate-700 mb-2">Mailer</label>
                                                <select name="mailer" required class="w-full rounded-xl border-slate-300 px-4 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                                                    <option value="smtp" {{ $settings->mailer === 'smtp' ? 'selected' : '' }}>SMTP</option>
                                                    <option value="sendmail" {{ $settings->mailer === 'sendmail' ? 'selected' : '' }}>Sendmail</option>
                                                    <option value="log" {{ $settings->mailer === 'log' ? 'selected' : '' }}>Log</option>
                                                    <option value="array" {{ $settings->mailer === 'array' ? 'selected' : '' }}>Array</option>
                                                </select>
                                            </div>

                                            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                                <label class="block text-sm font-medium text-slate-700 mb-2">Host</label>
                                                <input type="text" name="host" value="{{ old('host', $settings->host) }}"
                                                       class="w-full rounded-xl border-slate-300 px-4 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500"
                                                       placeholder="smtp.example.com">
                                            </div>

                                            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                                <label class="block text-sm font-medium text-slate-700 mb-2">Port</label>
                                                <input type="number" name="port" value="{{ old('port', $settings->port) }}" min="1" max="65535"
                                                       class="w-full rounded-xl border-slate-300 px-4 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500"
                                                       placeholder="587">
                                            </div>

                                            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                                <label class="block text-sm font-medium text-slate-700 mb-2">Encryption</label>
                                                <select name="encryption" class="w-full rounded-xl border-slate-300 px-4 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                                                    <option value="">None</option>
                                                    <option value="tls" {{ $settings->encryption === 'tls' ? 'selected' : '' }}>TLS</option>
                                                    <option value="ssl" {{ $settings->encryption === 'ssl' ? 'selected' : '' }}>SSL</option>
                                                </select>
                                            </div>

                                            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                                <label class="block text-sm font-medium text-slate-700 mb-2">Username</label>
                                                <input type="text" name="username" value="{{ old('username', $settings->username) }}"
                                                       class="w-full rounded-xl border-slate-300 px-4 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500"
                                                       placeholder="your-email@example.com">
                                            </div>

                                            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                                <label class="block text-sm font-medium text-slate-700 mb-2">Password</label>
                                                <input type="password" name="password" autocomplete="new-password"
                                                       class="w-full rounded-xl border-slate-300 px-4 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500"
                                                       placeholder="Leave blank to keep current password">
                                                <p class="mt-2 text-xs text-slate-500">Leave blank if you do not want to change the password.</p>
                                            </div>

                                            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                                <label class="block text-sm font-medium text-slate-700 mb-2">From Address</label>
                                                <input type="email" name="from_address" value="{{ old('from_address', $settings->from_address) }}"
                                                       class="w-full rounded-xl border-slate-300 px-4 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500"
                                                       placeholder="noreply@example.com">
                                            </div>

                                            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                                <label class="block text-sm font-medium text-slate-700 mb-2">From Name</label>
                                                <input type="text" name="from_name" value="{{ old('from_name', $settings->from_name) }}"
                                                       class="w-full rounded-xl border-slate-300 px-4 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500"
                                                       placeholder="Your App Name">
                                            </div>
                                        </div>
                                    </section>

                                    <div class="flex justify-end">
                                        <button type="submit"
                                                class="inline-flex items-center justify-center gap-2 rounded-2xl bg-green-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-green-700">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            Save Settings
                                        </button>
                                    </div>
                                </form>

                                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mt-8">
                                    <!-- Test Connection -->
                                    <section class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                                        <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/80">
                                            <h4 class="text-base font-semibold text-slate-900">Server Connection Testing</h4>
                                            <p class="text-sm text-slate-500 mt-1">Verify the server settings before sending real emails.</p>
                                        </div>

                                        <div class="p-6">
                                            <button onclick="testConnection()" id="testConnectionBtn"
                                                    class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                                                <svg id="testConnectionSpinner" class="hidden animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                <span>Test Connection</span>
                                            </button>

                                            <div id="connectionResult" class="mt-4 hidden" x-data="{ show: true }" x-show="show"></div>
                                        </div>
                                    </section>

                                    <!-- Test Email -->
                                    <section class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                                        <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/80">
                                            <h4 class="text-base font-semibold text-slate-900">Send Test Email</h4>
                                            <p class="text-sm text-slate-500 mt-1">Send a sample email to confirm delivery is working correctly.</p>
                                        </div>

                                        <div class="p-6">
                                            <form id="testEmailForm" onsubmit="sendTestEmail(event)" class="space-y-4">
                                                @csrf
                                                <div>
                                                    <label class="block text-sm font-medium text-slate-700 mb-2">Test Email Address</label>
                                                    <input type="email" id="testEmail" name="test_email" required value="{{ auth()->user()->email }}"
                                                           class="w-full rounded-xl border-slate-300 px-4 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500"
                                                           placeholder="test@example.com">
                                                </div>

                                                <button type="submit" id="sendTestBtn"
                                                        class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                                                    <svg id="sendTestSpinner" class="hidden animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    <span>Send Test Email</span>
                                                </button>
                                            </form>

                                            <div id="testEmailResult" class="mt-4 hidden" x-data="{ show: true }" x-show="show"></div>
                                        </div>
                                    </section>
                                </div>
                            </div>

                            <!-- Email Templates Tab -->
                            <div id="tab-content-templates" class="tab-content hidden">
                                <section class="rounded-3xl border border-dashed border-slate-300 bg-slate-50/70 p-12 text-center">
                                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <h3 class="mt-4 text-lg font-semibold text-slate-900">Coming Soon</h3>
                                    <p class="mt-2 text-sm text-slate-500">Email templates feature will be available soon.</p>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });

            const serverTab = document.getElementById('tab-server');
            const templatesTab = document.getElementById('tab-templates');

            [serverTab, templatesTab].forEach(btn => {
                btn.classList.remove('bg-white', 'text-green-700', 'shadow-sm');
                btn.classList.add('text-slate-600');
            });

            document.getElementById('tab-content-' + tab).classList.remove('hidden');

            const activeTab = document.getElementById('tab-' + tab);
            activeTab.classList.remove('text-slate-600');
            activeTab.classList.add('bg-white', 'text-green-700', 'shadow-sm');
        }

        function testConnection() {
            const btn = document.getElementById('testConnectionBtn');
            const spinner = document.getElementById('testConnectionSpinner');
            const resultDiv = document.getElementById('connectionResult');

            btn.disabled = true;
            spinner.classList.remove('hidden');
            resultDiv.classList.add('hidden');

            fetch('{{ route("settings.email.test-connection") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                spinner.classList.add('hidden');
                btn.disabled = false;
                resultDiv.classList.remove('hidden');

                if (data.success) {
                    resultDiv.className = 'mt-4 bg-green-50 border border-green-200 rounded-2xl p-4 shadow-sm';
                    resultDiv.innerHTML = `
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 text-green-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span class="text-green-800 font-medium">${data.message}</span>
                            </div>
                        </div>
                    `;
                } else {
                    resultDiv.className = 'mt-4 bg-red-50 border border-red-200 rounded-2xl p-4 shadow-sm';
                    resultDiv.innerHTML = `
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 text-red-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="text-red-800 font-medium">${data.message}</span>
                            </div>
                        </div>
                    `;
                }
            })
            .catch(error => {
                spinner.classList.add('hidden');
                btn.disabled = false;
                resultDiv.classList.remove('hidden');
                resultDiv.className = 'mt-4 bg-red-50 border border-red-200 rounded-2xl p-4 shadow-sm';
                resultDiv.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="h-5 w-5 text-red-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-red-800 font-medium">An error occurred: ${error.message}</span>
                        </div>
                    </div>
                `;
            });
        }

        function sendTestEmail(event) {
            event.preventDefault();

            const form = document.getElementById('testEmailForm');
            const btn = document.getElementById('sendTestBtn');
            const spinner = document.getElementById('sendTestSpinner');
            const resultDiv = document.getElementById('testEmailResult');

            btn.disabled = true;
            spinner.classList.remove('hidden');
            resultDiv.classList.add('hidden');

            const formData = new FormData(form);

            fetch('{{ route("settings.email.send-test") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                spinner.classList.add('hidden');
                btn.disabled = false;
                resultDiv.classList.remove('hidden');

                if (data.success) {
                    resultDiv.className = 'mt-4 bg-green-50 border border-green-200 rounded-2xl p-4 shadow-sm';
                    resultDiv.innerHTML = `
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 text-green-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span class="text-green-800 font-medium">${data.message}</span>
                            </div>
                        </div>
                    `;
                } else {
                    resultDiv.className = 'mt-4 bg-red-50 border border-red-200 rounded-2xl p-4 shadow-sm';
                    resultDiv.innerHTML = `
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 text-red-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="text-red-800 font-medium">${data.message}</span>
                            </div>
                        </div>
                    `;
                }
            })
            .catch(error => {
                spinner.classList.add('hidden');
                btn.disabled = false;
                resultDiv.classList.remove('hidden');
                resultDiv.className = 'mt-4 bg-red-50 border border-red-200 rounded-2xl p-4 shadow-sm';
                resultDiv.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="h-5 w-5 text-red-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-red-800 font-medium">An error occurred: ${error.message}</span>
                        </div>
                    </div>
                `;
            });
        }
    </script>

    <x-copyright-minimal />
</x-app-layout>