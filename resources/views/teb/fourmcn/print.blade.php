@extends('teb.layout')
@section('title', $p->fourMcn->mcn_no)
@section('content')
@php($m = $p->fourMcn)
<div class="row spread noprint" style="margin-bottom:12px">
  <a class="btn ghost" href="{{ route('teb.projects.show', $p) }}">Back</a>
  <button class="btn" onclick="window.print()">Print / Save as PDF</button>
</div>
<div class="card">
  <div class="row spread" style="border-bottom:2px solid var(--ink);padding-bottom:10px;margin-bottom:16px">
    <div><div class="small muted">Telford Engineering Board</div><h1 style="margin:0">4M CHANGE NOTICE</h1></div>
    <div style="text-align:right"><div><strong>4MCN No:</strong> {{ $m->mcn_no }}</div><div><strong>TEB No:</strong> {{ $p->teb_no }}</div>
      <div class="small muted">System generated {{ $m->created_at->format('m/d/Y H:i') }}</div></div>
  </div>
  <dl class="kv">
    <dt>TEB Change Title</dt><dd>{{ $p->title }}</dd>
    <dt>Category</dt><dd>{{ $p->category_label }}</dd>
    <dt>Customer</dt><dd>{{ $p->customer_name ?: '—' }}</dd>
    <dt>4M item(s) changed</dt><dd>{{ $m->change_items_label }}</dd>
    <dt>Before change</dt><dd>{{ $m->before_change }}</dd>
    <dt>After change</dt><dd>{{ $m->after_change }}</dd>
    <dt>Reason for change</dt><dd>{{ $m->reason }}</dd>
    <dt>Effectivity date</dt><dd>{{ $m->effectivity_date->format('m/d/Y') }}</dd>
    <dt>Customer notification</dt><dd>{{ $m->customer_notification ? 'Required' : 'Not required' }}</dd>
    <dt>Affected Areas</dt><dd>{{ $p->affected_areas_label }}</dd>
    <dt>Prepared by</dt><dd>{{ $m->prepared_name }}</dd>
    <dt>TEB approved</dt><dd>{{ $p->approved_at?->format('m/d/Y') }}</dd>
  </dl>
  <h2 style="margin-top:20px">TEB approval</h2>
  @include('teb.partials.approval-section', ['p' => $p])
</div>
@endsection
