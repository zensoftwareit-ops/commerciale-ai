<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#17233c">
    <link rel="icon" type="image/svg+xml" href="{{ asset('brand/daria-mark.svg') }}">
    <title>@yield('title', 'Admin · Daria')</title>
    <style>
        :root{font-family:Manrope,Inter,system-ui,sans-serif;color:#17233c;background:#f8f7f5;--ink:#17233c;--coral:#ff6b5e;--coral-dark:#e9564d;--mint:#45d6b5;--cream:#fff9f4;--slate:#58647a;--cloud:#edf1f5}
        *{box-sizing:border-box}body{margin:0}header{background:var(--ink);color:#fff;padding:14px 28px;display:flex;justify-content:space-between;align-items:center;gap:24px}header img{display:block;width:124px;height:auto}header a{color:#fff9f4;text-decoration:none}header nav{display:flex;align-items:center;gap:14px}header form{display:inline}header button{background:transparent!important;padding:0!important;color:#fff9f4!important}main{max-width:1450px;margin:auto;padding:30px}.toolbar{display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.card{background:#fff;border:1px solid var(--cloud);border-radius:14px;padding:20px;margin-bottom:18px;box-shadow:0 2px 10px rgb(23 35 60 / 8%)}h1,h2,h3{margin-top:0;color:var(--ink)}label{display:block;font-weight:650;font-size:13px;margin:10px 0 5px}input,select,textarea{width:100%;padding:9px 11px;border:1px solid #d0d5dd;border-radius:8px}input:focus,select:focus,textarea:focus{outline:0;border-color:var(--coral);box-shadow:0 0 0 3px rgb(255 107 94 / 18%)}button,.btn{border:0;border-radius:14px;background:var(--coral);color:var(--ink);padding:10px 14px;font-weight:750;cursor:pointer}.muted{color:var(--slate);font-size:13px}.badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#fff0ed;color:#a7352c;font-size:11px;font-weight:800}.error{background:#fff0f2;color:#c83f4d;padding:10px;border-radius:8px}.notice{background:#eaf9f5;color:#16866f;padding:10px;border-radius:8px;margin-bottom:15px}.table{overflow:auto}table{border-collapse:collapse;width:100%;min-width:1000px}th,td{padding:11px;border-bottom:1px solid var(--cloud);text-align:left;vertical-align:top}th{font-size:11px;color:var(--slate);background:#fafbfc}@media(max-width:900px){.grid{grid-template-columns:1fr}main{padding:18px}header{padding:13px 18px}header nav{gap:8px;font-size:12px}}
    </style>
</head>
<body>
<header>
    <a href="{{ route('admin.licensing') }}"><img src="{{ asset('brand/daria-logo-white.svg') }}" alt="Daria"></a>
    <nav><span>Super Admin</span><a href="{{ route('admin.organizations.index') }}">Clienti</a><a href="{{ route('admin.licensing') }}">Licenze</a><a href="{{ route('admin.account.edit') }}">Account</a><form method="post" action="{{ route('logout') }}">@csrf<button>Esci</button></form></nav>
</header>
<main>
    @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
    @foreach($errors->all() as $error)<div class="error">{{ $error }}</div>@endforeach
    @yield('content')
</main>
</body>
</html>
