@extends('layouts.app')

@section('title', __('Ingredient Categories') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div style="min-width: 0; flex: 1;">
        <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
            <span style="background: rgba(6, 182, 212, 0.15); color: #06b6d4; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                <i class="fa-solid fa-folder-tree"></i>
            </span>
            <span>{{ __('Ingredient Categories') }}</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
            {{ __('Organize raw materials and items into hierarchical categories (e.g. Meat -> Beef, Dairy, Vegetables, Spices).') }}
        </p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="{{ route('admin.inventory.ingredients.index') }}" class="btn btn-secondary" style="border-radius: 12px; font-weight: 600;">
            &larr; {{ __('Catalog') }}
        </a>
        @can('ingredients.create')
            <button type="button" class="btn btn-primary" onclick="document.getElementById('newCategoryModal').style.display='flex'" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; font-weight: 700;">
                <i class="fa-solid fa-plus"></i> {{ __('Add Category') }}
            </button>
        @endcan
    </div>
</div>

<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-card);">
    @if($categories->isEmpty())
        <div style="text-align: center; padding: 3.5rem 1rem; color: var(--text-muted);">
            <i class="fa-solid fa-folder-open" style="font-size: 2.5rem; color: var(--text-muted); margin-bottom: 0.75rem;"></i>
            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;">{{ __('No categories created yet') }}</h3>
            <p style="margin: 0;">{{ __('Create your first category to group related raw materials and ingredients.') }}</p>
        </div>
    @else
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.02); color: var(--text-muted); text-align: left;">
                        <th style="padding: 0.85rem 1rem;">{{ __('Category Name') }}</th>
                        <th style="padding: 0.85rem 1rem;">{{ __('Parent Category') }}</th>
                        <th style="padding: 0.85rem 1rem; text-align: center;">{{ __('Items Count') }}</th>
                        <th style="padding: 0.85rem 1rem; text-align: center;">{{ __('Sort Order') }}</th>
                        <th style="padding: 0.85rem 1rem; text-align: center;">{{ __('Status') }}</th>
                        <th style="padding: 0.85rem 1rem; text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($categories as $category)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 0.85rem 1rem;">
                                <div style="font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fa-solid fa-folder" style="color: #06b6d4;"></i>
                                    <span>{{ $category->name }}</span>
                                </div>
                                @if($category->description)
                                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">{{ $category->description }}</div>
                                @endif
                            </td>
                            <td style="padding: 0.85rem 1rem; color: var(--text-muted);">
                                {{ $category->parent?->name ?: '-' }}
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: center; font-weight: 700; color: var(--text-main);">
                                {{ $category->ingredients_count }}
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: center; color: var(--text-muted);">
                                {{ $category->sort_order }}
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: center;">
                                <span style="width: 8px; height: 8px; border-radius: 50%; display: inline-block; background: {{ $category->is_active ? '#10b981' : '#94a3b8' }};"></span>
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: right;">
                                <div style="display: flex; gap: 0.4rem; justify-content: flex-end;">
                                    @can('ingredients.delete')
                                        <form method="POST" action="{{ route('admin.inventory.categories.destroy', $category) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete this category?') }}')" style="display: inline;">
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
        @if($categories->hasPages())
            <div style="padding: 1rem; border-top: 1px solid var(--border-color);">
                {{ $categories->links() }}
            </div>
        @endif
    @endif
</div>

<!-- Modal: New Category -->
<div id="newCategoryModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="background: var(--bg-card); border-radius: 20px; max-width: 500px; width: 100%; padding: 1.5rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0;">{{ __('Add Ingredient Category') }}</h3>
            <button type="button" onclick="document.getElementById('newCategoryModal').style.display='none'" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">&times;</button>
        </div>

        <form method="POST" action="{{ route('admin.inventory.categories.store') }}">
            @csrf
            <div style="margin-bottom: 1rem;">
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">{{ __('Category Name') }} <span style="color: #ef4444;">*</span></label>
                <input type="text" name="name" required placeholder="{{ __('e.g. Dairy & Cheeses') }}" class="form-input" style="width: 100%; border-radius: 10px;">
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">{{ __('Parent Category (Optional)') }}</label>
                <select name="parent_id" class="form-select" style="width: 100%; border-radius: 10px;">
                    <option value="">-- {{ __('None (Root Category)') }} --</option>
                    @foreach($parentCategories as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">{{ __('Description') }}</label>
                <textarea name="description" rows="2" class="form-input" style="width: 100%; border-radius: 10px;"></textarea>
            </div>

            <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1.5rem;">
                <div style="flex: 1;">
                    <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">{{ __('Sort Order') }}</label>
                    <input type="number" name="sort_order" value="0" class="form-input" style="width: 100%; border-radius: 10px;">
                </div>
                <div style="flex: 1; padding-top: 1.25rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.88rem; font-weight: 700; color: var(--text-main);">
                        <input type="checkbox" name="is_active" value="1" checked>
                        <span>{{ __('Active') }}</span>
                    </label>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="document.getElementById('newCategoryModal').style.display='none'" class="btn btn-secondary" style="border-radius: 10px;">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700;">{{ __('Create Category') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

