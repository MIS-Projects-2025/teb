<?php
// app/Services/Teb/TebGates.php

namespace App\Services\Teb;

// What each gate of the TRB process records (deck "New TRB Flow", slides 1, 2 and 23), kept in
// teb_projects.gate_records as {gate: {field: value, ..., recorded_at, recorded_by}}.
class TebGates
{
    const GATES = [
        'feasibility' => [
            'step'   => 3,
            'title'  => 'Feasibility and Change Assessment (Gate 1)',
            'fields' => [
                'risks'              => 'Preliminary risks',
                'investment'         => 'Investment',
                'affected_documents' => 'Affected processes / documents',
                'required_approvals' => 'Required approvals',
            ],
        ],
        'plan' => [
            'step'   => 4,
            'title'  => 'Qualification Package',
            'fields' => [
                'acceptance_criteria'   => 'Acceptance criteria',
                'uses_customer_product' => 'Trial uses customer product',
            ],
        ],
        'results' => [
            'step'   => 6,
            'title'  => 'TRB Review and Approval Record',
            'fields' => [
                'residual_risks'     => 'Residual risks',
                'summary_of_changes' => 'Summary of changes',
                'training_plan'      => 'Training and documentation plan',
                'conclusion'         => 'Conclusion and recommendation',
            ],
        ],
        'customer' => [
            'step'   => 7,
            'title'  => 'Customer Approval Record',
            'fields' => [
                'adi_notified'     => 'ADI Oversight Team notified',
                'adi_presentation' => 'ADI presentation',
                'conditions'       => 'Approval conditions',
            ],
        ],
        'readiness' => [
            'step'   => 8,
            'title'  => 'Released Documents and Readiness Record (Gate 2)',
            'fields' => [
                'swi_spec_numbers' => 'SWI / specification numbers',
                'checks'           => 'Readiness',
            ],
        ],
        'implementation' => [
            'step'   => 9,
            'title'  => 'Implementation Record',
            'fields' => [
                'effective_date'        => 'Effective date',
                'affected_machines_lots' => 'Affected machines / lots',
                'initial_results'       => 'Initial results',
            ],
        ],
        'closure' => [
            'step'   => 10,
            'title'  => 'Effectiveness and Closure Report',
            'fields' => [
                'piev'        => 'PIEV acceptance criteria',
                'verified_by' => 'Verified by (Engineering / QA)',
            ],
        ],
    ];

    // Step 8: SWI/specifications released; training, equipment, materials and all approval conditions ready.
    const READINESS_CHECKS = [
        'documents_released' => 'SWI / specifications released by Document Control',
        'training_complete'  => 'Training complete',
        'equipment_ready'    => 'Equipment ready',
        'materials_ready'    => 'Materials ready',
        'conditions_met'     => 'All approval conditions met',
    ];

    // Step 10: Post-Implementation Effectiveness Verification (PIEV) acceptance criteria.
    const PIEV_CHECKS = [
        'objective_achieved'  => 'The change objective has been achieved',
        'no_negative_impact'  => 'No negative impact to quality, reliability, yield, process capability or customer requirements',
        'controls_updated'    => 'Process controls, specifications and documentation updated and implemented',
        'training_records'    => 'Training records for affected personnel',
        'results_meet'        => 'Trial, qualification, validation or verification results meet acceptance criteria',
        'no_issues'           => 'No significant issues, excursions, complaints or audit findings during monitoring',
        'risks_closed'        => 'All identified risks and actions closed',
        'owner_approved'      => 'Change owner and approver reviewed and approved the effectiveness results',
    ];

    // Saves one gate's values (blank ones dropped) on the project.
    public static function record(\App\Models\Teb\TebProject $project, string $gate, array $values, ?TebActor $by): void
    {
        $values = array_filter($values, fn($v) => $v !== null && $v !== '' && $v !== []);
        if (! $values) {
            return;
        }

        // A gate recorded in several actions (step 7: ADI notice, then approval) keeps its earlier values.
        $records = $project->gate_records ?? [];
        $records[$gate] = ['recorded_at' => now()->format('m/d/Y H:i'), 'recorded_by' => $by?->name] + $values + ($records[$gate] ?? []);
        $project->gate_records = $records;
        $project->save();
    }

    public static function value(\App\Models\Teb\TebProject $project, string $gate, string $field): mixed
    {
        return ($project->gate_records ?? [])[$gate][$field] ?? null;
    }

    // For the project page: [{gate, step, title, rows: [[label, text]], recorded_at, recorded_by}], in step order.
    public static function present(\App\Models\Teb\TebProject $project): array
    {
        $out = [];
        foreach (self::GATES as $gate => $def) {
            $rec = ($project->gate_records ?? [])[$gate] ?? null;
            if (! $rec) {
                continue;
            }
            $rows = [];
            foreach ($def['fields'] as $field => $label) {
                if (! array_key_exists($field, $rec)) {
                    continue;
                }
                $v = $rec[$field];
                $text = match (true) {
                    $field === 'checks' => self::checkText(self::READINESS_CHECKS, $v),
                    $field === 'piev'   => self::checkText(self::PIEV_CHECKS, $v),
                    is_bool($v)         => $v ? 'Yes' : 'No',
                    default             => (string) $v,
                };
                $rows[] = [$label, $text];
            }
            $out[] = [
                'gate' => $gate,
                'step' => $def['step'],
                'title' => $def['title'],
                'rows' => $rows,
                'recorded_at' => $rec['recorded_at'] ?? null,
                'recorded_by' => $rec['recorded_by'] ?? null
            ];
        }

        return $out;
    }

    protected static function checkText(array $labels, $checked): string
    {
        $checked = (array) $checked;

        return collect($labels)->map(fn($label, $k) => (! empty($checked[$k]) ? '✓ ' : '✗ ') . $label)->implode("\n");
    }
}
