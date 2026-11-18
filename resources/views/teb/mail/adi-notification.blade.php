<div style="font-family:Segoe UI,Arial,sans-serif;font-size:14px;color:#1d2430">
  <p>Telford submits the following <strong>TEB</strong> to the ADI Oversight Team for review and approval.</p>
  <table cellpadding="4" style="border-collapse:collapse">
    <tr>
      <td><strong>Presentation</strong></td>
      <td>{{ $presentationAt ? \Illuminate\Support\Carbon::parse($presentationAt)->format('l, m/d/Y H:i') : 'To be scheduled' }}</td>
    </tr>
    <tr>
      <td><strong>Where</strong></td>
      <td>{{ $location ?: '—' }}</td>
    </tr>
    <tr>
      <td><strong>TRB No</strong></td>
      <td>{{ $p->display_no }}</td>
    </tr>
    <tr>
      <td><strong>TRB Change Title</strong></td>
      <td>{{ $p->title }}</td>
    </tr>
    <tr>
      <td><strong>Customer</strong></td>
      <td>{{ $p->customer_name }}</td>
    </tr>
    <tr>
      <td><strong>Classification</strong></td>
      <td>{{ $p->classification_label }}</td>
    </tr>
    <tr>
      <td><strong>Type of Change</strong></td>
      <td>{{ $p->types_of_change_label }}</td>
    </tr>
    <tr>
      <td><strong>Project Owner</strong></td>
      <td>{{ $p->owner_name }} ({{ $p->owner_department }})</td>
    </tr>
    <tr>
      <td valign="top"><strong>Change Description</strong></td>
      <td>{!! nl2br(e($p->change_description ?: $p->project_description)) !!}</td>
    </tr>
    @if($notes)<tr>
      <td valign="top"><strong>Notes</strong></td>
      <td>{!! nl2br(e($notes)) !!}</td>
    </tr>@endif
  </table>
  <p><a href="{{ route('teb.projects.show', $p) }}">Open in the TRB Portal</a></p>
</div>