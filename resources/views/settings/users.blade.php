<x-app-layout>
    <main class="min-h-screen bg-slate-50/70 pb-24 pt-2">
        <div class="mx-auto max-w-[1500px] px-3 sm:px-6 lg:px-8">

            <div>

                <div>
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

                    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <div class="bg-gradient-to-r from-emerald-950 via-emerald-900 to-emerald-700 px-5 py-6 text-white sm:px-7 sm:py-8">
                            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                <div class="flex items-start gap-4">
                                    <div class="shrink-0 rounded-2xl bg-white/10 p-3 ring-1 ring-white/20">
                                        <svg class="h-6 w-6 text-emerald-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-[.18em] text-emerald-200">Access and permissions</p>
                                        <h1 class="mt-1 text-2xl font-black sm:text-3xl">User management</h1>
                                        <p class="mt-2 max-w-2xl text-sm text-emerald-100">{{ auth()->user()->isSuperuser() ? 'Manage platform users and organization staff without mixing farm access.' : 'Create roles and control access for staff in your farm.' }}</p>
                                    </div>
                                </div>

                                <button onclick="openCreateModal()"
                                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-black text-emerald-900 shadow-sm transition hover:bg-emerald-50">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Create User
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 border-b border-slate-200 bg-white sm:grid-cols-4">
                            @foreach([['Users', $userStats['total'], 'All managed accounts'], ['Active', $userStats['active'], 'Can currently sign in'], ['Inactive', $userStats['inactive'], 'Access is suspended'], [auth()->user()->isSuperuser() ? 'Farms' : 'Organization', $userStats['farms'], auth()->user()->isSuperuser() ? 'Active organizations' : 'Your farm']] as [$label, $value, $hint])
                                <div class="border-b border-r border-slate-100 p-4 last:border-r-0 sm:border-b-0 sm:p-5"><p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ $label }}</p><p class="mt-1 text-2xl font-black text-slate-950">{{ $value }}</p><p class="mt-1 text-xs text-slate-500">{{ $hint }}</p></div>
                            @endforeach
                        </div>

                            <form method="GET" class="grid gap-3 border-b border-slate-200 bg-slate-50/70 px-5 py-5 sm:px-7 md:grid-cols-2 xl:grid-cols-[minmax(240px,1.5fr)_minmax(180px,1fr)_minmax(160px,.8fr)_minmax(200px,1fr)_auto] xl:items-end">
                                <div>
                                    <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Search users</label>
                                    <input name="q" value="{{ request('q') }}" placeholder="Name, email or phone" class="w-full rounded-xl border-slate-300 bg-white text-sm">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Role</label>
                                    <select name="role" class="w-full rounded-xl border-slate-300 bg-white text-sm"><option value="">All roles</option>@foreach($availableRoles as $roleName)<option value="{{ $roleName }}" @selected(request('role') === $roleName)>{{ str($roleName)->replace('_', ' ')->headline() }}</option>@endforeach</select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Status</label>
                                    <select name="status" class="w-full rounded-xl border-slate-300 bg-white text-sm"><option value="">Any status</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></select>
                                </div>
                                @if(auth()->user()->isSuperuser())<div>
                                    <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Farm</label>
                                    <select name="organization_id" class="w-full rounded-xl border-slate-300 text-sm">
                                        <option value="">All farms and platform users</option>
                                        @foreach($organizations as $organization)
                                            <option value="{{ $organization->id }}" @selected((string) request('organization_id') === (string) $organization->id)>{{ $organization->name }}</option>
                                        @endforeach
                                    </select>
                                </div>@endif
                                <div class="flex gap-2"><button class="flex-1 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Filter</button><a href="{{ route('settings.users') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-bold text-slate-700">Reset</a></div>
                            </form>

                        <div class="overflow-x-auto">
                            <table class="min-w-[980px] w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50/90">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Email</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Phone</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Role</th>
                                        @if(auth()->user()->isSuperuser())<th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Farm</th>@endif
                                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Active Status</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse($users as $user)
                                        <tr class="transition hover:bg-emerald-50/30">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-sm font-black text-emerald-800">{{ str($user->first_name ?: $user->name)->substr(0, 1)->upper() }}{{ str($user->last_name)->substr(0, 1)->upper() }}</span><div><div class="font-bold text-slate-900">{{ $user->first_name }} {{ $user->last_name }}</div><p class="mt-0.5 text-xs text-slate-500">Added {{ $user->created_at?->format('d M Y') }}</p></div></div>
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-700">
                                                {{ $user->email }}
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-700">
                                                {{ $user->phone ?? 'Not provided' }}
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-bold text-blue-800">
                                                    {{ str($user->role ?? 'N/A')->replace('_', ' ')->headline() }}
                                                </span>
                                            </td>

                                            @if(auth()->user()->isSuperuser())
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-slate-700"><span class="rounded-lg bg-slate-100 px-2.5 py-1.5">{{ $user->organization?->name ?? 'Platform' }}</span></td>
                                            @endif

                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <label class="inline-flex items-center rounded-full bg-slate-50 px-3 py-1.5">
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
                                            <td colspan="{{ auth()->user()->isSuperuser() ? 7 : 6 }}" class="px-6 py-12 text-center">
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
    <div id="createModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/60 p-3 sm:p-6">
        <div class="mx-auto flex h-[calc(100dvh-1.5rem)] w-full max-w-3xl flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl sm:h-[calc(100dvh-3rem)]">
            <div class="flex items-center justify-between p-6 border-b border-slate-200 flex-shrink-0">
                <div><p class="text-xs font-bold uppercase tracking-[.18em] text-emerald-700">Account provisioning</p><h2 class="mt-1 text-2xl font-black text-slate-900">Create a user</h2><p class="mt-1 text-sm text-slate-500">Choose whether this person works for a farm or manages the platform.</p></div>
                <button onclick="closeCreateModal()" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form action="{{ route('settings.users.store') }}" method="POST" class="flex min-h-0 flex-1 flex-col overflow-hidden">
                @csrf
                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-5 sm:p-7" tabindex="0">
                    <div class="space-y-6">
                        @if(auth()->user()->isSuperuser())
                            <fieldset>
                                <legend class="text-sm font-black text-slate-900">Account access level</legend>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <label data-account-card="farm" class="cursor-pointer rounded-2xl border-2 border-emerald-600 bg-emerald-50 p-4"><input type="radio" name="account_scope" value="farm" checked class="text-emerald-700"><span class="ml-2 font-black text-slate-900">Farm staff</span><p class="ml-6 mt-1 text-sm text-slate-600">Belongs to one organization and uses its farm records.</p></label>
                                    <label data-account-card="platform" class="cursor-pointer rounded-2xl border-2 border-slate-200 p-4"><input type="radio" name="account_scope" value="platform" class="text-emerald-700"><span class="ml-2 font-black text-slate-900">Platform superuser</span><p class="ml-6 mt-1 text-sm text-slate-600">Belongs to no farm and manages the whole platform.</p></label>
                                </div>
                            </fieldset>
                        @else
                            <input type="hidden" name="account_scope" value="farm">
                        @endif

                        <section class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 sm:p-5">
                            <h3 class="font-black text-slate-900">Account assignment</h3>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                @if(auth()->user()->isSuperuser())
                                    <div id="create_organization_wrap">
                                        <label class="block text-sm font-bold text-slate-700 mb-2">Farm / Organization</label>
                                        <select name="organization_id" id="create_organization_id" class="w-full rounded-xl border-slate-300 px-3 py-2.5 bg-white"><option value="">Select a farm</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}">{{ $organization->name }}</option>@endforeach</select>
                                        <p class="mt-1 text-xs text-slate-500">Required for farm staff accounts.</p>
                                    </div>
                                @endif
                                <div id="create_role_wrap">
                                    <label class="block text-sm font-bold text-slate-700 mb-2">Farm role</label>
                                    <select name="role" id="create_role" required class="w-full rounded-xl border-slate-300 px-3 py-2.5 bg-white">@foreach(array_diff($availableRoles, ['superuser']) as $roleName)<option value="{{ $roleName }}">{{ str($roleName)->replace('_', ' ')->headline() }}</option>@endforeach</select>
                                    <p class="mt-1 text-xs text-slate-500">Admin is the highest role inside a farm.</p>
                                </div>
                                <div id="platform_role_summary" class="hidden rounded-xl border border-violet-200 bg-violet-50 p-4 sm:col-span-2"><p class="text-sm font-black text-violet-950">Platform Superuser</p><p class="mt-1 text-xs text-violet-800">No organization will be attached.</p></div>
                            </div>
                        </section>

                        <section><h3 class="mb-4 font-black text-slate-900">Personal and login details</h3>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-2">First Name</label>
                                <input type="text" name="first_name" required maxlength="50" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-2">Last Name</label>
                                <input type="text" name="last_name" required maxlength="50" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
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
                            <label class="block text-sm font-medium text-slate-700 mb-2">Password</label>
                            <input type="password" name="password" required minlength="8" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Confirm Password</label>
                            <input type="password" name="password_confirmation" required minlength="8" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                        </div>

                        </div></section>

                        <div class="flex items-start gap-3 rounded-2xl border border-slate-200 p-4">
                            <input type="checkbox" name="active_status" id="create_active_status" value="1" checked class="rounded border-slate-300 text-green-600 shadow-sm focus:border-green-500 focus:ring-green-500">
                            <label for="create_active_status" class="text-sm text-slate-700"><strong class="block text-slate-900">Activate account immediately</strong><span class="text-xs text-slate-500">Inactive accounts cannot sign in.</span></label>
                        </div>
                    </div>
                </div>

                <div class="p-5 sm:px-7 border-t border-slate-200 flex flex-col-reverse sm:flex-row sm:justify-end gap-3 flex-shrink-0">
                    <button type="button" onclick="closeCreateModal()" class="rounded-2xl bg-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-300 transition">
                        Cancel
                    </button>
                    <button type="submit" class="rounded-2xl bg-green-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-green-700 transition">
                        Create Account
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
                            @foreach($availableRoles as $roleName)
                                <option value="{{ $roleName }}">{{ str($roleName)->replace('_', ' ')->headline() }}</option>
                            @endforeach
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
            document.documentElement.classList.add('overflow-hidden');
        }

        function closeCreateModal() {
            document.getElementById('createModal').classList.add('hidden');
            document.documentElement.classList.remove('overflow-hidden');
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

        const createRole = document.getElementById('create_role');
        const organizationWrap = document.getElementById('create_organization_wrap');
        const organizationSelect = document.getElementById('create_organization_id');
        const roleWrap = document.getElementById('create_role_wrap');
        const platformSummary = document.getElementById('platform_role_summary');
        function syncCreateOrganization() {
            const selectedScope = document.querySelector('input[name="account_scope"]:checked')?.value || 'farm';
            const platformAccount = selectedScope === 'platform';
            organizationWrap?.classList.toggle('hidden', platformAccount);
            roleWrap?.classList.toggle('hidden', platformAccount);
            platformSummary?.classList.toggle('hidden', !platformAccount);
            if (organizationSelect) {
                organizationSelect.required = !platformAccount;
                if (platformAccount) organizationSelect.value = '';
            }
            if (createRole) createRole.disabled = platformAccount;
            document.querySelectorAll('[data-account-card]').forEach(card => {
                const active = card.dataset.accountCard === selectedScope;
                card.classList.toggle('border-emerald-600', active);
                card.classList.toggle('bg-emerald-50', active);
                card.classList.toggle('border-slate-200', !active);
            });
        }
        document.querySelectorAll('input[name="account_scope"]').forEach(input => input.addEventListener('change', syncCreateOrganization));
        syncCreateOrganization();
    </script>
</x-app-layout>
