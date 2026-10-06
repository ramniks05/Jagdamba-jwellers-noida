<?php

namespace App\Http\Controllers\Web\Masters;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masters\DesignRequest;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Design;
use App\Services\Masters\DesignService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DesignController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Design::class);
        $search = trim((string) $request->query('search', ''));

        return view('masters.designs.index', [
            'search' => $search,
            'designs' => Design::query()->with(['collection', 'category'])->matching($search)->orderBy('sort_order')->orderBy('name')->paginate(20)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Design::class);

        return view('masters.designs.form', $this->formData(new Design([
            'is_active' => true,
            'sort_order' => 0,
        ])));
    }

    public function store(DesignRequest $request, DesignService $designs, CompanyContext $context): RedirectResponse
    {
        $designs->create($context->company(), $request->validated());

        return redirect()->route('designs.index')->with('status', 'Design saved.');
    }

    public function edit(Design $design): View
    {
        $this->authorize('update', $design);

        return view('masters.designs.form', $this->formData($design));
    }

    public function update(DesignRequest $request, Design $design, DesignService $designs): RedirectResponse
    {
        $designs->update($design, $request->validated());

        return redirect()->route('designs.index')->with('status', 'Design saved.');
    }

    public function destroy(Design $design, DesignService $designs): RedirectResponse
    {
        $this->authorize('delete', $design);
        $designs->delete($design);

        return redirect()->route('designs.index')->with('status', 'Design removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Design $design): array
    {
        return [
            'design' => $design,
            'collections' => Collection::query()->orderBy('name')->get(),
            'categories' => Category::query()->orderBy('name')->get(),
        ];
    }
}
