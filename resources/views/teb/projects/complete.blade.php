@extends('teb.layout')
@section('title', 'Complete TEB Form')
@section('content')
<h1>Complete TEB Form · {{ $p->teb_no }}</h1>
<p class="muted">Upload the TEB Report and confirm completion. The portal then generates the TEB form with Completed status and routes it for approval.</p>
<form method="post" enctype="multipart/form-data" action="{{ route('teb.projects.complete.store', $p) }}">
  @csrf
  <div class="card grid" style="max-width:720px">
    <div>
      <label for="teb_report">TEB Report</label>
      <input type="file" id="teb_report" name="teb_report">
      @if($p->attachmentsOf('teb_report')->isNotEmpty())<div class="small muted">On file: {{ $p->attachmentsOf('teb_report')->pluck('original_name')->implode(', ') }}</div>@endif
    </div>
    <div>
      <label>Does this change need a 4M Change Notice (4MCN)?</label>
      <div class="checks">
        <label><input type="radio" name="requires_4mcn" value="1" @checked(old('requires_4mcn', $p->requires_4mcn) === true || old('requires_4mcn') === '1') required> Yes, generate a 4MCN after approval</label>
        <label><input type="radio" name="requires_4mcn" value="0" @checked(old('requires_4mcn', $p->requires_4mcn) === false || old('requires_4mcn') === '0')> No</label>
      </div>
    </div>
    <div><label for="completion_remarks">Completion remarks</label><textarea id="completion_remarks" name="completion_remarks">{{ old('completion_remarks', $p->completion_remarks) }}</textarea></div>
  </div>
  <div class="row">
    <button class="btn">Complete and route for approval</button>
    <a class="btn ghost" href="{{ route('teb.projects.show', $p) }}">Back</a>
  </div>
</form>
@endsection
