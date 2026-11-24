<?php

namespace Tests\Feature;

use App\Mail\TebReviewInvitation;
use App\Models\Teb\TebApproval;
use App\Models\Teb\TebProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Page;
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
        Storage::fake('local');
        Mail::fake();
    }

    // Signs in the way AuthMiddleware's fast path expects: a live Authify token in session('emp_data').
    protected function as(string $id)
    {
        $s = DB::connection('authify')->table('authify_sessions')->where('emp_id', $id)->first();

        return $this->withSession(['emp_data' => [
            'token' => $s->token,
            'emp_id' => $s->emp_id,
            'emp_name' => $s->emp_name,
            'emp_dept' => $s->emp_dept,
        ]]);
    }

    protected function register(array $over = []): TebProject
    {
        $d = $this->fx['dept'];
        // Classification follows the BMP category: Machine & Process Qualification is Major, Equipment & System Enhancement Minor.
        $over += ['bmp' => ['T' => ($over['classification'] ?? 'major') === 'major' ? 'Machine & Process Qualification' : 'Equipment & System Enhancement']];
        unset($over['classification']);
        $this->as('1001')->post(route('teb.projects.store'), $over + [
            'title'               => 'Qualify new reflow oven line 3',
            'category'            => 'machine_qualification',
            'customer_ids'        => ['1', '3'],
            'fmea'                => UploadedFile::fake()->create('fmea.xlsx', 20),
            'types_of_change'     => ['permanent', 'addition'],
            'affected_areas'      => [$d('Production'), $d('Facilities')],
            'project_description' => 'Install and qualify oven',
            'charter' => ['problem_statement' => 'Capacity gap', 'goal_statement' => '+20% UPH by Q1', 'project_scope' => 'Line 3'],
            'current_status'      => 'Old oven',
            'change_description'  => 'New oven',
        ])->assertSessionHasNoErrors()->assertRedirect();

        return TebProject::latest('id')->first();
    }

    public function test_without_authify_session_redirects_to_authify_login(): void
    {
        $this->get(route('dashboard'))->assertRedirectContains('authify.test/login');
    }

    public function test_dashboard_renders_with_session_user(): void
    {
        $this->as('2001')->get(route('dashboard'))->assertOk()
            ->assertInertia(fn(Page $page) => $page->component('Teb/Dashboard')
                ->where('teb.me', '2001')->where('teb.isAdmin', false)->where('total', 0)->etc());
    }

    public function test_anyone_can_register_but_only_process_engineering_is_admin(): void
    {
        // A Production supervisor registers a project.
        $d = $this->fx['dept'];
        $this->as('2001')->post(route('teb.projects.store'), [
            'title' => 'Line 2 fixture',
            'category' => 'machine_modification',
            'bmp' => ['T' => 'Equipment & System Enhancement'],
            'types_of_change' => ['addition'],
            'affected_areas' => [$d('Production')],
            'project_description' => 'New fixture',
            'charter' => ['problem_statement' => 'Capacity gap', 'goal_statement' => '+20% UPH by Q1', 'project_scope' => 'Line 3'],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $p = TebProject::latest('id')->first();
        $this->assertSame('Production', $p->owner_department);

        $this->as('2001')->get(route('dashboard'))
            ->assertInertia(fn(Page $page) => $page->where('teb.isAdmin', false)->etc());
        $this->as('2004')->get(route('dashboard')) // Process Engineering supervisor
            ->assertInertia(fn(Page $page) => $page->where('teb.isAdmin', true)->etc());
        $this->as('2004')->get(route('teb.review.index'))->assertOk();
    }

    public function test_all_departments_selected_shows_all_departments(): void
    {
        $all = \App\Models\Lookups\Department::pluck('id')->map(fn($id) => (int) $id)->all();
        $this->as('1001')->post(route('teb.projects.store'), [
            'title' => 'Plant-wide',
            'category' => 'procedure',
            'bmp' => ['T' => 'Equipment & System Enhancement'],
            'types_of_change' => ['revision'],
            'affected_areas' => $all,
            'project_description' => 'Affects everyone',
            'charter' => ['problem_statement' => 'Capacity gap', 'goal_statement' => '+20% UPH by Q1', 'project_scope' => 'Line 3'],
        ])->assertRedirect();
        $p = TebProject::where('title', 'Plant-wide')->firstOrFail();
        $this->assertCount(count($all), $p->affectedAreas);

        $this->as('1001')->get(route('teb.projects.show', $p))
            ->assertInertia(fn(Page $page) => $page->where('p.affected_areas', ['All departments'])
                ->where('p.affected_areas_label', 'All departments')->etc());
    }

    public function test_reviewed_needs_a_held_review(): void
    {
        $p = $this->register(['classification' => 'minor', 'fmea' => null]);
        $this->as('1002')->post(route('teb.review.status', $p), ['status' => 'reviewed'])
            ->assertSessionHasErrors(['status' => 'Schedule a TRB review first. It can be marked Reviewed once the review has taken place.']);
        $this->assertSame(TebProject::STATUS_SUBMITTED, $p->fresh()->status);

        $this->heldReview($p);
        $this->as('1002')->post(route('teb.review.status', $p), ['status' => 'reviewed'])->assertSessionHasNoErrors();
        $this->assertSame(TebProject::STATUS_PENDING_TEB_NUMBER, $p->fresh()->status);
    }

    // First employee the masterlist allows to sign this approval box (as a Supervisor unless a level is given).
    protected function signer(TebApproval $a, ?string $level = null): string
    {
        if ($a->isExecutive()) {
            return \App\Services\Teb\ApproverCandidates::eligibleExecutive($a->level)[0];
        }
        $level ??= $a->level === TebApproval::ANY_LEVEL ? 'supervisor' : $a->level;

        return \App\Services\Teb\ApproverCandidates::eligible((int) $a->department_id, $level)[0];
    }

    // Step 4-5: originator submits the trial plan, every plan approval box signs -> Trial Authorized.
    protected function authorizeTrial(TebProject $p): void
    {
        $this->as('1001')->post(route('teb.projects.plan', $p), [
            'trial_plan' => UploadedFile::fake()->create('plan.pdf', 10),
            'acceptance_criteria' => 'Cpk >= 1.33',
            'uses_customer_product' => 0
        ])->assertSessionHasNoErrors();
        $this->assertSame(TebProject::STATUS_PLAN_FOR_APPROVAL, $p->fresh()->status);
        foreach ($p->approvals()->where('round', 'plan')->orderBy('level_order')->get() as $a) {
            $this->as($this->signer($a))->post(route('teb.approvals.act', $a), ['decision' => 'approved'])->assertSessionHasNoErrors();
        }
        $this->assertSame(TebProject::STATUS_TRIAL_AUTHORIZED, $p->fresh()->status);
    }

    // Step 6 approval boxes.
    protected function results(TebProject $p)
    {
        return $p->approvals()->where('round', 'results');
    }

    // A review that has already taken place.
    protected function heldReview(TebProject $p): void
    {
        $p->reviews()->create(['review_at' => now()->subHours(3), 'duration_minutes' => 60, 'location' => 'Conf A', 'reviewees' => [], 'invites_sent' => 0]);
    }

    public function test_classification_follows_bmp_categories(): void
    {
        $base = [
            'title' => 'x',
            'category' => 'procedure',
            'types_of_change' => ['revision'],
            'affected_areas' => [1],
            'charter' => ['problem_statement' => 'p', 'goal_statement' => 'g', 'project_scope' => 's']
        ];
        $this->as('1001')->post(route('teb.projects.store'), $base)->assertSessionHasErrors(['bmp', 'classification']);
        $this->as('1001')->post(route('teb.projects.store'), $base + ['bmp' => ['T' => '']])->assertSessionHasErrors(['bmp.T' => 'Pick the Technology category.']);
        $this->as('1001')->post(route('teb.projects.store'), $base + ['bmp' => ['T' => 'Not a category']])->assertSessionHasErrors('bmp.T');
        // Minor + Minor = Minor; the client cannot override it.
        $this->as('1001')->post(route('teb.projects.store'), $base + [
            'classification' => 'major',
            'bmp' => ['T' => 'Automation & Digitalization', 'R' => 'Alert & Escalation Management']
        ])->assertSessionHasNoErrors();
        $p = TebProject::latest('id')->first();
        $this->assertSame('minor', $p->classification);
        $this->assertSame(['T' => 'Automation & Digitalization', 'R' => 'Alert & Escalation Management'], $p->charter['bmp']);
        $this->assertSame('p', $p->project_description);
        // No Category on the form any more: the BMP categories stand in for it.
        $this->assertNull($p->category);
        $this->assertSame(implode(', ', $p->charter['bmp']), $p->category_label);

        // Members and Sponsor(s) are picked from the masterlist, several each; names are stored with the ids.
        $this->as('1001')->post(route('teb.projects.store'), array_replace_recursive($base, [
            'bmp' => ['T' => 'Automation & Digitalization'],
            'charter' => ['member_ids' => ['2001', '2004'], 'sponsor_ids' => ['3002', '3003']]
        ]))->assertSessionHasNoErrors();
        $c = TebProject::latest('id')->first()->charter;
        $this->assertSame('Sup Production, Sup ProcessEngineering', $c['members']);
        $this->assertSame(['3002', '3003'], $c['sponsor_ids']);
        $this->assertSame('Dora Director, Pia President', $c['sponsor_name']);

        // Timeline is picked on a calendar (start and target completion); the end can't be before the start.
        $this->as('1001')->post(route('teb.projects.store'), array_replace_recursive($base, [
            'bmp' => ['T' => 'Automation & Digitalization'],
            'charter' => ['timeline_start' => '2026-11-10', 'timeline_end' => '2027-02-28']
        ]))->assertSessionHasNoErrors();
        $c = TebProject::latest('id')->first()->charter;
        $this->assertSame('Nov 10, 2026 – Feb 28, 2027', $c['timeline']);
        $this->assertSame('2027-02-28', $c['timeline_end']);
        $this->as('1001')->post(route('teb.projects.store'), array_replace_recursive($base, [
            'bmp' => ['T' => 'Automation & Digitalization'],
            'charter' => ['timeline_start' => '2026-11-10', 'timeline_end' => '2026-11-01']
        ]))->assertSessionHasErrors('charter.timeline_end');
        $this->as('1001')->get(route('teb.projects.create'))->assertInertia(fn(Page $page) => $page->where('employees.0.name', 'Cruz, Ben · Process Engineering')->etc());
        // Any Major category makes it Major (and then FMEA is required).
        $this->as('1001')->post(route('teb.projects.store'), $base + ['bmp' => ['T' => 'Automation & Digitalization', 'C' => 'Scrap Reduction']])->assertSessionHasErrors('fmea');
    }

    public function test_bmp_maintenance(): void
    {
        $this->assertSame('major', \App\Models\Teb\TebBmpCategory::map()['T']['Machine & Process Qualification']);
        $base = [
            'title' => 'x',
            'types_of_change' => ['revision'],
            'affected_areas' => [1],
            'charter' => ['problem_statement' => 'p', 'goal_statement' => 'g', 'project_scope' => 's']
        ];

        // Admins only.
        $this->as('2001')->post(route('teb.settings.bmp.store'), ['driver' => 'T', 'name' => 'Robotics', 'classification' => 'major'])->assertRedirect(route('dashboard'));
        $this->assertSame(0, \App\Models\Teb\TebBmpCategory::where('name', 'Robotics')->count());
        $this->as('1001')->post(route('teb.settings.bmp.store'), ['driver' => 'T', 'name' => 'Robotics', 'classification' => 'minor'])->assertSessionHasNoErrors();
        $this->as('1001')->post(route('teb.settings.bmp.store'), ['driver' => 'T', 'name' => 'Robotics', 'classification' => 'major'])->assertSessionHasErrors('name');
        $this->as('1001')->get(route('teb.settings'))->assertInertia(fn(Page $page) => $page->where('teb.options.bmpCategories.T.Robotics', 'minor')->etc());

        // A new category is offered on the form and carries its classification.
        $this->as('2001')->post(route('teb.projects.store'), $base + ['bmp' => ['T' => 'Robotics']])->assertSessionHasNoErrors();
        $p = TebProject::latest('id')->first();
        $this->assertSame('minor', $p->classification);

        // Renaming carries over to the entries that picked it; in use, it can't be deleted.
        $cat = \App\Models\Teb\TebBmpCategory::where('name', 'Robotics')->first();
        $this->as('1001')->put(route('teb.settings.bmp.update', $cat), ['name' => 'Robotics & Cobots', 'classification' => 'minor', 'active' => true])->assertSessionHasNoErrors();
        $this->assertSame('Robotics & Cobots', $p->fresh()->charter['bmp']['T']);
        $this->as('1001')->delete(route('teb.settings.bmp.destroy', $cat))->assertSessionHas('error');
        $this->assertModelExists($cat);

        // Retired: not accepted on a new entry, but the entry that picked it can still be saved with it.
        $this->as('1001')->put(route('teb.settings.bmp.update', $cat), ['name' => 'Robotics & Cobots', 'classification' => 'minor', 'active' => false])->assertSessionHasNoErrors();
        $this->as('2001')->post(route('teb.projects.store'), $base + ['bmp' => ['T' => 'Robotics & Cobots']])->assertSessionHasErrors('bmp.T');
        $this->as('2001')->put(route('teb.projects.update', $p), $base + ['bmp' => ['T' => 'Robotics & Cobots']])->assertSessionHasNoErrors();
        $this->assertSame('minor', $p->fresh()->classification);

        // A changed classification counts from then on.
        $this->as('1001')->put(route('teb.settings.bmp.update', $cat), ['name' => 'Robotics & Cobots', 'classification' => 'major', 'active' => true])->assertSessionHasNoErrors();
        $this->assertSame('major', TebProject::classificationFor(['T' => 'Robotics & Cobots']));

        // Unused: deleted.
        $this->as('1001')->post(route('teb.settings.bmp.store'), ['driver' => 'C', 'name' => 'Energy', 'classification' => 'minor']);
        $this->as('1001')->delete(route('teb.settings.bmp.destroy', \App\Models\Teb\TebBmpCategory::where('name', 'Energy')->first()))->assertSessionHas('success');
        $this->assertArrayNotHasKey('Energy', \App\Models\Teb\TebBmpCategory::map()['C']);
    }

    public function test_major_change_requires_fmea(): void
    {
        $this->as('1001')->post(route('teb.projects.store'), [
            'title' => 'x',
            'category' => 'procedure',
            'bmp' => ['T' => 'Machine & Process Qualification'],
            'types_of_change' => ['revision'],
            'affected_areas' => [1],
            'project_description' => 'y',
        ])->assertSessionHasErrors('fmea');
    }

    public function test_full_flow_register_review_number_complete_approve_4mcn(): void
    {
        $p = $this->register();
        $this->assertSame(TebProject::STATUS_SUBMITTED, $p->status);
        $this->assertSame('Acme Semicon, Globex Optics', $p->customer_name);
        $this->assertSame(['1', '3'], $p->customer_ids);
        $this->assertSame('1', (string) $p->customer_id);
        $this->assertSame('Ana Reyes', $p->owner_name);
        $this->assertSame('Process Engineering', $p->owner_department);
        $this->assertCount(1, $p->attachments()->where('type', 'fmea')->get());

        // Only Process Engineering is TRB Admin; Production is bounced back to the TRB dashboard.
        $this->as('2001')->get(route('teb.review.index'))->assertRedirect(route('dashboard'));
        $this->as('2001')->post(route('teb.review.assign', $p))->assertRedirect(route('dashboard'));
        $this->assertNull($p->fresh()->teb_no);

        // TRB Admin schedules review -> Open, invites sent.
        $this->as('1002')->get(route('teb.review.index'))->assertOk()
            ->assertInertia(fn(Page $page) => $page->component('Teb/Review/Index')->has('projects.data', 1)
                ->where('projects.data.0.title', $p->title)->where('teb.isAdmin', true)->etc());
        $this->as('1002')->get(route('teb.review.schedule', $p))->assertOk()
            ->assertInertia(fn(Page $page) => $page->component('Teb/Review/Schedule')->where('emails', ['ben.cruz@example.test', 'pe.sup@example.test', 'prod.sup@example.test'])->etc());
        $this->as('1002')->post(route('teb.review.schedule.store', $p), [
            'review_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'duration_minutes' => 60,
            'location' => 'Conf A',
            'reviewees' => ['prod.sup@example.test', 'pe.sup@example.test'],
            'notes' => 'Bring FMEA',
        ])->assertRedirect();
        Mail::assertSent(TebReviewInvitation::class, 3);
        $this->as('2001')->post(route('teb.review.resend', [$p, $p->reviews()->first()]))->assertRedirect(route('dashboard'));
        $this->as('1002')->post(route('teb.review.resend', [$p, $p->reviews()->first()]))->assertSessionHas('success', 'Invite resent. 3 of 3 calendar invite(s) sent.');
        Mail::assertSent(TebReviewInvitation::class, 6);
        $this->assertSame(6, $p->reviews()->first()->invites_sent);
        // Build the invite for real (the fake skips attachments): duration arrives from the form as a string.
        $fresh = $p->reviews()->first();
        $fresh->duration_minutes = '30';
        $invite = new TebReviewInvitation($fresh);
        $ics = $invite->attachments()[0]->attachWith(fn() => null, fn($data) => $data());
        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('METHOD:PUBLISH', $ics); // REQUEST makes Exchange reject the mail
        $this->assertStringNotContainsString('method=REQUEST', $invite->attachments()[0]->mime ?? '');
        $this->assertStringContainsString('Bring FMEA', $invite->render());
        $this->assertSame(TebProject::STATUS_OPEN, $p->fresh()->status);
        $review = $p->reviews()->first();
        $this->as('1001')->get(route('teb.reviews.ics', [$p, $review]))->assertOk()->assertSee('BEGIN:VEVENT');

        // Status updates; Reviewed without number -> Pending TRB Number.
        $this->as('1002')->post(route('teb.review.status', $p), ['status' => 'hold', 'remarks' => 'wait'])->assertRedirect();
        $this->as('1002')->post(route('teb.review.status', $p), ['status' => 'ongoing'])->assertRedirect();
        // Reviewed is blocked until the scheduled review has taken place.
        $this->as('1002')->post(route('teb.review.status', $p), ['status' => 'reviewed'])->assertSessionHasErrors('status');
        $this->assertSame(TebProject::STATUS_ONGOING, $p->fresh()->status);
        $this->travel(2)->days();
        $this->as('1002')->post(route('teb.review.status', $p), ['status' => 'reviewed'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(TebProject::STATUS_PENDING_TEB_NUMBER, $p->fresh()->status);

        // Assign TRB No -> Reviewed.
        $this->as('1002')->post(route('teb.review.assign', $p))->assertRedirect();
        $p->refresh();
        $this->assertSame(now()->format('Y') . '-00001', $p->teb_no);
        $this->assertSame(TebProject::STATUS_REVIEWED, $p->status);

        // Step 4: the trial plan cannot be skipped; results can only be uploaded once the trial is authorized.
        $this->as('1001')->get(route('teb.projects.complete', $p))->assertForbidden();
        $this->as('1001')->post(route('teb.projects.plan', $p), [])->assertSessionHasErrors('trial_plan');
        $this->as('1001')->post(route('teb.projects.plan', $p), ['trial_plan' => UploadedFile::fake()->create('plan.pdf', 10), 'acceptance_criteria' => 'Cpk >= 1.33', 'uses_customer_product' => 0])->assertSessionHasNoErrors();
        $this->assertSame(7, $p->approvals()->where('round', 'plan')->count()); // 5 departments incl. QMS + OD + President
        $this->assertSame('Cpk >= 1.33', $p->fresh()->gate_records['plan']['acceptance_criteria']);
        // Plan disapproved -> address gaps and resubmit.
        $qa = $p->approvals()->where('round', 'plan')->where('department_group', 'Quality Assurance')->first();
        $this->as($this->signer($qa))->post(route('teb.approvals.act', $qa), ['decision' => 'rejected', 'remarks' => 'Add sample size']);
        $this->assertSame(TebProject::STATUS_PLAN_RETURNED, $p->fresh()->status);
        $p->update(['status' => TebProject::STATUS_PLAN_RETURNED]);
        $p->attachments()->where('type', 'trial_plan')->delete();
        $this->authorizeTrial($p);

        // Step 5: a trial on customer product needs the customer trial authorization before the results.
        $p->update(['gate_records' => ['plan' => ['acceptance_criteria' => 'Cpk >= 1.33', 'uses_customer_product' => true]]]);
        $this->as('1001')->post(route('teb.projects.complete.store', $p), [
            'teb_report' => UploadedFile::fake()->create('report.pdf', 50),
            'requires_4mcn' => 1,
            'conclusion' => 'Passed',
        ])->assertSessionHasErrors('customer_trial');
        $this->as('1001')->post(route('teb.projects.complete.store', $p), [
            'requires_4mcn' => 1,
            'conclusion' => 'Passed',
        ])->assertSessionHasErrors('customer_trial');
        $p->attachments()->where('type', 'teb_report')->delete();

        // Step 6: originator completes with TRB report -> For Approval, approvals built.
        $this->as('1001')->get(route('teb.projects.complete', $p))->assertOk()
            ->assertInertia(fn(Page $page) => $page->component('Teb/Projects/Complete')->etc());
        $this->as('1001')->post(route('teb.projects.complete.store', $p), [
            'teb_report' => UploadedFile::fake()->create('report.pdf', 50),
            'requires_4mcn' => 1,
            'conclusion' => 'Passed',
            'customer_trial' => UploadedFile::fake()->create('acme-trial-ok.pdf', 5),
            'residual_risks' => 'None open',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('None open', $p->fresh()->gate_records['results']['residual_risks']);
        $p->refresh();
        $this->assertSame(TebProject::STATUS_FOR_APPROVAL, $p->status);
        // One box per department: the 4 fixed ones. The owner is in Process Engineering, so there is no Others row,
        // even though Facilities is an affected area. Major change: Operation Director and President follow.
        $this->assertSame(7, $this->results($p)->count());
        $this->assertSame([TebApproval::ANY_LEVEL, 'operation_director', 'president'], $this->results($p)->orderBy('level_order')->pluck('level')->unique()->values()->all());
        $this->assertSame(0, $this->results($p)->where('department_group', 'like', 'Others:%')->count());

        // A Manufacturing Supervisor sees it in their list and on the project page it is their turn.
        $box = $this->results($p)->where('department_group', 'Manufacturing')->first();
        $this->as($this->signer($box))->get(route('teb.approvals.index'))->assertOk()
            ->assertInertia(fn(Page $page) => $page->component('Teb/Approvals/Index')->where('projects.data.0.teb_no', $p->teb_no)->etc());
        $this->as($this->signer($box))->get(route('teb.projects.show', $p))->assertOk()
            ->assertInertia(fn(Page $page) => $page->component('Teb/Projects/Show')->where('myTurn.id', $box->id)->has('p.approvals', 14)->etc());

        // Someone outside Manufacturing / without an approval level cannot sign it.
        $this->as('1001')->post(route('teb.approvals.act', $box), ['decision' => 'approved'])->assertSessionHasErrors('approval');
        $this->assertNull($box->fresh()->approver_employid);

        // President cannot sign before the departments and the Operation Director.
        $president = $this->results($p)->where('level', 'president')->first();
        $this->as($this->signer($president))->post(route('teb.approvals.act', $president), ['decision' => 'approved'])->assertSessionHasErrors('approval');

        // Departments sign at the same time, any order; one signature each. Then Operation Director, then President.
        foreach ($this->results($p)->orderBy('level_order')->orderByDesc('department_order')->get() as $a) {
            $this->as($this->signer($a))->post(route('teb.approvals.act', $a), ['decision' => 'approved'])->assertSessionHasNoErrors();
        }
        $p->refresh();
        $this->assertSame(TebProject::STATUS_APPROVED, $p->status);
        // Whoever signed is recorded on each box.
        $this->assertSame(0, $p->approvals()->whereNull('approver_employid')->count());
        $this->assertSame('Sup Production', $this->results($p)->where('department_group', 'Manufacturing')->value('approver_name'));
        $this->assertSame('Pia President', $this->results($p)->where('level', 'president')->value('approver_name'));
        $this->assertTrue($p->needsFourMcn());

        // Customer approval required? Only the TRB Admin decides.
        $this->as('2001')->post(route('teb.projects.stage', $p), ['action' => 'customer', 'decision' => 1])->assertForbidden();
        $this->as('1002')->post(route('teb.projects.stage', $p), ['action' => 'secured', 'decision' => 1])->assertSessionHasErrors('stage');
        $this->as('1002')->post(route('teb.projects.stage', $p), ['action' => 'customer', 'decision' => 1])->assertSessionHasNoErrors();
        $this->assertSame(TebProject::STATUS_ADI_REVIEW, $p->fresh()->status);
        // Step 7: the ADI Oversight Team is emailed (picked or typed addresses).
        $this->as('1002')->post(route('teb.projects.stage', $p), ['action' => 'notify_adi'])->assertSessionHasErrors('emails');
        $this->as('1002')->post(route('teb.projects.stage', $p), ['action' => 'notify_adi', 'fields' => [
            'emails' => ['adi.oversight@example.test', 'pe.sup@example.test'],
            'presentation_at' => '2026-11-05T09:00',
            'location' => 'Teams',
        ]])->assertSessionHasNoErrors();
        Mail::assertSent(\App\Mail\TebAdiNotification::class, 2);
        $this->assertSame(TebProject::STATUS_ADI_REVIEW, $p->fresh()->status);
        $this->assertSame('adi.oversight@example.test, pe.sup@example.test', $p->fresh()->gate_records['customer']['adi_notified']);
        $this->as('1002')->post(route('teb.projects.stage', $p), ['action' => 'secured', 'decision' => 0])->assertSessionHasErrors('remarks');
        $this->as('1002')->post(route('teb.projects.stage', $p), ['action' => 'secured', 'decision' => 0, 'remarks' => 'Customer wants Cpk data'])->assertSessionHasNoErrors();
        $this->assertSame(TebProject::STATUS_CUSTOMER_HOLD, $p->fresh()->status);
        $this->as('1002')->post(route('teb.projects.stage', $p), ['action' => 'resolved'])->assertSessionHasNoErrors();
        $this->assertSame(TebProject::STATUS_ADI_REVIEW, $p->fresh()->status);

        // 4MCN generated by originator.
        // The form prefills Current Status / Change Description from the charter's Problem Statement / Project Scope.
        $this->as('1001')->get(route('teb.fourmcn.create', $p))->assertOk()
            ->assertInertia(fn(Page $page) => $page->component('Teb/FourMcn/Form')
                ->where('p.charter.problem_statement', 'Capacity gap')->where('p.charter.project_scope', 'Line 3'));
        $this->as('1001')->post(route('teb.fourmcn.store', $p), [
            'title' => 'New fixture',
            'change_items' => ['machine', 'method'],
            'classification' => 'minor',
            'types_of_change' => ['temporary'],
            'date_written' => now()->toDateString(),
            'effectivity_date' => now()->addWeek()->toDateString(),
            'expiration_date' => now()->addMonth()->toDateString(),
            'extension_no' => 1,
            'before_change' => 'a',
            'after_change' => 'b',
            'reason' => 'c',
            'attachments_note' => 'FMEA',
        ])->assertSessionHasNoErrors()->assertRedirect(route('teb.fourmcn.show', $p));
        $this->assertSame('4MCN-' . now()->format('Y') . '-00001', $p->fresh()->fourMcn->mcn_no);

        $this->as('1002')->post(route('teb.projects.stage', $p), [
            'action' => 'secured',
            'decision' => 1,
            'stage_file' => UploadedFile::fake()->create('customer-ok.pdf', 5),
            'stage_file_type' => 'customer_approval',
            'fields' => ['conditions' => 'Monitor yield for 3 lots'],
        ])->assertSessionHasNoErrors();
        $this->assertSame('Monitor yield for 3 lots', $p->fresh()->gate_records['customer']['conditions']);
        $this->assertSame(TebProject::STATUS_DOC_RELEASE, $p->fresh()->status);
        $this->assertSame(1, $p->attachments()->where('type', 'customer_approval')->count());

        // Step 8: an unchecked item is a readiness gap.
        $this->as('1002')->post(route('teb.projects.stage', $p), ['action' => 'readiness', 'checks' => ['documents_released' => 1, 'training_complete' => 0, 'equipment_ready' => 1, 'materials_ready' => 1, 'conditions_met' => 1]])->assertSessionHasErrors('swi_spec_numbers');
        $this->as('1002')->post(route('teb.projects.stage', $p), ['action' => 'readiness', 'fields' => ['swi_spec_numbers' => 'SWI-123 rev B'], 'checks' => ['documents_released' => 1, 'training_complete' => 0, 'equipment_ready' => 1, 'materials_ready' => 1, 'conditions_met' => 1]])->assertSessionHasNoErrors();
        $this->assertSame(TebProject::STATUS_READINESS_GAPS, $p->fresh()->status);
        $this->assertStringContainsString('Training complete', $p->statusLogs()->reorder('id', 'desc')->value('remarks'));
        $this->as('1002')->post(route('teb.projects.stage', $p), ['action' => 'gaps_closed'])->assertSessionHasNoErrors();
        $this->as('1002')->post(route('teb.projects.stage', $p), ['action' => 'readiness', 'fields' => ['swi_spec_numbers' => 'SWI-123 rev B'], 'checks' => ['documents_released' => 1, 'training_complete' => 1, 'equipment_ready' => 1, 'materials_ready' => 1, 'conditions_met' => 1]])->assertSessionHasNoErrors();
        $this->assertSame(TebProject::STATUS_IMPLEMENTATION, $p->fresh()->status);

        // Step 9: the originator may report implementation done.
        $this->as('1001')->post(route('teb.projects.stage', $p), ['action' => 'implemented'])->assertSessionHasErrors('effective_date');
        $impl = ['action' => 'implemented', 'fields' => ['effective_date' => now()->toDateString(), 'affected_machines_lots' => 'Oven 3, lots A1-A3', 'initial_results' => 'Yield 98%']];
        $this->as('1001')->post(route('teb.projects.stage', $p), $impl)->assertSessionHasNoErrors();
        $this->assertSame('Oven 3, lots A1-A3', $p->fresh()->gate_records['implementation']['affected_machines_lots']);
        $this->assertSame(TebProject::STATUS_EFFECTIVENESS, $p->fresh()->status);

        // Step 10: not effective -> containment -> back to step 6 (A).
        $this->as('1002')->post(route('teb.projects.stage', $p), ['action' => 'effectiveness', 'decision' => 0, 'remarks' => 'Yield dropped'])->assertSessionHasNoErrors();
        $this->assertSame(TebProject::STATUS_CONTAINMENT, $p->fresh()->status);
        $this->as('1001')->post(route('teb.projects.complete.store', $p), ['requires_4mcn' => 1, 'conclusion' => 'Reworked after containment'])->assertSessionHasNoErrors();
        $this->assertSame(TebProject::STATUS_FOR_APPROVAL, $p->fresh()->status);
        $this->assertSame(7, $p->approvals()->where('round', 'plan')->where('status', 'approved')->count()); // plan approvals kept
        foreach ($this->results($p)->orderBy('level_order')->get() as $a) {
            $this->as($this->signer($a))->post(route('teb.approvals.act', $a), ['decision' => 'approved'])->assertSessionHasNoErrors();
        }
        $this->assertSame(TebProject::STATUS_APPROVED, $p->fresh()->status);
        $this->as('1002')->post(route('teb.projects.stage', $p), ['action' => 'customer', 'decision' => 0])->assertSessionHasNoErrors();
        $this->assertSame(TebProject::STATUS_DOC_RELEASE, $p->fresh()->status);
        $this->assertFalse($p->fresh()->customer_approval_required);
        $this->as('1002')->post(route('teb.projects.stage', $p), ['action' => 'readiness', 'fields' => ['swi_spec_numbers' => 'SWI-123 rev B'], 'checks' => ['documents_released' => 1, 'training_complete' => 1, 'equipment_ready' => 1, 'materials_ready' => 1, 'conditions_met' => 1]]);
        $this->as('1002')->post(route('teb.projects.stage', $p), $impl);
        $piev = array_fill_keys(array_keys(\App\Services\Teb\TebGates::PIEV_CHECKS), 1);
        $this->as('1002')->post(route('teb.projects.stage', $p), ['action' => 'effectiveness', 'decision' => 1, 'checks' => ['objective_achieved' => 1]])->assertSessionHasErrors('checks');
        $this->as('1002')->post(route('teb.projects.stage', $p), ['action' => 'effectiveness', 'decision' => 1, 'checks' => $piev, 'fields' => ['verified_by' => 'QA: R. Santos']])->assertSessionHasErrors('stage_file');
        $this->as('1002')->post(route('teb.projects.stage', $p), [
            'action' => 'effectiveness',
            'decision' => 1,
            'checks' => $piev,
            'fields' => ['verified_by' => 'QA: R. Santos'],
            'stage_file' => UploadedFile::fake()->create('closure.pdf', 5),
            'stage_file_type' => 'closure_report',
        ])->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame(TebProject::STATUS_CLOSED, $p->status);
        $this->assertNotNull($p->closed_at);
        $this->as('1001')->get(route('teb.projects.show', $p))->assertOk()
            ->assertInertia(fn(Page $page) => $page->where('p.step', 11)->where('p.status_label', 'Project Closed')
                ->where('p.gates.0.title', 'Qualification Package')->has('p.gates', 6)->etc());
        $this->as('1001')->get(route('teb.fourmcn.show', $p))->assertOk()
            ->assertInertia(fn(Page $page) => $page->component('Teb/FourMcn/Print')->where('p.four_mcn.mcn_no', '4MCN-' . now()->format('Y') . '-00001')
                ->where('p.four_mcn.justification', 'c')->where('p.four_mcn.extension_no', 1)->where('p.four_mcn.types_of_change', ['temporary'])->etc());

        // Forms, dashboard and export.
        $this->as('1001')->get(route('teb.projects.form', $p))->assertOk()
            ->assertInertia(fn(Page $page) => $page->component('Teb/Projects/Print')->where('p.status', 'closed')->etc());
        $this->as('1001')->get(route('dashboard'))->assertOk()
            ->assertInertia(fn(Page $page) => $page->where('total', 1)->where('byStatus.closed', 1)->etc());
        $csv = $this->as('1001')->get(route('teb.projects.export', ['category' => 'T']));
        $csv->assertOk();
        $this->assertStringContainsString($p->teb_no, $csv->streamedContent());
        $this->assertStringContainsString('4MCN-', $csv->streamedContent());
    }

    public function test_disapproval_returns_to_originator_who_can_resubmit(): void
    {
        $p = $this->register(['classification' => 'minor', 'fmea' => null]);
        $this->heldReview($p);
        $this->as('1002')->post(route('teb.review.status', $p), ['status' => 'reviewed']);
        $this->as('1002')->post(route('teb.review.assign', $p));
        $tebNo = $p->fresh()->teb_no;
        $this->assertMatchesRegularExpression('/^\d{4}-\d{5}$/', $tebNo);
        $this->authorizeTrial($p);
        $this->as('1001')->post(route('teb.projects.complete.store', $p), [
            'teb_report' => UploadedFile::fake()->create('r.pdf', 5),
            'requires_4mcn' => 0,
            'conclusion' => 'Passed',
        ]);
        $sup = $this->results($p)->where('department_group', 'Quality Assurance')->first();
        $this->as($this->signer($sup))->post(route('teb.approvals.act', $sup), ['decision' => 'rejected'])->assertSessionHasErrors('remarks');
        $this->as($this->signer($sup))->post(route('teb.approvals.act', $sup), ['decision' => 'rejected', 'remarks' => 'Need MSA data']);
        $p->refresh();
        $this->assertSame(TebProject::STATUS_RETURNED, $p->status);
        $this->assertSame($tebNo, $p->teb_no);

        // Resubmit rebuilds a fresh approval section; report already on file.
        $this->as('1001')->post(route('teb.projects.complete.store', $p), ['requires_4mcn' => 0, 'conclusion' => 'Passed'])->assertSessionHasNoErrors();
        $this->assertSame(TebProject::STATUS_FOR_APPROVAL, $p->fresh()->status);
        $this->assertSame(0, $this->results($p)->where('status', TebApproval::REJECTED)->count());
    }

    public function test_any_supervisor_section_head_or_manager_signs_once_for_the_department(): void
    {
        $p = $this->register(['classification' => 'minor', 'fmea' => null]);
        $p->update(['owner_department' => 'Facilities']); // Others = owner's department
        // Facilities has no Manager: the department still routes, its Supervisor or Section Head can sign.
        $mgr = DB::connection('masterlist')->table('employee_details')->where('firstname', 'Mgr')->where('lastname', 'Facilities')->value('employid');
        DB::connection('masterlist')->table('employee_job_level')->where('employid', $mgr)->delete();
        $this->heldReview($p);
        $this->as('1002')->post(route('teb.review.status', $p), ['status' => 'reviewed']);
        $this->as('1002')->post(route('teb.review.assign', $p));
        $this->authorizeTrial($p);
        $this->as('1001')->post(route('teb.projects.complete.store', $p), [
            'teb_report' => UploadedFile::fake()->create('r.pdf', 5),
            'requires_4mcn' => 0,
            'conclusion' => 'Passed',
        ])->assertSessionHasNoErrors();
        $this->assertSame(6, $this->results($p)->count()); // Minor change: no Operation Director or President.
        $this->assertSame(0, $p->approvals()->whereIn('level', ['operation_director', 'president'])->count());

        // Manager signs Manufacturing alone; nobody else is needed there.
        $box = $this->results($p)->where('department_group', 'Manufacturing')->first();
        $this->as($this->signer($box, 'manager'))->post(route('teb.approvals.act', $box), ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame(TebApproval::APPROVED, $box->fresh()->status);
        $this->as($this->signer($box, 'supervisor'))->post(route('teb.approvals.act', $box), ['decision' => 'approved'])->assertSessionHasErrors('approval');

        // Section Head signs Others: Facilities.
        $others = $this->results($p)->where('department_group', 'Others: Facilities')->first();
        $this->as($this->signer($others, 'section_head'))->post(route('teb.approvals.act', $others), ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame(TebProject::STATUS_FOR_APPROVAL, $p->fresh()->status);
    }

    // TSPI approval of a TRB from another department (Others): every approver who sees it in For Approval either gets
    // the Approve button, or is told whose signatures they wait for (Operation Director / President on Major changes).
    public function test_tspi_approval_for_other_department_shows_approve_or_who_signs_first(): void
    {
        DB::connection('authify')->table('authify_sessions')->insert([
            'token' => 'tok-3001',
            'portal_id' => config('services.authify.portal_id'),
            'emp_id' => '3001',
            'emp_name' => 'Wally Lead',
            'emp_dept' => 'Warehouse',
            'generated_at' => now()
        ]);
        $p = $this->register();
        $p->update(['owner_department' => 'Warehouse']); // Others = owner's department
        $this->heldReview($p);
        $this->as('1002')->post(route('teb.review.status', $p), ['status' => 'reviewed']);
        $this->as('1002')->post(route('teb.review.assign', $p));
        $this->as('1001')->post(route('teb.projects.plan', $p), [
            'trial_plan' => UploadedFile::fake()->create('plan.pdf', 10),
            'acceptance_criteria' => 'Cpk >= 1.33',
            'uses_customer_product' => 0
        ])->assertSessionHasNoErrors();
        foreach ($p->approvals()->where('round', 'plan')->orderBy('level_order')->get() as $a) {
            $signer = str_starts_with($a->department_group, 'Others') ? '3001' : $this->signer($a); // Warehouse has a Section Head only
            $this->as($signer)->post(route('teb.approvals.act', $a), ['decision' => 'approved'])->assertSessionHasNoErrors();
        }
        $this->assertSame(TebProject::STATUS_TRIAL_AUTHORIZED, $p->fresh()->status);
        $this->as('1001')->post(route('teb.projects.complete.store', $p), [
            'teb_report' => UploadedFile::fake()->create('r.pdf', 5),
            'requires_4mcn' => 0,
            'conclusion' => 'Passed',
            'customer_trial' => UploadedFile::fake()->create('trial.pdf', 5),
        ])->assertSessionHasNoErrors();
        $this->assertSame(TebProject::STATUS_FOR_APPROVAL, $p->fresh()->status);

        // Others: Warehouse is signed by its Section Head (position level), who gets Approve in the list and on the page.
        $others = $this->results($p)->where('department_group', 'Others: Warehouse')->first();
        $this->assertSame(['3001'], \App\Services\Teb\ApproverCandidates::eligible((int) $others->department_id, 'section_head'));
        $this->as('3001')->get(route('teb.approvals.index'))->assertOk()
            ->assertInertia(fn(Page $page) => $page->has('projects.data', 1)
                ->where('projects.data.0.my_approval.can_sign', true)->where('projects.data.0.my_approval.label', 'Others: Warehouse')->etc());
        $this->as('3001')->get(route('teb.projects.show', $p))->assertOk()
            ->assertInertia(fn(Page $page) => $page->where('myTurn.id', $others->id)->where('myWait', null)->etc());

        // The Operation Director (also in Warehouse) sees it listed, but signs after every department: no Approve
        // button, and the page names the boxes still open.
        $this->as('3002')->get(route('teb.approvals.index'))->assertOk()
            ->assertInertia(fn(Page $page) => $page->has('projects.data', 1)
                ->where('projects.data.0.my_approval.can_sign', false)
                ->where('projects.data.0.my_approval.label', 'Operation Director')
                ->where('projects.data.0.my_approval.waiting_on', ['Manufacturing', 'Process Engineering', 'Equipment Engineering', 'Quality Assurance', 'Quality Management System', 'Others: Warehouse'])->etc());
        $this->as('3002')->get(route('teb.projects.show', $p))->assertOk()
            ->assertInertia(fn(Page $page) => $page->where('myTurn', null)->where('myWait.label', 'Operation Director')->has('myWait.on', 6)->etc());

        // TRB Admin sees every TRB in approval, but only gets Approve where they can sign (Process Engineering).
        $this->as('1002')->get(route('teb.approvals.index'))->assertOk()
            ->assertInertia(fn(Page $page) => $page->where('projects.data.0.my_approval', null)->etc());
        $pe = $this->results($p)->where('department_group', 'Process Engineering')->first();
        $this->as($this->signer($pe))->get(route('teb.approvals.index'))->assertOk()
            ->assertInertia(fn(Page $page) => $page->where('projects.data.0.my_approval.can_sign', true)->etc());

        // Every department signs; then the Operation Director can approve, and the President waits only on them.
        foreach ($this->results($p)->where('level', TebApproval::ANY_LEVEL)->get() as $a) {
            $this->as($a->id === $others->id ? '3001' : $this->signer($a))->post(route('teb.approvals.act', $a), ['decision' => 'approved'])->assertSessionHasNoErrors();
        }
        $this->assertSame(TebApproval::APPROVED, $others->fresh()->status);
        $this->as('3002')->get(route('teb.projects.show', $p))->assertOk()
            ->assertInertia(fn(Page $page) => $page->where('myWait', null)->where('myTurn.label', 'Operation Director')->etc());
        $this->as('3003')->get(route('teb.projects.show', $p))->assertOk()
            ->assertInertia(fn(Page $page) => $page->where('myTurn', null)->where('myWait.on', ['Operation Director'])->etc());

        $od = $this->results($p)->where('level', 'operation_director')->first();
        $this->as('3002')->post(route('teb.approvals.act', $od), ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->as('3003')->get(route('teb.approvals.index'))->assertOk()
            ->assertInertia(fn(Page $page) => $page->where('projects.data.0.my_approval.can_sign', true)->etc());
        $pres = $this->results($p)->where('level', 'president')->first();
        $this->as('3003')->post(route('teb.approvals.act', $pres), ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame(TebProject::STATUS_APPROVED, $p->fresh()->status);
    }

    public function test_completion_is_blocked_when_a_department_has_no_approver(): void
    {
        $p = $this->register(['classification' => 'minor', 'fmea' => null]);
        $p->update(['owner_department' => 'Facilities']); // Others = owner's department
        $ml = DB::connection('masterlist');
        $ids = $ml->table('employee_details')->where('lastname', 'Facilities')->pluck('employid');
        $ml->table('employee_job_level')->whereIn('employid', $ids)->delete();
        $this->heldReview($p);
        $this->as('1002')->post(route('teb.review.status', $p), ['status' => 'reviewed']);
        $this->as('1002')->post(route('teb.review.assign', $p));
        $this->as('1001')->post(route('teb.projects.plan', $p), [
            'trial_plan' => UploadedFile::fake()->create('plan.pdf', 5),
            'acceptance_criteria' => 'Cpk >= 1.33',
            'uses_customer_product' => 0
        ])->assertSessionHasErrors(['approval' => 'No Supervisor, Section Head or Manager in the masterlist for: Facilities. Check their department and job level in the masterlist.']);
        $this->assertSame(TebProject::STATUS_REVIEWED, $p->fresh()->status);
        $this->assertSame(0, $p->approvals()->count());
    }

    public function test_shared_departments_can_sign_for_each_other(): void
    {
        $ml = DB::connection('masterlist');
        // MIS has one manager who also manages Facilities; Facilities has no manager of its own.
        $mis = $ml->table('department')->insertGetId(['dep_name' => 'MIS']);
        $ml->table('employee_details')->insert(['employid' => 4001, 'firstname' => 'Max', 'lastname' => 'Shared', 'email' => 'max@example.test']);
        $ml->table('employee_department')->insert(['employid' => 4001, 'department_id' => $mis]);
        $ml->table('employee_job_level')->insert(['employid' => 4001, 'job_level_id' => 4]);
        $mgr = $ml->table('employee_details')->where('firstname', 'Mgr')->where('lastname', 'Facilities')->value('employid');
        $ml->table('employee_job_level')->where('employid', $mgr)->delete();

        $p = $this->register(['classification' => 'minor', 'fmea' => null]);
        $p->update(['owner_department' => 'Facilities']); // Others = owner's department
        $this->heldReview($p);
        $this->as('1002')->post(route('teb.review.status', $p), ['status' => 'reviewed']);
        $this->as('1002')->post(route('teb.review.assign', $p));
        $this->as('1001')->post(route('teb.projects.plan', $p), ['trial_plan' => UploadedFile::fake()->create('plan.pdf', 5), 'acceptance_criteria' => 'Cpk >= 1.33', 'uses_customer_product' => 0])->assertSessionHasNoErrors();
        $this->assertSame(TebProject::STATUS_PLAN_FOR_APPROVAL, $p->fresh()->status);

        $box = $p->approvals()->where('department_group', 'Others: Facilities')->first();
        $this->assertTrue(\App\Services\Teb\ApproverCandidates::canSign('4001', $box->department_id, 'manager'));
        $this->assertTrue($box->canBeSignedBy(new \App\Services\Teb\TebActor('4001', 'Max Shared')));
        // Sharing covers approval levels only, not the Facilities staff list.
        $this->assertFalse(\App\Services\Teb\ApproverCandidates::canSign('4001', $box->department_id, 'supervisor'));
    }

    public function test_feasibility_revise_resubmits_and_close_ends_the_project(): void
    {
        $p = $this->register(['classification' => 'minor', 'fmea' => null]);
        $this->as('1002')->post(route('teb.review.status', $p), ['status' => 'revise'])->assertSessionHasErrors('remarks');
        $this->as('1002')->post(route('teb.review.status', $p), ['status' => 'revise', 'remarks' => 'Add the line layout'])->assertSessionHasNoErrors();
        $this->assertSame(TebProject::STATUS_REVISE, $p->fresh()->status);

        // Saving the edit resubmits it for review.
        $this->as('1001')->put(route('teb.projects.update', $p), [
            'title' => 'Revised',
            'category' => 'machine_qualification',
            'bmp' => ['T' => 'Equipment & System Enhancement'],
            'types_of_change' => ['permanent'],
            'affected_areas' => [$this->fx['dept']('Production')],
            'project_description' => 'with layout',
            'charter' => ['problem_statement' => 'Capacity gap', 'goal_statement' => '+20% UPH by Q1', 'project_scope' => 'Line 3'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(TebProject::STATUS_SUBMITTED, $p->fresh()->status);

        $this->as('1002')->post(route('teb.review.status', $p), [
            'status' => 'dropped',
            'remarks' => 'Not cost effective',
            'assessment' => ['investment' => 'PHP 2.4M', 'risks' => '']
        ])->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame('PHP 2.4M', $p->gate_records['feasibility']['investment']);
        $this->assertArrayNotHasKey('risks', $p->gate_records['feasibility']);
        $this->assertSame(TebProject::STATUS_DROPPED, $p->status);
        $this->assertNotNull($p->closed_at);
        // Nothing moves after Close (Do not proceed).
        $this->as('1002')->post(route('teb.review.status', $p), ['status' => 'open'])->assertSessionHasErrors('status');
        $this->as('1001')->post(route('teb.projects.plan', $p), ['trial_plan' => UploadedFile::fake()->create('plan.pdf', 5), 'acceptance_criteria' => 'Cpk >= 1.33', 'uses_customer_product' => 0])->assertSessionHasErrors('status');
    }

    public function test_material_change_from_customer_review_returns_to_teb(): void
    {
        $p = $this->register(['classification' => 'minor', 'fmea' => null]);
        $p->update(['status' => TebProject::STATUS_ADI_REVIEW, 'teb_no' => '2026-00099']);
        $this->as('1002')->post(route('teb.projects.stage', $p), ['action' => 'material_change'])->assertSessionHasErrors('remarks');
        $this->as('1002')->post(route('teb.projects.stage', $p), ['action' => 'material_change', 'remarks' => 'Customer asks for a different flux'])->assertSessionHasNoErrors();
        $this->assertSame(TebProject::STATUS_RETURNED, $p->fresh()->status);
        $this->assertTrue($p->fresh()->canBeCompleted());
    }

    public function test_reroute_adds_new_departments_and_keeps_signatures(): void
    {
        $p = $this->register(['classification' => 'minor', 'fmea' => null]);
        $this->heldReview($p);
        $this->as('1002')->post(route('teb.review.status', $p), ['status' => 'reviewed']);
        $this->as('1002')->post(route('teb.review.assign', $p));
        $this->authorizeTrial($p);
        $this->as('1001')->post(route('teb.projects.complete.store', $p), ['teb_report' => UploadedFile::fake()->create('r.pdf', 5), 'requires_4mcn' => 0, 'conclusion' => 'Passed']);
        // Routed before QMS was a signatory; Manufacturing already signed.
        $this->results($p)->where('department_group', 'Quality MAnagement System')->delete();
        $box = $this->results($p)->where('department_group', 'Manufacturing')->first();
        $this->as($this->signer($box))->post(route('teb.approvals.act', $box), ['decision' => 'approved']);

        $this->artisan('teb:reroute', ['teb_no' => $p->teb_no])->assertSuccessful();
        $this->assertSame(1, $this->results($p)->where('department_group', 'Quality MAnagement System')->where('status', 'pending')->count());
        $this->assertSame(TebApproval::APPROVED, $box->fresh()->status);
        $this->assertSame(range(0, 4), $this->results($p)->orderBy('department_order')->pluck('department_order')->all());
    }

    public function test_4mcn_is_generated_when_adi_approves(): void
    {
        $p = $this->register(['classification' => 'minor', 'fmea' => null]);
        $p->update([
            'status' => TebProject::STATUS_ADI_REVIEW,
            'teb_no' => '2026-00099',
            'requires_4mcn' => true,
            'gate_records' => ['results' => ['conclusion' => 'Meets criteria']]
        ]);
        $this->as('1002')->post(route('teb.projects.stage', $p), ['action' => 'secured', 'decision' => 1])->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame(TebProject::STATUS_DOC_RELEASE, $p->status);
        $this->assertSame('Meets criteria', $p->fourMcn->reason);
        $this->assertSame($p->title, $p->fourMcn->title);
        $this->assertSame('Capacity gap', $p->charter['problem_statement']);
        // Current Status (before change) = Problem / Opportunity Statement, Change Description (after change) = Project Scope.
        $this->assertSame('Capacity gap', $p->fourMcn->before_change);
        $this->assertSame('Line 3', $p->fourMcn->after_change);
    }

    public function test_teb_admin_can_approve_the_trial_plan_when_configured(): void
    {
        config(['teb.plan_approval' => 'admin']);
        $p = $this->register(['classification' => 'minor', 'fmea' => null]);
        $this->heldReview($p);
        $this->as('1002')->post(route('teb.review.status', $p), ['status' => 'reviewed']);
        $this->as('1002')->post(route('teb.review.assign', $p));
        $this->as('1001')->post(route('teb.projects.plan', $p), ['trial_plan' => UploadedFile::fake()->create('plan.pdf', 5), 'acceptance_criteria' => 'Cpk >= 1.33', 'uses_customer_product' => 0])->assertSessionHasNoErrors();
        $this->assertSame(0, $p->approvals()->count());
        $this->as('2001')->post(route('teb.projects.stage', $p), ['action' => 'plan', 'decision' => 1])->assertForbidden();
        $this->as('1002')->post(route('teb.projects.stage', $p), ['action' => 'plan', 'decision' => 0])->assertSessionHasErrors('remarks');
        $this->as('1002')->post(route('teb.projects.stage', $p), ['action' => 'plan', 'decision' => 1])->assertSessionHasNoErrors();
        $this->assertSame(TebProject::STATUS_TRIAL_AUTHORIZED, $p->fresh()->status);
    }

    public function test_only_originator_can_edit_and_only_while_editable(): void
    {
        $p = $this->register();
        $this->as('1002')->get(route('teb.projects.edit', $p))->assertForbidden();
        $this->as('1001')->get(route('teb.projects.edit', $p))->assertOk()
            ->assertInertia(fn(Page $page) => $page->component('Teb/Projects/Form')->where('p.title', $p->title)->etc());
        $this->as('1001')->put(route('teb.projects.update', $p), [
            'title' => 'Renamed',
            'category' => 'machine_qualification',
            'bmp' => ['T' => 'Machine & Process Qualification'],
            'types_of_change' => ['temporary'],
            'affected_areas' => [$this->fx['dept']('Production')],
            'project_description' => 'z',
            'charter' => ['problem_statement' => 'Capacity gap', 'goal_statement' => '+20% UPH by Q1', 'project_scope' => 'Line 3'],
        ])->assertSessionHasNoErrors()->assertRedirect(route('teb.projects.show', $p));
        $this->assertSame('Renamed', $p->fresh()->title);
        $this->as('1002')->post(route('teb.review.status', $p), ['status' => 'ongoing']);
        $this->as('1001')->get(route('teb.projects.edit', $p))->assertForbidden();
    }

    public function test_operation_director_and_president_need_executive_position_level_35(): void
    {
        $this->assertSame(['3002'], \App\Services\Teb\ApproverCandidates::eligibleExecutive('operation_director'));
        // Victor Vice has job level President but no Executive position level, so only Pia qualifies.
        $this->assertSame(['3003'], \App\Services\Teb\ApproverCandidates::eligibleExecutive('president'));

        // Not an Executive any more: a Major change can no longer be routed until someone else is.
        DB::connection('masterlist')->table('employee_position_level')->where('employid', 3003)->update(['position_level_id' => 1]);
        $this->assertSame([], $this->freshExecutives('president'));

        // TEB_EXECUTIVE_POSITION_LEVEL_IDS empty: job level alone decides again.
        config(['teb.executive_position_level_ids' => []]);
        $this->assertSame(['3003', '3004'], $this->freshExecutives('president'));
    }

    protected function freshExecutives(string $key): array
    {
        $ids = (new class extends \App\Services\Teb\ApproverCandidates {
            public static function fresh(): array
            {
                return static::loadExecutives();
            }
        })::fresh()[$key];
        $ids = array_column($ids, 'id');
        sort($ids);

        return $ids;
    }

    public function test_settings_and_register_form(): void
    {
        $this->as('1002')->get(route('teb.settings'))->assertOk()
            ->assertInertia(fn(Page $page) => $page->component('Teb/Settings/Index')->has('departments', 7)
                // Equipment Engineering (first A-Z): one person per level, picked by masterlist job level.
                ->where('departments.0.name', 'Equipment Engineering')
                ->has('departments.0.candidates.supervisor', 1)->where('departments.0.candidates.supervisor.0.job_level', 'Supervisor')
                ->has('departments.0.candidates.section_head', 1)->where('departments.0.candidates.section_head.0.job_level', 'Section Head')
                ->has('departments.0.candidates.manager', 1)->where('departments.0.candidates.manager.0.job_level', 'Manager')
                // Warehouse (last A-Z): matched as Section Head through position_level only.
                ->where('departments.6.name', 'Warehouse')
                ->has('departments.6.candidates.section_head', 1)->where('departments.6.candidates.section_head.0.job_level', 'Engineer · Section Head')
                ->missing('departments.6.candidates.supervisor')->missing('departments.6.candidates.manager')
                ->has('departments.6.candidates.all', 2)
                // Major-change signers: position level 35 Executive only; job level picks Operation Director or President.
                ->where('executives.0.label', 'Operation Director')->has('executives.0.candidates', 1)
                ->where('executives.0.candidates.0.name', 'Director, Dora')
                ->where('executives.1.label', 'President')->has('executives.1.candidates', 1)
                ->where('executives.1.candidates.0.name', 'President, Pia')
                ->etc());
        $this->as('2001')->get(route('teb.settings'))->assertRedirect(route('dashboard'));
        $this->as('1001')->get(route('teb.projects.create'))->assertOk()
            ->assertInertia(fn(Page $page) => $page->component('Teb/Projects/Form')->where('customers.0.name', 'Acme Semicon')
                ->where('ownerDept', 'Process Engineering')->has('departments', 7)->etc());
    }
}
