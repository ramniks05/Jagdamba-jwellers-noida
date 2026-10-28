<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\RetailProductRequest;
use App\Models\RetailProduct;
use App\Services\Commerce\RetailProductService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RetailProductController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', RetailProduct::class);
        $search = trim((string) $request->query('search', ''));
        $show = in_array($request->query('show'), ['active', 'hidden'], true) ? $request->query('show') : 'all';
        $like = '%'.addcslashes($search, '%_\\').'%';

        return view('commerce.retail-products.index', [
            'search' => $search,
            'show' => $show,
            'products' => RetailProduct::query()
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('code', 'like', $like)))
                ->when($show !== 'all', fn ($query) => $query->where('is_active', $show === 'active'))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(30)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', RetailProduct::class);

        return view('commerce.retail-products.form', [
            'product' => new RetailProduct(['is_active' => true, 'sort_order' => 0, 'default_tax_mode' => 'none']),
        ]);
    }

    public function store(RetailProductRequest $request, RetailProductService $products, CompanyContext $context): RedirectResponse
    {
        $products->create($context->company(), $request->validated());

        return redirect()->route('retail-products.index')->with('status', 'Other item saved.');
    }

    public function edit(RetailProduct $retailProduct): View
    {
        $this->authorize('update', $retailProduct);

        return view('commerce.retail-products.form', [
            'product' => $retailProduct,
        ]);
    }

    public function update(RetailProductRequest $request, RetailProduct $retailProduct, RetailProductService $products): RedirectResponse
    {
        $products->update($retailProduct, $request->validated());

        return redirect()->route('retail-products.index')->with('status', 'Other item saved.');
    }

    public function destroy(RetailProduct $retailProduct, RetailProductService $products): RedirectResponse
    {
        $this->authorize('delete', $retailProduct);
        $products->delete($retailProduct);

        return redirect()->route('retail-products.index')->with('status', 'Other item removed.');
    }
}
