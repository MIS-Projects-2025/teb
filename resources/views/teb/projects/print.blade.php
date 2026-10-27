@extends('teb.layout')
@section('title', 'TEB Form '.$p->teb_no)
@section('content')
<div class="row spread noprint" style="margin-bottom:12px">
  <a class="btn ghost" href="{{ route('teb.projects.show', $p) }}">Back</a>
  <button class="btn" onclick="window.print()">Print / Save as PDF</button>
</div>
<div class="card">
  <div class="row spread" style="border-bottom:2px solid var(--ink);padding-bottom:10px;margin-bottom:16px">
    <div><div class="small muted">Telford Engineering Board</div><h1 style="margin:0">TEB FORM</h1></div>
    <div style="text-align:right"><div><strong>TEB No:</strong> {{ $p->display_no }}</div>
      <div><strong>Status:</strong> {{ $p->completed_at ? 'COMPLETED' : strtoupper($p->status_label) }}</div>
      <div class="small muted">System generated {{ now()->format('m/d/Y H:i') }}</div></div>
  </div>
  @include('teb.partials.details', ['p' => $p])
  <h2 style="margin-top:20px">Documents</h2>
  <p>{{ $p->attachments->map(fn ($f) => $f->type_label.': '.$f->original_name)->implode(' · ') ?: '—' }}</p>
  @if($p->approvals->isNotEmpty())
    <h2 style="margin-top:20px">Approval</h2>
    @include('teb.partials.approval-section', ['p' => $p])
  @endif
</div>
@endsection
