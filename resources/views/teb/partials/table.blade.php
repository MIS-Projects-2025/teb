{{-- Columns from slides 3 and 4. $actions is a closure-free slot name: review | approval | default --}}
<div class="tbl">
<table>
  <thead><tr>
    <th>TEB No</th><th>Date Created</th><th>TEB Change Title</th><th>Customer</th><th>Project Description</th>
    <th>Project Owner</th><th>Classification</th><th>Type of Change</th><th>Affected Areas</th><th>Status</th><th class="noprint">Action</th>
  </tr></thead>
  <tbody>
  @forelse($projects as $row)
    <tr>
      <td style="white-space:nowrap">{{ $row->display_no }}</td>
      <td style="white-space:nowrap">{{ $row->created_at->format('m/d/Y') }}</td>
      <td style="min-width:180px"><a href="{{ route('teb.projects.show', $row) }}">{{ $row->title }}</a><div class="small muted">{{ $row->category_label }}</div></td>
      <td>{{ $row->customer_name }}</td>
      <td class="small" style="min-width:180px">{{ \Illuminate\Support\Str::limit($row->project_description, 110) }}</td>
      <td>{{ $row->owner_name }}<div class="small muted">{{ $row->owner_department }}</div></td>
      <td>{{ $row->classification_label }}</td>
      <td class="small">{{ $row->types_of_change_label }}</td>
      <td class="small">{{ $row->affected_areas_label }}</td>
      <td>@include('teb.partials.status', ['status' => $row->status])
        @if($row->needsFourMcn())<div class="small" style="color:var(--warn);margin-top:4px">4MCN pending</div>@endif</td>
      <td class="noprint"><div style="display:flex;flex-direction:column;gap:4px;white-space:nowrap">
        <a class="btn sm ghost" href="{{ route('teb.projects.show', $row) }}">View</a>
        @if(($mode ?? '') === 'review')
          <a class="btn sm ghost" href="{{ route('teb.review.schedule', $row) }}">Schedule Review</a>
          <a class="btn sm ghost" href="{{ route('teb.projects.show', $row) }}#update-status">Update Status</a>
        @elseif(($mode ?? '') === 'approval')
          <a class="btn sm" href="{{ route('teb.projects.show', $row) }}#approval">Approve</a>
        @endif
      </div></td>
    </tr>
  @empty
    <tr><td colspan="11" class="muted" style="text-align:center;padding:28px">No TEB entries found.</td></tr>
  @endforelse
  </tbody>
</table>
</div>
@if(method_exists($projects, 'links'))<div style="margin-top:12px" class="noprint">{{ $projects->links() }}</div>@endif
