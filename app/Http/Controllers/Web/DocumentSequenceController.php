<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Foundation\DocumentSequenceRequest;
use App\Models\DocumentSequence;
use App\Services\Foundation\DocumentNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DocumentSequenceController extends Controller
{
    public function index(DocumentNumberService $numbers): View
    {
        $this->authorize('viewAny', DocumentSequence::class);

        $sequences = DocumentSequence::query()->orderBy('document_type')->get();
        $previews = [];
        $previewErrors = [];

        foreach ($sequences as $sequence) {
            try {
                $previews[$sequence->id] = $numbers->preview($sequence);
            } catch (ValidationException $exception) {
                $previewErrors[$sequence->id] = collect($exception->errors())->flatten()->first();
            }
        }

        return view('foundation.sequences.index', [
            'sequences' => $sequences,
            'previews' => $previews,
            'previewErrors' => $previewErrors,
        ]);
    }

    public function edit(DocumentSequence $documentSequence, DocumentNumberService $numbers): View
    {
        $this->authorize('update', $documentSequence);

        $preview = null;
        $previewError = null;

        try {
            $preview = $numbers->preview($documentSequence);
        } catch (ValidationException $exception) {
            $previewError = collect($exception->errors())->flatten()->first();
        }

        return view('foundation.sequences.edit', [
            'sequence' => $documentSequence,
            'preview' => $preview,
            'previewError' => $previewError,
        ]);
    }

    public function update(
        DocumentSequenceRequest $request,
        DocumentSequence $documentSequence,
        DocumentNumberService $numbers,
    ): RedirectResponse {
        $numbers->update($documentSequence, $request->validated());

        return redirect()->route('document-sequences.index')->with('status', 'Number series saved.');
    }
}
