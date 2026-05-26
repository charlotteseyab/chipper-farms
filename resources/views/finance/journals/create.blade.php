<x-app-layout>
    <div class="max-w-6xl mx-auto py-6 px-4">
        <div class="mb-4">
            <a href="{{ route('finance.journals.index') }}" class="text-sm text-slate-500 hover:underline">← Back to Batches</a>
        </div>

        <h1 class="text-2xl font-bold text-gray-800 mb-2">New Manual Journal Entry</h1>
        <p class="text-sm text-gray-500 mb-6">Add at least 2 lines. Total debits must equal total credits.</p>


        <form method="POST" action="{{ route('finance.journals.store') }}" id="journal-form"
              x-data="journalForm()" x-init="init()">
            @csrf

            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Posting Date</label>
                        <input type="date" name="posting_date" required value="{{ old('posting_date', now()->toDateString()) }}"
                               class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Reference</label>
                        <input type="text" name="reference" value="{{ old('reference') }}" placeholder="Optional document reference"
                               class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Journal Type</label>
                        <input type="text" name="journal_type" value="{{ old('journal_type', 'manual') }}"
                               class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                    <textarea name="description" rows="2" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Why this journal is being posted">{{ old('description') }}</textarea>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Lines</h3>
                    <button type="button" @click="addLine()" class="text-sm text-emerald-700 hover:underline font-medium">+ Add line</button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-2 py-2 text-left">Account</th>
                                <th class="px-2 py-2 text-left">Side</th>
                                <th class="px-2 py-2 text-right">Amount</th>
                                <th class="px-2 py-2 text-left">Description</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(line, idx) in lines" :key="idx">
                                <tr>
                                    <td class="py-2 pr-2">
                                        <select :name="`lines[${idx}][account_id]`" x-model="line.account_id" required
                                                class="w-full rounded-lg border-slate-200 text-sm">
                                            <option value="">Select account...</option>
                                            @foreach($accounts as $acc)
                                                <option value="{{ $acc->id }}">{{ $acc->code }} — {{ $acc->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="py-2 pr-2">
                                        <select :name="`lines[${idx}][side]`" x-model="line.side" required
                                                class="rounded-lg border-slate-200 text-sm">
                                            <option value="debit">Debit</option>
                                            <option value="credit">Credit</option>
                                        </select>
                                    </td>
                                    <td class="py-2 pr-2 text-right">
                                        <input type="number" step="0.01" min="0.01" :name="`lines[${idx}][amount]`" x-model="line.amount" required
                                               class="w-32 rounded-lg border-slate-200 text-sm text-right">
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input type="text" :name="`lines[${idx}][description]`" x-model="line.description"
                                               class="w-full rounded-lg border-slate-200 text-sm">
                                    </td>
                                    <td class="py-2 text-right">
                                        <button type="button" @click="removeLine(idx)" :disabled="lines.length <= 2"
                                                class="text-red-500 hover:text-red-700 disabled:text-slate-300 text-xs">Remove</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-slate-50 font-semibold text-sm">
                            <tr>
                                <td colspan="2" class="px-2 py-3 text-right text-slate-600">Totals</td>
                                <td class="px-2 py-3 text-right">
                                    <span x-text="totalDebit.toFixed(2)"></span> Dr /
                                    <span x-text="totalCredit.toFixed(2)"></span> Cr
                                </td>
                                <td colspan="2" class="px-2 py-3">
                                    <span x-show="balanced()" class="text-emerald-700 text-xs">✓ Balanced</span>
                                    <span x-show="!balanced()" class="text-amber-700 text-xs">✗ Unbalanced — diff: <span x-text="(totalDebit - totalCredit).toFixed(2)"></span></span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 justify-end">
                <a href="{{ route('finance.journals.index') }}" class="px-4 py-2.5 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl text-center">Cancel</a>

                <button type="submit" :disabled="!balanced()"
                        class="px-4 py-2.5 bg-amber-600 hover:bg-amber-700 disabled:bg-slate-300 text-white text-sm font-semibold rounded-xl">
                    Propose for Approval
                </button>

                @if(auth()->user()?->hasPermission('approve_own_journals'))
                    <button type="submit" formaction="{{ route('finance.journals.store-and-approve') }}" :disabled="!balanced()"
                            class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 text-white text-sm font-semibold rounded-xl">
                        Propose &amp; Approve (Override)
                    </button>
                @endif
            </div>
        </form>

        <script>
            function journalForm() {
                return {
                    lines: [
                        { account_id: '', side: 'debit', amount: '', description: '' },
                        { account_id: '', side: 'credit', amount: '', description: '' },
                    ],
                    init() {},
                    addLine() {
                        this.lines.push({ account_id: '', side: 'debit', amount: '', description: '' });
                    },
                    removeLine(idx) {
                        if (this.lines.length > 2) this.lines.splice(idx, 1);
                    },
                    get totalDebit() {
                        return this.lines
                            .filter(l => l.side === 'debit')
                            .reduce((s, l) => s + (parseFloat(l.amount) || 0), 0);
                    },
                    get totalCredit() {
                        return this.lines
                            .filter(l => l.side === 'credit')
                            .reduce((s, l) => s + (parseFloat(l.amount) || 0), 0);
                    },
                    balanced() {
                        return this.totalDebit > 0 && Math.abs(this.totalDebit - this.totalCredit) < 0.005;
                    },
                };
            }
        </script>
    </div>
</x-app-layout>
