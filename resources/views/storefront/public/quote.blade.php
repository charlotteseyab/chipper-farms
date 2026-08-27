<x-storefront.layout :title="'Request a quote'">
    <style>
        .quote-submit-button {
            display: inline-flex;
            min-width: 220px;
            min-height: 48px;
            align-items: center;
            justify-content: center;
            padding: 0 24px;
            white-space: nowrap;
            line-height: 1.2;
        }
        @media (max-width: 639px) {
            .quote-submit-button { width: 100%; }
        }
    </style>
    @php
        $initialItems = old('items', [['kind' => 'live_pig', 'description' => '', 'quantity' => 1, 'quantity_unit' => 'head', 'line_note' => '']]);
        $productKinds = ['live_pig' => 'Live pigs', 'piglet_lot' => 'Piglets', 'breeding_stock' => 'Breeding stock', 'feed' => 'Feed', 'manure' => 'Manure', 'service' => 'Service'];
    @endphp
    <div class="mx-auto max-w-4xl px-4 py-8 sm:py-12">
        <div class="mb-7"><p class="text-xs font-bold uppercase tracking-[.18em] text-emerald-700">Pricing request</p><h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">Request a quote</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Tell us what you need and how you want to receive it. Our team will confirm availability and send you a price.</p></div>

        <div class="mb-7 grid grid-cols-3 overflow-hidden rounded-2xl border border-slate-200 bg-white text-xs sm:text-sm">
            @foreach([['1','Your details'],['2','What you need'],['3','Fulfilment']] as [$number, $label])<div class="flex items-center gap-2 border-r border-slate-100 px-3 py-3 sm:px-5"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700">{{ $number }}</span><span class="font-semibold text-slate-600">{{ $label }}</span></div>@endforeach
        </div>

        @if($errors->any())<div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><p class="font-semibold">Please check the information below.</p><ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <form method="POST" action="{{ route('storefront.quote.store') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="source" value="{{ $source }}">

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="mb-5"><h2 class="font-bold text-slate-900">1. Your details</h2><p class="mt-1 text-xs text-slate-500">We will use these details to send the quotation and ask any follow-up questions.</p></div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm"><span class="font-medium text-slate-600">Full name <span class="text-red-500">*</span></span><input type="text" name="contact_name" value="{{ old('contact_name', $user?->name) }}" required autocomplete="name" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"></label>
                    <label class="block text-sm"><span class="font-medium text-slate-600">Email address</span><input type="email" name="contact_email" value="{{ old('contact_email', $user?->email) }}" autocomplete="email" @if($buyer ?? null) readonly @endif class="mt-1.5 w-full rounded-xl border-slate-300 text-sm {{ ($buyer ?? null) ? 'bg-slate-100' : '' }}"><span class="mt-1 block text-xs text-slate-400">{{ ($buyer ?? null) ? 'Linked to your buyer account so this request appears under My quotes.' : 'Used for your confirmation and quotation.' }}</span></label>
                    <label class="block text-sm"><span class="font-medium text-slate-600">Phone number</span><input type="tel" name="contact_phone" value="{{ old('contact_phone', $user?->phone) }}" autocomplete="tel" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"><span class="mt-1 block text-xs text-slate-400">Provide either an email address or phone number.</span></label>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="mb-5 flex items-start justify-between gap-4"><div><h2 class="font-bold text-slate-900">2. What do you need?</h2><p class="mt-1 text-xs text-slate-500">Add quantities and details that will help us price accurately.</p></div>@unless($listing)<button type="button" id="quote-add-item" class="shrink-0 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700">+ Add item</button>@endunless</div>
                @if($listing)
                    @php
                        $listingUnit = old('items.0.quantity_unit', $listing->quantity_unit ?: 'head');
                        $listingUnitLabels = [
                            'head' => 'Pigs',
                            'piglets' => 'Piglets',
                            'kg' => 'Kilograms (kg)',
                            'bag' => 'Bags',
                            'bag_25kg' => '25 kg bags',
                            'bag_50kg' => '50 kg bags',
                            'tonne' => 'Tonnes',
                            'truckload' => 'Truckloads',
                            'job' => 'Complete job',
                            'hour' => 'Hours',
                            'day' => 'Days',
                            'unit' => 'Units',
                        ];
                    @endphp
                    <input type="hidden" name="items[0][listing_id]" value="{{ $listing->id }}"><input type="hidden" name="items[0][kind]" value="{{ $listing->kind }}"><input type="hidden" name="items[0][description]" value="{{ $listing->title }}">
                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50/60 p-4"><p class="text-xs font-bold uppercase tracking-wide text-emerald-700">Selected product</p><p class="mt-1 text-lg font-bold text-slate-900">{{ $listing->title }}</p><div class="mt-4 grid gap-3 sm:grid-cols-3"><label class="text-sm"><span class="text-slate-600">Quantity</span><input type="number" min="0.0001" step="0.0001" name="items[0][quantity]" value="{{ old('items.0.quantity', 1) }}" required class="mt-1 w-full rounded-xl border-slate-300 bg-white text-sm"></label><label class="text-sm"><span class="text-slate-600">Measured in</span><input type="hidden" name="items[0][quantity_unit]" value="{{ $listingUnit }}"><input value="{{ $listingUnitLabels[$listingUnit] ?? ucfirst(str_replace('_', ' ', $listingUnit)) }}" readonly class="mt-1 w-full rounded-xl border-slate-300 bg-slate-100 text-sm"></label><label class="text-sm"><span class="text-slate-600">Item note</span><input name="items[0][line_note]" value="{{ old('items.0.line_note') }}" placeholder="Breed, size, etc." class="mt-1 w-full rounded-xl border-slate-300 bg-white text-sm"></label></div></div>
                @else
                    <div id="quote-items" class="space-y-4">@foreach($initialItems as $index => $item)@include('storefront.public.partials.quote-item-row', ['index' => $index, 'item' => $item])@endforeach</div>
                    <template id="quote-item-template">@include('storefront.public.partials.quote-item-row', ['index' => '__INDEX__', 'item' => []])</template>
                @endif
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="mb-5"><h2 class="font-bold text-slate-900">3. Fulfilment</h2><p class="mt-1 text-xs text-slate-500">Choose how you would like to receive the items.</p></div>
                @php $selectedFulfilment = old('fulfilment_preference', 'pickup'); @endphp
                <div class="grid gap-3 {{ $deliveryEnabled ? 'sm:grid-cols-2' : '' }}"><label data-fulfilment-card class="flex cursor-pointer gap-3 rounded-2xl border p-4 {{ $selectedFulfilment === 'pickup' ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200' }}"><input type="radio" name="fulfilment_preference" value="pickup" @checked($selectedFulfilment === 'pickup') class="mt-1 text-emerald-600"><span><span class="block font-bold text-slate-800">Farm pickup</span><span class="mt-1 block text-xs text-slate-500">Collect from the farm after confirmation.</span></span></label>@if($deliveryEnabled)<label data-fulfilment-card class="flex cursor-pointer gap-3 rounded-2xl border p-4 {{ $selectedFulfilment === 'delivery' ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200' }}"><input type="radio" name="fulfilment_preference" value="delivery" @checked($selectedFulfilment === 'delivery') class="mt-1 text-emerald-600"><span><span class="block font-bold text-slate-800">Local delivery</span><span class="mt-1 block text-xs text-slate-500">Availability and cost will be included in the quote.</span></span></label>@endif</div>
                @if($deliveryEnabled)<div id="quote-delivery-fields" class="mt-4 grid gap-4 rounded-2xl bg-slate-50 p-4 sm:grid-cols-2 {{ $selectedFulfilment === 'delivery' ? '' : 'hidden' }}"><label class="text-sm"><span class="font-medium text-slate-600">Delivery town or city <span class="text-red-500">*</span></span><input name="delivery_city" value="{{ old('delivery_city') }}" class="mt-1 w-full rounded-xl border-slate-300 bg-white text-sm"></label><label class="text-sm"><span class="font-medium text-slate-600">Region <span class="text-red-500">*</span></span><input name="delivery_region" value="{{ old('delivery_region') }}" class="mt-1 w-full rounded-xl border-slate-300 bg-white text-sm"></label></div>@endif
                <label class="mt-4 block text-sm"><span class="font-medium text-slate-600">General message <span class="text-slate-400">(optional)</span></span><textarea name="message" rows="3" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" placeholder="Preferred date, special instructions or anything else we should know">{{ old('message') }}</textarea></label>
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between"><a href="{{ route('storefront.shop') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-center text-sm font-semibold text-slate-600">Cancel</a><div class="text-center sm:text-right"><button type="submit" class="quote-submit-button rounded-xl bg-emerald-700 text-sm font-bold text-white hover:bg-emerald-800">Submit quote request</button><p class="mt-2 text-xs text-slate-400">No payment is required at this stage.</p></div></div>
        </form>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const items = document.getElementById('quote-items');
            const add = document.getElementById('quote-add-item');
            const template = document.getElementById('quote-item-template');
            const unitsByCategory = {
                live_pig: [['head', 'Pigs'], ['kg', 'Kilograms (kg)']],
                piglet_lot: [['piglets', 'Piglets'], ['head', 'Pigs']],
                breeding_stock: [['head', 'Breeding pigs']],
                feed: [['bag_25kg', '25 kg bags'], ['bag_50kg', '50 kg bags'], ['bag', 'Bags (other size)'], ['kg', 'Kilograms (kg)'], ['tonne', 'Tonnes']],
                manure: [['bag', 'Bags'], ['kg', 'Kilograms (kg)'], ['tonne', 'Tonnes'], ['truckload', 'Truckloads']],
                service: [['job', 'Complete job'], ['hour', 'Hours'], ['day', 'Days']],
            };
            const refreshUnits = (row, keepSelection = true) => {
                const category = row.querySelector('[data-quote-category]');
                const unit = row.querySelector('[data-quote-unit]');
                if (!category || !unit) return;
                const previous = keepSelection ? (unit.value || unit.dataset.selectedUnit) : '';
                const options = unitsByCategory[category.value] || [['unit', 'Units']];
                unit.innerHTML = options.map(([value, label]) => `<option value="${value}">${label}</option>`).join('');
                if (options.some(([value]) => value === previous)) unit.value = previous;
                unit.dataset.selectedUnit = '';
            };
            const reindex = () => {
                if (!items) return;
                const rows = [...items.querySelectorAll('.quote-item-row')];
                rows.forEach((row, index) => {
                    row.querySelector('.quote-item-title').textContent = `Item ${index + 1}`;
                    row.querySelectorAll('[name]').forEach(field => field.name = field.name.replace(/items\[[^\]]+\]/, `items[${index}]`));
                    const remove = row.querySelector('.quote-remove-item');
                    remove.hidden = rows.length === 1;
                    refreshUnits(row);
                });
                if (add) add.disabled = rows.length >= 8;
            };
            add?.addEventListener('click', () => {
                if (!items || !template || items.children.length >= 8) return;
                items.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(items.children.length)));
                reindex();
            });
            items?.addEventListener('click', event => {
                const remove = event.target.closest('.quote-remove-item');
                if (!remove || items.children.length === 1) return;
                remove.closest('.quote-item-row').remove();
                reindex();
            });
            items?.addEventListener('change', event => {
                if (!event.target.matches('[data-quote-category]')) return;
                refreshUnits(event.target.closest('.quote-item-row'), false);
            });
            document.querySelectorAll('input[name="fulfilment_preference"]').forEach(radio => radio.addEventListener('change', () => {
                const delivery = document.getElementById('quote-delivery-fields');
                if (delivery) delivery.classList.toggle('hidden', radio.value !== 'delivery');
                document.querySelectorAll('[data-fulfilment-card]').forEach(card => {
                    const selected = card.querySelector('input').checked;
                    card.classList.toggle('border-emerald-500', selected); card.classList.toggle('bg-emerald-50', selected); card.classList.toggle('border-slate-200', !selected);
                });
            }));
            reindex();
        });
    </script>
</x-storefront.layout>
