<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIngredientCategoryRequest;
use App\Http\Requests\UpdateIngredientCategoryRequest;
use App\Models\IngredientCategory;
use App\Security\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class IngredientCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::INGREDIENTS_VIEW);

        $categories = IngredientCategory::with(['parent', 'children'])
            ->withCount('ingredients')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(30);

        $parentCategories = IngredientCategory::whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return view('admin.inventory.categories.index', compact('categories', 'parentCategories'));
    }

    public function store(StoreIngredientCategoryRequest $request): RedirectResponse
    {
        IngredientCategory::create($request->validated());

        return redirect()->route('admin.inventory.categories.index')
            ->with('success', __('Category created successfully.'));
    }

    public function update(UpdateIngredientCategoryRequest $request, IngredientCategory $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('admin.inventory.categories.index')
            ->with('success', __('Category updated successfully.'));
    }

    public function destroy(IngredientCategory $category): RedirectResponse
    {
        $this->authorizePermission(Permission::INGREDIENTS_DELETE);

        if ($category->ingredients()->exists()) {
            return back()->with('error', __('Cannot delete category because it contains ingredients.'));
        }

        $category->delete();

        return redirect()->route('admin.inventory.categories.index')
            ->with('success', __('Category deleted successfully.'));
    }

    protected function authorizePermission(string $permission): void
    {
        if (! Auth::user()?->hasPermission($permission)) {
            abort(403, 'Unauthorized action.');
        }
    }
}

