<?php

namespace App\Http\Controllers\Web\Masters;

use App\Enums\StoneGradeKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Masters\StoneGradeRequest;
use App\Models\StoneGrade;
use App\Services\Masters\StoneGradeService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StoneGradeController extends Controller
{
    public function create(): View
    {
        $this->authorize('create', StoneGrade::class);

        return view('masters.stones.grade-form', [
            'grade' => new StoneGrade(['is_active' => true, 'sort_order' => 0, 'kind' => StoneGradeKind::Cut]),
            'kinds' => StoneGradeKind::cases(),
        ]);
    }

    public function store(StoneGradeRequest $request, StoneGradeService $grades, CompanyContext $context): RedirectResponse
    {
        $grades->create($context->company(), $request->validated());

        return redirect()->route('stones.index')->with('status', 'Stone grade saved.');
    }

    public function edit(StoneGrade $stoneGrade): View
    {
        $this->authorize('update', $stoneGrade);

        return view('masters.stones.grade-form', [
            'grade' => $stoneGrade,
            'kinds' => StoneGradeKind::cases(),
        ]);
    }

    public function update(StoneGradeRequest $request, StoneGrade $stoneGrade, StoneGradeService $grades): RedirectResponse
    {
        $grades->update($stoneGrade, $request->validated());

        return redirect()->route('stones.index')->with('status', 'Stone grade saved.');
    }

    public function destroy(StoneGrade $stoneGrade, StoneGradeService $grades): RedirectResponse
    {
        $this->authorize('delete', $stoneGrade);
        $grades->delete($stoneGrade);

        return redirect()->route('stones.index')->with('status', 'Stone grade removed.');
    }
}
