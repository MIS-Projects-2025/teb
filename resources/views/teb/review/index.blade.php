@extends('teb.layout')
@section('title', 'For Review')
@section('content')
<div class="row spread" style="margin-bottom:16px">
  <h1 style="margin:0">For Review <span class="muted" style="font-weight:400">· Process Engineering</span></h1>
  <a class="btn ghost" href="{{ route('teb.projects.export', request()->query()) }}">Extract report (CSV)</a>
</div>
<div class="card">
  @include('teb.partials.filters', ['action' => route('teb.review.index'), 'statuses' => $statuses])
  @include('teb.partials.table', ['projects' => $projects, 'mode' => 'review'])
</div>
@endsection
