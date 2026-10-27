<?php
// app/Http/Controllers/Teb/ReviewController.php

namespace App\Http\Controllers\Teb;

use App\Mail\TebReviewInvitation;
use App\Models\Masterlist\Employee as MasterEmployee;
use App\Models\Teb\TebProject;
use App\Models\Teb\TebReview;
use App\Services\Teb\EmailDirectory;
use App\Services\Teb\IcsBuilder;
use App\Services\Teb\TebPresenter;
use App\Services\Teb\TebUser;
use App\Services\Teb\TebWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

// "For Review (Process Engineering)" — TEB Admin only (slide 3).
class ReviewController extends TebController
{
    const LIST_STATUSES = [
        TebProject::STATUS_SUBMITTED,
        TebProject::STATUS_OPEN,
        TebProject::STATUS_HOLD,
        TebProject::STATUS_ONGOING,
        TebProject::STATUS_PENDING_TEB_NUMBER,
        TebProject::STATUS_REVIEWED,
        TebProject::STATUS_FOR_APPROVAL,
    ];

    public function __construct(protected TebWorkflow $workflow)
    {
    }

    public function index(Request $request)
    {
        $projects = TebProject::with('affectedAreas')
            ->whereIn('status', $request->input('status') ? [$request->input('status')] : self::LIST_STATUSES)
            ->category($request->input('category'))
            ->search($request->input('q'))
            ->orderBy('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($p) => TebPresenter::row($p));

        return $this->render('Review/Index', [
            'projects' => $projects,
            'statuses' => self::LIST_STATUSES,
            'filters'  => $request->only('q', 'category', 'status'),
        ]);
    }

    public function scheduleForm(TebProject $project)
    {
        $cfg = config('teb.email_accounts');

        return $this->render('Review/Schedule', [
            'p'           => TebPresenter::detail($project->load('affectedAreas', 'attachments', 'reviews')),
            'emails'      => rescue(fn () => EmailDirectory::options(), [], report: true),
            'emailSource' => "{$cfg['connection']}: {$cfg['table']}",
        ]);
    }

    public function schedule(Request $request, TebProject $project)
    {
        $data = $request->validate([
            'review_at'        => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'location'         => ['nullable', 'string', 'max:255'],
            'reviewees'        => ['required', 'array', 'min:1'],
            'reviewees.*'      => ['email', 'distinct'],
            'notes'            => ['nullable', 'string', 'max:5000'],
        ]);

        $user   = TebUser::required();
        $review = $project->reviews()->create($data + ['scheduled_by' => $user->id]);
        [$sent, $total, $mailError] = $this->sendInvites($project, $review);

        if ($project->status === TebProject::STATUS_SUBMITTED) {
            $this->workflow->changeStatus($project, TebProject::STATUS_OPEN, $user, 'Review scheduled for '.$review->review_at->format('m/d/Y H:i'));
        }

        return $this->backWithMailResult($project, "Review scheduled. {$sent} of {$total} calendar invite(s) sent.", $mailError);
    }

    // Sends the same invite again to the review's reviewees and the owner.
    public function resend(TebProject $project, TebReview $review)
    {
        abort_unless($review->teb_project_id === $project->id, 404);

        [$sent, $total, $mailError] = $this->sendInvites($project, $review);

        return $this->backWithMailResult($project, "Invite resent. {$sent} of {$total} calendar invite(s) sent.", $mailError);
    }

    // Reviewees plus the owner. A failed invite doesn't stop the others; the first mail error is returned so it can be fixed.
    protected function sendInvites(TebProject $project, TebReview $review): array
    {
        // Reviews saved before reviewees were email addresses hold employee IDs; look those up in the masterlist.
        [$emails, $ids] = collect($review->reviewees ?? [])->partition(fn ($v) => str_contains((string) $v, '@'));
        if ($ids->isNotEmpty()) {
            $emails = $emails->merge(MasterEmployee::whereIn('employid', $ids->all())->pluck('email'));
        }
        if ($ownerEmail = $project->owner?->email) {
            $emails->push($ownerEmail);
        }
        $emails = $emails->map(fn ($e) => mb_strtolower(trim((string) $e)))->filter()->unique()->values();

        $sent = 0;
        $mailError = null;
        foreach ($emails as $email) {
            try {
                Mail::to($email)->send(new TebReviewInvitation($review));
                $sent++;
            } catch (\Throwable $e) {
                report($e);
                $mailError ??= $e->getMessage();
            }
        }
        $review->update(['invites_sent' => $review->invites_sent + $sent]);

        return [$sent, $emails->count(), $mailError];
    }

    protected function backWithMailResult(TebProject $project, string $message, ?string $mailError)
    {
        $redirect = redirect()->route('teb.projects.show', $project)->with('success', $message);

        return $mailError
            ? $redirect->with('error', 'Some invites were not sent. Mail error: '.str($mailError)->limit(300))
            : $redirect;
    }

    public function ics(TebProject $project, TebReview $review)
    {
        abort_unless($review->teb_project_id === $project->id, 404);

        return response(IcsBuilder::forReview($review), 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="teb-review-'.$review->id.'.ics"',
        ]);
    }

    public function updateStatus(Request $request, TebProject $project)
    {
        $data = $request->validate([
            'status'  => ['required', Rule::in(TebProject::REVIEW_STATUSES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->workflow->updateReviewStatus($project, $data['status'], TebUser::required(), $data['remarks'] ?? null);

        return redirect()->route('teb.projects.show', $project)->with('success', 'Status updated to '.$project->status_label.'.');
    }

    public function assignNumber(Request $request, TebProject $project)
    {
        $this->workflow->assignTebNo($project, TebUser::required());

        return redirect()->route('teb.projects.show', $project)->with('success', "TEB No. {$project->teb_no} assigned.");
    }
}
