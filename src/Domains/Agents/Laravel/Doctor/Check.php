<?php

namespace LaRecipe\Domains\Agents\Laravel\Doctor;

use LaRecipe\Domains\Agents\DTOs\DoctorCheck;

/**
 * One agent-readiness check. Checks request the app as an agent would and
 * grade the answer; each one says how to fix what it finds.
 */
interface Check
{
    public function run(DoctorContext $context): DoctorCheck;
}
