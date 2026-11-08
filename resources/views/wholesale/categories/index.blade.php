@extends('layouts.app')

@section('title', __('Wholesale Product Categories'))

@section('content')
<div class="content-wrapper">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 12px; background: rgba(20, 184, 166, 0.15); color: #14b8a6; font-size: 1.25rem;">
                    <i class="fa-solid fa-folder-tree"></i>
                </span>
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ __('Wholesale Product Categories') }}
                </h1>
            </div>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0;">
                {{ __('Manage product classifications, hierarchy, and grouping for wholesale distribution.') }}
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <a href="{{ route('admin.wholesale.products.index') }}" 
               style="padding: 0.65rem 1.15rem; border-radius: 10px; background: var(--bg-card); color: var(--text-main); font-weight: 700; border: 1px solid var(--border-color); text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-boxes-stacked"></i>
                <span>{{ __('Wholesale Catalog') }}</span>
            </a>
            <a href="{{ route('admin.wholesale.categories.create') }}" 
               style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #14b8a6; color: #fff; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(20, 184, 166, 0.35);">
                <i class="fa-solid fa-plus"></i>
                <span>{{ __('Add Category') }}</span>
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 12px; padding: 1rem 1.25rem; color: #10b981; font-weight: 600; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 12px; padding: 1rem 1.25rem; color: #ef4444; font-weight: 600; margin-bottom: 1.5rem;">
            @foreach($errors->all() as $error)
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>{{ $error }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Metrics Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.02);">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                {{ __('Total Categories') }}
            </div>
            <div style="font-size: 1.8rem; font-weight: 800; color: var(--text-main); margin-top: 0.25rem;">
                {{ $totalCategories }}
            </div>
        </div>
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.02);">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                {{ __('Root Categories') }}
            </div>
            <div style="font-size: 1.8rem; font-weight: 800; color: #14b8a6; margin-top: 0.25rem;">
                {{ $rootCategories }}
            </div>
        </div>
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.02);">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                {{ __('Active Categories') }}
            </div>
            <div style="font-size: 1.8rem; font-weight: 800; color: #10b981; margin-top: 0.25rem;">
                {{ $activeCategories }}
            </div>
        </div>
    </div>

    <!-- Categories Table -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                <thead>
                    <tr style="background: var(--bg-main); border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em;">
                        <th style="padding: 1rem 1.25rem;">{{ __('Category Name') }}</th>
                        <th style="padding: 1rem 1.25rem;">{{ __('Parent Category') }}</th>
                        <th style="padding: 1rem 1.25rem;">{{ __('Assigned Products') }}</th>
                        <th style="padding: 1rem 1.25rem;">{{ __('Sort Order') }}</th>
                        <th style="padding: 1rem 1.25rem;">{{ __('Status') }}</th>
                        <th style="padding: 1rem 1.25rem; text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody style="divide-y: 1px solid var(--border-color);">
                    @forelse($categories as $category)
                        <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.15s ease;" onmouseover="this.style.background='rgba(99, 102, 241, 0.02)'" onmouseout="this.style.background='transparent'">
                            <td style="padding: 1.1rem 1.25rem;">
                                <div style="font-weight: 700; color: var(--text-main);">
                                    {{ $category->name }}
                                </div>
                                @if($category->description)
                                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem;">
                                        {{ Str::limit($category->description, 60) }}
                                    </div>
                                @endif
                            </td>
                            <td style="padding: 1.1rem 1.25rem; color: var(--text-muted);">
                                @if($category->parent)
                                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; background: var(--bg-main); padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.8rem; border: 1px solid var(--border-color);">
                                        <i class="fa-solid fa-turn-up" style="transform: rotate(90deg); font-size: 0.65rem;"></i>
                                        {{ $category->parent->name }}
                                    </span>
                                @else
                                    <span style="color: var(--text-muted); font-style: italic; font-size: 0.8rem;">&mdash; {{ __('Root') }} &mdash;</span>
                                @endif
                            </td>
                            <td style="padding: 1.1rem 1.25rem;">
                                <span style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.25rem 0.65rem; border-radius: 8px; font-weight: 700; font-size: 0.82rem; background: rgba(99, 102, 241, 0.1); color: #6366f1;">
                                    <i class="fa-solid fa-box"></i>
                                    {{ $category->products_count }} {{ __('products') }}
                                </span>
                            </td>
                            <td style="padding: 1.1rem 1.25rem; font-family: monospace; color: var(--text-muted);">
                                {{ $category->sort_order }}
                            </td>
                            <td style="padding: 1.1rem 1.25rem;">
                                @if($category->is_active)
                                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; background: rgba(16, 185, 129, 0.12); color: #10b981;">
                                        <i class="fa-solid fa-circle-check" style="font-size: 0.7rem;"></i> {{ __('Active') }}
                                    </span>
                                @else
                                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; background: rgba(239, 68, 68, 0.12); color: #ef4444;">
                                        <i class="fa-solid fa-circle-xmark" style="font-size: 0.7rem;"></i> {{ __('Inactive') }}
                                    </span>
                                @endif
                            </td>
                            <td style="padding: 1.1rem 1.25rem; text-align: right;">
                                <div style="display: inline-flex; gap: 0.5rem; align-items: center;">
                                    <a href="{{ route('admin.wholesale.categories.edit', $category) }}" 
                                       style="padding: 0.45rem 0.85rem; border-radius: 8px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.82rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                                        <i class="fa-solid fa-pen-to-square"></i> {{ __('Edit') }}
                                    </a>
                                    <form method="POST" action="{{ route('admin.wholesale.categories.destroy', $category) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete this category?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                style="padding: 0.45rem 0.75rem; border-radius: 8px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444; font-size: 0.82rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem;">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding: 3rem 1.5rem; text-align: center; color: var(--text-muted);">
                                <i class="fa-solid fa-folder-open" style="font-size: 2.5rem; opacity: 0.3; margin-bottom: 0.75rem; display: block;"></i>
                                <div style="font-weight: 700; font-size: 1rem; color: var(--text-main); margin-bottom: 0.25rem;">
                                    {{ __('No categories found.') }}
                                </div>
                                <div style="font-size: 0.85rem; margin-bottom: 1rem;">
                                    {{ __('Get started by creating your first wholesale product category.') }}
                                </div>
                                <a href="{{ route('admin.wholesale.categories.create') }}" 
                                   style="padding: 0.55rem 1.15rem; border-radius: 8px; background: #14b8a6; color: #fff; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.85rem;">
                                    <i class="fa-solid fa-plus"></i> {{ __('Create Category') }}
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($categories->hasPages())
            <div style="padding: 1.25rem; border-top: 1px solid var(--border-color);">
                {{ $categories->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

