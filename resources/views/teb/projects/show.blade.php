@extends('teb.layout')
@section('title', $p->display_no.' · '.$p->title)
@section('content')
@php
  use App\Models\Teb\TebProject as T;
  $steps = [
    'Registered'         => true,
    'Under review'       => $p->status !== T::STATUS_SUBMITTED,
    'Reviewed'           => in_array($p->status, [T::STATUS_PENDING_TEB_NUMBER, T::STATUS_REVIEWED, T::STATUS_FOR_APPROVAL, T::STATUS_RETURNED, T::STATUS_APPROVED], true),
    'TEB No. assigned'   => (bool) $p->teb_no,
    'TEB form completed' => (bool) $p->completed_at,
    'Approved'           => $p->status === T::STATUS_APPROVED,
  ];
  if ($p->requires_4mcn) { $steps['4MCN generated'] = (bool) $p->fourMcn; }
  $now = collect($steps)->search(false);
@endphp

<div class="row spread" style="margin-bottom:12px">
  <div>
    <div class="small muted">{{ $p->category_label }}</div>
    <h1 style="margin:2px 0 0">{{ $p->display_no }} · {{ $p->title }}</h1>
  </div>
  <div class="row">@include('teb.partials.status', ['status' => $p->status])
    <a class="btn ghost" href="{{ $back }}">Back to list</a></div>
</div>

<div class="flow">@foreach($steps as $label => $done)<span class="{{ $done ? 'done' : ($label === $now ? 'now' : '') }}">{{ $done ? '✓ ' : '' }}{{ $label }}</span>@endforeach</div>

{{-- What the current user can do next --}}
<div class="card noprint">
  <h2>Actions</h2>
  <div class="row">
    @if($p->teb_no)<a class="btn ghost" href="{{ route('teb.projects.form', $p) }}" target="_blank">TEB Form (print / PDF)</a>@endif
    @if($isOwner && $p->isEditable())<a class="btn ghost" href="{{ route('teb.projects.edit', $p) }}">Edit entry</a>@endif
    @if($isOwner && $p->canBeCompleted())<a class="btn" href="{{ route('teb.projects.complete', $p) }}">Complete TEB form &amp; upload TEB Report</a>@endif
    @if($isOwner && $p->needsFourMcn())<a class="btn" href="{{ route('teb.fourmcn.create', $p) }}">Generate 4MCN Form</a>@endif
    @if($p->fourMcn)<a class="btn ghost" href="{{ route('teb.fourmcn.show', $p) }}" target="_blank">4MCN Form {{ $p->fourMcn->mcn_no }}</a>@endif
    @if($isAdmin && ! in_array($p->status, [T::STATUS_FOR_APPROVAL, T::STATUS_APPROVED], true))
      <a class="btn ghost" href="{{ route('teb.review.schedule', $p) }}">Schedule TEB Review</a>
    @endif
  </div>

  @if($isAdmin && ! in_array($p->status, [T::STATUS_FOR_APPROVAL, T::STATUS_APPROVED], true))
    <div class="grid g2" style="margin-top:16px">
      <form id="update-status" method="post" action="{{ route('teb.review.status', $p) }}" class="grid" style="align-content:start">
        @csrf
        <label for="status">Update status (TEB Admin)</label>
        <div class="row">
          <select id="status" name="status" style="max-width:220px">
            @foreach(T::REVIEW_STATUSES as $s)<option value="{{ $s }}" @selected($p->status === $s)>{{ T::STATUS_LABELS[$s] }}</option>@endforeach
          </select>
          <button class="btn">Update</button>
        </div>
        <input type="text" name="remarks" placeholder="Remarks (optional)">
        @if(! $p->teb_no)<div class="small muted">Setting “Reviewed” before a TEB No. exists moves the entry to Pending TEB Number.</div>@endif
      </form>
      @if(! $p->teb_no)
      <form method="post" action="{{ route('teb.review.assign', $p) }}" class="grid" style="align-content:start">
        @csrf
        <label for="teb_no">Assign TEB Number (TEB Admin)</label>
        <div class="row">
          <input type="text" id="teb_no" name="teb_no" placeholder="Leave blank for next: {{ T::nextTebNo() }}" style="max-width:280px">
          <button class="btn">Assign</button>
        </div>
      </form>
      @endif
    </div>
  @endif
</div>

<div class="grid g2" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr)">
  <div class="card"><h2>Project details</h2>@include('teb.partials.details', ['p' => $p])</div>
  <div>
    <div class="card">
      <h2>Documents</h2>
      @forelse($p->attachments as $f)
        <div style="padding:6px 0;border-bottom:1px solid var(--line)">
          <span class="chip">{{ $f->type_label }}</span>
          <a href="{{ route('teb.projects.download', [$p, $f]) }}">{{ $f->original_name }}</a>
        </div>
      @empty
        <p class="muted">No documents yet.</p>
      @endforelse
      @if($isOwner && $p->status !== T::STATUS_APPROVED)
      <form method="post" enctype="multipart/form-data" action="{{ route('teb.projects.upload', $p) }}" class="grid noprint" style="margin-top:12px">
        @csrf
        <div class="row">
          <select name="type" style="max-width:150px">@foreach(\App\Models\Teb\TebAttachment::TYPE_LABELS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
          <input type="file" name="file" required style="flex:1">
        </div>
        <button class="btn sm ghost">Upload</button>
      </form>
      @endif
    </div>
    <div class="card">
      <h2>TEB reviews</h2>
      @forelse($p->reviews as $r)
        <div style="padding:6px 0;border-bottom:1px solid var(--line)">
          <strong>{{ $r->review_at->format('D, m/d/Y H:i') }}</strong> · {{ $r->duration_minutes }} min
          <div class="small muted">{{ $r->location ?: 'No location' }} · {{ $r->invites_sent }} invite(s) sent</div>
          @if($r->notes)<div class="small">{{ $r->notes }}</div>@endif
          <a class="small" href="{{ route('teb.reviews.ics', [$p, $r]) }}">Add to calendar (.ics)</a>
        </div>
      @empty
        <p class="muted">No review scheduled yet.</p>
      @endforelse
    </div>
  </div>
</div>

@if($p->approvals->isNotEmpty())
<div class="card" id="approval">
  <h2>Approval section</h2>
  @include('teb.partials.approval-section', ['p' => $p])
  @if($myTurn && $p->status === T::STATUS_FOR_APPROVAL)
    <form method="post" action="{{ route('teb.approvals.act', $myTurn) }}" class="grid noprint" style="margin-top:16px;max-width:560px">
      @csrf
      <label for="remarks">Your decision as {{ $myTurn->department_group }} {{ $myTurn->level_label }}</label>
      <textarea id="remarks" name="remarks" placeholder="Remarks (required when disapproving)" style="min-height:60px"></textarea>
      <div class="row">
        <button class="btn ok" name="decision" value="approved">Approve</button>
        <button class="btn bad" name="decision" value="rejected">Disapprove</button>
      </div>
    </form>
  @endif
</div>
@endif

<div class="card">
  <h2>History</h2>
  <ul class="timeline">
    @foreach($p->statusLogs as $log)
      <li><strong>{{ $log->to_label }}</strong> <span class="muted small">· {{ $log->created_at->format('m/d/Y H:i') }} · {{ $log->emp_name }}</span>
        @if($log->remarks)<div class="small">{{ $log->remarks }}</div>@endif</li>
    @endforeach
  </ul>
</div>
@endsection
