<x-app-layout>
    <main class="min-h-screen bg-slate-50/70 py-2">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- <div class="mb-6">
                <div class="rounded-3xl bg-gradient-to-r from-[#1C6C51] via-[#238b67] to-[#2aa578] p-6 md:p-8 text-white shadow-lg">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-sm font-medium text-white/80 mb-2">System Configuration</p>
                            <h1 class="text-2xl md:text-3xl font-bold tracking-tight">Account Settings</h1>
                            <p class="mt-2 text-sm md:text-base text-white/85 max-w-3xl">
                                Manage user accounts, roles, and access status for staff using the system.
                            </p>
                        </div>

                        <div class="rounded-2xl bg-white/10 backdrop-blur-sm px-5 py-4 border border-white/15">
                            <p class="text-xs uppercase tracking-[0.18em] text-white/70">Settings Area</p>
                            <p class="mt-1 text-lg font-semibold">User Management</p>
                        </div>
                    </div>
                </div>
            </div> -->

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-slate-50/70">
                    <nav class="flex flex-wrap gap-x-6 gap-y-2 px-6 py-4" aria-label="Tabs">
                        @if(auth()->user()->isSuperuser() || auth()->user()->isAdmin())
                            <a href="{{ route('settings.general') }}"
                               class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('settings.general-settings*') ? 'bg-green-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
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
                           class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('settings.setup') ? 'bg-green-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                            Breeding Cycle
                        </a>

                        @if(auth()->user()->isSuperuser() || auth()->user()->isAdmin())
                            <a href="{{ route('settings.email') }}"
                               class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('settings.email*') ? 'bg-green-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                Email Settings
                            </a>
                        @endif
                    </nav>
                </div>

                <div class="p-6 md:p-8">
                    <div id="inlineFlashWrap" class="mb-6 hidden">
                        <div id="inlineFlash" class="rounded-2xl border border-green-200 bg-green-50 px-4 py-4 shadow-sm">
                            <div class="flex items-center justify-between gap-4">
                                <div class="flex items-center">
                                    <svg class="h-5 w-5 text-green-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <span id="inlineFlashText" class="text-green-800 font-medium"></span>
                                </div>
                                <button type="button"
                                        onclick="hideInlineFlash()"
                                        class="text-green-600 hover:text-green-800 hover:bg-green-100 rounded-full p-1 transition-colors duration-200">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <section class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/80">
                            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                <div class="flex items-start gap-4">
                                    <div class="shrink-0 rounded-2xl bg-blue-100 p-3">
                                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-slate-900">User Management</h3>
                                        <p class="text-sm text-slate-500 mt-1">Create, update, activate, and manage system users and roles.</p>
                                    </div>
                                </div>

                                <button onclick="openCreateModal()"
                                        class="inline-flex items-center justify-center gap-2 rounded-2xl bg-green-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-green-700">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Create User
                                </button>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Email</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Phone</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Role</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Active Status</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse($users as $user)
                                        <tr class="hover:bg-slate-50/70 transition">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="font-semibold text-slate-800">
                                                    {{ $user->first_name }} {{ $user->last_name }}
                                                </div>
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-700">
                                                {{ $user->email }}
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-700">
                                                {{ $user->phone ?? 'N/A' }}
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800 border border-blue-200">
                                                    {{ $user->role ?? 'N/A' }}
                                                </span>
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <label class="inline-flex items-center">
                                                    <input type="checkbox"
                                                           class="rounded border-slate-300 text-green-600 shadow-sm focus:border-green-500 focus:ring focus:ring-green-500 focus:ring-opacity-50"
                                                           {{ $user->active_status ? 'checked' : '' }}
                                                           onchange="updateUserStatus({{ $user->id }}, this.checked)"
                                                           {{ $user->id === auth()->id() ? 'disabled' : '' }}>
                                                    <span id="userStatusText{{ $user->id }}" class="ml-2 text-sm text-slate-700">
                                                        {{ $user->active_status ? 'Active' : 'Inactive' }}
                                                    </span>
                                                </label>
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex flex-wrap gap-2">
                                                    <button onclick="openEditModal({{ $user->id }}, '{{ $user->first_name }}', '{{ $user->last_name }}', '{{ $user->email }}', '{{ $user->phone ?? '' }}', '{{ $user->role }}', {{ $user->active_status ? 'true' : 'false' }})"
                                                            class="inline-flex items-center rounded-lg bg-indigo-50 px-3 py-1.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-100 transition">
                                                        Edit
                                                    </button>

                                                    @if($user->id !== auth()->id() && !$user->isSuperuser())
                                                        <button onclick="confirmDelete({{ $user->id }}, '{{ $user->first_name }} {{ $user->last_name }}')"
                                                                class="inline-flex items-center rounded-lg bg-red-50 px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-100 transition">
                                                            Delete
                                                        </button>
                                                    @else
                                                        <span class="inline-flex items-center rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-semibold text-slate-400">
                                                            Delete
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-6 py-12 text-center">
                                                <div class="flex flex-col items-center justify-center">
                                                    <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5V4H2v16h5m10 0v-2a4 4 0 00-4-4H9a4 4 0 00-4 4v2m12 0H7m10-12a4 4 0 11-8 0 4 4 0 018 0z" />
                                                        </svg>
                                                    </div>
                                                    <h3 class="text-base font-semibold text-slate-700">No users found</h3>
                                                    <p class="mt-1 text-sm text-slate-500">Create your first user to begin managing accounts.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if($users->hasPages())
                            <div class="px-6 py-4 border-t border-slate-200 bg-white">
                                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                    <div class="text-sm text-slate-600">
                                        Showing {{ $users->firstItem() }} to {{ $users->lastItem() }} of {{ $users->total() }} results
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <x-simple-pagination :paginator="$users" />
                                    </div>
                                </div>
                            </div>
                        @endif
                    </section>
                </div>
            </div>
        </div>
    </main>

    <!-- Create User Modal -->
    <div id="createModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full max-h-[90vh] flex flex-col border border-slate-200">
            <div class="flex items-center justify-between p-6 border-b border-slate-200 flex-shrink-0">
                <h2 class="text-xl font-bold text-slate-900">Create New User</h2>
                <button onclick="closeCreateModal()" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form action="{{ route('settings.users.store') }}" method="POST" class="flex flex-col flex-1 min-h-0">
                @csrf
                <div class="p-6 overflow-y-auto flex-1">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-2">First Name</label>
                                <input type="text" name="first_name" required maxlength="50" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-2">Last Name</label>
                                <input type="text" name="last_name" required maxlength="50" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Email</label>
                            <input type="email" name="email" required maxlength="50" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Phone</label>
                            <input type="text" name="phone" maxlength="20" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Role</label>
                            <select name="role" required class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                                <option value="superuser">Superuser</option>
                                <option value="admin">Admin</option>
                                <option value="farmmanager">Farm Manager</option>
                                <option value="dataentry">Data Entry</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Password</label>
                            <input type="password" name="password" required minlength="8" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Confirm Password</label>
                            <input type="password" name="password_confirmation" required minlength="8" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" name="active_status" id="create_active_status" value="1" checked class="rounded border-slate-300 text-green-600 shadow-sm focus:border-green-500 focus:ring-green-500">
                            <label for="create_active_status" class="ml-2 text-sm text-slate-700">Active Status</label>
                        </div>
                    </div>
                </div>

                <div class="p-6 border-t border-slate-200 flex justify-end gap-3 flex-shrink-0">
                    <button type="button" onclick="closeCreateModal()" class="rounded-2xl bg-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-300 transition">
                        Cancel
                    </button>
                    <button type="submit" class="rounded-2xl bg-green-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-green-700 transition">
                        Create User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full border border-slate-200">
            <div class="flex items-center justify-between p-6 border-b border-slate-200">
                <h2 class="text-xl font-bold text-slate-900">Edit User</h2>
                <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form id="editForm" method="POST" class="p-6">
                @csrf
                @method('PUT')

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">First Name</label>
                            <input type="text" name="first_name" id="edit_first_name" required maxlength="50" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Last Name</label>
                            <input type="text" name="last_name" id="edit_last_name" required maxlength="50" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Email</label>
                        <input type="email" name="email" id="edit_email" required maxlength="50" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Phone</label>
                        <input type="text" name="phone" id="edit_phone" maxlength="20" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Role</label>
                        <select name="role" id="edit_role" required class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                            <option value="superuser">Superuser</option>
                            <option value="admin">Admin</option>
                            <option value="farmmanager">Farm Manager</option>
                            <option value="dataentry">Data Entry</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Password (leave blank to keep current)</label>
                        <input type="password" name="password" minlength="8" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Confirm Password</label>
                        <input type="password" name="password_confirmation" minlength="8" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                    </div>

                    <div class="flex items-center">
                        <input type="hidden" name="active_status" value="0">
                        <input type="checkbox" name="active_status" id="edit_active_status" value="1" class="rounded border-slate-300 text-green-600 shadow-sm focus:border-green-500 focus:ring-green-500">
                        <label for="edit_active_status" class="ml-2 text-sm text-slate-700">Active Status</label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="closeEditModal()" class="rounded-2xl bg-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-300 transition">
                        Cancel
                    </button>
                    <button type="submit" class="rounded-2xl bg-green-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-green-700 transition">
                        Update User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full border border-slate-200">
            <div class="p-6">
                <h2 class="text-xl font-bold text-slate-900 mb-4">Confirm Delete</h2>
                <p class="text-slate-700 mb-6">
                    Are you sure you want to delete user <span id="deleteUserName" class="font-semibold"></span>? This action cannot be undone.
                </p>
                <form id="deleteForm" method="POST" class="flex justify-end gap-3">
                    @csrf
                    @method('DELETE')
                    <button type="button" onclick="closeDeleteModal()" class="rounded-2xl bg-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-300 transition">
                        Cancel
                    </button>
                    <button type="submit" class="rounded-2xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700 transition">
                        Delete User
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openCreateModal() {
            document.getElementById('createModal').classList.remove('hidden');
        }

        function closeCreateModal() {
            document.getElementById('createModal').classList.add('hidden');
        }

        function openEditModal(id, firstName, lastName, email, phone, role, activeStatus) {
            document.getElementById('editForm').action = '{{ route("settings.users.update", ":id") }}'.replace(':id', id);
            document.getElementById('edit_first_name').value = firstName;
            document.getElementById('edit_last_name').value = lastName;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_phone').value = phone || '';

            const roleSelect = document.getElementById('edit_role');
            roleSelect.value = role;

            if (role === 'superuser') {
                roleSelect.setAttribute('disabled', 'disabled');
                roleSelect.classList.add('bg-slate-100', 'cursor-not-allowed');
            } else {
                roleSelect.removeAttribute('disabled');
                roleSelect.classList.remove('bg-slate-100', 'cursor-not-allowed');
            }

            document.getElementById('edit_active_status').checked = activeStatus;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        function confirmDelete(id, userName) {
            document.getElementById('deleteForm').action = '{{ route("settings.users.delete", ":id") }}'.replace(':id', id);
            document.getElementById('deleteUserName').textContent = userName;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        function updateUserStatus(userId, activeStatus) {
            const statusTextEl = document.getElementById(`userStatusText${userId}`);
            const checkboxEl = event?.target;

            if (statusTextEl) {
                statusTextEl.textContent = activeStatus ? 'Active' : 'Inactive';
            }

            fetch(`{{ url('settings/users') }}/${userId}/status`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ active_status: activeStatus })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const wrap = document.getElementById('inlineFlashWrap');
                    const text = document.getElementById('inlineFlashText');
                    if (wrap && text) {
                        text.textContent = data.message || 'User status updated successfully.';
                        wrap.classList.remove('hidden');
                    }
                } else {
                    if (statusTextEl) {
                        statusTextEl.textContent = (!activeStatus) ? 'Active' : 'Inactive';
                    }
                    if (checkboxEl) {
                        checkboxEl.checked = !activeStatus;
                    }
                    alert(data.message || 'Failed to update user status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (statusTextEl) {
                    statusTextEl.textContent = (!activeStatus) ? 'Active' : 'Inactive';
                }
                if (checkboxEl) {
                    checkboxEl.checked = !activeStatus;
                }
                alert('An error occurred while updating user status');
            });
        }

        function hideInlineFlash() {
            const wrap = document.getElementById('inlineFlashWrap');
            if (wrap) {
                wrap.classList.add('hidden');
            }
        }

        document.getElementById('createModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeCreateModal();
        });
        document.getElementById('editModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeEditModal();
        });
        document.getElementById('deleteModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeDeleteModal();
        });
    </script>

    <x-copyright-minimal />
</x-app-layout>