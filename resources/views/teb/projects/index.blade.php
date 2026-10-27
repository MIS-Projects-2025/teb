@extends('teb.layout')
@section('title', $title)
@section('content')
<div class="row spread" style="margin-bottom:16px">
  <h1 style="margin:0">{{ $title }}</h1>
  <div class="row">
    <a class="btn ghost" href="{{ route('teb.projects.export', request()->query()) }}">Extract report (CSV)</a>
    <a class="btn" href="{{ route('teb.projects.create') }}">Register Project Entry</a>
  </div>
</div>
<div class="card">
  @include('teb.partials.filters', ['action' => route('teb.projects.index'), 'keep' => request('mine') ? ['mine' => 1] : [], 'dates' => true])
  @include('teb.partials.table', ['projects' => $projects])
</div>
@endsection
