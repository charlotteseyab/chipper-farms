<x-app-layout>
    <div class="py-2">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl rounded-xl border border-gray-200">
                <div class="p-6 border-b border-gray-200 flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Activity Log</h2>
                        <!-- <p class="text-sm text-gray-600">Login, logout and login attempts</p> -->
                    </div>
                    <form id="activityLogFilter" method="GET" class="flex items-center gap-2">
                        <input id="filterUser" type="text" name="user" value="{{ request('user') }}" placeholder="Search user" class="px-3 py-2 border rounded-lg text-sm">
                        <select id="filterAction" name="action" class="px-3 py-2 border rounded-lg text-sm">
                            <option value="">All Actions</option>
                            <option value="login" @selected(request('action')==='login')>Login</option>
                            <option value="logout" @selected(request('action')==='logout')>Logout</option>
                            <option value="login_attempt" @selected(request('action')==='login_attempt')>Login Attempt</option>
                        </select>
                        <button class="px-3 py-2 bg-green-600 text-white rounded-lg text-sm">Filter</button>
                        <button id="filterReset" type="button" class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm border border-gray-300">Reset</button>
                    </form>
                </div>
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User Agent</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Meta Data</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($logs as $log)
                                    <tr>
                                        <td class="px-4 py-2 text-sm text-gray-900">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-900">{{ $log->user->name ?? 'Unknown' }}<div class="text-xs text-gray-500">{{ $log->user->email ?? '' }}</div></td>
                                        <td class="px-4 py-2 text-sm">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $log->action === 'login' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">{{ ucfirst($log->action) }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-900">{{ $log->ip_address }}</td>
                                        <td class="px-4 py-2 text-[10px] text-gray-600">{{ Str::limit($log->user_agent, 100) }}</td>
                                        <td class="px-4 py-2 text-[11px] font-mono text-gray-700 max-w-xs break-words">
                                            @if(is_array($log->metadata) || is_object($log->metadata))
                                                <button type="button"
                                                        class="underline text-blue-600 hover:text-blue-800 cursor-pointer metadata-trigger"
                                                        data-meta='@json($log->metadata)'>
                                                    View
                                                </button>
                                                <span class="hidden">{!! nl2br(e(json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) !!}</span>
                                            @else
                                                <span class="text-gray-400 italic">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-4 py-6 text-center text-gray-500 text-sm" colspan="6">No activity found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">{{ $logs->withQueryString()->links('components.simple-pagination') }}</div>
                </div>
            </div>
        </div>
    </div>
    <div id="metadataModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden">
        <div class="bg-white rounded-lg w-full max-w-2xl md:max-w-xl shadow-lg p-6 relative">
            <button id="closeMetadataModal" class="absolute top-3 right-3 text-gray-400 hover:text-gray-700" aria-label="Close">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Activity Meta Data</h3>
            <pre id="metadataModalContent" class="text-xs font-mono bg-gray-100 rounded-lg p-4 overflow-x-auto whitespace-pre-wrap"></pre>
        </div>
    </div>
    <script>
        (function() {
            const form = document.getElementById('activityLogFilter');
            const input = document.getElementById('filterUser');
            const select = document.getElementById('filterAction');
            const resetBtn = document.getElementById('filterReset');

            let tId;
            function debounceSubmit() {
                if (tId) clearTimeout(tId);
                tId = setTimeout(() => form.requestSubmit(), 300);
            }

            if (input) {
                input.addEventListener('input', debounceSubmit);
                // Submit immediately when pressing Enter
                input.addEventListener('keydown', function(e){ if (e.key === 'Enter') form.requestSubmit(); });
            }
            if (select) {
                select.addEventListener('change', () => form.requestSubmit());
            }
            if (resetBtn) {
                resetBtn.addEventListener('click', () => {
                    if (input) input.value = '';
                    if (select) select.value = '';
                    form.requestSubmit();
                });
            }
        })();
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const triggers = document.querySelectorAll('.metadata-trigger');
            const modal = document.getElementById('metadataModal');
            const modalContent = document.getElementById('metadataModalContent');
            const closeBtn = document.getElementById('closeMetadataModal');
            triggers.forEach(btn => {
                btn.addEventListener('click', function() {
                    try {
                        const meta = JSON.parse(this.dataset.meta);
                        modalContent.textContent = JSON.stringify(meta, null, 2);
                    } catch (e) {
                        modalContent.textContent = this.dataset.meta;
                    }
                    modal.classList.remove('hidden');
                });
            });
            closeBtn.addEventListener('click', function() {
                modal.classList.add('hidden');
            });
            // Allow clicking outside modal to close
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                }
            });
        });
    </script>
</x-app-layout>


