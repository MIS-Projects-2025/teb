@php($tebUser = \App\Services\Teb\TebUser::current())
@php($tebAdmin = $tebUser && \App\Services\Teb\TebUser::isAdmin($tebUser))
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'TEB Portal') · Telford Engineering Board</title>
<style>
:root{--ink:#1d2430;--muted:#5d6878;--line:#d9dee6;--bg:#f4f6f9;--card:#fff;--brand:#0b4f8a;--brand-2:#e8f0f8;--ok:#1f7a4d;--warn:#a86400;--bad:#b42318;--chip:#eef1f5}
*{box-sizing:border-box}
body{margin:0;font:14px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:var(--ink);background:var(--bg)}
a{color:var(--brand);text-decoration:none}a:hover{text-decoration:underline}
header.top{background:var(--brand);color:#fff;padding:0 24px;display:flex;align-items:center;gap:24px;min-height:56px;flex-wrap:wrap}
header.top .brand{font-weight:700;letter-spacing:.2px;font-size:16px;color:#fff}
header.top nav{display:flex;gap:4px;flex-wrap:wrap;flex:1}
header.top nav a{color:#dce8f5;padding:8px 12px;border-radius:6px}
header.top nav a.on,header.top nav a:hover{background:rgba(255,255,255,.14);color:#fff;text-decoration:none}
header.top .who{font-size:13px;color:#dce8f5;display:flex;gap:10px;align-items:center}
header.top .who button{background:none;border:1px solid rgba(255,255,255,.4);color:#fff;border-radius:6px;padding:4px 10px;cursor:pointer}
main{max-width:1440px;margin:0 auto;padding:24px}
h1{font-size:22px;margin:0 0 16px}h2{font-size:16px;margin:0 0 12px}
.card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:20px;margin-bottom:20px}
.grid{display:grid;gap:16px}.g2{grid-template-columns:repeat(2,minmax(0,1fr))}.g3{grid-template-columns:repeat(3,minmax(0,1fr))}.g4{grid-template-columns:repeat(4,minmax(0,1fr))}
@media(max-width:820px){.g2,.g3,.g4{grid-template-columns:1fr}main{padding:16px}}
.row{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.spread{justify-content:space-between}
label{display:block;font-weight:600;font-size:13px;margin-bottom:4px}
input[type=text],input[type=date],input[type=datetime-local],input[type=number],input[type=password],input[type=file],select,textarea{width:100%;padding:8px 10px;border:1px solid var(--line);border-radius:6px;font:inherit;background:#fff}
textarea{min-height:96px;resize:vertical}
.checks{display:flex;flex-wrap:wrap;gap:6px 18px}.checks label{font-weight:400;display:flex;gap:6px;align-items:center;margin:0}
.multi{max-height:180px;overflow:auto;border:1px solid var(--line);border-radius:6px;padding:8px 10px;display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:4px 12px}
.multi label{font-weight:400;display:flex;gap:6px;align-items:center;margin:0}
.btn{display:inline-block;padding:8px 14px;border-radius:6px;border:1px solid var(--brand);background:var(--brand);color:#fff;font:inherit;font-weight:600;cursor:pointer}
.btn:hover{text-decoration:none;filter:brightness(1.08)}
.btn.ghost{background:#fff;color:var(--brand)}.btn.ok{background:var(--ok);border-color:var(--ok)}.btn.bad{background:var(--bad);border-color:var(--bad)}.btn.sm{padding:4px 10px;font-size:13px}
table{width:100%;border-collapse:collapse}th,td{padding:8px 10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}
th{font-size:12px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);background:#fafbfc}
.tbl{overflow-x:auto}
.chip{display:inline-block;padding:2px 8px;border-radius:999px;background:var(--chip);font-size:12px;font-weight:600;white-space:nowrap}
.s-submitted_for_review{background:#e8eefc;color:#2a4db3}.s-open{background:#e6f4ff;color:#0b5c9e}.s-hold{background:#fff3e0;color:var(--warn)}
.s-ongoing{background:#f0eaff;color:#5b3cc4}.s-pending_teb_number{background:#fff8d6;color:#7a5d00}.s-reviewed{background:#e3f6ee;color:var(--ok)}
.s-for_approval{background:#fde9f3;color:#a3236a}.s-returned{background:#fdecea;color:var(--bad)}.s-approved{background:#d9f2e4;color:#11603a}
.a-approved{color:var(--ok);font-weight:600}.a-rejected{color:var(--bad);font-weight:600}.a-pending{color:var(--muted)}
.muted{color:var(--muted)}.small{font-size:12px}.err{color:var(--bad);font-size:12px;margin-top:4px}
.flash{padding:10px 14px;border-radius:8px;margin-bottom:16px;background:#e3f6ee;color:#11603a;border:1px solid #b7e2cb}
.flash.bad{background:#fdecea;color:var(--bad);border-color:#f5c2bd}
.stat{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:14px 16px;display:block;color:inherit}
.stat:hover{text-decoration:none;border-color:var(--brand)}
.stat .n{font-size:28px;font-weight:700;font-variant-numeric:tabular-nums}.stat .l{color:var(--muted);font-size:13px}
.stat.total{background:var(--brand);color:#fff;border-color:var(--brand)}.stat.total .l{color:#dce8f5}
dl.kv{display:grid;grid-template-columns:200px 1fr;gap:8px 16px;margin:0}dl.kv dt{color:var(--muted);font-weight:600}dl.kv dd{margin:0;white-space:pre-wrap}
@media(max-width:820px){dl.kv{grid-template-columns:1fr}}
.timeline{list-style:none;padding:0;margin:0}.timeline li{padding:8px 0 8px 16px;border-left:2px solid var(--line);position:relative}
.timeline li:before{content:"";position:absolute;left:-6px;top:13px;width:10px;height:10px;border-radius:50%;background:var(--brand)}
.flow{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:20px}.flow span{padding:6px 10px;border-radius:6px;background:var(--chip);font-size:12px;color:var(--muted)}
.flow span.done{background:var(--brand-2);color:var(--brand)}.flow span.now{background:#fff;color:var(--brand);font-weight:600;box-shadow:inset 0 0 0 1.5px var(--brand)}
@media print{header.top,.noprint{display:none!important}body{background:#fff}main{padding:0}.card{border:0;padding:0}}
</style>
@stack('head')
</head>
<body>
@if($tebUser)
<header class="top noprint">
  <a class="brand" href="{{ route('teb.dashboard') }}">TEB Portal</a>
  <nav>
    <a href="{{ route('teb.dashboard') }}" class="{{ request()->routeIs('teb.dashboard') ? 'on' : '' }}">Dashboard</a>
    <a href="{{ route('teb.projects.create') }}" class="{{ request()->routeIs('teb.projects.create') ? 'on' : '' }}">Register Project</a>
    <a href="{{ route('teb.projects.index', ['mine' => 1]) }}" class="{{ request()->routeIs('teb.projects.index') && request('mine') ? 'on' : '' }}">My Projects</a>
    <a href="{{ route('teb.projects.index') }}" class="{{ request()->routeIs('teb.projects.index') && ! request('mine') ? 'on' : '' }}">TEB Register</a>
    @if($tebAdmin)<a href="{{ route('teb.review.index') }}" class="{{ request()->routeIs('teb.review.*') ? 'on' : '' }}">For Review</a>@endif
    <a href="{{ route('teb.approvals.index') }}" class="{{ request()->routeIs('teb.approvals.*') ? 'on' : '' }}">For Approval</a>
    @if($tebAdmin)<a href="{{ route('teb.settings') }}" class="{{ request()->routeIs('teb.settings*') ? 'on' : '' }}">Settings</a>@endif
  </nav>
  <div class="who">
    <span>{{ $tebUser->EMPNAME }}{{ $tebAdmin ? ' · TEB Admin' : '' }}</span>
    <form method="post" action="{{ route('teb.logout') }}">@csrf<button>Log out</button></form>
  </div>
</header>
@endif
<main>
  @if(session('success'))<div class="flash noprint">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="flash bad noprint">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
  @yield('content')
</main>
</body>
</html>
