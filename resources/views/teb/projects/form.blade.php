@extends('teb.layout')
@section('title', $p->exists ? 'Edit Project Entry' : 'Register Project Entry')
@section('content')
@php($types = old('types_of_change', $p->types_of_change ?? []))
@php($areas = array_map('intval', old('affected_areas', $selected)))
<h1>{{ $p->exists ? 'Edit Project Entry' : 'Register Project Entry' }}</h1>
<form method="post" enctype="multipart/form-data" action="{{ $p->exists ? route('teb.projects.update', $p) : route('teb.projects.store') }}">
  @csrf
  @if($p->exists) @method('PUT') @endif
  <div class="card grid">
    <div class="grid g2">
      <div><label for="title">TEB Change Title</label><input type="text" id="title" name="title" value="{{ old('title', $p->title) }}" required></div>
      <div><label>TEB No</label><input type="text" value="{{ $p->teb_no ?: 'yyyy-xxxxxxxxx (assigned by TEB Admin after review)' }}" disabled></div>
    </div>
    <div class="grid g3">
      <div><label for="category">Category</label>
        <select id="category" name="category" required>
          <option value="">Select…</option>
          @foreach(config('teb.categories') as $k => $v)<option value="{{ $k }}" @selected(old('category', $p->category) === $k)>{{ $v }}</option>@endforeach
        </select></div>
      <div><label for="customer_id">Customer</label>
        <select id="customer_id" name="customer_id">
          <option value="">Select…</option>
          @foreach($customers as $id => $name)<option value="{{ $id }}" @selected((string) old('customer_id', $p->customer_id) === (string) $id)>{{ $name }}</option>@endforeach
        </select>
        @if(empty($customers))<div class="err">Customer list (qdn_db.customer_list) could not be loaded.</div>@endif</div>
      <div><label>Date Created</label><input type="text" value="{{ ($p->created_at ?? now())->format('m/d/Y') }}" disabled></div>
    </div>
    <div class="grid g2">
      <div><label>Change Classification</label>
        <div class="checks">
          @foreach(config('teb.classifications') as $k => $v)
            <label><input type="radio" name="classification" value="{{ $k }}" @checked(old('classification', $p->classification) === $k) required onchange="document.getElementById('fmea-box').style.display = this.value === 'major' ? 'block' : 'none'"> {{ $v }}</label>
          @endforeach
        </div>
        <div id="fmea-box" style="margin-top:8px;display:{{ old('classification', $p->classification) === 'major' ? 'block' : 'none' }}">
          <label for="fmea">FMEA document (required for Major)</label>
          <input type="file" id="fmea" name="fmea">
          @if($p->exists && $p->attachmentsOf('fmea')->isNotEmpty())<div class="small muted">On file: {{ $p->attachmentsOf('fmea')->pluck('original_name')->implode(', ') }}</div>@endif
        </div>
      </div>
      <div><label>Type of Change</label>
        <div class="checks">
          @foreach(config('teb.types_of_change') as $k => $v)
            <label><input type="checkbox" name="types_of_change[]" value="{{ $k }}" @checked(in_array($k, $types, true))> {{ $v }}</label>
          @endforeach
        </div></div>
    </div>
    <div><label>Affected Areas (department)</label>
      <div class="multi">
        @foreach($departments as $id => $name)
          <label><input type="checkbox" name="affected_areas[]" value="{{ $id }}" @checked(in_array((int) $id, $areas, true))> {{ $name }}</label>
        @endforeach
      </div></div>
    <div class="grid g2">
      <div><label>Project Owner</label><input type="text" value="{{ $ownerName }}" disabled></div>
      <div><label>Department</label><input type="text" value="{{ $ownerDept }}" disabled></div>
    </div>
    <div><label for="project_description">Project Description</label><textarea id="project_description" name="project_description" required>{{ old('project_description', $p->project_description) }}</textarea></div>
    <div class="grid g2">
      <div><label for="current_status">Current Status</label><textarea id="current_status" name="current_status">{{ old('current_status', $p->current_status) }}</textarea></div>
      <div><label for="change_description">Change Description</label><textarea id="change_description" name="change_description">{{ old('change_description', $p->change_description) }}</textarea></div>
    </div>
    <div><label for="attachments">Other supporting documents</label><input type="file" id="attachments" name="attachments[]" multiple></div>
  </div>
  <div class="row">
    <button class="btn">{{ $p->exists ? 'Save changes' : 'Submit for review' }}</button>
    <a class="btn ghost" href="{{ $p->exists ? route('teb.projects.show', $p) : route('teb.dashboard') }}">Back</a>
  </div>
</form>
@endsection
