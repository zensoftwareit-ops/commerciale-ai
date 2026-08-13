<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Commerciale AI')</title>
    <style>
        :root{font-family:Inter,ui-sans-serif,system-ui,sans-serif;color:#172033;background:#f4f6f8}*{box-sizing:border-box}body{margin:0}a{color:#2457d6;text-decoration:none}.top{background:#111827;color:white;padding:1rem 2rem;display:flex;justify-content:space-between;align-items:center}.top a{color:white}.brand{font-weight:800}.container{max-width:1180px;margin:2rem auto;padding:0 1rem}.card{background:white;border:1px solid #e3e8ef;border-radius:14px;padding:1.25rem;box-shadow:0 4px 18px #1118270a}.grid{display:grid;gap:1rem}.grid-2{grid-template-columns:repeat(auto-fit,minmax(280px,1fr))}.toolbar{display:flex;gap:.75rem;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:1rem}.btn{display:inline-block;border:0;border-radius:9px;padding:.7rem 1rem;background:#2457d6;color:white;font-weight:700;cursor:pointer}.btn-muted{background:#e8edf5;color:#172033}input,select,textarea{width:100%;padding:.7rem;border:1px solid #cad2df;border-radius:8px;background:white}label{display:block;font-weight:700;margin:.8rem 0 .35rem}.error{color:#b42318}.notice{padding:.8rem 1rem;border-radius:8px;background:#e8f7ee;color:#176b3a;margin-bottom:1rem}.warning{padding:.8rem 1rem;border-radius:8px;background:#fff3d6;color:#8a4b08;margin-bottom:1rem}.email-preview{white-space:normal;border:1px solid #e3e8ef;background:#fafbfc;border-radius:8px;padding:1rem;line-height:1.55;margin:1rem 0}table{border-collapse:collapse;width:100%}th,td{text-align:left;padding:.8rem;border-bottom:1px solid #e8edf3}th{font-size:.78rem;text-transform:uppercase;color:#667085}.badge{display:inline-block;padding:.2rem .55rem;border-radius:999px;background:#e8edf5;font-size:.8rem}.hot{background:#fee4e2;color:#b42318}.warm{background:#fff3d6;color:#8a4b08}.timeline{border-left:2px solid #d9e0e9;padding-left:1rem}.event{margin:0 0 1.25rem}.muted{color:#667085;font-size:.9rem}.auth{max-width:430px;margin:8vh auto}.stat{font-size:1.8rem;font-weight:800}h1{margin-top:0}@media(max-width:700px){.top{padding:1rem}table{display:block;overflow:auto}}
    </style>
</head>
<body>
@auth
    @php($activeOrganization=app(\App\Support\Tenancy\TenantContext::class)->organization())
    @php($activeRole=auth()->user()->roleFor($activeOrganization))
    @php($pendingInboundCount=in_array($activeRole, ['owner', 'sales'], true) ? \App\Models\InboundEmail::query()->where('status', 'pending')->count() : 0)
    <header class="top"><div><a class="brand" href="{{ route('leads.index') }}">Commerciale AI</a> <a style="margin-left:1rem" href="{{ route('knowledge.index') }}">Knowledge base</a> @if(in_array($activeRole, ['owner', 'sales'], true))<a style="margin-left:1rem" href="{{ route('inbound-emails.index') }}">Email da associare @if($pendingInboundCount)<span class="badge warm">{{ $pendingInboundCount }}</span>@endif</a>@endif @if($activeRole==='owner')<a style="margin-left:1rem" href="{{ route('settings.organization') }}">Azienda</a><a style="margin-left:1rem" href="{{ route('settings.sources') }}">Sorgenti</a>@endif</div><div><a href="{{ route('account.edit') }}">{{ auth()->user()->name }}</a> · {{ $activeOrganization?->name }} <form method="post" action="{{ route('logout') }}" style="display:inline">@csrf <button class="btn btn-muted" type="submit">Esci</button></form></div></header>
@endauth
<main class="container">
    @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
    @yield('content')
</main>
</body>
</html>
