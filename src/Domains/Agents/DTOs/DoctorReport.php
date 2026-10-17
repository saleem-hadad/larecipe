<?php

namespace LaRecipe\Domains\Agents\DTOs;

/**
 * A set's agent-readiness grade: every check, and a letter grade in the AFDocs
 * style (a pass counts fully, a warning half, a failure nothing).
 */
final readonly class DoctorReport
{
    /** @param list<DoctorCheck> $checks */
    public function __construct(
        public string $set,
        public string $url,
        public array $checks,
    ) {}

    public function score(): int
    {
        $graded = array_filter($this->checks, fn (DoctorCheck $check) => $check->status !== DoctorCheck::SKIP);

        if ($graded === []) {
            return 100;
        }

        $points = array_sum(array_map(fn (DoctorCheck $check) => match ($check->status) {
            DoctorCheck::PASS => 1.0,
            DoctorCheck::WARN => 0.5,
            default => 0.0,
        }, $graded));

        return (int) round($points / count($graded) * 100);
    }

    public function grade(): string
    {
        return match (true) {
            $this->score() >= 90 => 'A',
            $this->score() >= 80 => 'B',
            $this->score() >= 70 => 'C',
            $this->score() >= 60 => 'D',
            default => 'F',
        };
    }

    public function failed(): bool
    {
        return array_filter($this->checks, fn (DoctorCheck $check) => $check->status === DoctorCheck::FAIL) !== [];
    }

    public function toArray(): array
    {
        return [
            'set' => $this->set,
            'url' => $this->url,
            'grade' => $this->grade(),
            'score' => $this->score(),
            'checks' => array_map(fn (DoctorCheck $check) => $check->toArray(), $this->checks),
        ];
    }
}
