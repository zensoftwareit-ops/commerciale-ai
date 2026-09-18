<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#17233c">
    <link rel="icon" type="image/svg+xml" href="/brand/daria-mark.svg">
    <title>@yield('code') · @yield('title') · Daria</title>
    <style>
        :root{font-family:Manrope,Inter,ui-sans-serif,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#17233c;background:#f6f7fb;--ink:#17233c;--muted:#667085;--brand:#ff6b5e;--brand-dark:#e9564d;--line:#e4e7ec}
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:radial-gradient(circle at 15% 15%,rgba(69,214,181,.15),transparent 30%),radial-gradient(circle at 85% 80%,rgba(255,107,94,.16),transparent 32%),#f8f7f5;color:var(--ink)}
        main{width:min(100%,700px)}.brand{display:inline-flex;margin:0 0 22px 5px}.brand img{display:block;width:154px;height:auto}.card{position:relative;overflow:hidden;background:#fff;border:1px solid rgba(228,231,236,.9);border-radius:26px;padding:clamp(28px,6vw,54px);box-shadow:0 24px 70px rgba(23,35,60,.12)}.card:before{content:"";position:absolute;inset:0 0 auto;height:5px;background:linear-gradient(90deg,var(--brand),#45d6b5)}
        .code{display:inline-flex;align-items:center;min-height:28px;padding:5px 11px;border-radius:999px;background:#fff0ed;color:#c83f4d;font-weight:850;font-size:12px;letter-spacing:.12em;text-transform:uppercase}h1{margin:20px 0 10px;font-size:clamp(30px,6vw,48px);line-height:1.05;letter-spacing:-.045em;color:#101828}p{max-width:570px;margin:0;color:var(--muted);font-size:16px;line-height:1.65}.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:30px}.button{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:11px 18px;border:1px solid transparent;border-radius:14px;background:var(--brand);color:var(--ink);font:inherit;font-weight:780;text-decoration:none;cursor:pointer;box-shadow:0 4px 14px rgba(23,35,60,.09)}.button:hover{background:var(--brand-dark)}.button.secondary{background:#fff;color:#344054;border-color:#d0d5dd}.button.secondary:hover{background:#f9fafb}.reference{margin-top:24px;padding-top:20px;border-top:1px solid var(--line);color:#98a2b3;font-size:12px}
        @media(max-width:520px){body{padding:16px}.card{border-radius:20px}.actions{display:grid}.button{width:100%}}
    </style>
</head>
<body>
<main>
    <a class="brand" href="/" aria-label="Torna a Daria"><img src="/brand/daria-logo-horizontal.svg" alt="Daria"></a>
    <section class="card">
        <span class="code">Errore @yield('code')</span>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            @yield('actions')
            <a class="button" href="/">Torna a Daria</a>
        </div>
        <div class="reference">Se il problema continua, comunica all’assistenza l’ora in cui si è verificato.</div>
    </section>
</main>
</body>
</html>
