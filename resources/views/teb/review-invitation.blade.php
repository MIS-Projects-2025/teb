<div style="font-family:Segoe UI,Arial,sans-serif;font-size:14px;color:#1d2430">
  <p>You are invited to a <strong>TEB Review</strong>.</p>
  <table cellpadding="4" style="border-collapse:collapse">
    <tr><td><strong>When</strong></td><td>{{ $review->review_at->format('l, m/d/Y H:i') }} ({{ $review->duration_minutes }} min)</td></tr>
    <tr><td><strong>Where</strong></td><td>{{ $review->location ?: '—' }}</td></tr>
    <tr><td><strong>TEB No</strong></td><td>{{ $p->display_no }}</td></tr>
    <tr><td><strong>TEB Change Title</strong></td><td>{{ $p->title }}</td></tr>
    <tr><td><strong>Category</strong></td><td>{{ $p->category_label }}</td></tr>
    <tr><td><strong>Customer</strong></td><td>{{ $p->customer_name }}</td></tr>
    <tr><td><strong>Project Owner</strong></td><td>{{ $p->owner_name }} ({{ $p->owner_department }})</td></tr>
    <tr><td><strong>Classification</strong></td><td>{{ $p->classification_label }}</td></tr>
    <tr><td><strong>Type of Change</strong></td><td>{{ $p->types_of_change_label }}</td></tr>
    <tr><td><strong>Affected Areas</strong></td><td>{{ $p->affected_areas_label }}</td></tr>
    <tr><td valign="top"><strong>Project Description</strong></td><td>{!! nl2br(e($p->project_description)) !!}</td></tr>
    @if($review->notes)<tr><td valign="top"><strong>Notes</strong></td><td>{!! nl2br(e($review->notes)) !!}</td></tr>@endif
  </table>
  <p><a href="{{ route('teb.projects.show', $p) }}">Open in the TEB Portal</a> · the calendar invite is attached.</p>
</div>
