<?php

namespace Tests\Feature;

use App\Mail\TebReviewInvitation;
use App\Models\Employee;
use App\Models\Teb\TebAdmin;
use App\Models\Teb\TebApproval;
use App\Models\Teb\TebApproverMatrix;
use App\Models\Teb\TebProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\LegacySchema;
use Tests\TestCase;

class TebFlowTest extends TestCase
{
    use RefreshDatabase;

    protected array $fx;

    protected function setUp(): void
    {
        parent::setUp();
        LegacySchema::create();
        $this->fx = LegacySchema::seed();
        foreach ($this->fx['matrix'] as [$dept, $level, $emp]) {
            TebApproverMatrix::create(['department_id' => $dept, 'level' => $level, 'employid' => $emp]);
        }
        TebAdmin::create(['employid' => '1002']);
        TebAdmin::flushCache();
        Storage::fake('local');
        Mail::fake();
    }

    protected function as(string $id)
    {
        return $this->actingAs(Employee::find($id));
    }

    protected function register(array $over = []): TebProject
    {
        $d = $this->fx['dept'];
        $this->as('1001')->post(route('teb.projects.store'), $over + [
            'title'               => 'Qualify new reflow oven line 3',
            'category'            => 'machine_qualification',
            'customer_id'         => 1,
            'classification'      => 'major',
            'fmea'                => UploadedFile::fake()->create('fmea.xlsx', 20),
            'types_of_change'     => ['permanent', 'addition'],
            'affected_areas'      => [$d('Production'), $d('Facilities')],
            'project_description' => 'Install and qualify oven',
            'current_status'      => 'Old oven',
            'change_description'  => 'New oven',
        ])->assertSessionHasNoErrors()->assertRedirect();

        return TebProject::latest('id')->first();
    }

