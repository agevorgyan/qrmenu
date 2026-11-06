@extends('layouts.app')

@section('title', __('Ingredients & Items Catalog') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div x-data="{ importModalOpen: false, isUploading: false, selectedFileName: '' }">
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div style="min-width: 0; flex: 1;">
        <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
            <span style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                <i class="fa-solid fa-wheat-awn"></i>
            </span>
            <span>{{ __('Ingredients & Catalog') }}</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
            {{ __('Manage raw materials, ingredients, semi-finished goods, packaging, and consumables.') }}
        </p>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        <a href="{{ route('admin.inventory.ingredients.export') }}" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; font-weight: 600;">
            <i class="fa-solid fa-file-csv"></i> {{ __('Export CSV') }}
        </a>
        @can('ingredients.create')
            <button type="button" @click="importModalOpen = true" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; font-weight: 600; border-color: rgba(16, 185, 129, 0.4); color: #10b981;">
                <i class="fa-solid fa-file-excel"></i> {{ __('Import Excel / CSV') }}
            </button>
            <a href="{{ route('admin.inventory.ingredients.create') }}" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; font-weight: 700;">
                <i class="fa-solid fa-plus"></i> {{ __('Add Ingredient') }}
            </a>
        @endcan
    </div>
</div>

<!-- Search & Filter Bar -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
    <form method="GET" action="{{ route('admin.inventory.ingredients.index') }}" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 2; min-width: 220px;">
            <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('Search') }}</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search name, SKU, barcode...') }}" class="form-input" style="width: 100%; border-radius: 10px;">
        </div>

        <div style="flex: 1; min-width: 160px;">
            <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('Category') }}</label>
            <select name="category_id" class="form-select" style="width: 100%; border-radius: 10px;">
                <option value="">{{ __('All Categories') }}</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ (string) request('category_id') === (string) $cat->id ? 'selected' : '' }}>
                        {{ $cat->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div style="flex: 1; min-width: 160px;">
            <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('Item Type') }}</label>
            <select name="type" class="form-select" style="width: 100%; border-radius: 10px;">
                <option value="">{{ __('All Types') }}</option>
                <option value="raw_material" {{ request('type') === 'raw_material' ? 'selected' : '' }}>{{ __('Raw Material') }}</option>
                <option value="ingredient" {{ request('type') === 'ingredient' ? 'selected' : '' }}>{{ __('Ingredient') }}</option>
                <option value="semi_finished" {{ request('type') === 'semi_finished' ? 'selected' : '' }}>{{ __('Semi-Finished') }}</option>
                <option value="packaging" {{ request('type') === 'packaging' ? 'selected' : '' }}>{{ __('Packaging') }}</option>
                <option value="consumable" {{ request('type') === 'consumable' ? 'selected' : '' }}>{{ __('Consumable') }}</option>
            </select>
        </div>

        <div style="display: flex; align-items: center; gap: 0.5rem; padding-bottom: 0.5rem;">
            <label style="display: flex; align-items: center; gap: 0.4rem; cursor: pointer; font-size: 0.88rem; font-weight: 600; color: var(--text-main);">
                <input type="checkbox" name="low_stock" value="1" {{ request('low_stock') ? 'checked' : '' }} onchange="this.form.submit()">
                <span>{{ __('Low Stock Only') }}</span>
            </label>
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700; padding: 0.55rem 1.25rem;">
                <i class="fa-solid fa-filter"></i> {{ __('Filter') }}
            </button>
            @if(request()->hasAny(['search', 'category_id', 'type', 'low_stock']))
                <a href="{{ route('admin.inventory.ingredients.index') }}" class="btn btn-secondary" style="border-radius: 10px; padding: 0.55rem 1rem;">
                    {{ __('Reset') }}
                </a>
            @endif
        </div>
    </form>
</div>

