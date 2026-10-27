<?php
// app/Services/Teb/TebActor.php

namespace App\Services\Teb;

// The signed-in employee, built from the Authify session (session('emp_data')).
final class TebActor
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $dept = null,
        public readonly ?string $jobLevel = null,
    ) {}

    public static function fromEmpData(?array $emp): ?self
    {
        if (empty($emp['emp_id'])) {
            return null;
        }

        return new self(
            (string) $emp['emp_id'],
            (string) ($emp['emp_name'] ?? $emp['emp_id']),
            $emp['emp_dept'] ?? null,
            isset($emp['emp_job_level']) ? (string) $emp['emp_job_level'] : null,
        );
    }
}
