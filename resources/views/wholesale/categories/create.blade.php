@extends('layouts.app')

@section('title', __('Create Wholesale Category'))

@section('content')
<div class="content-wrapper" style="max-width: 800px; margin: 0 auto;">
    <!-- Back & Header -->
    <div style="margin-bottom: 2rem;">
        <a href="{{ route('admin.wholesale.categories.index') }}" style="color: #14b8a6; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
            <i class="fa-solid fa-arrow-left"></i>
            <span>{{ __('Back to Categories') }}</span>
        </a>
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <span style="display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 12px; background: rgba(20, 184, 166, 0.15); color: #14b8a6; font-size: 1.25rem;">
                <i class="fa-solid fa-folder-plus"></i>
            </span>
            <div>
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ __('Create Wholesale Category') }}
                </h1>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.2rem 0 0 0;">
                    {{ __('Define a new category or sub-category for wholesale catalog items.') }}
                </p>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    @if($errors->any())
        <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 12px; padding: 1rem 1.25rem; color: #ef4444; font-weight: 600; margin-bottom: 1.5rem;">
            <ul style="margin: 0; padding-left: 1.25rem;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Form -->
    <form method="POST" action="{{ route('admin.wholesale.categories.store') }}" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
        @csrf

        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            <!-- Category Name -->
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 0.5rem;">
                    {{ __('Category Name') }} *
                </label>
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="{{ __('e.g., Մսամթերք և Կիսաֆաբրիկատներ, Խմիչքներ, Հացաբուլկեղեն...') }}"
                       style="width: 100%; padding: 0.75rem 1rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.95rem;">
            </div>

            <!-- Parent Category -->
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 0.5rem;">
                    {{ __('Parent Category (Optional)') }}
                </label>
                <select name="parent_id" style="width: 100%; padding: 0.75rem 1rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.95rem;">
                    <option value="">&mdash; {{ __('None (Root Category)') }} &mdash;</option>
                    @foreach($parentCategories as $parent)
                        <option value="{{ $parent->id }}" {{ old('parent_id') == $parent->id ? 'selected' : '' }}>
                            {{ $parent->name }}
                        </option>
                    @endforeach
                </select>
                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem;">
                    {{ __('Leave empty if this is a top-level category.') }}
                </div>
            </div>

            <!-- Description -->
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 0.5rem;">
                    {{ __('Description') }}
                </label>
                <textarea name="description" rows="3" placeholder="{{ __('Optional notes or internal scope for this wholesale product group...') }}"
                          style="width: 100%; padding: 0.75rem 1rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.95rem; resize: vertical;">{{ old('description') }}</textarea>
            </div>

            <!-- Sort Order & Active Status -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; align-items: center;">
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 0.5rem;">
                        {{ __('Sort Order') }}
                    </label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0"
                           style="width: 100%; padding: 0.75rem 1rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.95rem;">
                </div>

                <div style="padding-top: 1.25rem;">
                    <label style="display: inline-flex; align-items: center; gap: 0.6rem; cursor: pointer; user-select: none;">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                               style="width: 18px; height: 18px; border-radius: 4px; accent-color: #14b8a6; cursor: pointer;">
                        <span style="font-size: 0.9rem; font-weight: 700; color: var(--text-main);">
                            {{ __('Category is Active') }}
                        </span>
                    </label>
                </div>
            </div>

            <!-- Actions -->
            <div style="display: flex; justify-content: flex-end; gap: 1rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem; margin-top: 0.5rem;">
                <a href="{{ route('admin.wholesale.categories.index') }}" 
                   style="padding: 0.75rem 1.5rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-weight: 700; text-decoration: none;">
                    {{ __('Cancel') }}
                </a>
                <button type="submit" 
                        style="padding: 0.75rem 1.75rem; border-radius: 10px; background: #14b8a6; color: #fff; font-weight: 700; border: none; cursor: pointer; box-shadow: 0 4px 14px rgba(20, 184, 166, 0.35);">
                    <i class="fa-solid fa-check"></i> {{ __('Save Category') }}
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

