<?php

namespace App\Http\Controllers\Web\Masters;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masters\CategoryRequest;
use App\Models\Category;
use App\Services\Masters\CategoryService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CategoryController extends Controller
{
    use ListsMasterRecords;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Category::class);
        $search = $this->search($request);
        $show = $this->visibility($request);

        return view('masters.categories.index', [
            'search' => $search,
            'show' => $show,
            'categories' => $this->visible(Category::query(), $show)
                ->with('parent')
                ->withCount(['children', 'items'])
                ->matching($search)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Category::class);

        return view('masters.categories.form', [
            'category' => new Category(['is_active' => true, 'sort_order' => 0]),
            'parents' => $this->parentChoices(new Category),
        ]);
    }

    public function store(CategoryRequest $request, CategoryService $categories, CompanyContext $context): RedirectResponse
    {
        $categories->create($context->company(), $request->validated());

        return redirect()->route('categories.index')->with('status', 'Category saved.');
    }

    public function edit(Category $category): View
    {
        $this->authorize('update', $category);

        return view('masters.categories.form', [
            'category' => $category,
            'parents' => $this->parentChoices($category),
        ]);
    }

    public function update(CategoryRequest $request, Category $category, CategoryService $categories): RedirectResponse
    {
        $categories->update($category, $request->validated());

        return redirect()->route('categories.index')->with('status', 'Category saved.');
    }

    public function destroy(Category $category, CategoryService $categories): RedirectResponse
    {
        $this->authorize('delete', $category);
        $categories->delete($category);

        return redirect()->route('categories.index')->with('status', 'Category removed.');
    }

    /**
     * @return Collection<int, Category>
     */
    private function parentChoices(Category $category): Collection
    {
        $all = Category::query()->orderBy('name')->get();
        $blocked = [];

        if ($category->exists) {
            $blocked[$category->id] = true;
            $queue = [$category->id];

            while ($queue !== []) {
                $current = array_shift($queue);

                foreach ($all as $item) {
                    if ((int) $item->parent_id === (int) $current && ! isset($blocked[$item->id])) {
                        $blocked[$item->id] = true;
                        $queue[] = $item->id;
                    }
                }
            }
        }

        return $all->reject(fn (Category $item) => isset($blocked[$item->id]))->values();
    }
}
