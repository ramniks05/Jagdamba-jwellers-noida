<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

#[Fillable([
    'company_id',
    'document_sequence_id',
    'document_type',
    'number',
    'issued_at',
    'issued_by',
])]
class DocumentNumberAllocation extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'issued_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Issued document numbers cannot be changed.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Issued document numbers cannot be deleted.');
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(DocumentSequence::class, 'document_sequence_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
