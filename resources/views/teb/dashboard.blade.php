@extends('teb.layout')
@section('title', 'Dashboard')
@section('content')
<div class="row spread" style="margin-bottom:16px">
  <h1 style="margin:0">Dashboard</h1>
  <div class="row">
    <a class="btn" href="{{ route('teb.projects.create') }}">Register Project Entry</a>
    <a class="btn ghost" href="{{ route('teb.projects.export') }}">Extract report (CSV)</a>
  </div>
</div>

<div class="grid g4" style="margin-bottom:20px;grid-template-columns:repeat(auto-fill,minmax(150px,1fr))">
  <a class="stat total" href="{{ route('teb.projects.index') }}"><div class="n">{{ $total }}</div><div class="l">Total TEB</div></a>
  @foreach($statuses as $s)
    <a class="stat" href="{{ route('teb.projects.index', ['status' => $s]) }}"><div class="n">{{ $byStatus[$s] ?? 0 }}</div><div class="l">{{ \App\Models\Teb\TebProject::STATUS_LABELS[$s] }}</div></a>
  @endforeach
  <a class="stat" href="{{ route('teb.projects.index', ['status' => 'approved']) }}"><div class="n">{{ $pending4mcn }}</div><div class="l">4MCN Pending</div></a>
</div>

<div class="card">
  <h2>By category</h2>
  <div class="tbl"><table>
    <thead><tr><th>Category</th><th>Total</th>@foreach($statuses as $s)<th>{{ \App\Models\Teb\TebProject::STATUS_LABELS[$s] }}</th>@endforeach</tr></thead>
    <tbody>
    @foreach(config('teb.categories') as $key => $label)
      @php($rows = $byCategory[$key] ?? collect())
      <tr>
        <td><a href="{{ route('teb.projects.index', ['category' => $key]) }}">{{ $label }}</a></td>
        <td><strong>{{ $rows->sum('total') }}</strong></td>
        @foreach($statuses as $s)<td>{{ optional($rows->firstWhere('status', $s))->total ?? 0 }}</td>@endforeach
      </tr>
    @endforeach
    </tbody>
  </table></div>
</div>

<div class="grid g2">
  <div class="card">
    <div class="row spread"><h2>Waiting for my approval</h2><a href="{{ route('teb.approvals.index', ['mine' => 1]) }}" class="small">All</a></div>
    @forelse($myApprovals as $a)
      <div style="padding:8px 0;border-bottom:1px solid var(--line)">
        <a href="{{ route('teb.projects.show', $a->project) }}#approval">{{ $a->project->display_no }} · {{ $a->project->title }}</a>
        <div class="small muted">{{ $a->department_group }} · {{ $a->level_label }}</div>
      </div>
    @empty
      <p class="muted">Nothing waiting on you.</p>
    @endforelse
  </div>
  <div class="card">
    <div class="row spread"><h2>My recent projects</h2><a href="{{ route('teb.projects.index', ['mine' => 1]) }}" class="small">All</a></div>
    @forelse($myProjects as $p)
      <div class="row spread" style="padding:8px 0;border-bottom:1px solid var(--line)">
        <a href="{{ route('teb.projects.show', $p) }}">{{ $p->display_no }} · {{ $p->title }}</a>
        @include('teb.partials.status', ['status' => $p->status])
      </div>
    @empty
      <p class="muted">You have not registered a project yet.</p>
    @endforelse
  </div>
</div>
@endsection
