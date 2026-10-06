<?php

namespace App\Policies;

use App\Models\DocumentSequence;
use App\Models\User;
use App\Policies\Concerns\ManagesCompanyRecords;

class DocumentSequencePolicy
{
    use ManagesCompanyRecords;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'sequences.view');
    }

    public function view(User $user, DocumentSequence $documentSequence): bool
    {
        return $this->allowsCompany($user, (int) $documentSequence->company_id, 'sequences.view');
    }

    public function update(User $user, DocumentSequence $documentSequence): bool
    {
        return $this->allowsCompany($user, (int) $documentSequence->company_id, 'sequences.update');
    }
}
