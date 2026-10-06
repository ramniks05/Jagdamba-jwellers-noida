<?php

namespace App\Http\Controllers\Web;

use App\Enums\BranchStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Foundation\BranchRequest;
use App\Models\Branch;
use App\Services\Foundation\BranchService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Branch::class);

        return view('foundation.branches.index', [
            'branches' => Branch::query()->visibleTo(auth()->user())->orderByDesc('is_head_office')->orderBy('name')->paginate(15),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Branch::class);

        return view('foundation.branches.form', [
            'branch' => new Branch([
                'country' => 'India',
                'status' => BranchStatus::Active,
            ]),
        ]);
    }

    public function store(BranchRequest $request, BranchService $branches, CompanyContext $context): RedirectResponse
    {
        $branches->create($context->company(), $request->validated());

        return redirect()->route('branches.index')->with('status', 'Branch saved.');
    }

    public function edit(Branch $branch): View
    {
        $this->authorize('update', $branch);

        return view('foundation.branches.form', ['branch' => $branch]);
    }

    public function update(BranchRequest $request, Branch $branch, BranchService $branches): RedirectResponse
    {
        $branches->update($branch, $request->validated());

        return redirect()->route('branches.index')->with('status', 'Branch saved.');
    }

    public function destroy(Branch $branch, BranchService $branches): RedirectResponse
    {
        $this->authorize('delete', $branch);
        $branches->delete($branch);

        return redirect()->route('branches.index')->with('status', 'Branch removed.');
    }
}
