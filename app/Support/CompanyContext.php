<?php

namespace App\Support;

use App\Models\Company;
use RuntimeException;

/**
 * Holds the company for the current request, job, or command.
 *
 * Queries on tenant-owned models return nothing until a company is set.
 * Call bypassing() only from maintenance commands that must see every shop.
 */
class CompanyContext
{
    private ?Company $company = null;

    private bool $bypass = false;

    public function set(?Company $company): void
    {
        $this->company = $company;
    }

    public function company(): ?Company
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company ? (int) $this->company->id : null;
    }

    public function forget(): void
    {
        $this->company = null;
        $this->bypass = false;
    }

    public function ensureId(int $companyId): void
    {
        if ($this->isBypassed()) {
            return;
        }

        if ($this->id() === null) {
            $company = Company::query()->find($companyId);

            if (! $company) {
                throw new RuntimeException('Company not found.');
            }

            $this->set($company);

            return;
        }

        if ($this->id() !== $companyId) {
            throw new RuntimeException('Active company does not match this record.');
        }
    }

    public function bypass(bool $bypass = true): void
    {
        $this->bypass = $bypass;
    }

    public function isBypassed(): bool
    {
        return $this->bypass;
    }

    public function bypassing(callable $callback): mixed
    {
        $previousBypass = $this->bypass;
        $previousCompany = $this->company;
        $this->bypass = true;

        try {
            return $callback();
        } finally {
            $this->bypass = $previousBypass;
            $this->company = $previousCompany;
        }
    }
}