    public function test_login_with_masterlist_account(): void
    {
        $this->post(route('teb.login.attempt'), ['username' => 'areyes', 'password' => 'secret'])->assertRedirect(route('teb.dashboard'));
        $this->assertAuthenticatedAs(Employee::find('1001'));
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('teb.dashboard'))->assertRedirect(route('teb.login'));
    }

    public function test_major_change_requires_fmea(): void
    {
        $this->as('1001')->post(route('teb.projects.store'), [
            'title' => 'x', 'category' => 'procedure', 'classification' => 'major', 'types_of_change' => ['revision'],
            'affected_areas' => [1], 'project_description' => 'y',
        ])->assertSessionHasErrors('fmea');
    }

    public function test_full_flow_register_review_number_complete_approve_4mcn(): void
    {
        $p = $this->register();
        $this->assertSame(TebProject::STATUS_SUBMITTED, $p->status);
        $this->assertSame('Acme Semicon', $p->customer_name);
        $this->assertSame('Ana Reyes', $p->owner_name);
        $this->assertSame('Process Engineering', $p->owner_department);
        $this->assertCount(1, $p->attachments()->where('type', 'fmea')->get());

        // Originator cannot use admin screens.
        $this->as('1001')->get(route('teb.review.index'))->assertForbidden();

        // TEB Admin schedules review -> Open, invites sent.
        $this->as('1002')->get(route('teb.review.index'))->assertOk()->assertSee($p->title);
        $this->as('1002')->get(route('teb.review.schedule', $p))->assertOk();
        $this->as('1002')->post(route('teb.review.schedule.store', $p), [
            'review_at' => now()->addDay()->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'location' => 'Conf A',
            'reviewees' => ['2001', '2004'], 'notes' => 'Bring FMEA',
        ])->assertRedirect();
        Mail::assertSent(TebReviewInvitation::class, 3);
        $this->assertSame(TebProject::STATUS_OPEN, $p->fresh()->status);
        $review = $p->reviews()->first();
        $this->as('1001')->get(route('teb.reviews.ics', [$p, $review]))->assertOk()->assertSee('BEGIN:VEVENT');

        // Status updates; Reviewed without number -> Pending TEB Number.
        $this->as('1002')->post(route('teb.review.status', $p), ['status' => 'hold', 'remarks' => 'wait'])->assertRedirect();
        $this->as('1002')->post(route('teb.review.status', $p), ['status' => 'ongoing'])->assertRedirect();
        $this->as('1002')->post(route('teb.review.status', $p), ['status' => 'reviewed'])->assertRedirect();
        $this->assertSame(TebProject::STATUS_PENDING_TEB_NUMBER, $p->fresh()->status);

        // Assign TEB No -> Reviewed.
        $this->as('1002')->post(route('teb.review.assign', $p))->assertRedirect();
        $p->refresh();
        $this->assertSame(now()->format('Y').'-000000001', $p->teb_no);
        $this->assertSame(TebProject::STATUS_REVIEWED, $p->status);

        // Originator completes with TEB report -> For Approval, approvals built.
        $this->as('1001')->get(route('teb.projects.complete', $p))->assertOk();
        $this->as('1001')->post(route('teb.projects.complete.store', $p), [
            'teb_report' => UploadedFile::fake()->create('report.pdf', 50), 'requires_4mcn' => 1,
        ])->assertRedirect();
        $p->refresh();
        $this->assertSame(TebProject::STATUS_FOR_APPROVAL, $p->status);
        // 4 fixed departments + Facilities as Others, 3 levels each.
        $this->assertSame(15, $p->approvals()->count());
        $this->assertSame(1, $p->approvals()->where('department_group', 'Others: Facilities')->where('level', 'manager')->count());

        // Manager cannot sign before supervisor.
        $mgr = $p->approvals()->where('department_group', 'Production')->where('level', 'manager')->first();
        $this->as($mgr->approver_employid)->post(route('teb.approvals.act', $mgr), ['decision' => 'approved'])->assertSessionHasErrors('approval');

        // Approver sees it in their list.
        $sup = $p->approvals()->where('department_group', 'Production')->where('level', 'supervisor')->first();
        $this->as($sup->approver_employid)->get(route('teb.approvals.index'))->assertOk()->assertSee($p->teb_no);

        // Everyone signs in order.
        foreach ($p->approvals()->orderBy('level_order')->get() as $a) {
            $this->as($a->approver_employid)->post(route('teb.approvals.act', $a), ['decision' => 'approved'])->assertSessionHasNoErrors();
        }
        $p->refresh();
        $this->assertSame(TebProject::STATUS_APPROVED, $p->status);
        $this->assertTrue($p->needsFourMcn());

        // 4MCN generated by originator.
        $this->as('1001')->post(route('teb.fourmcn.store', $p), [
            'change_items' => ['machine', 'method'], 'before_change' => 'a', 'after_change' => 'b', 'reason' => 'c',
            'effectivity_date' => now()->addWeek()->toDateString(), 'customer_notification' => 1,
        ])->assertRedirect(route('teb.fourmcn.show', $p));
        $this->assertSame('4MCN-'.now()->format('Y').'-00001', $p->fresh()->fourMcn->mcn_no);
        $this->as('1001')->get(route('teb.fourmcn.show', $p))->assertOk()->assertSee('4M CHANGE NOTICE');

        // Forms, pages and export.
        $this->as('1001')->get(route('teb.projects.form', $p))->assertOk()->assertSee('COMPLETED');
        $this->as('1001')->get(route('teb.projects.show', $p))->assertOk()->assertSee('Approval section');
        $this->as('1001')->get(route('teb.dashboard'))->assertOk()->assertSee('Total TEB');
        $csv = $this->as('1001')->get(route('teb.projects.export', ['category' => 'machine_qualification']));
        $csv->assertOk();
        $this->assertStringContainsString($p->teb_no, $csv->streamedContent());
        $this->assertStringContainsString('4MCN-', $csv->streamedContent());
    }

    public function test_disapproval_returns_to_originator_who_can_resubmit(): void
    {
        $p = $this->register(['classification' => 'minor', 'fmea' => null]);
        $this->as('1002')->post(route('teb.review.status', $p), ['status' => 'reviewed']);
        $this->as('1002')->post(route('teb.review.assign', $p), ['teb_no' => '2026-000000777']);
        $this->as('1001')->post(route('teb.projects.complete.store', $p), [
            'teb_report' => UploadedFile::fake()->create('r.pdf', 5), 'requires_4mcn' => 0,
        ]);
        $sup = $p->approvals()->where('department_group', 'Quality Assurance')->where('level', 'supervisor')->first();
        $this->as($sup->approver_employid)->post(route('teb.approvals.act', $sup), ['decision' => 'rejected'])->assertSessionHasErrors('remarks');
        $this->as($sup->approver_employid)->post(route('teb.approvals.act', $sup), ['decision' => 'rejected', 'remarks' => 'Need MSA data']);
        $p->refresh();
        $this->assertSame(TebProject::STATUS_RETURNED, $p->status);
        $this->assertSame('2026-000000777', $p->teb_no);

        // Resubmit rebuilds a fresh approval section; report already on file.
        $this->as('1001')->post(route('teb.projects.complete.store', $p), ['requires_4mcn' => 0])->assertSessionHasNoErrors();
        $this->assertSame(TebProject::STATUS_FOR_APPROVAL, $p->fresh()->status);
        $this->assertSame(0, $p->approvals()->where('status', TebApproval::REJECTED)->count());
    }

    public function test_only_originator_can_edit_and_only_while_editable(): void
    {
        $p = $this->register();
        $this->as('1002')->get(route('teb.projects.edit', $p))->assertForbidden();
        $this->as('1001')->get(route('teb.projects.edit', $p))->assertOk();
        $this->as('1002')->post(route('teb.review.status', $p), ['status' => 'ongoing']);
        $this->as('1001')->get(route('teb.projects.edit', $p))->assertForbidden();
    }

    public function test_settings_pages(): void
    {
        $this->as('1002')->get(route('teb.settings'))->assertOk()->assertSee('Approver matrix');
        $this->as('1002')->post(route('teb.settings.admin.add'), ['employid' => '1001'])->assertSessionHasNoErrors();
        $this->assertTrue(TebAdmin::isTebAdmin('1001'));
        $this->as('1001')->get(route('teb.projects.create'))->assertOk()->assertSee('Acme Semicon')->assertSee('Quality Assurance');
    }
}
