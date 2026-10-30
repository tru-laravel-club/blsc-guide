@props(['title' => null, 'wide' => false])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Contributor guide' }} · BLSC</title>
    <style>
        :root {
            --bg: #f7f7f4; --card: #ffffff; --text: #1d211d; --muted: #5f665f; --border: #e2e5de;
            --done: #2f7d4f; --progress: #2368a2; --ready: #a86a00; --blocked: #b3361f; --planned: #7b817b;
            --code: #eef0ea; --think: #eaf2fa; --try: #edf6ef; --git: #f6f0e6;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #111411; --card: #191d19; --text: #ecefe8; --muted: #a2aa9f; --border: #2a302a;
                --done: #5cc287; --progress: #6bb1ee; --ready: #e0a43a; --blocked: #ef7b63; --planned: #8c938b;
                --code: #222822; --think: #18232e; --try: #17241b; --git: #262118;
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 16px/1.7 ui-sans-serif, system-ui, sans-serif; }
        a { color: inherit; text-underline-offset: 3px; }

        .topbar { border-bottom: 1px solid var(--border); }
        .topbar nav { max-width: 1100px; margin: 0 auto; padding: 14px 20px; display: flex; gap: 24px; font-size: 15px; }
        .topbar a { text-decoration: none; color: var(--muted); }
        .topbar a:hover, .topbar a.active { color: var(--text); }
        .topbar .brand { font-weight: 700; color: var(--text); margin-right: auto; }

        main { max-width: 760px; margin: 0 auto; padding: 48px 20px 96px; }
        main.wide { max-width: 1100px; }

        h1 { font-size: 34px; line-height: 1.2; margin: 0 0 12px; letter-spacing: -0.01em; }
        h2 { font-size: 22px; line-height: 1.3; margin: 64px 0 16px; letter-spacing: -0.01em; }
        h3 { font-size: 17px; margin: 32px 0 8px; }
        p { margin: 0 0 16px; }
        .lead { font-size: 18px; color: var(--muted); }
        .muted { color: var(--muted); }
        .meta { font-size: 14px; color: var(--muted); }

        .grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); }
        .card { display: block; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 20px 22px; text-decoration: none; }
        a.card:hover { border-color: var(--muted); }
        .card-title { font-weight: 600; font-size: 17px; margin-bottom: 4px; }

        .status { display: inline-flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 600; }
        .status-done { color: var(--done); } .status-in-progress { color: var(--progress); } .status-ready { color: var(--ready); }
        .status-blocked { color: var(--blocked); } .status-not-planned { color: var(--planned); }
        .legend { display: flex; flex-wrap: wrap; gap: 20px; margin: 24px 0 0; }

        .notice { border-left: 4px solid var(--blocked); background: var(--card); padding: 16px 20px; border-radius: 8px; margin: 24px 0; }
        .notice.ok { border-color: var(--done); }
        .notice ul { margin: 8px 0; }

        .tags { display: flex; flex-wrap: wrap; gap: 8px; margin: 16px 0 0; }
        .tag { font-size: 13px; border: 1px solid var(--border); border-radius: 999px; padding: 3px 12px; }

        .prose p { margin: 0 0 14px; }
        .prose p:last-child { margin-bottom: 0; }
        p.inline { margin: 0; }
        .list { padding-left: 22px; display: grid; gap: 10px; margin: 0; }

        pre, code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 14px; }
        pre { background: var(--code); border-radius: 8px; padding: 14px 16px; overflow-x: auto; margin: 12px 0 0; line-height: 1.6; }
        p code, li code, td code { background: var(--code); border-radius: 4px; padding: 1px 6px; }

        ol.steps { list-style: none; padding: 0; margin: 0; display: grid; gap: 28px; }
        .step { padding: 28px; }
        .step-head { display: flex; align-items: center; gap: 14px; margin-bottom: 16px; }
        .step-number { flex: none; width: 32px; height: 32px; border-radius: 50%; display: grid; place-items: center; font-weight: 700; font-size: 15px; border: 2px solid var(--border); color: var(--muted); }
        .step-number.done { border-color: var(--done); background: var(--done); color: var(--bg); }
        .step-title { font-weight: 650; font-size: 19px; line-height: 1.3; }

        .box { border-radius: 10px; padding: 16px 20px; margin: 20px 0 0; }
        .box-label { display: block; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
        .box p { margin: 0 0 8px; }
        .box p:last-child { margin-bottom: 0; }
        .think { background: var(--think); }
        .think .box-label { color: var(--progress); }
        .try { background: var(--try); }
        .try .box-label { color: var(--done); }
        .git { background: var(--git); }
        .git .box-label { color: var(--ready); }
        .git pre { background: transparent; padding: 0; margin: 8px 0 0; }

        details.reveal { border: 1px solid var(--border); border-radius: 10px; padding: 12px 18px; margin: 12px 0 0; }
        details.reveal summary { cursor: pointer; font-weight: 600; font-size: 15px; }
        details.reveal[open] summary { margin-bottom: 8px; }
        details.reveal p { margin: 8px 0; }
        .reveals { margin-top: 20px; }

        .links { list-style: none; padding: 0; margin: 20px 0 0; display: grid; gap: 6px; font-size: 15px; }
        .link-type { display: inline-block; min-width: 60px; font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; }

        .table { width: 100%; border-collapse: collapse; padding: 0; }
        .table td { padding: 12px 20px; border-bottom: 1px solid var(--border); vertical-align: top; }
        .table tr:last-child td { border-bottom: 0; }
        .spaced { margin-top: 16px; }
        .mark-tools form { margin: 12px 0; }
        .button { font: inherit; font-size: 15px; font-weight: 600; cursor: pointer; border-radius: 8px; padding: 8px 18px; border: 1px solid var(--done); background: var(--done); color: var(--bg); }
        .button.secondary { background: transparent; color: var(--text); border-color: var(--border); }
        .button:hover { opacity: .9; }

        @media (max-width: 600px) {
            main { padding-top: 32px; }
            h1 { font-size: 28px; }
            h2 { margin-top: 48px; }
            .step { padding: 20px; }
        }
    </style>
</head>
<body>
<header class="topbar">
    <nav>
        <a class="brand" href="{{ route('blsc-guide.index') }}">BLSC guide</a>
        <a href="{{ route('blsc-guide.start') }}" @class(['active' => request()->routeIs('blsc-guide.start')])>Start here</a>
        <a href="{{ route('blsc-guide.index') }}" @class(['active' => request()->routeIs('blsc-guide.index')])>All features</a>
        <a href="{{ route('blsc-guide.git') }}" @class(['active' => request()->routeIs('blsc-guide.git')])>Git habits</a>
        <a href="{{ route('blsc-guide.reactive') }}" @class(['active' => request()->routeIs('blsc-guide.reactive')])>The reactive way</a>
    </nav>
</header>
<main @class(['wide' => $wide])>
    {{ $slot }}
</main>
</body>
</html>
