@extends('teb.layout')
@section('title', 'For Approval')
@section('content')
<div class="row spread" style="margin-bottom:16px">
  <h1 style="margin:0">For Approval</h1>
  @if(\App\Services\Teb\TebUser::isAdmin())
    <div class="row">
      <a class="btn {{ request('mine') ? 'ghost' : '' }} sm" href="{{ route('teb.approvals.index') }}">All in approval</a>
      <a class="btn {{ request('mine') ? '' : 'ghost' }} sm" href="{{ route('teb.approvals.index', ['mine' => 1]) }}">Assigned to me</a>
    </div>
  @endif
</div>
<div class="card">@include('teb.partials.table', ['projects' => $projects, 'mode' => 'approval'])</div>
@endsection
