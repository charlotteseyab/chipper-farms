<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-6">
            <div>
                <a href="{{ route('purchase-stock-intakes.index') }}" class="text-sm text-slate-500">Back to purchased stock intake</a>
                <h1 class="text-2xl font-bold text-slate-900 mt-2">{{ $intake->batch_number }}</h1>
                <p class="text-sm text-slate-500 mt-1">{{ $intake->supplier_name }} • {{ optional($intake->purchase_date)->format('M d, Y') }}</p>
            </div>
            <span class="self-start rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">
                {{ $statuses[$intake->status] ?? $intake->status }}
            </span>
        </div>

        @if(session('success'))
            <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-5 lg:grid-cols-[1fr_360px]">
            <div class="space-y-5">
                <div class="bg-white border border-slate-200 rounded-xl p-5">
                    <h2 class="text-lg font-bold text-slate-900 mb-4">Purchase Details</h2>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div><div class="text-xs font-semibold uppercase text-slate-400">Category</div><div class="mt-1 text-sm text-slate-800">{{ str_replace('_', ' ', ucfirst($intake->animal_category)) }}</div></div>
                        <div><div class="text-xs font-semibold uppercase text-slate-400">Breed</div><div class="mt-1 text-sm text-slate-800">{{ $intake->breed ?: 'Not specified' }}</div></div>
                        <div><div class="text-xs font-semibold uppercase text-slate-400">Reference</div><div class="mt-1 text-sm text-slate-800">{{ $intake->document_reference ?: 'None' }}</div></div>
                        <div><div class="text-xs font-semibold uppercase text-slate-400">Ordered</div><div class="mt-1 text-sm text-slate-800">{{ number_format($intake->quantity_ordered) }}</div></div>
                        <div><div class="text-xs font-semibold uppercase text-slate-400">Received</div><div class="mt-1 text-sm text-slate-800">{{ $intake->quantity_received === null ? 'Pending' : number_format($intake->quantity_received) }}</div></div>
                        <div><div class="text-xs font-semibold uppercase text-slate-400">Rejected</div><div class="mt-1 text-sm text-slate-800">{{ number_format($intake->quantity_rejected) }}</div></div>
                        <div><div class="text-xs font-semibold uppercase text-slate-400">Male / Female</div><div class="mt-1 text-sm text-slate-800">{{ $intake->male_quantity ?? '-' }} / {{ $intake->female_quantity ?? '-' }}</div></div>
                        <div><div class="text-xs font-semibold uppercase text-slate-400">Estimated Age</div><div class="mt-1 text-sm text-slate-800">{{ $intake->estimated_age_days ? number_format($intake->estimated_age_days) . ' days' : 'Not specified' }}</div></div>
                        <div><div class="text-xs font-semibold uppercase text-slate-400">Average Weight</div><div class="mt-1 text-sm text-slate-800">{{ $intake->average_weight_kg ? number_format((float) $intake->average_weight_kg, 2) . ' kg' : 'Not recorded' }}</div></div>
                        <div><div class="text-xs font-semibold uppercase text-slate-400">Purchase Cost</div><div class="mt-1 text-sm text-slate-800">GHS {{ number_format((float) $intake->total_purchase_cost, 2) }}</div></div>
                        <div><div class="text-xs font-semibold uppercase text-slate-400">Transport Cost</div><div class="mt-1 text-sm text-slate-800">GHS {{ number_format((float) $intake->transport_cost, 2) }}</div></div>
                        <div><div class="text-xs font-semibold uppercase text-slate-400">Cost per Received Animal</div><div class="mt-1 text-sm text-slate-800">GHS {{ number_format($intake->costPerReceivedAnimal(), 2) }}</div></div>
                        <div><div class="text-xs font-semibold uppercase text-slate-400">Finance Status</div><div class="mt-1 text-sm text-slate-800">{{ str_replace('_', ' ', ucfirst($intake->finance_posting_status ?? 'not_configured')) }}</div></div>
                    </div>
                    @if($intake->finance_posting_message)
                        <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">{{ $intake->finance_posting_message }}</p>
                    @endif
                </div>

                <div class="bg-white border border-slate-200 rounded-xl p-5">
                    <h2 class="text-lg font-bold text-slate-900 mb-4">Housing & Health</h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div><div class="text-xs font-semibold uppercase text-slate-400">Room</div><div class="mt-1 text-sm text-slate-800">{{ $intake->room ? $intake->room->name . ' / ' . optional($intake->room->structure)->name : 'Not assigned' }}</div></div>
                        <div><div class="text-xs font-semibold uppercase text-slate-400">Group</div><div class="mt-1 text-sm text-slate-800">{{ optional($intake->group)->name ?: 'Not assigned' }}</div></div>
                        <div><div class="text-xs font-semibold uppercase text-slate-400">Health Status</div><div class="mt-1 text-sm text-slate-800">{{ $intake->health_status ?: 'Not recorded' }}</div></div>
                        <div><div class="text-xs font-semibold uppercase text-slate-400">Quarantine Release</div><div class="mt-1 text-sm text-slate-800">{{ optional($intake->quarantine_release_date)->format('M d, Y') ?: 'Not set' }}</div></div>
                        <div><div class="text-xs font-semibold uppercase text-slate-400">Quarantine Decision</div><div class="mt-1 text-sm font-semibold text-slate-800">{{ $intake->quarantine_outcome ? str_replace('_', ' ', ucfirst($intake->quarantine_outcome)) : 'Awaiting review' }}</div></div>
                        <div><div class="text-xs font-semibold uppercase text-slate-400">Cleared for Stock</div><div class="mt-1 text-sm font-semibold text-emerald-700">{{ number_format($intake->clearedQuantity()) }}</div></div>
                    </div>
                    @if($intake->health_notes || $intake->notes)
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div><div class="text-xs font-semibold uppercase text-slate-400">Health Notes</div><p class="mt-1 text-sm text-slate-700">{{ $intake->health_notes ?: 'None' }}</p></div>
                            <div><div class="text-xs font-semibold uppercase text-slate-400">Purchase Notes</div><p class="mt-1 text-sm text-slate-700">{{ $intake->notes ?: 'None' }}</p></div>
                        </div>
                    @endif
                </div>

                @if($intake->pigs->isNotEmpty())
                    <div class="bg-white border border-slate-200 rounded-xl p-5">
                        <h2 class="text-lg font-bold text-slate-900 mb-4">Generated Pig Records</h2>
                        <div class="grid gap-3 md:grid-cols-2">
                            @foreach($intake->pigs as $pig)
                                <a href="{{ route('pigs.show', $pig) }}" class="rounded-lg border border-slate-200 p-3 hover:border-emerald-300">
                                    <div class="font-semibold text-slate-900">{{ $pig->ear_tag_number }}</div>
                                    <div class="text-xs text-slate-500">{{ $pig->stock_type }} • {{ $pig->gender }} • {{ $pig->breed }}</div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="space-y-5">
                <div class="bg-white border border-slate-200 rounded-xl p-5">
                    <h2 class="text-lg font-bold text-slate-900 mb-4">Workflow Actions</h2>

                    @if($intake->status === \App\Models\PurchaseStockIntake::STATUS_PENDING_RECEIVE)
                        <form method="POST" action="{{ route('purchase-stock-intakes.receive', $intake) }}" class="space-y-3">
                            @csrf
                            <label class="block"><span class="text-sm font-semibold text-slate-700">Quantity Received</span><input type="number" min="0" name="quantity_received" value="{{ old('quantity_received', $intake->quantity_ordered) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required></label>
                            <label class="block"><span class="text-sm font-semibold text-slate-700">Quantity Rejected</span><input type="number" min="0" name="quantity_rejected" value="{{ old('quantity_rejected', 0) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label>
                            <label class="block"><span class="text-sm font-semibold text-slate-700">Health Status</span><input name="health_status" value="{{ old('health_status', $intake->health_status) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label>
                            <label class="block"><span class="text-sm font-semibold text-slate-700">Health Notes</span><textarea name="health_notes" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm">{{ old('health_notes', $intake->health_notes) }}</textarea></label>
                            <button class="w-full rounded-lg px-4 py-2 text-sm font-semibold" style="background:#1d4ed8;color:#ffffff;border:1px solid #1d4ed8;box-shadow:0 8px 18px rgba(29,78,216,.18);">Receive Animals</button>
                        </form>
                    @elseif($intake->status === \App\Models\PurchaseStockIntake::STATUS_RECEIVED || ($intake->status === \App\Models\PurchaseStockIntake::STATUS_QUARANTINED && ! $intake->room_id))
                        <form method="POST" action="{{ route('purchase-stock-intakes.quarantine', $intake) }}" class="space-y-3">
                            @csrf
                            @if($intake->status === \App\Models\PurchaseStockIntake::STATUS_QUARANTINED)
                                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                                    This intake is already marked as quarantined, but no physical quarantine room was assigned. Select the room before approval.
                                </div>
                            @endif
                            <label class="block"><span class="text-sm font-semibold text-slate-700">Quarantine Room</span><select name="room_id" required class="mt-1 w-full rounded-lg border-slate-300 text-sm"><option value="">Select room</option>@foreach($rooms as $room)@php $available = $room->capacity ? max(0, (int) $room->capacity - (int) $room->active_pigs_count) : null; @endphp<option value="{{ $room->id }}" @selected(old('room_id', $intake->room_id) == $room->id)>{{ optional($room->structure)->name }} / {{ $room->name }} @if($available === null)(unlimited)@else({{ $available }} available)@endif</option>@endforeach</select><span class="mt-1 block text-xs text-slate-500">The physical room where the animals will remain isolated during quarantine.</span></label>
                            <label class="block"><span class="text-sm font-semibold text-slate-700">Health Status</span><input name="health_status" value="{{ old('health_status', $intake->health_status) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label>
                            <label class="block"><span class="text-sm font-semibold text-slate-700">Release Date</span><input type="date" name="quarantine_release_date" value="{{ old('quarantine_release_date', optional($intake->quarantine_release_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label>
                            <label class="block"><span class="text-sm font-semibold text-slate-700">Health Notes</span><textarea name="health_notes" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm">{{ old('health_notes', $intake->health_notes) }}</textarea></label>
                            <button class="w-full rounded-lg px-4 py-2 text-sm font-semibold" style="background:#b45309;color:#ffffff;border:1px solid #b45309;box-shadow:0 8px 18px rgba(180,83,9,.18);">
                                {{ $intake->status === \App\Models\PurchaseStockIntake::STATUS_QUARANTINED ? 'Assign Quarantine Housing' : 'Move to Quarantine' }}
                            </button>
                        </form>
                    @elseif($intake->status === \App\Models\PurchaseStockIntake::STATUS_QUARANTINED && ! $intake->hasPassedQuarantineReview())
                        @if($intake->quarantine_outcome === \App\Models\PurchaseStockIntake::QUARANTINE_EXTENDED)
                            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                                Quarantine was extended to <strong>{{ optional($intake->quarantine_release_date)->format('M d, Y') }}</strong>. Complete another review when the animals are ready.
                            </div>
                        @else
                            <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">
                                Review the animals after quarantine. Approval is available only when at least one animal is cleared.
                            </div>
                        @endif
                        <form method="POST" action="{{ route('purchase-stock-intakes.quarantine-review', $intake) }}" class="space-y-3" x-data="{ outcome: '{{ old('quarantine_outcome', '') }}' }">
                            @csrf
                            <label class="block"><span class="text-sm font-semibold text-slate-700">Quarantine Decision</span><select name="quarantine_outcome" x-model="outcome" required class="mt-1 w-full rounded-lg border-slate-300 text-sm"><option value="">Choose decision</option><option value="cleared">Clear all animals</option><option value="extended">Extend quarantine</option><option value="partially_cleared">Clear some, reject some</option><option value="rejected">Reject entire intake</option></select></label>
                            <label x-show="outcome === 'partially_cleared'" x-cloak class="block"><span class="text-sm font-semibold text-slate-700">Number Rejected in Quarantine</span><input type="number" min="1" max="{{ max(1, $intake->receivedQuantity() - 1) }}" name="quarantine_rejected_quantity" value="{{ old('quarantine_rejected_quantity') }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm"><span class="mt-1 block text-xs text-slate-500">Received: {{ $intake->receivedQuantity() }}. The remainder will be cleared.</span></label>
                            <label x-show="outcome === 'extended'" x-cloak class="block"><span class="text-sm font-semibold text-slate-700">Next Review Date</span><input type="date" min="{{ now()->toDateString() }}" name="quarantine_release_date" value="{{ old('quarantine_release_date', optional($intake->quarantine_release_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label>
                            <label x-show="outcome === 'partially_cleared' || outcome === 'rejected'" x-cloak class="block"><span class="text-sm font-semibold text-slate-700">Disposition of Rejected Animals</span><select name="quarantine_disposition" class="mt-1 w-full rounded-lg border-slate-300 text-sm"><option value="">Choose what happened</option><option value="returned_to_supplier" @selected(old('quarantine_disposition') === 'returned_to_supplier')>Returned to supplier</option><option value="euthanized" @selected(old('quarantine_disposition') === 'euthanized')>Humanely euthanized</option><option value="died_in_quarantine" @selected(old('quarantine_disposition') === 'died_in_quarantine')>Died in quarantine</option><option value="isolated_elsewhere" @selected(old('quarantine_disposition') === 'isolated_elsewhere')>Moved to continued isolation</option></select></label>
                            <label class="block"><span class="text-sm font-semibold text-slate-700">Current Health Status</span><input name="health_status" value="{{ old('health_status', $intake->health_status) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label>
                            <label x-show="outcome === 'partially_cleared' || outcome === 'rejected'" x-cloak class="block"><span class="text-sm font-semibold text-slate-700">Decision Reason</span><textarea name="quarantine_review_notes" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm" placeholder="Record symptoms, veterinary advice and reason for the decision.">{{ old('quarantine_review_notes') }}</textarea></label>
                            <label class="block"><span class="text-sm font-semibold text-slate-700">Updated Health Notes</span><textarea name="health_notes" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm">{{ old('health_notes', $intake->health_notes) }}</textarea></label>
                            <button class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white">Save Quarantine Decision</button>
                        </form>
                    @elseif($intake->status === \App\Models\PurchaseStockIntake::STATUS_QUARANTINED && $intake->hasPassedQuarantineReview())
                        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">
                            <strong>{{ $intake->clearedQuantity() }} animal{{ $intake->clearedQuantity() === 1 ? '' : 's' }}</strong> cleared for stock entry.
                            @if($intake->quarantine_rejected_quantity > 0)
                                {{ $intake->quarantine_rejected_quantity }} will not be added ({{ str_replace('_', ' ', $intake->quarantine_disposition) }}).
                            @endif
                        </div>
                        <form method="POST" action="{{ route('purchase-stock-intakes.approve', $intake) }}">
                            @csrf
                            <button class="w-full rounded-lg px-4 py-2 text-sm font-semibold" style="background:#14532d;color:#ffffff;border:1px solid #14532d;box-shadow:0 8px 18px rgba(20,83,45,.22);">Approve Stock Entry</button>
                        </form>
                    @elseif($intake->status === \App\Models\PurchaseStockIntake::STATUS_APPROVED || $canMaterializeLegacyPiglets)
                        <form method="POST" action="{{ route('purchase-stock-intakes.add-to-stock', $intake) }}">
                            @csrf
                            @if($canMaterializeLegacyPiglets)
                                <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                                    This older piglet intake was counted only as a batch. Create its individual main-stock record{{ $intake->receivedQuantity() === 1 ? '' : 's' }} now so it appears in All Stock.
                                </div>
                            @endif
                            <div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <div class="text-sm font-bold text-slate-900">Final Housing</div>
                                <p class="mt-1 text-xs text-slate-500">Choose the physical room where the cleared animals will be housed. Room capacity is checked before stock records are created.</p>
                            </div>
                            <div class="mb-4 space-y-3">
                                <label class="block">
                                    <span class="text-sm font-semibold text-slate-700">Room</span>
                                    <select name="room_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                        <option value="">Select room</option>
                                        @foreach($rooms as $room)
                                            @php
                                                $available = $room->capacity ? max(0, (int) $room->capacity - (int) $room->active_pigs_count) : null;
                                            @endphp
                                            <option value="{{ $room->id }}" @selected(old('room_id', $intake->room_id) == $room->id)>
                                                {{ optional($room->structure)->name }} / {{ $room->name }}
                                                @if($available === null)
                                                    (unlimited)
                                                @else
                                                    ({{ $available }} available)
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                                <div class="text-sm font-bold text-slate-900">Proposed Ear Tag Numbers</div>
                                <p class="mt-1 text-xs text-slate-600">Based on the most recently added animal. Keep these suggestions or replace any tag before adding the animals.</p>
                            </div>
                            <div class="mb-4 max-h-72 space-y-3 overflow-y-auto pr-1">
                                @for($i = 0; $i < $intake->clearedQuantity(); $i++)
                                    <div class="grid gap-2 sm:grid-cols-2">
                                    <label class="block"><span class="text-sm font-semibold text-slate-700">Animal {{ $i + 1 }} Tag Colour</span><select name="tag_color_ids[]" required class="mt-1 w-full rounded-lg border-slate-300 text-sm"><option value="">Select tag colour</option>@foreach($tagColors as $color)<option value="{{ $color->id }}" @selected((string) old('tag_color_ids.' . $i) === (string) $color->id)>{{ $color->name }}</option>@endforeach</select></label>
                                    <label class="block">
                                        <span class="text-sm font-semibold text-slate-700">Animal {{ $i + 1 }} Ear Tag</span>
                                        <input name="ear_tag_numbers[]" value="{{ old('ear_tag_numbers.' . $i, $proposedEarTags[$i] ?? '') }}" required maxlength="50" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                    </label>
                                    </div>
                                @endfor
                            </div>
                            <button class="w-full rounded-lg px-4 py-2 text-sm font-semibold" style="background:#0f172a;color:#ffffff;border:1px solid #0f172a;box-shadow:0 8px 18px rgba(15,23,42,.22);">{{ $canMaterializeLegacyPiglets ? 'Create Main Stock Records' : 'Add to Main Stock' }}</button>
                        </form>
                        <p class="mt-3 text-xs text-slate-500">Each animal cleared after quarantine will appear in All Stock as an individual, traceable record.</p>
                    @else
                        <p class="text-sm text-slate-500">No further workflow actions are available.</p>
                    @endif
                </div>

                <div class="bg-white border border-slate-200 rounded-xl p-5">
                    <h2 class="text-lg font-bold text-slate-900 mb-4">Audit Trail</h2>
                    <div class="space-y-3 text-sm text-slate-700">
                        <div><span class="font-semibold">Created:</span> {{ optional($intake->created_at)->format('M d, Y H:i') }}</div>
                        <div><span class="font-semibold">Received:</span> {{ optional($intake->received_at)->format('M d, Y H:i') ?: 'Pending' }}</div>
                        <div><span class="font-semibold">Quarantined:</span> {{ optional($intake->quarantined_at)->format('M d, Y H:i') ?: 'Pending' }}</div>
                        <div><span class="font-semibold">Quarantine reviewed:</span> {{ optional($intake->quarantine_reviewed_at)->format('M d, Y H:i') ?: 'Pending' }}</div>
                        <div><span class="font-semibold">Approved:</span> {{ optional($intake->approved_at)->format('M d, Y H:i') ?: 'Pending' }}</div>
                        <div><span class="font-semibold">Added to stock:</span> {{ optional($intake->added_to_stock_at)->format('M d, Y H:i') ?: 'Pending' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
