<?php

namespace App\Events\Foundation;

use App\Models\DocumentNumberAllocation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentNumberIssued
{
    use Dispatchable, SerializesModels;

    public function __construct(public DocumentNumberAllocation $allocation) {}
}
