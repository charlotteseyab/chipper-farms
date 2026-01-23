<x-app-layout>
    <main class="flex-1 p-6 pt-0 min-h-screen">
        <div class="max-w-7xl mx-auto">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <!-- Settings Tabs -->
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-8 px-6" aria-label="Tabs">
                        <a href="{{ route('settings.setup') }}" class="border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 py-4 px-1 text-sm font-medium">
                            Breeding Cycle
                        </a>
                        @if(auth()->user()->isSuperuser())
                        <a href="{{ route('settings.users') }}" class="border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 py-4 px-1 text-sm font-medium">
                            Account Settings
                        </a>
                        @endif
                        <a href="{{ route('settings.email') }}" class="border-b-2 border-green-500 text-green-600 py-4 px-1 text-sm font-medium">
                            Email Settings
                        </a>
                    </nav>
                </div>

                <!-- Email Settings Content -->
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center">
                            <div class="bg-blue-100 p-3 rounded-lg mr-4">
                                <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Email Settings</h3>
                                <p class="text-gray-500 text-sm">Configure email server settings and templates</p>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Navigation -->
                    <div class="border-b border-gray-200 mb-6">
                        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                            <button onclick="switchTab('server')" id="tab-server" class="border-b-2 border-green-500 text-green-600 py-4 px-1 text-sm font-medium whitespace-nowrap">
                                Server Settings
                            </button>
                            <button onclick="switchTab('templates')" id="tab-templates" class="border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 py-4 px-1 text-sm font-medium whitespace-nowrap">
                                Email Templates
                            </button>
                        </nav>
                    </div>

                    <!-- Server Settings Tab -->
                    <div id="tab-content-server" class="tab-content">
                        <form action="{{ route('settings.email.update') }}" method="POST" class="space-y-6">
                            @csrf
                            @method('PUT')

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Mailer</label>
                                    <select name="mailer" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                        <option value="smtp" {{ $settings->mailer === 'smtp' ? 'selected' : '' }}>SMTP</option>
                                        <option value="sendmail" {{ $settings->mailer === 'sendmail' ? 'selected' : '' }}>Sendmail</option>
                                        <option value="log" {{ $settings->mailer === 'log' ? 'selected' : '' }}>Log</option>
                                        <option value="array" {{ $settings->mailer === 'array' ? 'selected' : '' }}>Array</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Host</label>
                                    <input type="text" name="host" value="{{ old('host', $settings->host) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="smtp.example.com">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
                                    <input type="number" name="port" value="{{ old('port', $settings->port) }}" min="1" max="65535" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="587">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Encryption</label>
                                    <select name="encryption" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                        <option value="">None</option>
                                        <option value="tls" {{ $settings->encryption === 'tls' ? 'selected' : '' }}>TLS</option>
                                        <option value="ssl" {{ $settings->encryption === 'ssl' ? 'selected' : '' }}>SSL</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                                    <input type="text" name="username" value="{{ old('username', $settings->username) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="your-email@example.com">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                    <input type="password" name="password" autocomplete="new-password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="Leave blank to keep current password">
                                    <p class="mt-1 text-xs text-gray-500">Leave blank if you don't want to change the password</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">From Address</label>
                                    <input type="email" name="from_address" value="{{ old('from_address', $settings->from_address) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="noreply@example.com">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">From Name</label>
                                    <input type="text" name="from_name" value="{{ old('from_name', $settings->from_name) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="Your App Name">
                                </div>
                            </div>

                            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors shadow-sm font-medium">
                                    Save Settings
                                </button>
                            </div>
                        </form>

                        <!-- Server Connection Testing Section -->
                        <div class="mt-8 pt-6 border-t border-gray-200">
                            <h4 class="text-md font-semibold text-gray-900 mb-4">Server Connection Testing</h4>
                            <p class="text-sm text-gray-600 mb-4">Test your SMTP server connection to verify the settings are correct.</p>
                            <button onclick="testConnection()" id="testConnectionBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors shadow-sm font-medium flex items-center gap-2">
                                <svg id="testConnectionSpinner" class="hidden animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>Test Connection</span>
                            </button>
                            <div id="connectionResult" class="mt-4 hidden" x-data="{ show: true }" x-show="show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 transform scale-100" x-transition:leave-end="opacity-0 transform scale-95"></div>
                        </div>

                        <!-- Test Email Section -->
                        <div class="mt-8 pt-6 border-t border-gray-200">
                            <h4 class="text-md font-semibold text-gray-900 mb-4">Send Test Email</h4>
                            <p class="text-sm text-gray-600 mb-4">Send a test email to verify your email configuration is working correctly.</p>
                            <form id="testEmailForm" onsubmit="sendTestEmail(event)" class="flex gap-3 items-end">
                                @csrf
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Test Email Address</label>
                                    <input type="email" id="testEmail" name="test_email" required value="{{ auth()->user()->email }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="test@example.com">
                                </div>
                                <button type="submit" id="sendTestBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors shadow-sm font-medium flex items-center gap-2">
                                    <svg id="sendTestSpinner" class="hidden animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>Send Test Email</span>
                                </button>
                            </form>
                            <div id="testEmailResult" class="mt-4 hidden" x-data="{ show: true }" x-show="show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 transform scale-100" x-transition:leave-end="opacity-0 transform scale-95"></div>
                        </div>
                    </div>

                    <!-- Email Templates Tab -->
                    <div id="tab-content-templates" class="tab-content hidden">
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <h3 class="mt-4 text-lg font-medium text-gray-900">Coming Soon</h3>
                            <p class="mt-2 text-sm text-gray-500">Email templates feature will be available soon.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function switchTab(tab) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });

            // Remove active styling from all tabs
            document.querySelectorAll('[id^="tab-"]').forEach(btn => {
                btn.classList.remove('border-green-500', 'text-green-600');
                btn.classList.add('border-transparent', 'text-gray-500');
            });

            // Show selected tab content
            document.getElementById('tab-content-' + tab).classList.remove('hidden');

            // Add active styling to selected tab
            const activeTab = document.getElementById('tab-' + tab);
            activeTab.classList.remove('border-transparent', 'text-gray-500');
            activeTab.classList.add('border-green-500', 'text-green-600');
        }

        function testConnection() {
            const btn = document.getElementById('testConnectionBtn');
            const spinner = document.getElementById('testConnectionSpinner');
            const resultDiv = document.getElementById('connectionResult');

            // Disable button and show spinner
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
                    resultDiv.className = 'mt-4 bg-green-50 border border-green-200 rounded-lg p-4 shadow-sm';
                    resultDiv.innerHTML = `
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 text-green-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span class="text-green-800 font-medium">${data.message}</span>
                            </div>
                            <button @click="show = false"
                                    class="ml-4 flex-shrink-0 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-full p-1 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                                    aria-label="Close message">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    `;
                } else {
                    resultDiv.className = 'mt-4 bg-red-50 border border-red-200 rounded-lg p-4 shadow-sm';
                    resultDiv.innerHTML = `
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 text-red-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="text-red-800 font-medium">${data.message}</span>
                            </div>
                            <button @click="show = false"
                                    class="ml-4 flex-shrink-0 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-full p-1 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                    aria-label="Close message">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    `;
                }
            })
            .catch(error => {
                spinner.classList.add('hidden');
                btn.disabled = false;
                resultDiv.classList.remove('hidden');
                resultDiv.className = 'mt-4 bg-red-50 border border-red-200 rounded-lg p-4 shadow-sm';
                resultDiv.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="h-5 w-5 text-red-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-red-800 font-medium">An error occurred: ${error.message}</span>
                        </div>
                        <button @click="show = false"
                                class="ml-4 flex-shrink-0 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-full p-1 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                aria-label="Close message">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
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
            const emailInput = document.getElementById('testEmail');

            // Disable button and show spinner
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
                    resultDiv.className = 'mt-4 bg-green-50 border border-green-200 rounded-lg p-4 shadow-sm';
                    resultDiv.innerHTML = `
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 text-green-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span class="text-green-800 font-medium">${data.message}</span>
                            </div>
                            <button @click="show = false"
                                    class="ml-4 flex-shrink-0 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-full p-1 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                                    aria-label="Close message">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    `;
                } else {
                    resultDiv.className = 'mt-4 bg-red-50 border border-red-200 rounded-lg p-4 shadow-sm';
                    resultDiv.innerHTML = `
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 text-red-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="text-red-800 font-medium">${data.message}</span>
                            </div>
                            <button @click="show = false"
                                    class="ml-4 flex-shrink-0 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-full p-1 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                    aria-label="Close message">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    `;
                }
            })
            .catch(error => {
                spinner.classList.add('hidden');
                btn.disabled = false;
                resultDiv.classList.remove('hidden');
                resultDiv.className = 'mt-4 bg-red-50 border border-red-200 rounded-lg p-4 shadow-sm';
                resultDiv.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="h-5 w-5 text-red-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-red-800 font-medium">An error occurred: ${error.message}</span>
                        </div>
                        <button @click="show = false"
                                class="ml-4 flex-shrink-0 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-full p-1 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                aria-label="Close message">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                `;
            });
        }
    </script>
    <x-copyright-minimal />
</x-app-layout>
