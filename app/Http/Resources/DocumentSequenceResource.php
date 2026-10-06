<?php

namespace App\Http\Resources;

use App\Models\DocumentSequence;
use App\Services\Foundation\DocumentNumberService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\ValidationException;

/** @mixin DocumentSequence */
class DocumentSequenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'document_type' => $this->document_type->value,
            'label' => $this->document_type->label(),
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
            'separator' => $this->separator,
            'padding' => $this->padding,
            'next_number' => $this->next_number,
            'reset_policy' => $this->reset_policy->value,
            'last_issued_number' => $this->last_issued_number,
            'last_issued_at' => $this->last_issued_at?->toIso8601String(),
            'is_active' => $this->is_active,
            'is_system' => $this->is_system,
            'scope_key' => $this->scope_key,
            'next_preview' => $this->nextPreview(),
        ];
    }

    private function nextPreview(): ?string
    {
        try {
            return app(DocumentNumberService::class)->preview($this->resource);
        } catch (ValidationException) {
            return null;
        }
    }
}
