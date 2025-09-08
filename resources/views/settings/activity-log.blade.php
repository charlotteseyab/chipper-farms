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
                                        <td class="px-4 py-2 text-[10px] text-gray-600">{{ 
                                            Str::limit($log->user_agent, 100)
                                        }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-4 py-6 text-center text-gray-500 text-sm" colspan="5">No activity found.</td>
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
</x-app-layout>


