<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#111827">
    <title>@yield('title', 'Commerciale AI')</title>
    <style>
        :root{
            font-family:Inter,ui-sans-serif,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
            color:#172033;background:#f6f7fb;font-synthesis:none;
            --ink:#172033;--muted:#667085;--line:#e4e7ec;--surface:#fff;--soft:#f8fafc;
            --brand:#3157d5;--brand-dark:#2442aa;--brand-soft:#eef2ff;--nav:#111827;
            --green:#067647;--green-soft:#ecfdf3;--amber:#b54708;--amber-soft:#fffaeb;
            --red:#b42318;--red-soft:#fef3f2;--shadow:0 1px 2px rgba(16,24,40,.04),0 8px 24px rgba(16,24,40,.05)
        }
        *{box-sizing:border-box}html{min-height:100%}body{margin:0;min-height:100vh;background:#f6f7fb;color:var(--ink);font-size:14px;line-height:1.5}
        a{color:var(--brand);text-decoration:none}a:hover{color:var(--brand-dark)}button,input,select,textarea{font:inherit}
        .app-shell{min-height:100vh}.sidebar{position:fixed;inset:0 auto 0 0;width:256px;background:var(--nav);color:#d0d5dd;padding:24px 16px;display:flex;flex-direction:column;z-index:30}
        .brand-lockup{display:flex;align-items:center;gap:11px;padding:0 8px 22px;color:white}.brand-lockup:hover{color:white}.brand-mark{width:34px;height:34px;border-radius:10px;background:linear-gradient(145deg,#6784ed,#3157d5);display:grid;place-items:center;box-shadow:0 8px 20px rgba(49,87,213,.3);font-weight:800}.brand-copy{line-height:1.15}.brand-copy strong{display:block;font-size:15px;letter-spacing:-.01em}.brand-copy small{color:#98a2b3;font-size:11px}
        .org-chip{margin:0 4px 20px;padding:10px 12px;border:1px solid #344054;border-radius:10px;background:#1d2939}.org-chip span{display:block;color:#98a2b3;font-size:10px;text-transform:uppercase;letter-spacing:.08em}.org-chip strong{display:block;color:#f2f4f7;font-size:13px;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .nav-label{padding:0 12px;margin:9px 0 6px;color:#667085;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em}.nav{display:grid;gap:3px}.nav-link{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:9px;color:#b8c0ce;font-weight:600}.nav-link:hover{background:#1d2939;color:white}.nav-link.active{background:#263455;color:white;box-shadow:inset 3px 0 #7590f4}.nav-icon{width:19px;height:19px;display:grid;place-items:center;color:currentColor}.nav-icon svg{width:19px;height:19px;stroke:currentColor}.nav-count{margin-left:auto;min-width:20px;height:20px;padding:0 6px;border-radius:10px;background:#fdb022;color:#111827;display:grid;place-items:center;font-size:11px;font-weight:800}
        .sidebar-footer{margin-top:auto;padding-top:16px;border-top:1px solid #344054}.user-card{display:flex;align-items:center;gap:10px;padding:8px}.avatar{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#344054;color:#f2f4f7;font-weight:700}.user-meta{min-width:0;flex:1}.user-meta a{display:block;color:#f2f4f7;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.user-meta small{color:#98a2b3}.logout-button{border:0;background:transparent;color:#98a2b3;cursor:pointer;padding:7px;border-radius:7px}.logout-button:hover{background:#344054;color:white}
        .workspace{margin-left:256px;min-height:100vh}.mobile-topbar{display:none}.container{max-width:1440px;margin:0 auto;padding:36px 36px 64px}
        h1,h2,h3{color:#101828;letter-spacing:-.025em}h1{font-size:28px;line-height:1.2;margin:0 0 5px}h2{font-size:18px;margin:0 0 14px}h3{font-size:15px;margin:22px 0 8px}p{margin:.5rem 0 1rem}.page-kicker{color:var(--brand);font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;margin-bottom:7px}
        .toolbar{display:flex;gap:16px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:22px}.toolbar h1,.toolbar h2{margin-bottom:4px}.toolbar>div{min-width:0}
        .card{background:var(--surface);border:1px solid var(--line);border-radius:13px;padding:22px;box-shadow:var(--shadow)}.grid{display:grid;gap:16px}.grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;border:1px solid transparent;border-radius:8px;min-height:40px;padding:9px 15px;background:var(--brand);color:white;font-weight:700;line-height:1;cursor:pointer;box-shadow:0 1px 2px rgba(16,24,40,.08);transition:.16s ease}.btn:hover{background:var(--brand-dark);color:white;transform:translateY(-1px)}.btn:active{transform:none}.btn-muted{background:white;color:#344054;border-color:#d0d5dd}.btn-muted:hover{background:#f9fafb;color:#101828}.btn-danger{background:#d92d20}.btn-danger:hover{background:#b42318}
        label{display:block;color:#344054;font-size:13px;font-weight:650;margin:15px 0 6px}input,select,textarea{width:100%;padding:10px 12px;border:1px solid #d0d5dd;border-radius:8px;background:white;color:#101828;outline:0;box-shadow:0 1px 2px rgba(16,24,40,.03);transition:border-color .15s,box-shadow .15s}input:hover,select:hover,textarea:hover{border-color:#98a2b3}input:focus,select:focus,textarea:focus{border-color:#617be5;box-shadow:0 0 0 3px rgba(49,87,213,.12)}textarea{line-height:1.55;resize:vertical}input[type=checkbox]{accent-color:var(--brand)}
        .error,.notice,.warning{padding:11px 13px;border-radius:9px;margin:10px 0;border:1px solid}.error{color:var(--red);background:var(--red-soft);border-color:#fecdca}.notice{color:var(--green);background:var(--green-soft);border-color:#abefc6}.warning{color:var(--amber);background:var(--amber-soft);border-color:#fedf89}
        .flash{display:flex;align-items:center;gap:10px;margin-bottom:18px}.flash:before{content:'✓';width:20px;height:20px;border-radius:50%;display:grid;place-items:center;background:#12b76a;color:white;font-size:11px;font-weight:900}
        .muted{color:var(--muted);font-size:13px}.stat{font-size:28px;line-height:1.15;font-weight:750;letter-spacing:-.04em;color:#101828}.badge{display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:999px;background:#f2f4f7;color:#475467;border:1px solid #eaecf0;font-size:11px;font-weight:750;line-height:1.2;text-transform:uppercase;letter-spacing:.025em}.badge.hot,.hot{background:var(--red-soft);color:var(--red);border-color:#fecdca}.badge.warm,.warm{background:var(--amber-soft);color:var(--amber);border-color:#fedf89}.badge.cold{background:#eff8ff;color:#175cd3;border-color:#b2ddff}.badge[data-status="sent"],.badge.success{background:var(--green-soft);color:var(--green);border-color:#abefc6}
        .table-wrap{overflow:auto;margin:0 -1px}table{border-collapse:separate;border-spacing:0;width:100%}th,td{text-align:left;padding:13px 14px;border-bottom:1px solid #eaecf0;vertical-align:middle}th{background:#f9fafb;color:#667085;font-size:10px;text-transform:uppercase;letter-spacing:.07em;font-weight:750;white-space:nowrap}thead th:first-child{border-radius:8px 0 0 0}thead th:last-child{border-radius:0 8px 0 0}tbody tr{transition:background .12s}tbody tr:hover{background:#fafbff}tbody tr:last-child td{border-bottom:0}td strong{color:#101828}
        .email-preview{white-space:normal;border:1px solid #e4e7ec;background:#fbfcfe;border-radius:10px;padding:18px;line-height:1.65;margin:14px 0}.timeline{border-left:1px solid #d0d5dd;padding-left:20px;margin-left:5px}.event{position:relative;margin:0 0 22px}.event:before{content:'';position:absolute;left:-25px;top:5px;width:8px;height:8px;border-radius:50%;background:#617be5;border:3px solid #eef2ff;box-sizing:content-box}
        hr{margin:28px 0!important;border:0!important;border-top:1px solid #eaecf0!important}code{background:#f2f4f7;border:1px solid #eaecf0;border-radius:5px;padding:2px 5px;color:#344054}.auth-shell{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;padding:24px;background:radial-gradient(circle at 20% 20%,#eef2ff 0,transparent 34%),#f7f8fc}.auth{width:100%;max-width:430px;margin:0}.auth.card{padding:32px}.auth:before{content:'CA';display:grid;place-items:center;width:42px;height:42px;border-radius:12px;background:linear-gradient(145deg,#6784ed,#3157d5);color:white;font-weight:800;margin-bottom:22px;box-shadow:0 10px 24px rgba(49,87,213,.24)}
        nav[role=navigation]{margin-top:14px;color:#667085;font-size:12px}nav[role=navigation]>div:first-child{display:none}nav[role=navigation]>div:last-child{display:flex;align-items:center;justify-content:space-between;gap:14px}nav[role=navigation] p{margin:0}nav[role=navigation] svg{width:16px;height:16px}nav[role=navigation] a,nav[role=navigation] span[aria-current=page]>span{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 9px;border:1px solid #d0d5dd;background:white;color:#344054}nav[role=navigation] a:hover{background:#f9fafb}nav[role=navigation] span[aria-current=page]>span{background:var(--brand-soft);border-color:#c7d2fe;color:var(--brand);font-weight:700}
        .metric-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:18px}.metric{padding:17px 18px;background:white;border:1px solid var(--line);border-radius:11px;box-shadow:0 1px 2px rgba(16,24,40,.03)}.metric-label{color:#667085;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em}.metric-value{font-size:23px;font-weight:760;color:#101828;margin-top:5px}.metric-detail{color:#667085;font-size:12px;margin-top:2px}.setup-card{position:relative;overflow:hidden}.setup-card:before{content:'';position:absolute;inset:0 auto 0 0;width:4px;background:var(--brand)}.check-list{display:flex;gap:7px;flex-wrap:wrap;margin-top:13px}.filter-bar{display:grid;grid-template-columns:minmax(180px,240px) minmax(220px,1fr) auto;gap:12px;align-items:end}.filter-bar label{margin-top:0}.table-card{padding:8px}.table-card .pagination{padding:12px 14px}.lead-heading{display:flex;align-items:center;gap:14px}.lead-avatar{width:48px;height:48px;border-radius:12px;background:var(--brand-soft);color:var(--brand);display:grid;place-items:center;font-size:17px;font-weight:800}.contact-line{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.contact-line a{color:#475467}.back-link{display:inline-flex;align-items:center;gap:5px;color:#667085;font-weight:600;font-size:12px;margin-bottom:12px}.back-link:hover{color:var(--brand)}
        @media(max-width:1050px){.sidebar{width:224px}.workspace{margin-left:224px}.container{padding:28px 24px}.grid-2{grid-template-columns:1fr}.lead-table th:nth-child(2),.lead-table td:nth-child(2),.lead-table th:nth-child(6),.lead-table td:nth-child(6){display:none}}
        @media(max-width:760px){.sidebar{transform:translateX(-100%);transition:transform .2s ease;width:270px}.nav-open .sidebar{transform:translateX(0);box-shadow:20px 0 60px rgba(16,24,40,.3)}.workspace{margin-left:0}.mobile-topbar{height:60px;padding:0 17px;background:white;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:20}.menu-button{border:0;background:#f2f4f7;border-radius:8px;width:36px;height:36px;color:#344054;font-size:20px}.container{padding:22px 16px 48px}.metric-grid{grid-template-columns:1fr}.filter-bar{grid-template-columns:1fr}.card{padding:17px}.toolbar{align-items:flex-start}.toolbar>.btn{width:100%}h1{font-size:24px}.table-card{padding:0;border-radius:11px}.lead-table{min-width:760px}.auth.card{padding:24px}.nav-open:after{content:'';position:fixed;inset:0;background:rgba(16,24,40,.45);z-index:25}}
    </style>
    @stack('styles')
</head>
<body>
@auth
    @php($activeOrganization=app(\App\Support\Tenancy\TenantContext::class)->organization())
    @php($activeRole=auth()->user()->roleFor($activeOrganization))
    @php($pendingInboundCount=in_array($activeRole, ['owner', 'sales'], true) ? \App\Models\InboundEmail::query()->where('status', 'pending')->count() : 0)
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <a class="brand-lockup" href="{{ route('leads.index') }}">
                <span class="brand-mark">CA</span>
                <span class="brand-copy"><strong>Commerciale AI</strong><small>Sales workspace</small></span>
            </a>
            <div class="org-chip"><span>Workspace</span><strong>{{ $activeOrganization?->name }}</strong></div>
            <div class="nav-label">Operatività</div>
            <nav class="nav" aria-label="Navigazione principale">
                <a class="nav-link @if(request()->routeIs('leads.*')) active @endif" href="{{ route('leads.index') }}"><span class="nav-icon"><svg fill="none" viewBox="0 0 24 24"><path d="M4 7.5h16M4 12h16M4 16.5h10" stroke-width="1.8" stroke-linecap="round"/></svg></span>Lead inbox</a>
                <a class="nav-link @if(request()->routeIs('knowledge.*')) active @endif" href="{{ route('knowledge.index') }}"><span class="nav-icon"><svg fill="none" viewBox="0 0 24 24"><path d="M5 4.5h10a3 3 0 0 1 3 3v12H8a3 3 0 0 1-3-3v-12Z" stroke-width="1.8"/><path d="M8 8h6M8 12h6" stroke-width="1.8" stroke-linecap="round"/></svg></span>Knowledge base</a>
                @if(in_array($activeRole, ['owner', 'sales'], true))
                    <a class="nav-link @if(request()->routeIs('inbound-emails.*')) active @endif" href="{{ route('inbound-emails.index') }}"><span class="nav-icon"><svg fill="none" viewBox="0 0 24 24"><path d="M4 6.5h16v11H4z" stroke-width="1.8"/><path d="m5 8 7 5 7-5" stroke-width="1.8" stroke-linecap="round"/></svg></span>Email da associare @if($pendingInboundCount)<span class="nav-count">{{ $pendingInboundCount }}</span>@endif</a>
                @endif
            </nav>
            @if($activeRole==='owner')
                <div class="nav-label">Configurazione</div>
                <nav class="nav" aria-label="Configurazione">
                    <a class="nav-link @if(request()->routeIs('settings.organization*') || request()->routeIs('settings.pricing-rules.*')) active @endif" href="{{ route('settings.organization') }}"><span class="nav-icon"><svg fill="none" viewBox="0 0 24 24"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" stroke-width="1.8"/><path d="M19 13.5v-3l-2-.7-.7-1.6.9-2-2.1-2.1-2 .9-1.6-.7-.7-2h-3l-.7 2-1.6.7-2-.9-2.1 2.1.9 2-.7 1.6-2 .7v3l2 .7.7 1.6-.9 2 2.1 2.1 2-.9 1.6.7.7 2h3l.7-2 1.6-.7 2 .9 2.1-2.1-.9-2 .7-1.6 2-.7Z" stroke-width="1.5"/></svg></span>Azienda e AI</a>
                    <a class="nav-link @if(request()->routeIs('settings.sources*')) active @endif" href="{{ route('settings.sources') }}"><span class="nav-icon"><svg fill="none" viewBox="0 0 24 24"><path d="M8 12h8M12 8v8" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="12" r="8.5" stroke-width="1.8"/></svg></span>Sorgenti lead</a>
                </nav>
            @endif
            <div class="sidebar-footer">
                <div class="user-card">
                    <span class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</span>
                    <span class="user-meta"><a href="{{ route('account.edit') }}">{{ auth()->user()->name }}</a><small>{{ ucfirst($activeRole) }}</small></span>
                    <form method="post" action="{{ route('logout') }}">@csrf<button class="logout-button" type="submit" title="Esci" aria-label="Esci">↗</button></form>
                </div>
            </div>
        </aside>
        <div class="workspace">
            <header class="mobile-topbar"><a class="brand-lockup" style="padding:0;color:#101828" href="{{ route('leads.index') }}"><span class="brand-mark">CA</span><span class="brand-copy"><strong>Commerciale AI</strong></span></a><button class="menu-button" type="button" onclick="document.body.classList.toggle('nav-open')" aria-label="Apri menu">☰</button></header>
            <main class="container">
                @if(session('status'))<div class="notice flash">{{ session('status') }}</div>@endif
                @yield('content')
            </main>
        </div>
    </div>
@else
    <main class="auth-shell">@if(session('status'))<div class="notice flash">{{ session('status') }}</div>@endif @yield('content')</main>
@endauth
</body>
</html>

