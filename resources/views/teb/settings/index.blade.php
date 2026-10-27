@extends('teb.layout')
@section('title', 'TEB Settings')
@section('content')
<h1>TEB Settings</h1>
<div class="card">
  <h2>Approver matrix</h2>
  <p class="muted small">Who signs the approval section for each department. Bold departments are always routed ({{ implode(', ', $fixed) }}); any other affected area is routed as “Others”. Enter a masterlist employee ID, or leave blank to clear.</p>
  <div class="tbl"><table>
    <thead><tr><th>Department</th>@foreach($levels as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
    <tbody>
    @foreach($departments as $d)
      <tr>
        <td>@if(in_array($d->dep_name, $fixed, true))<strong>{{ $d->dep_name }}</strong>@else{{ $d->dep_name }}@endif</td>
        @foreach($levels as $level => $label)
          @php($m = ($matrix[$d->id] ?? collect())->firstWhere('level', $level))
          <td>
            <form method="post" action="{{ route('teb.settings.approver') }}" class="row" style="gap:4px;flex-wrap:nowrap">
              @csrf
              <input type="hidden" name="department_id" value="{{ $d->id }}"><input type="hidden" name="level" value="{{ $level }}">
              <input type="text" name="employid" value="{{ $m?->employid }}" placeholder="Emp ID" style="width:100px">
              <button class="btn sm ghost">Save</button>
            </form>
            @if($m)<div class="small muted">{{ $names[$m->employid] ?? '' }}</div>@endif
          </td>
        @endforeach
      </tr>
    @endforeach
    </tbody>
  </table></div>
</div>
<div class="card" style="max-width:640px">
  <h2>TEB Admins</h2>
  <p class="muted small">TEB Admins schedule reviews, update status and assign TEB numbers. Masterlist super admins are always TEB Admins.</p>
  @foreach($admins as $a)
    <div class="row spread" style="padding:6px 0;border-bottom:1px solid var(--line)">
      <span>{{ $a->employid }} · {{ $names[$a->employid] ?? '' }}</span>
      <form method="post" action="{{ route('teb.settings.admin.remove', $a) }}">@csrf @method('DELETE')<button class="btn sm ghost">Remove</button></form>
    </div>
  @endforeach
  <form method="post" action="{{ route('teb.settings.admin.add') }}" class="row" style="margin-top:12px">
    @csrf
    <input type="text" name="employid" placeholder="Employee ID" style="max-width:200px" required>
    <button class="btn sm">Add TEB Admin</button>
  </form>
</div>
@endsection
