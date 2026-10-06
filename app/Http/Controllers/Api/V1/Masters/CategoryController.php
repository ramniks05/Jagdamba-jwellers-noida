<?php

namespace App\Http\Controllers\Api\V1\Masters;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masters\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\Masters\CategoryService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Category::class);
        $search = trim((string) $request->query('search', ''));

        return CategoryResource::collection(
            Category::query()->with('parent')->matching($search)->orderBy('sort_order')->orderBy('name')->paginate(20),
        );
    }

    public function store(CategoryRequest $request, CategoryService $categories, CompanyContext $context): CategoryResource
    {
        return new CategoryResource($categories->create($context->company(), $request->validated())->load('parent'));
    }

    public function show(Category $category): CategoryResource
    {
        $this->authorize('view', $category);

        return new CategoryResource($category->load('parent'));
    }

    public function update(CategoryRequest $request, Category $category, CategoryService $categories): CategoryResource
    {
        return new CategoryResource($categories->update($category, $request->validated())->load('parent'));
    }

    public function destroy(Category $category, CategoryService $categories): JsonResponse
    {
        $this->authorize('delete', $category);
        $categories->delete($category);

        return response()->json(['message' => 'Category removed.']);
    }
}
