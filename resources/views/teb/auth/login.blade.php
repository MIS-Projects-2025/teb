@extends('teb.layout')
@section('title', 'Log in')
@section('content')
<div style="max-width:380px;margin:60px auto">
  <div class="card">
    <h1 style="margin-bottom:4px">Telford Engineering Board</h1>
    <p class="muted" style="margin-top:0">TEB Portal · log in with your masterlist account</p>
    <form method="post" action="{{ route('teb.login.attempt') }}" class="grid">
      @csrf
      <div><label for="username">Username</label><input type="text" id="username" name="username" value="{{ old('username') }}" required autofocus></div>
      <div><label for="password">Password</label><input type="password" id="password" name="password" required></div>
      <label class="row" style="font-weight:400"><input type="checkbox" name="remember" value="1"> Remember me</label>
      <button class="btn">Log in</button>
    </form>
  </div>
</div>
@endsection
