<x-app-layout>
    <style>
        .intake-page {
            max-width: 1120px;
            margin: 0 auto;
            padding: 24px 16px 40px;
        }

        .intake-top {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 22px;
        }

        .intake-back {
            color: #475569;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
        }

        .intake-title {
            margin: 8px 0 4px;
            color: #0f172a;
            font-size: 28px;
            font-weight: 800;
        }

        .intake-subtitle {
            color: #64748b;
            font-size: 14px;
            line-height: 1.5;
            margin: 0;
        }

        .intake-note {
            flex: 0 0 260px;
            border: 1px solid #bbf7d0;
            background: #f0fdf4;
            color: #166534;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 13px;
            line-height: 1.45;
            font-weight: 700;
        }

        .intake-error {
            margin-bottom: 18px;
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #b91c1c;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 14px;
            font-weight: 700;
        }

        .intake-form {
            display: grid;
            gap: 18px;
        }

        .intake-section {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 18px;
        }

        .section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .section-title {
            margin: 0;
            color: #0f172a;
            font-size: 16px;
            font-weight: 800;
        }

        .section-hint {
            margin: 0;
            color: #64748b;
            font-size: 13px;
        }

        .field-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .field-grid.three {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .field {
            display: block;
        }

        .field.wide {
            grid-column: 1 / -1;
        }

        .field span {
            display: block;
            color: #334155;
            font-size: 13px;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            color: #0f172a;
            font-size: 14px;
            min-height: 42px;
        }

        .field textarea {
            min-height: 92px;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            border-color: #15803d;
            box-shadow: 0 0 0 3px rgba(21, 128, 61, .14);
        }

        .form-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px 18px;
        }

        .cancel-link,
        .submit-button {
            border-radius: 8px;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
            min-height: 42px;
            display: inline-flex;
            align-items: center;
        }

        .cancel-link {
            border: 1px solid #cbd5e1;
            color: #334155;
            background: #ffffff;
        }

        .submit-button {
            border: 1px solid #14532d;
            background: #14532d;
            color: #ffffff;
            box-shadow: 0 8px 18px rgba(20, 83, 45, .22);
            cursor: pointer;
        }

        @media (max-width: 760px) {
            .intake-top {
                display: block;
            }

            .intake-note {
                margin-top: 14px;
            }

            .field-grid,
            .field-grid.three {
                grid-template-columns: 1fr;
            }

            .form-actions {
                align-items: stretch;
                flex-direction: column-reverse;
            }

            .cancel-link,
            .submit-button {
                justify-content: center;
                width: 100%;
            }
        }
    </style>

    <div class="intake-page">
        <div class="intake-top">
            <div>
                <a href="{{ route('purchase-stock-intakes.index') }}" class="intake-back">Back to purchased stock intake</a>
                <h1 class="intake-title">Create Purchased Stock Intake</h1>
                <p class="intake-subtitle">Record the purchase first. Stock counts change only after the animals are received, approved, and added to stock.</p>
            </div>

            <div class="intake-note">
                Purchased animals do not need sow or boar details. Supplier, quantity, cost, and intake health are the important fields.
            </div>
        </div>

        @if($errors->any())
            <div class="intake-error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('purchase-stock-intakes.store') }}" class="intake-form">
            @csrf

            <section class="intake-section">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">Purchase Source</h2>
                        <p class="section-hint">Who supplied the animals and when the purchase happened.</p>
                    </div>
                </div>

                <div class="field-grid">
                    <label class="field">
                        <span>Supplier / Source Farm</span>
                        <input name="supplier_name" value="{{ old('supplier_name') }}" required placeholder="External farm or seller name">
                    </label>
                    <label class="field">
                        <span>Purchase Date</span>
                        <input type="date" name="purchase_date" value="{{ old('purchase_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                    </label>
                    <label class="field wide">
                        <span>Document / Receipt Reference</span>
                        <input name="document_reference" value="{{ old('document_reference') }}" placeholder="Receipt, invoice, delivery note, or reference number">
                    </label>
                </div>
            </section>

            <section class="intake-section">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">Animal Details</h2>
                        <p class="section-hint">Use piglet batch when the animals are tracked as a group.</p>
                    </div>
                </div>

                <div class="field-grid">
                    <label class="field">
                        <span>Animal Category</span>
                        <select name="animal_category" required>
                            @foreach($categories as $value => $label)
                                <option value="{{ $value }}" @selected(old('animal_category') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span>Breed</span>
                        <select name="breed" data-searchable-select required>
                            <option value="">Select breed</option>
                            @foreach($breeds as $code => $name)
                                <option value="{{ $code }}" @selected(old('breed') === $code)>{{ $name }} ({{ $code }})</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span>Estimated Age Days <small>(this or date of birth)</small></span>
                        <input type="number" min="0" max="5475" name="estimated_age_days" value="{{ old('estimated_age_days') }}" placeholder="Example: 42">
                    </label>
                    <label class="field">
                        <span>Estimated Date of Birth</span>
                        <input type="date" name="estimated_date_of_birth" value="{{ old('estimated_date_of_birth') }}" max="{{ now()->toDateString() }}">
                    </label>
                    <label class="field wide">
                        <span>Average Weight kg</span>
                        <input type="number" step="0.01" min="0.1" max="500" name="average_weight_kg" value="{{ old('average_weight_kg') }}" placeholder="Average intake weight per animal">
                    </label>
                </div>
            </section>

            <section class="intake-section">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">Quantity And Cost</h2>
                        <p class="section-hint">Male and female quantities are optional and cannot exceed the ordered quantity. Each animal's sex is confirmed when it is tagged.</p>
                    </div>
                </div>

                <div class="field-grid three">
                    <label class="field">
                        <span>Quantity Ordered</span>
                        <input type="number" min="1" max="1000" name="quantity_ordered" value="{{ old('quantity_ordered') }}" required placeholder="Total animals bought">
                    </label>
                    <label class="field">
                        <span>Male Quantity</span>
                        <input type="number" min="0" name="male_quantity" value="{{ old('male_quantity') }}" placeholder="Optional">
                    </label>
                    <label class="field">
                        <span>Female Quantity</span>
                        <input type="number" min="0" name="female_quantity" value="{{ old('female_quantity') }}" placeholder="Optional">
                    </label>
                    <label class="field">
                        <span>Purchase Cost</span>
                        <input type="number" step="0.01" min="0" name="total_purchase_cost" value="{{ old('total_purchase_cost') }}" required placeholder="Total paid for animals">
                    </label>
                    <label class="field">
                        <span>Transport / Extra Cost</span>
                        <input type="number" step="0.01" min="0" name="transport_cost" value="{{ old('transport_cost', 0) }}" placeholder="0.00">
                    </label>
                </div>
            </section>

            <section class="intake-section">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">Health And Notes</h2>
                        <p class="section-hint">Capture the intake condition before receiving and quarantine actions are completed.</p>
                    </div>
                </div>

                <div class="field-grid">
                    <label class="field">
                        <span>Health Status</span>
                        <select name="health_status">
                            <option value="">Not checked yet</option>
                            @foreach($healthStatuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('health_status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field wide">
                        <span>Health Notes</span>
                        <textarea name="health_notes" rows="3" placeholder="Visible symptoms, treatments, vaccines, or quarantine concerns">{{ old('health_notes') }}</textarea>
                    </label>
                    <label class="field wide">
                        <span>Purchase Notes</span>
                        <textarea name="notes" rows="3" placeholder="Any extra supplier or purchase notes">{{ old('notes') }}</textarea>
                    </label>
                </div>
            </section>

            <div class="form-actions">
                <a href="{{ route('purchase-stock-intakes.index') }}" class="cancel-link">Cancel</a>
                <button class="submit-button">Create Purchase Intake</button>
            </div>
        </form>
    </div>
</x-app-layout>
