<?php

namespace App\Http\Controllers\Web\Masters;

use App\Http\Controllers\Controller;
use App\Models\StoneGrade;
use App\Models\StoneType;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoneDirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StoneType::class);
        $search = trim((string) $request->query('search', ''));

        return view('masters.stones.index', [
            'search' => $search,
            'types' => StoneType::query()->matching($search)->orderBy('sort_order')->orderBy('name')->get(),
            'grades' => StoneGrade::query()->matching($search)->orderBy('kind')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }
}
