{{-- $action, optional $statuses, $keep (hidden query params) --}}
<form method="get" action="{{ $action }}" class="row noprint" style="margin-bottom:16px">
  @foreach(($keep ?? []) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
  <input type="text" name="q" value="{{ request('q') }}" placeholder="Search TEB No, title, customer, owner" style="max-width:280px">
  <select name="category" style="max-width:280px">
    <option value="">All categories</option>
    @foreach(config('teb.categories') as $k => $v)<option value="{{ $k }}" @selected(request('category') === $k)>{{ $v }}</option>@endforeach
  </select>
  <select name="status" style="max-width:220px">
    <option value="">All statuses</option>
    @foreach(($statuses ?? array_keys(\App\Models\Teb\TebProject::STATUS_LABELS)) as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ \App\Models\Teb\TebProject::STATUS_LABELS[$s] }}</option>@endforeach
  </select>
  @if(! empty($dates))
    <input type="date" name="from" value="{{ request('from') }}" style="max-width:160px" title="Created from">
    <input type="date" name="to" value="{{ request('to') }}" style="max-width:160px" title="Created to">
  @endif
  <button class="btn ghost">Filter</button>
</form>
