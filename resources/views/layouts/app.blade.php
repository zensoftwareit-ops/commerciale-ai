<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#17233C">
    <link rel="icon" type="image/svg+xml" href="{{ asset('brand/daria-mark.svg') }}">
    <title>@yield('title', 'Daria')</title>
    <style>
        :root{
            font-family:Manrope,Inter,ui-sans-serif,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
            color:#17233c;background:#f8f7f5;font-synthesis:none;
            --ink:#17233c;--muted:#58647a;--line:#edf1f5;--surface:#fff;--soft:#fff9f4;
            --brand:#ff6b5e;--brand-dark:#e9564d;--brand-soft:#fff0ed;--nav:#17233c;
            --green:#16866f;--green-soft:#eaf9f5;--amber:#b96b00;--amber-soft:#fff5e7;
            --red:#c83f4d;--red-soft:#fff0f2;--shadow:0 2px 10px rgba(23,35,60,.08),0 16px 42px rgba(23,35,60,.04)
        }
        *{box-sizing:border-box}html{min-height:100%}body{margin:0;min-height:100vh;background:#f6f7fb;color:var(--ink);font-size:14px;line-height:1.5}
        a{color:var(--brand);text-decoration:none}a:hover{color:var(--brand-dark)}button,input,select,textarea{font:inherit}
        .app-shell{min-height:100vh}.sidebar{position:fixed;inset:0 auto 0 0;width:256px;background:var(--nav);color:#d0d5dd;padding:24px 16px;display:flex;flex-direction:column;z-index:30}
        .brand-lockup{display:flex;align-items:center;padding:0 8px 22px;color:white}.brand-lockup:hover{color:white}.brand-logo{display:block;width:142px;height:auto}.brand-logo-mobile{display:block;width:92px;height:auto}.brand-copy{line-height:1.15}.brand-copy small{color:#aab3c2;font-size:11px}
        .org-chip{margin:0 4px 20px;padding:10px 12px;border:1px solid #344054;border-radius:10px;background:#1d2939}.org-chip span{display:block;color:#98a2b3;font-size:10px;text-transform:uppercase;letter-spacing:.08em}.org-chip strong{display:block;color:#f2f4f7;font-size:13px;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .nav-label{padding:0 12px;margin:9px 0 6px;color:#7f8ba0;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em}.nav{display:grid;gap:3px}.nav-link{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:9px;color:#c5ccd7;font-weight:600}.nav-link:hover{background:#21304d;color:white}.nav-link.active{background:#263654;color:white;box-shadow:inset 3px 0 var(--brand)}.nav-icon{width:19px;height:19px;display:grid;place-items:center;color:currentColor}.nav-icon svg{width:19px;height:19px;stroke:currentColor}.nav-count{margin-left:auto;min-width:20px;height:20px;padding:0 6px;border-radius:10px;background:var(--brand);color:var(--ink);display:grid;place-items:center;font-size:11px;font-weight:800}
        .sidebar-footer{margin-top:auto;padding-top:16px;border-top:1px solid #344054}.user-card{display:flex;align-items:center;gap:10px;padding:8px}.avatar{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#344054;color:#f2f4f7;font-weight:700}.user-meta{min-width:0;flex:1}.user-meta a{display:block;color:#f2f4f7;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.user-meta small{color:#98a2b3}.logout-button{border:0;background:transparent;color:#98a2b3;cursor:pointer;padding:7px;border-radius:7px}.logout-button:hover{background:#344054;color:white}
        .workspace{margin-left:256px;min-height:100vh}.mobile-topbar{display:none}.container{max-width:1440px;margin:0 auto;padding:36px 36px 64px}
        h1,h2,h3{color:#101828;letter-spacing:-.025em}h1{font-size:28px;line-height:1.2;margin:0 0 5px}h2{font-size:18px;margin:0 0 14px}h3{font-size:15px;margin:22px 0 8px}p{margin:.5rem 0 1rem}.page-kicker{color:var(--brand);font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;margin-bottom:7px}
        .toolbar{display:flex;gap:16px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:22px}.toolbar h1,.toolbar h2{margin-bottom:4px}.toolbar>div{min-width:0}
        .card{background:var(--surface);border:1px solid var(--line);border-radius:13px;padding:22px;box-shadow:var(--shadow)}.grid{display:grid;gap:16px}.grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;border:1px solid transparent;border-radius:14px;min-height:40px;padding:9px 15px;background:var(--brand);color:var(--ink);font-weight:750;line-height:1;cursor:pointer;box-shadow:0 2px 10px rgba(23,35,60,.08);transition:.16s ease}.btn:hover{background:var(--brand-dark);color:var(--ink);transform:translateY(-1px)}.btn:active{transform:none}.btn-muted{background:white;color:#344054;border-color:#d0d5dd}.btn-muted:hover{background:#f9fafb;color:#101828}.btn-danger{background:#d92d20;color:white}.btn-danger:hover{background:#b42318;color:white}
        label{display:block;color:#344054;font-size:13px;font-weight:650;margin:15px 0 6px}input,select,textarea{width:100%;padding:10px 12px;border:1px solid #d0d5dd;border-radius:8px;background:white;color:#101828;outline:0;box-shadow:0 1px 2px rgba(16,24,40,.03);transition:border-color .15s,box-shadow .15s}input:hover,select:hover,textarea:hover{border-color:#98a2b3}input:focus,select:focus,textarea:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(255,107,94,.18)}textarea{line-height:1.55;resize:vertical}input[type=checkbox]{accent-color:var(--brand)}
        .error,.notice,.warning{padding:11px 13px;border-radius:9px;margin:10px 0;border:1px solid}.error{color:var(--red);background:var(--red-soft);border-color:#fecdca}.notice{color:var(--green);background:var(--green-soft);border-color:#abefc6}.warning{color:var(--amber);background:var(--amber-soft);border-color:#fedf89}
        .flash{display:flex;align-items:center;gap:10px;margin-bottom:18px}.flash:before{content:'✓';width:20px;height:20px;border-radius:50%;display:grid;place-items:center;background:#12b76a;color:white;font-size:11px;font-weight:900}
        .muted{color:var(--muted);font-size:13px}.stat{font-size:28px;line-height:1.15;font-weight:750;letter-spacing:-.04em;color:#101828}.badge{display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:999px;background:#f2f4f7;color:#475467;border:1px solid #eaecf0;font-size:11px;font-weight:750;line-height:1.2;text-transform:uppercase;letter-spacing:.025em}.badge.hot,.hot{background:var(--red-soft);color:var(--red);border-color:#fecdca}.badge.warm,.warm{background:var(--amber-soft);color:var(--amber);border-color:#fedf89}.badge.cold{background:#eff8ff;color:#175cd3;border-color:#b2ddff}.badge[data-status="sent"],.badge.success{background:var(--green-soft);color:var(--green);border-color:#abefc6}
        .table-wrap{overflow:auto;margin:0 -1px}table{border-collapse:separate;border-spacing:0;width:100%}th,td{text-align:left;padding:13px 14px;border-bottom:1px solid #eaecf0;vertical-align:middle}th{background:#f9fafb;color:#667085;font-size:10px;text-transform:uppercase;letter-spacing:.07em;font-weight:750;white-space:nowrap}thead th:first-child{border-radius:8px 0 0 0}thead th:last-child{border-radius:0 8px 0 0}tbody tr{transition:background .12s}tbody tr:hover{background:#fafbff}tbody tr:last-child td{border-bottom:0}td strong{color:#101828}
        .email-preview{white-space:normal;border:1px solid #e4e7ec;background:#fbfcfe;border-radius:10px;padding:18px;line-height:1.65;margin:14px 0}.timeline{border-left:1px solid #d0d5dd;padding-left:20px;margin-left:5px}.event{position:relative;margin:0 0 22px}.event:before{content:'';position:absolute;left:-25px;top:5px;width:8px;height:8px;border-radius:50%;background:#617be5;border:3px solid #eef2ff;box-sizing:content-box}
        hr{margin:28px 0!important;border:0!important;border-top:1px solid #eaecf0!important}code{background:#f2f4f7;border:1px solid #eaecf0;border-radius:5px;padding:2px 5px;color:#344054}.auth-shell{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:18px;padding:24px;background:radial-gradient(circle at 18% 18%,rgba(69,214,181,.18) 0,transparent 30%),radial-gradient(circle at 82% 78%,rgba(255,107,94,.16) 0,transparent 32%),var(--soft)}.auth-brand img{display:block;width:170px;height:auto}.auth-brand-signature{color:var(--muted);font-size:11px;text-align:center;margin-top:-10px}.auth{width:100%;max-width:430px;margin:0}.auth.card{padding:32px;border-radius:24px}
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
    @php($unreadNotificationCount=\App\Models\CommercialNotification::query()->where('user_id',auth()->id())->whereNull('read_at')->count())
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <a class="brand-lockup" href="{{ route('leads.index') }}">
                <img class="brand-logo" src="{{ asset('brand/daria-logo-white.svg') }}" alt="Daria">
            </a>
            <div class="org-chip"><span>Workspace</span><strong>{{ $activeOrganization?->name }}</strong></div>
            <div class="nav-label">Operatività</div>
            <nav class="nav" aria-label="Navigazione principale">
                <a class="nav-link @if(request()->routeIs('leads.*')) active @endif" href="{{ route('leads.index') }}"><span class="nav-icon"><svg fill="none" viewBox="0 0 24 24"><path d="M4 7.5h16M4 12h16M4 16.5h10" stroke-width="1.8" stroke-linecap="round"/></svg></span>Lead inbox</a>
                <a class="nav-link @if(request()->routeIs('knowledge.*')) active @endif" href="{{ route('knowledge.index') }}"><span class="nav-icon"><svg fill="none" viewBox="0 0 24 24"><path d="M5 4.5h10a3 3 0 0 1 3 3v12H8a3 3 0 0 1-3-3v-12Z" stroke-width="1.8"/><path d="M8 8h6M8 12h6" stroke-width="1.8" stroke-linecap="round"/></svg></span>Knowledge base</a>
                <a class="nav-link @if(request()->routeIs('notifications.*')) active @endif" href="{{ route('notifications.index') }}"><span class="nav-icon"><svg fill="none" viewBox="0 0 24 24"><path d="M18 9a6 6 0 0 0-12 0c0 7-2.5 7-2.5 7h17S18 16 18 9Z" stroke-width="1.8"/><path d="M10 20h4" stroke-width="1.8" stroke-linecap="round"/></svg></span>Notifiche <span class="nav-count" id="notification-count" @if(!$unreadNotificationCount) style="display:none" @endif>{{ $unreadNotificationCount }}</span></a>
                @if(in_array($activeRole, ['owner', 'sales'], true))
                    <a class="nav-link @if(request()->routeIs('inbound-emails.*')) active @endif" href="{{ route('inbound-emails.index') }}"><span class="nav-icon"><svg fill="none" viewBox="0 0 24 24"><path d="M4 6.5h16v11H4z" stroke-width="1.8"/><path d="m5 8 7 5 7-5" stroke-width="1.8" stroke-linecap="round"/></svg></span>Email da associare @if($pendingInboundCount)<span class="nav-count">{{ $pendingInboundCount }}</span>@endif</a>
                @endif
            </nav>
            @if($activeRole==='owner')
                <div class="nav-label">Configurazione</div>
                <nav class="nav" aria-label="Configurazione">
                    <a class="nav-link @if(request()->routeIs('settings.organization*') || request()->routeIs('settings.pricing-rules.*')) active @endif" href="{{ route('settings.organization') }}"><span class="nav-icon"><svg fill="none" viewBox="0 0 24 24"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" stroke-width="1.8"/><path d="M19 13.5v-3l-2-.7-.7-1.6.9-2-2.1-2.1-2 .9-1.6-.7-.7-2h-3l-.7 2-1.6.7-2-.9-2.1 2.1.9 2-.7 1.6-2 .7v3l2 .7.7 1.6-.9 2 2.1 2.1 2-.9 1.6.7.7 2h3l.7-2 1.6-.7 2 .9 2.1-2.1-.9-2 .7-1.6 2-.7Z" stroke-width="1.5"/></svg></span>Azienda e AI</a>
                    <a class="nav-link @if(request()->routeIs('settings.sources*')) active @endif" href="{{ route('settings.sources') }}"><span class="nav-icon"><svg fill="none" viewBox="0 0 24 24"><path d="M8 12h8M12 8v8" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="12" r="8.5" stroke-width="1.8"/></svg></span>Sorgenti lead</a>
                    <a class="nav-link @if(request()->routeIs('settings.mailboxes.*')) active @endif" href="{{ route('settings.mailboxes.index') }}"><span class="nav-icon"><svg fill="none" viewBox="0 0 24 24"><path d="M4 6.5h16v11H4z" stroke-width="1.8"/><path d="m5 8 7 5 7-5" stroke-width="1.8" stroke-linecap="round"/></svg></span>Caselle email</a>
                    <a class="nav-link @if(request()->routeIs('settings.users.*')) active @endif" href="{{ route('settings.users.index') }}"><span class="nav-icon"><svg fill="none" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3" stroke-width="1.8"/><path d="M3.5 19c.5-4 2.5-6 5.5-6s5 2 5.5 6M16 8.5a2.5 2.5 0 0 1 0 5M16 15c2.5.2 4 1.6 4.5 4" stroke-width="1.8" stroke-linecap="round"/></svg></span>Utenti</a>
                </nav>
            @endif
            @if(auth()->user()->is_super_admin)<div class="nav-label">Piattaforma</div><nav class="nav"><a class="nav-link" href="{{ route('admin.licensing') }}">Pannello licenze</a></nav>@endif
            <div class="sidebar-footer">
                <div class="user-card">
                    <span class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</span>
                    <span class="user-meta"><a href="{{ route('account.edit') }}">{{ auth()->user()->name }}</a><small>{{ ucfirst($activeRole) }}</small></span>
                    <form method="post" action="{{ route('logout') }}">@csrf<button class="logout-button" type="submit" title="Esci" aria-label="Esci">↗</button></form>
                </div>
            </div>
        </aside>
        <div class="workspace">
            <header class="mobile-topbar"><a class="brand-lockup" style="padding:0;color:#101828" href="{{ route('leads.index') }}"><img class="brand-logo-mobile" src="{{ asset('brand/daria-logo-horizontal.svg') }}" alt="Daria"></a><button class="menu-button" type="button" onclick="document.body.classList.toggle('nav-open')" aria-label="Apri menu">☰</button></header>
            <main class="container">
                @if(session('status'))<div class="notice flash">{{ session('status') }}</div>@endif
                @yield('content')
            </main>
        </div>
    </div>
    <script>
    (() => {
        const endpoint = @json(route('notifications.unread'));
        const startedAt = Date.now();
        const shown = new Set();
        const count = document.getElementById('notification-count');
        const button = document.getElementById('enable-browser-notifications');
        const status = document.getElementById('browser-notification-status');
        const updateButton = () => {
            if (!button || !('Notification' in window)) return;
            button.textContent = Notification.permission === 'granted' ? 'Notifiche browser attive' : 'Attiva notifiche browser';
        };
        if (button) button.addEventListener('click', async () => {
            if (!('Notification' in window)) { if(status){status.style.display='block';status.textContent='Questo browser non supporta le notifiche.';} return; }
            const permission = await Notification.requestPermission();
            updateButton();
            if(status){status.style.display='block';status.textContent=permission === 'granted' ? 'Notifiche del browser attivate.' : 'Autorizzazione alle notifiche non concessa.';}
        });
        const poll = async () => {
            try {
                const response = await fetch(endpoint, {headers:{'Accept':'application/json'},credentials:'same-origin'});
                if (!response.ok) return;
                const data = await response.json();
                if (count) { count.textContent=data.count; count.style.display=data.count ? 'grid' : 'none'; }
                if ('Notification' in window && Notification.permission === 'granted') {
                    data.items.forEach(item => {
                        if (shown.has(item.id) || Date.parse(item.created_at) < startedAt) return;
                        shown.add(item.id);
                        const notification = new Notification(item.title, {body:item.message,tag:item.id});
                        notification.onclick=()=>{window.focus();window.location.href=item.url;};
                    });
                }
            } catch (_) {}
        };
        updateButton(); poll(); window.setInterval(poll, 30000);
    })();
    </script>
@else
    <main class="auth-shell"><a class="auth-brand" href="{{ url('/') }}"><img src="{{ asset('brand/daria-logo-horizontal.svg') }}" alt="Daria"></a><div class="auth-brand-signature">Daria by Zen Software</div>@if(session('status'))<div class="notice flash">{{ session('status') }}</div>@endif @yield('content')</main>
@endauth
</body>
</html>
