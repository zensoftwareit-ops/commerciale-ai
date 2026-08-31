<?php

namespace App\Contracts;

interface SetupWizardGenerator
{
    /** @return array<string, mixed> */
    public function generate(string $description, array $existingProfile = [], array $website = []): array;
}
