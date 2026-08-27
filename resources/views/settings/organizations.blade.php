<x-app-layout>
    <main class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="rounded-3xl border border-emerald-200 bg-gradient-to-r from-emerald-950 to-emerald-700 p-6 text-white shadow-sm sm:p-8">
                <p class="text-xs font-bold uppercase tracking-[.2em] text-emerald-200">Platform administration</p>
                <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div><h1 class="text-2xl font-black sm:text-3xl">Farm organizations</h1><p class="mt-2 max-w-2xl text-sm text-emerald-100">Create each farm as an isolated organization and assign its first administrator.</p></div>
                    <button type="button" onclick="document.getElementById('createFarm').classList.remove('hidden')" class="rounded-xl bg-white px-5 py-3 text-sm font-bold text-emerald-900">Add farm</button>
                </div>
            </header>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @forelse($organizations as $organization)
                    <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3"><div><h2 class="text-lg font-black text-slate-900">{{ $organization->name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $organization->legal_name ?: $organization->slug }}</p></div><span class="rounded-full px-3 py-1 text-xs font-bold {{ $organization->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">{{ $organization->is_active ? 'Active' : 'Inactive' }}</span></div>
                        <dl class="mt-5 grid grid-cols-2 gap-3 text-sm"><div class="rounded-2xl bg-slate-50 p-3"><dt class="text-slate-500">Staff accounts</dt><dd class="mt-1 text-xl font-black text-slate-900">{{ $organization->users_count }}</dd></div><div class="rounded-2xl bg-slate-50 p-3"><dt class="text-slate-500">Currency</dt><dd class="mt-1 text-xl font-black text-slate-900">{{ $organization->base_currency }}</dd></div></dl>
                        <div class="mt-4 space-y-1 text-sm text-slate-600"><p>{{ $organization->email ?: 'No contact email' }}</p><p>{{ $organization->phone ?: 'No contact phone' }}</p></div>
                        <div class="mt-5 grid grid-cols-2 gap-2"><a href="{{ route('settings.organizations.show', $organization) }}" class="rounded-xl bg-emerald-700 px-4 py-2.5 text-center text-sm font-bold text-white hover:bg-emerald-800">View farm</a><button type="button" onclick='openFarmEdit(@json($organization))' class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Edit farm</button></div>
                    </article>
                @empty
                    <div class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500 md:col-span-2 xl:col-span-3">No farms have been created yet.</div>
                @endforelse
            </section>
            {{ $organizations->links() }}
        </div>
    </main>

    <div id="createFarm" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/60 p-4">
        <div class="mx-auto my-6 max-w-3xl rounded-3xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b p-5"><div><h2 class="text-xl font-black">Create a farm</h2><p class="text-sm text-slate-500">The first administrator is created in the same step.</p></div><button type="button" onclick="document.getElementById('createFarm').classList.add('hidden')" class="text-2xl text-slate-500">&times;</button></div>
            <form method="POST" action="{{ route('settings.organizations.store') }}" class="space-y-6 p-5">@csrf
                <fieldset class="grid gap-4 sm:grid-cols-2"><legend class="col-span-full mb-3 font-bold text-slate-900">Farm details</legend>
                    <label class="text-sm font-semibold">Farm name<input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-xl border-slate-300"></label>
                    <label class="text-sm font-semibold">Legal name<input name="legal_name" value="{{ old('legal_name') }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
                    <label class="text-sm font-semibold">Email<input type="email" name="email" value="{{ old('email') }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
                    <label class="text-sm font-semibold">Phone<input name="phone" value="{{ old('phone') }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
                </fieldset>
                <fieldset class="grid gap-4 border-t pt-5 sm:grid-cols-2"><legend class="col-span-full mb-3 font-bold text-slate-900">First farm administrator</legend>
                    <label class="text-sm font-semibold">First name<input name="admin_first_name" required class="mt-1 w-full rounded-xl border-slate-300"></label>
                    <label class="text-sm font-semibold">Last name<input name="admin_last_name" required class="mt-1 w-full rounded-xl border-slate-300"></label>
                    <label class="text-sm font-semibold">Email<input type="email" name="admin_email" required class="mt-1 w-full rounded-xl border-slate-300"></label>
                    <label class="text-sm font-semibold">Phone<input name="admin_phone" class="mt-1 w-full rounded-xl border-slate-300"></label>
                    <label class="text-sm font-semibold">Temporary password<input type="password" name="admin_password" minlength="8" required class="mt-1 w-full rounded-xl border-slate-300"></label>
                    <label class="text-sm font-semibold">Confirm password<input type="password" name="admin_password_confirmation" minlength="8" required class="mt-1 w-full rounded-xl border-slate-300"></label>
                </fieldset>
                <div class="flex justify-end gap-3"><button type="button" onclick="document.getElementById('createFarm').classList.add('hidden')" class="rounded-xl border px-5 py-3 font-bold">Cancel</button><button class="rounded-xl bg-emerald-700 px-5 py-3 font-bold text-white">Create farm and admin</button></div>
            </form>
        </div>
    </div>

    <div id="editFarm" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/60 p-4"><div class="mx-auto my-10 max-w-xl rounded-3xl bg-white p-6 shadow-2xl"><div class="flex justify-between"><h2 class="text-xl font-black">Edit farm</h2><button type="button" onclick="document.getElementById('editFarm').classList.add('hidden')" class="text-2xl">&times;</button></div><form id="editFarmForm" method="POST" class="mt-5 grid gap-4">@csrf @method('PUT')<label class="text-sm font-semibold">Farm name<input id="farm_name" name="name" required class="mt-1 w-full rounded-xl border-slate-300"></label><label class="text-sm font-semibold">Legal name<input id="farm_legal_name" name="legal_name" class="mt-1 w-full rounded-xl border-slate-300"></label><label class="text-sm font-semibold">Slug<input id="farm_slug" name="slug" required class="mt-1 w-full rounded-xl border-slate-300"></label><label class="text-sm font-semibold">Email<input id="farm_email" type="email" name="email" class="mt-1 w-full rounded-xl border-slate-300"></label><label class="text-sm font-semibold">Phone<input id="farm_phone" name="phone" class="mt-1 w-full rounded-xl border-slate-300"></label><label class="flex items-center gap-2"><input id="farm_active" type="checkbox" value="1" class="rounded"><span class="font-semibold">Farm is active</span></label><input id="farm_active_value" type="hidden" name="is_active"><button class="rounded-xl bg-emerald-700 px-5 py-3 font-bold text-white">Save changes</button></form></div></div>
    <script>function openFarmEdit(farm){const form=document.getElementById('editFarmForm');form.action='{{ url('settings/organizations') }}/'+farm.id;for(const key of ['name','legal_name','slug','email','phone'])document.getElementById('farm_'+key).value=farm[key]||'';document.getElementById('farm_active').checked=!!farm.is_active;document.getElementById('farm_active_value').value=farm.is_active?1:0;document.getElementById('editFarm').classList.remove('hidden')}document.getElementById('farm_active')?.addEventListener('change',e=>document.getElementById('farm_active_value').value=e.target.checked?1:0);</script>
</x-app-layout>
