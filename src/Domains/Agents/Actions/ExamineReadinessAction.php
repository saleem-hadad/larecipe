<?php

namespace LaRecipe\Domains\Agents\Actions;

use LaRecipe\Domains\Agents\DTOs\DoctorReport;
use LaRecipe\Domains\Agents\Laravel\Doctor\DocsDoctor;
use LaRecipe\Domains\Content\DTOs\DocSet;

/**
 * The larecipe:doctor report for one set: every agent-readiness check with
 * its fix, and the grade. The admin UI shows it as the readiness card, so it
 * needs the same report the command prints without depending on the doctor's
 * Laravel internals.
 */
final class ExamineReadinessAction
{
    public function __construct(private readonly DocsDoctor $doctor) {}

    public function execute(DocSet $set): DoctorReport
    {
        return $this->doctor->examine($set);
    }
}
