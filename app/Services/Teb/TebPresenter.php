<?php
// app/Services/Teb/TebPresenter.php

namespace App\Services\Teb;

use App\Models\Teb\TebApproval;
use App\Models\Teb\TebProject;

// Plain arrays for the Inertia pages, so the pages never depend on model internals.
class TebPresenter
{
    public static function row(TebProject $p): array
    {
        return [
            'id'                   => $p->id,
            'teb_no'               => $p->teb_no,
            'display_no'           => $p->display_no,
            'created_at'           => $p->created_at?->format('m/d/Y'),
            'title'                => $p->title,
            'category'             => $p->category,
            'category_label'       => $p->category_label,
            'customer_name'        => $p->customer_name,
            'project_description'  => $p->project_description,
            'owner_name'           => $p->owner_name,
            'owner_department'     => $p->owner_department,
            'classification_label' => $p->classification_label,
            'types_of_change_label'=> $p->types_of_change_label,
            'affected_areas_label' => $p->affected_areas_label,
            'status'               => $p->status,
            'status_label'         => $p->status_label,
            'needs_4mcn'           => $p->needsFourMcn(),
        ];
    }

    public static function detail(TebProject $p): array
    {
        return self::row($p) + [
            'customer_id'          => $p->customer_id,
            'classification'       => $p->classification,
            'types_of_change'      => $p->types_of_change ?? [],
            'current_status'       => $p->current_status,
            'change_description'   => $p->change_description,
            'owner_employid'       => $p->owner_employid,
            'requires_4mcn'        => $p->requires_4mcn,
            'completion_remarks'   => $p->completion_remarks,
            'completed_at'         => $p->completed_at?->format('m/d/Y H:i'),
            'approved_at'          => $p->approved_at?->format('m/d/Y'),
            'is_editable'          => $p->isEditable(),
            'can_be_completed'     => $p->canBeCompleted(),
            'review_block'         => $p->reviewBlockReason(),
            'affected_areas'       => $p->covers_all_departments ? ['All departments'] : $p->affectedAreas->pluck('dep_name')->filter()->values()->all(),
            'affected_area_ids'    => $p->affectedAreas->pluck('department_id')->map(fn ($id) => (int) $id)->all(),
            'attachments'          => $p->attachments->map(fn ($f) => [
                'id'            => $f->id,
                'type'          => $f->type,
                'type_label'    => $f->type_label,
                'original_name' => $f->original_name,
                'url'           => route('teb.projects.download', [$p, $f]),
            ])->values()->all(),
            'reviews'              => $p->relationLoaded('reviews') ? $p->reviews->map(fn ($r) => [
                'id'               => $r->id,
                'review_at'        => $r->review_at->format('D, m/d/Y H:i'),
                'duration_minutes' => $r->duration_minutes,
                'location'         => $r->location,
                'notes'            => $r->notes,
                'invites_sent'     => $r->invites_sent,
                'ics_url'          => route('teb.reviews.ics', [$p, $r]),
            ])->values()->all() : [],
            'approvals'            => $p->relationLoaded('approvals') ? $p->approvals->map(fn (TebApproval $a) => [
                'id'               => $a->id,
                'department_group' => $a->department_group,
                'level'            => $a->level,
                'level_label'      => $a->level_label,
                'approver_employid'=> $a->approver_employid,
                'approver_name'    => $a->approver_name,
                'status'           => $a->status,
                'remarks'          => $a->remarks,
                'acted_at'         => $a->acted_at?->format('m/d/Y H:i'),
            ])->values()->all() : [],
            'history'              => $p->relationLoaded('statusLogs') ? $p->statusLogs->map(fn ($l) => [
                'id'       => $l->id,
                'status'   => $l->to_status,
                'label'    => $l->to_label,
                'at'       => $l->created_at->format('m/d/Y H:i'),
                'by'       => $l->emp_name,
                'remarks'  => $l->remarks,
            ])->values()->all() : [],
            'four_mcn'             => $p->relationLoaded('fourMcn') && $p->fourMcn ? [
                'mcn_no'             => $p->fourMcn->mcn_no,
                'title'              => $p->fourMcn->title ?? $p->title,
                'change_items'       => $p->fourMcn->change_items ?? [],
                'change_items_label' => $p->fourMcn->change_items_label,
                'classification'     => $p->fourMcn->classification ?? $p->classification,
                'types_of_change'    => $p->fourMcn->types_of_change ?? $p->types_of_change ?? [],
                'date_written'       => ($p->fourMcn->date_written ?? $p->fourMcn->created_at)->format('m/d/Y'),
                'effectivity_date'   => $p->fourMcn->effectivity_date->format('m/d/Y'),
                'expiration_date'    => $p->fourMcn->expiration_date?->format('m/d/Y'),
                'extension_no'       => $p->fourMcn->extension_no,
                'current_status'     => $p->fourMcn->before_change,
                'change_description' => $p->fourMcn->after_change,
                'justification'      => $p->fourMcn->reason,
                'attachments_note'   => $p->fourMcn->attachments_note,
                'prepared_name'      => $p->fourMcn->prepared_name,
                'created_at'         => $p->fourMcn->created_at->format('m/d/Y H:i'),
            ] : null,
        ];
    }

    // Lookups the pages need for dropdowns, checkboxes and labels.
    public static function options(): array
    {
        return [
            'categories'      => config('teb.categories'),
            'classifications' => config('teb.classifications'),
            'typesOfChange'   => config('teb.types_of_change'),
            'approvalLevels'  => config('teb.approval_levels'),
            'fourMItems'      => config('teb.4m_items'),
            'statuses'        => TebProject::STATUS_LABELS,
            'reviewStatuses'  => TebProject::REVIEW_STATUSES,
            'attachmentTypes' => \App\Models\Teb\TebAttachment::TYPE_LABELS,
        ];
    }
}
