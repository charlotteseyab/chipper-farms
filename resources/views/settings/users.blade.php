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
                        <a href="{{ route('settings.users') }}" class="border-b-2 border-green-500 text-green-600 py-4 px-1 text-sm font-medium">
                            Account Settings
                        </a>
                        @endif
                        @if(auth()->user()->isSuperuser() || auth()->user()->isAdmin())
                        <a href="{{ route('settings.email') }}" class="border-b-2 {{ request()->routeIs('settings.email*') ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} py-4 px-1 text-sm font-medium">
                            Email Settings
                        </a>
                        @endif
                    </nav>
                </div>

                <!-- Users Content -->
                <div class="p-6">
                    <!-- Inline Flash Message (matches app.blade style) -->
                    <div id="inlineFlashWrap" class="mb-4 hidden">
                        <div id="inlineFlash"
                             class="bg-green-50 border border-green-200 rounded-lg p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <svg class="h-5 w-5 text-green-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <span id="inlineFlashText" class="text-green-800 font-medium"></span>
                                </div>
                                <button type="button"
                                        onclick="hideInlineFlash()"
                                        class="ml-4 flex-shrink-0 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-full p-1 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                                        aria-label="Close message">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <div class="bg-blue-100 p-3 rounded-lg mr-4">
                                <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">User Management</h3>
                                <p class="text-gray-500 text-sm">Manage system users and their roles</p>
                            </div>
                        </div>
                        <button onclick="openCreateModal()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors shadow-sm font-medium flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Create User
                        </button>
                    </div>

                    <!-- Users Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Active Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($users as $user)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ $user->first_name }} {{ $user->last_name }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">{{ $user->email }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">{{ $user->phone ?? 'N/A' }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                                {{ $user->role ?? 'N/A' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <label class="inline-flex items-center">
                                                <input type="checkbox" 
                                                       class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-500 focus:ring focus:ring-green-500 focus:ring-opacity-50"
                                                       {{ $user->active_status ? 'checked' : '' }}
                                                       onchange="updateUserStatus({{ $user->id }}, this.checked)"
                                                       {{ $user->id === auth()->id() ? 'disabled' : '' }}>
                                                <span id="userStatusText{{ $user->id }}" class="ml-2 text-sm text-gray-900">{{ $user->active_status ? 'Active' : 'Inactive' }}</span>
                                            </label>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick="openEditModal({{ $user->id }}, '{{ $user->first_name }}', '{{ $user->last_name }}', '{{ $user->email }}', '{{ $user->phone ?? '' }}', '{{ $user->role }}', {{ $user->active_status ? 'true' : 'false' }})" 
                                                    class="text-indigo-600 hover:text-indigo-900 mr-4">Edit</button>
                                            @if($user->id !== auth()->id() && !$user->isSuperuser())
                                                <button onclick="confirmDelete({{ $user->id }}, '{{ $user->first_name }} {{ $user->last_name }}')" 
                                                        class="text-red-600 hover:text-red-900">Delete</button>
                                            @else
                                                <span class="text-gray-400">Delete</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                            No users found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination (embedded, simple style) -->
                    @if($users->hasPages())
                        <div class="px-6 py-4 border-t border-gray-200 bg-white">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700">
                                    Showing {{ $users->firstItem() }} to {{ $users->lastItem() }} of {{ $users->total() }} results
                                </div>
                                <div class="flex items-center space-x-2">
                                    <x-simple-pagination :paginator="$users" />
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </main>

    <!-- Create User Modal -->
    <div id="createModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between p-6 border-b border-gray-200 flex-shrink-0">
                <h2 class="text-xl font-bold text-gray-900">Create New User</h2>
                <button onclick="closeCreateModal()" class="text-gray-400 hover:text-gray-600">
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                            <input type="text" name="first_name" required maxlength="50" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                            <input type="text" name="last_name" required maxlength="50" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" required maxlength="50" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input type="text" name="phone" maxlength="20" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select name="role" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="superuser">Superuser</option>
                            <option value="admin">Admin</option>
                            <option value="farmmanager">Farm Manager</option>
                            <option value="dataentry">Data Entry</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input type="password" name="password" required minlength="8" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                        <input type="password" name="password_confirmation" required minlength="8" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" name="active_status" id="create_active_status" value="1" checked class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-500 focus:ring focus:ring-green-500 focus:ring-opacity-50">
                        <label for="create_active_status" class="ml-2 text-sm text-gray-700">Active Status</label>
                    </div>
                    </div>
                </div>
                <div class="p-6 border-t border-gray-200 flex justify-end gap-3 flex-shrink-0">
                    <button type="button" onclick="closeCreateModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        Create User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-900">Edit User</h2>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                            <input type="text" name="first_name" id="edit_first_name" required maxlength="50" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                            <input type="text" name="last_name" id="edit_last_name" required maxlength="50" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" id="edit_email" required maxlength="50" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input type="text" name="phone" id="edit_phone" maxlength="20" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select name="role" id="edit_role" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="superuser">Superuser</option>
                            <option value="admin">Admin</option>
                            <option value="farmmanager">Farm Manager</option>
                            <option value="dataentry">Data Entry</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password (leave blank to keep current)</label>
                        <input type="password" name="password" minlength="8" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                        <input type="password" name="password_confirmation" minlength="8" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div class="flex items-center">
                        <input type="hidden" name="active_status" value="0">
                        <input type="checkbox" name="active_status" id="edit_active_status" value="1" class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-500 focus:ring focus:ring-green-500 focus:ring-opacity-50">
                        <label for="edit_active_status" class="ml-2 text-sm text-gray-700">Active Status</label>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        Update User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
            <div class="p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Confirm Delete</h2>
                <p class="text-gray-700 mb-6">Are you sure you want to delete user <span id="deleteUserName" class="font-semibold"></span>? This action cannot be undone.</p>
                <form id="deleteForm" method="POST" class="flex justify-end gap-3">
                    @csrf
                    @method('DELETE')
                    <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
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

            // If this user is a superuser, lock the role field so it cannot be changed
            if (role === 'superuser') {
                roleSelect.setAttribute('disabled', 'disabled');
                roleSelect.classList.add('bg-gray-100', 'cursor-not-allowed');
            } else {
                roleSelect.removeAttribute('disabled');
                roleSelect.classList.remove('bg-gray-100', 'cursor-not-allowed');
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

            // Optimistically update the label immediately (no refresh)
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
                    // Show inline success message without a full page reload
                    const wrap = document.getElementById('inlineFlashWrap');
                    const text = document.getElementById('inlineFlashText');
                    if (wrap && text) {
                        text.textContent = data.message || 'User status updated successfully.';
                        wrap.classList.remove('hidden');
                    }
                } else {
                    // Revert label + checkbox on failure
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
                // Revert label + checkbox on error
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

        // Close modals on outside click
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
