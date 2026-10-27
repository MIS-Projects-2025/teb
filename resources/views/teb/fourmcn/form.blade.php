@extends('teb.layout')
@section('title', 'Generate 4MCN Form')
@section('content')
@php($items = old('change_items', []))
<h1>Generate 4MCN Form · {{ $p->teb_no }}</h1>
<form method="post" action="{{ route('teb.fourmcn.store', $p) }}">
  @csrf
  <div class="card grid" style="max-width:860px">
    <div><label>4M item(s) changed</label>
      <div class="checks">@foreach(config('teb.4m_items') as $k => $v)<label><input type="checkbox" name="change_items[]" value="{{ $k }}" @checked(in_array($k, $items, true))> {{ $v }}</label>@endforeach</div></div>
    <div class="grid g2">
      <div><label for="before_change">Before change</label><textarea id="before_change" name="before_change" required>{{ old('before_change', $p->current_status) }}</textarea></div>
      <div><label for="after_change">After change</label><textarea id="after_change" name="after_change" required>{{ old('after_change', $p->change_description) }}</textarea></div>
    </div>
    <div><label for="reason">Reason for change</label><textarea id="reason" name="reason" required>{{ old('reason', $p->project_description) }}</textarea></div>
    <div class="grid g2">
      <div><label for="effectivity_date">Effectivity date</label><input type="date" id="effectivity_date" name="effectivity_date" value="{{ old('effectivity_date') }}" required></div>
      <div><label>Customer notification required?</label>
        <div class="checks"><label><input type="radio" name="customer_notification" value="1" @checked(old('customer_notification') === '1') required> Yes</label>
          <label><input type="radio" name="customer_notification" value="0" @checked(old('customer_notification') === '0')> No</label></div></div>
    </div>
  </div>
  <div class="row"><button class="btn">Generate 4MCN</button><a class="btn ghost" href="{{ route('teb.projects.show', $p) }}">Back</a></div>
</form>
@endsection
