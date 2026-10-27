{{-- Slide 4: approval section at the bottom of the registered project. --}}
@php($groups = $p->approvals->groupBy('department_group'))
<div class="tbl">
<table>
  <thead><tr><th>Department</th>@foreach(config('teb.approval_levels') as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
  <tbody>
  @foreach($groups as $group => $rows)
    <tr>
      <td><strong>{{ $group }}</strong></td>
      @foreach(array_keys(config('teb.approval_levels')) as $level)
        @php($a = $rows->firstWhere('level', $level))
        <td>
          @if($a)
            <div>{{ $a->approver_name ?: 'No approver set' }}</div>
            <div class="small a-{{ $a->status }}">{{ ucfirst($a->status === 'rejected' ? 'disapproved' : $a->status) }}{{ $a->acted_at ? ' · '.$a->acted_at->format('m/d/Y H:i') : '' }}</div>
            @if($a->remarks)<div class="small muted">{{ $a->remarks }}</div>@endif
          @endif
        </td>
      @endforeach
    </tr>
  @endforeach
  </tbody>
</table>
</div>
