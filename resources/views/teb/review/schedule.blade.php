@extends('teb.layout')
@section('title', 'Schedule TEB Review')
@section('content')
<h1>Schedule TEB Review</h1>
<div class="grid g2" style="grid-template-columns:minmax(0,1fr) minmax(0,1fr)">
  <div class="card"><h2>Registered project</h2>@include('teb.partials.details', ['p' => $p])</div>
  <form method="post" action="{{ route('teb.review.schedule.store', $p) }}" class="card grid" style="align-content:start">
    @csrf
    <h2>Calendar invite</h2>
    <div class="grid g2">
      <div><label for="review_at">Date and time</label><input type="datetime-local" id="review_at" name="review_at" value="{{ old('review_at') }}" required></div>
      <div><label for="duration_minutes">Duration (minutes)</label><input type="number" id="duration_minutes" name="duration_minutes" value="{{ old('duration_minutes', 60) }}" min="15" max="480" required></div>
    </div>
    <div><label for="location">Location / meeting link</label><input type="text" id="location" name="location" value="{{ old('location') }}"></div>
    <div>
      <label>Reviewees</label>
      <input type="text" placeholder="Filter by name…" oninput="const q=this.value.toLowerCase();document.querySelectorAll('#reviewees label').forEach(l=>l.style.display=l.textContent.toLowerCase().includes(q)?'':'none')" style="margin-bottom:6px">
      <div class="multi" id="reviewees" style="max-height:260px">
        @foreach($employees as $e)
          <label><input type="checkbox" name="reviewees[]" value="{{ $e->employid }}" @checked(in_array((string) $e->employid, old('reviewees', []), true))> {{ $e->lastname }}, {{ $e->firstname }}</label>
        @endforeach
      </div>
      <div class="small muted">The project owner is always invited. Each invite carries the project details and an .ics calendar file.</div>
    </div>
    <div><label for="notes">Notes to reviewees</label><textarea id="notes" name="notes">{{ old('notes') }}</textarea></div>
    <div class="row">
      <button class="btn">Send invite</button>
      <a class="btn ghost" href="{{ route('teb.review.index') }}">Back to list</a>
    </div>
  </form>
</div>
@endsection
