<?php

namespace App\Support\Tenancy;

use App\Models\Organization;
use LogicException;

class TenantContext
{
    private ?Organization $organization = null;

    public function set(Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function clear(): void
    {
        $this->organization = null;
    }

    public function organization(): ?Organization
    {
        return $this->organization;
    }

    public function id(): ?string
    {
        return $this->organization?->getKey();
    }

    public function requireOrganization(): Organization
    {
        return $this->organization ?? throw new LogicException('No organization is active.');
    }
}
