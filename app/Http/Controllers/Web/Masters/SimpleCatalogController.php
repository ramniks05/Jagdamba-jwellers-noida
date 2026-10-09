<?php

namespace App\Http\Controllers\Web\Masters;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masters\CatalogRequest;
use App\Services\Masters\MasterRecordService;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

abstract class SimpleCatalogController extends Controller
{
    use ListsMasterRecords;

    abstract public static function modelClass(): string;

    abstract protected function singular(): string;

    abstract protected function routeName(): string;

    abstract public function routeKey(): string;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', static::modelClass());
        $show = $this->visibility($request);

        return view('masters.catalog.index', [
            'title' => $this->title(),
            'intro' => $this->intro(),
            'singular' => $this->singular(),
            'routeName' => $this->routeName(),
            'modelClass' => static::modelClass(),
            'search' => $this->search($request),
            'show' => $show,
            'counts' => $this->counts(),
            'records' => $this->visible($this->newQuery($request), $show)->paginate(20)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', static::modelClass());

        return view('masters.catalog.form', [
            'title' => 'Add '.strtolower($this->singular()),
            'singular' => $this->singular(),
            'routeName' => $this->routeName(),
            'backUrl' => $this->redirectTo(),
            'record' => new (static::modelClass())(['is_active' => true, 'sort_order' => 0]),
        ]);
    }

    public function store(CatalogRequest $request, MasterRecordService $records, CompanyContext $context): RedirectResponse
    {
        $records->create(static::modelClass(), $context->company(), $request->validated());

        return redirect()->to($this->redirectTo())->with('status', $this->singular().' saved.');
    }

    public function edit(Request $request): View
    {
        $record = $this->find($request);
        $this->authorize('update', $record);

        return view('masters.catalog.form', [
            'title' => 'Edit '.strtolower($this->singular()),
            'singular' => $this->singular(),
            'routeName' => $this->routeName(),
            'backUrl' => $this->redirectTo(),
            'record' => $record,
        ]);
    }

    public function update(CatalogRequest $request, MasterRecordService $records): RedirectResponse
    {
        $records->update($this->find($request), $request->validated());

        return redirect()->to($this->redirectTo())->with('status', $this->singular().' saved.');
    }

    public function destroy(Request $request, MasterRecordService $records): RedirectResponse
    {
        $record = $this->find($request);
        $this->authorize('delete', $record);
        $records->delete($record);

        return redirect()->to($this->redirectTo())->with('status', $this->singular().' removed.');
    }

    protected function title(): string
    {
        return $this->singular().'s';
    }

    protected function intro(): string
    {
        return '';
    }

    /**
     * @return array<string, string> relation => column heading
     */
    protected function counts(): array
    {
        return [];
    }

    protected function redirectTo(): string
    {
        return route($this->routeName().'.index');
    }

    protected function newQuery(Request $request)
    {
        $class = static::modelClass();

        return $class::query()
            ->withCount(array_keys($this->counts()))
            ->matching($this->search($request))
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    protected function find(Request $request): Model
    {
        $class = static::modelClass();
        $uuid = $request->route($this->routeKey());

        return $class::query()->where('uuid', $uuid)->firstOrFail();
    }
}