<!-- Ingredients Table -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-card);">
    @if($ingredients->isEmpty())
        <div style="text-align: center; padding: 3.5rem 1rem; color: var(--text-muted);">
            <i class="fa-solid fa-wheat-awn" style="font-size: 2.5rem; color: var(--text-muted); margin-bottom: 0.75rem;"></i>
            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;">{{ __('No ingredients found') }}</h3>
            <p style="margin: 0;">{{ __('Get started by adding your first ingredient or adjusting your filter criteria.') }}</p>
        </div>
    @else
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.02); color: var(--text-muted); text-align: left;">
                        <th style="padding: 0.85rem 1rem;">{{ __('Item & SKU') }}</th>
                        <th style="padding: 0.85rem 1rem;">{{ __('Type') }}</th>
                        <th style="padding: 0.85rem 1rem;">{{ __('Category') }}</th>
                        <th style="padding: 0.85rem 1rem;">{{ __('Unit') }}</th>
                        <th style="padding: 0.85rem 1rem; text-align: right;">{{ __('Current Cost') }}</th>
                        <th style="padding: 0.85rem 1rem; text-align: right;">{{ __('Stock Available') }}</th>
                        <th style="padding: 0.85rem 1rem; text-align: center;">{{ __('Status') }}</th>
                        <th style="padding: 0.85rem 1rem; text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ingredients as $ing)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 0.85rem 1rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    @if($ing->image)
                                        <img src="{{ $ing->image }}" alt="{{ $ing->name }}" style="width: 38px; height: 38px; border-radius: 8px; object-fit: cover; border: 1px solid var(--border-color);">
                                    @else
                                        <div style="width: 38px; height: 38px; border-radius: 8px; background: rgba(245, 158, 11, 0.1); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                                            <i class="fa-solid fa-cube"></i>
                                        </div>
                                    @endif
                                    <div>
                                        <div style="font-weight: 700; color: var(--text-main);">{{ $ing->name }}</div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">
                                            {{ $ing->sku ?: '-' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 0.85rem 1rem;">
                                <span style="font-size: 0.75rem; font-weight: 600; padding: 2px 7px; border-radius: 6px; background: rgba(0,0,0,0.05); color: var(--text-main); text-transform: capitalize;">
                                    {{ str_replace('_', ' ', $ing->type) }}
                                </span>
                            </td>
                            <td style="padding: 0.85rem 1rem; color: var(--text-muted);">
                                {{ $ing->category?->name ?: '-' }}
                            </td>
                            <td style="padding: 0.85rem 1rem; font-weight: 600;">
                                {{ $ing->unit?->code }}
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: right; font-weight: 700; color: var(--text-main);">
                                {{ number_format((float) $ing->current_cost, 2) }}
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: right;">
                                <span style="font-weight: 800; color: {{ $ing->isLowStock() ? '#ef4444' : '#10b981' }};">
                                    {{ number_format($ing->total_stock_available, 2) }}
                                </span>
                                @if($ing->isLowStock())
                                    <span style="font-size: 0.7rem; color: #ef4444; display: block; font-weight: 600;">
                                        {{ __('Low Stock') }}
                                    </span>
                                @endif
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: center;">
                                <span style="width: 8px; height: 8px; border-radius: 50%; display: inline-block; background: {{ $ing->is_active ? '#10b981' : '#94a3b8' }};"></span>
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: right;">
                                <div style="display: flex; gap: 0.4rem; justify-content: flex-end;">
                                    @can('ingredients.update')
                                        <a href="{{ route('admin.inventory.ingredients.edit', $ing) }}" class="btn btn-secondary" style="padding: 0.4rem 0.65rem; border-radius: 8px;" title="{{ __('Edit') }}">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                    @endcan
                                    @can('ingredients.delete')
                                        <form method="POST" action="{{ route('admin.inventory.ingredients.destroy', $ing) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete this ingredient?') }}')" style="display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-secondary" style="padding: 0.4rem 0.65rem; border-radius: 8px; color: #ef4444;" title="{{ __('Delete') }}">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($ingredients->hasPages())
            <div style="padding: 1rem; border-top: 1px solid var(--border-color);">
                {{ $ingredients->links() }}
            </div>
        @endif
    @endif
</div>

<!-- Import Modal -->
<div x-show="importModalOpen" x-cloak
     style="position: fixed; inset: 0; z-index: 1050; display: flex; align-items: center; justify-content: center; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); padding: 1rem;"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    <div @click.away="if (!isUploading) importModalOpen = false"
         class="card"
         style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; width: 100%; max-width: 600px; box-shadow: 0 20px 40px rgba(0,0,0,0.25); overflow: hidden; padding: 0;"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100">

        <!-- Modal Header -->
        <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
            <div style="display: flex; gap: 0.85rem; align-items: center;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(16, 185, 129, 0.12); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                    <i class="fa-solid fa-file-excel"></i>
                </div>
                <div>
                    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0;">
                        {{ __('Import Raw Materials & Ingredients') }}
                    </h3>
                    <p style="font-size: 0.82rem; color: var(--text-muted); margin: 0.2rem 0 0;">
                        {{ __('Upload an Excel (.xlsx) or CSV file to batch import items.') }}
                    </p>
                </div>
            </div>
            <button type="button" @click="if (!isUploading) importModalOpen = false" class="btn btn-secondary" style="padding: 0.35rem 0.6rem; border-radius: 8px;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Template Download Banner -->
        <div style="padding: 1.25rem 1.5rem 0.5rem;">
            <div style="background: rgba(16, 185, 129, 0.08); border: 1px dashed rgba(16, 185, 129, 0.4); border-radius: 14px; padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <div>
                    <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-main); display: flex; align-items: center; gap: 0.4rem;">
                        <i class="fa-solid fa-circle-down" style="color: #10b981;"></i> {{ __('Standard Import Template') }}
                    </div>
                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem;">
                        {{ __('Download the sample template with pre-filled examples.') }}
                    </div>
                </div>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="{{ route('admin.inventory.ingredients.template', ['format' => 'xlsx']) }}" class="btn btn-sm" style="background: #10b981; color: #fff; font-weight: 700; border-radius: 8px; display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 0.85rem;">
                        <i class="fa-solid fa-file-excel"></i> {{ __('Excel (.xlsx)') }}
                    </a>
                    <a href="{{ route('admin.inventory.ingredients.template', ['format' => 'csv']) }}" class="btn btn-sm btn-secondary" style="font-weight: 600; border-radius: 8px; display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 0.85rem;">
                        <i class="fa-solid fa-file-csv"></i> {{ __('CSV (.csv)') }}
                    </a>
                </div>
            </div>
        </div>

        <!-- Import Form -->
        <form method="POST" action="{{ route('admin.inventory.ingredients.import') }}" enctype="multipart/form-data" @submit="isUploading = true" style="padding: 1rem 1.5rem 1.5rem;">
            @csrf

            <!-- File Upload Input Area -->
            <div style="margin-bottom: 1.25rem;">
                <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.4rem; display: block;">
                    {{ __('Select Spreadsheet File') }} <span style="color: #ef4444;">*</span>
                </label>
                <div style="border: 2px dashed var(--border-color); border-radius: 14px; padding: 1.5rem 1rem; text-align: center; background: var(--bg-card); cursor: pointer; position: relative;"
                     @dragover.prevent=""
                     @drop.prevent="$refs.fileInput.files = $event.dataTransfer.files; selectedFileName = $refs.fileInput.files[0]?.name || ''">
                    <input type="file" name="file" x-ref="fileInput" accept=".xlsx,.csv,.txt" required
                           @change="selectedFileName = $refs.fileInput.files[0]?.name || ''"
                           style="position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 0.5rem;">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                    </div>
                    <div style="font-weight: 700; font-size: 0.92rem; color: var(--text-main);">
                        <span x-show="!selectedFileName">{{ __('Click or drag & drop file here') }}</span>
                        <span x-show="selectedFileName" x-text="selectedFileName" style="color: #10b981;"></span>
                    </div>
                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.25rem;">
                        {{ __('Supported formats: .xlsx, .csv (Max 10MB)') }}
                    </div>
                </div>
            </div>

            <!-- Options -->
            <div style="margin-bottom: 1.25rem; background: var(--bg-hover); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.85rem 1rem;">
                <label style="display: flex; align-items: flex-start; gap: 0.6rem; cursor: pointer; margin: 0;">
                    <input type="checkbox" name="update_existing" value="1" checked style="margin-top: 0.2rem; width: 16px; height: 16px; accent-color: var(--primary);">
                    <div>
                        <div style="font-weight: 700; font-size: 0.88rem; color: var(--text-main);">
                            {{ __('Update existing items (match by SKU or exact Name)') }}
                        </div>
                        <div style="font-size: 0.76rem; color: var(--text-muted); margin-top: 0.1rem;">
                            {{ __('If an item already exists, its cost, category, unit, and minimum stock will be updated.') }}
                        </div>
                    </div>
                </label>
            </div>

            <!-- Guidance Note -->
            <div style="font-size: 0.76rem; color: var(--text-muted); margin-bottom: 1.5rem; line-height: 1.45;">
                <strong style="color: var(--text-main);"><i class="fa-solid fa-circle-info"></i> {{ __('Supported Columns:') }}</strong>
                {{ __('Name (required), Unit (required: kg, g, l, ml, piece), SKU (optional, auto-generated), Type (raw_material, packaging, etc.), Category (auto-created if new), Cost, Min Stock, Barcode, Storage Conditions, Shelf Life Days.') }}
            </div>

            <!-- Modal Footer Buttons -->
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                <button type="button" @click="if (!isUploading) importModalOpen = false" :disabled="isUploading" class="btn btn-secondary" style="border-radius: 10px; font-weight: 600;">
                    {{ __('Cancel') }}
                </button>
                <button type="submit" :disabled="isUploading" class="btn btn-primary" style="background: #10b981; border-color: #10b981; border-radius: 10px; font-weight: 700; padding: 0.6rem 1.4rem;">
                    <span x-show="!isUploading"><i class="fa-solid fa-file-import"></i> {{ __('Import File') }}</span>
                    <span x-show="isUploading"><i class="fa-solid fa-spinner fa-spin"></i> {{ __('Importing...') }}</span>
                </button>
            </div>
        </form>
    </div>
</div>
</div>
@endsection

