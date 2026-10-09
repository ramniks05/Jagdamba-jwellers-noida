<?php

namespace App\Http\Controllers\Web\Masters;

use App\Http\Controllers\Controller;
use App\Models\StoneGrade;
use App\Models\StoneType;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoneDirectoryController extends Controller
{
    use ListsMasterRecords;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', StoneType::class);
        $search = $this->search($request);
        $show = $this->visibility($request);

        return view('masters.stones.index', [
            'search' => $search,
            'show' => $show,
            'types' => $this->visible(StoneType::query(), $show)->matching($search)->orderBy('sort_order')->orderBy('name')->get(),
            'grades' => $this->visible(StoneGrade::query(), $show)->matching($search)->orderBy('kind')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }
}
