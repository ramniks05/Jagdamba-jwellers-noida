<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Foundation\DocumentSequenceRequest;
use App\Http\Resources\DocumentSequenceResource;
use App\Models\DocumentSequence;
use App\Services\Foundation\DocumentNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DocumentSequenceController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', DocumentSequence::class);

        return DocumentSequenceResource::collection(
            DocumentSequence::query()->orderBy('document_type')->get(),
        );
    }

    public function show(DocumentSequence $documentSequence): DocumentSequenceResource
    {
        $this->authorize('view', $documentSequence);

        return new DocumentSequenceResource($documentSequence);
    }

    public function update(
        DocumentSequenceRequest $request,
        DocumentSequence $documentSequence,
        DocumentNumberService $numbers,
    ): DocumentSequenceResource {
        return new DocumentSequenceResource($numbers->update($documentSequence, $request->validated()));
    }

    public function preview(DocumentSequence $documentSequence, DocumentNumberService $numbers): JsonResponse
    {
        $this->authorize('view', $documentSequence);

        return response()->json([
            'preview' => $numbers->preview($documentSequence),
        ]);
    }
}
