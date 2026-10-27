<dl class="kv">
  <dt>TEB No</dt><dd>{{ $p->display_no }}</dd>
  <dt>TEB Change Title</dt><dd>{{ $p->title }}</dd>
  <dt>Category</dt><dd>{{ $p->category_label }}</dd>
  <dt>Customer</dt><dd>{{ $p->customer_name ?: '—' }}</dd>
  <dt>Change Classification</dt><dd>{{ $p->classification_label }}</dd>
  <dt>Type of Change</dt><dd>{{ $p->types_of_change_label }}</dd>
  <dt>Date Created</dt><dd>{{ $p->created_at->format('m/d/Y') }}</dd>
  <dt>Affected Areas</dt><dd>{{ $p->affected_areas_label }}</dd>
  <dt>Project Owner</dt><dd>{{ $p->owner_name }} · {{ $p->owner_department }}</dd>
  <dt>Project Description</dt><dd>{{ $p->project_description }}</dd>
  <dt>Current Status</dt><dd>{{ $p->current_status ?: '—' }}</dd>
  <dt>Change Description</dt><dd>{{ $p->change_description ?: '—' }}</dd>
  @if($p->completed_at)
    <dt>Completed</dt><dd>{{ $p->completed_at->format('m/d/Y H:i') }}{{ $p->completion_remarks ? ' · '.$p->completion_remarks : '' }}</dd>
    <dt>4MCN Required</dt><dd>{{ $p->requires_4mcn ? 'Yes' : 'No' }}{{ $p->fourMcn ? ' · '.$p->fourMcn->mcn_no : '' }}</dd>
  @endif
</dl>
