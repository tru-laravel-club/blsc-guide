<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Contributor guide' }} · BLSC</title>
    <style>
        :root {
            --bg: #f7f7f4; --card: #ffffff; --text: #1d211d; --muted: #5f665f; --border: #dcdfd8;
            --done: #2f7d4f; --progress: #2368a2; --ready: #a86a00; --blocked: #b3361f; --planned: #7b817b;
            --code: #eef0ea;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #111411; --card: #191d19; --text: #ecefe8; --muted: #a2aa9f; --border: #2c322c;
                --done: #5cc287; --progress: #6bb1ee; --ready: #e0a43a; --blocked: #ef7b63; --planned: #8c938b;
                --code: #222822;
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 15px/1.55 ui-sans-serif, system-ui, sans-serif; }
        a { color: inherit; }
        main { max-width: 1100px; margin: 0 auto; padding: 32px 16px 64px; }
        h1 { font-size: 28px; margin: 0 0 4px; }
        h2 { font-size: 18px; margin: 32px 0 12px; }
        .muted { color: var(--muted); }
        .grid { display: grid; gap: 12px; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); }
        .card { display: block; background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px; text-decoration: none; }
        a.card:hover { border-color: var(--muted); }
        .card-title { font-weight: 600; }
        .meta { font-size: 13px; color: var(--muted); margin-top: 4px; }
        .status { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; }
        .status-done { color: var(--done); } .status-in-progress { color: var(--progress); } .status-ready { color: var(--ready); }
        .status-blocked { color: var(--blocked); } .status-not-planned { color: var(--planned); }
        .legend { display: flex; flex-wrap: wrap; gap: 16px; margin: 16px 0 0; }
        .notice { border-left: 4px solid var(--blocked); background: var(--card); padding: 12px 16px; border-radius: 6px; margin: 16px 0; }
        .notice.ok { border-color: var(--done); }
        ol.steps { list-style: none; padding: 0; margin: 0; display: grid; gap: 12px; }
        .step { display: grid; grid-template-columns: 28px 1fr; gap: 8px; }
        .mark { font-weight: 700; }
        pre, code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; }
        pre { background: var(--code); border-radius: 6px; padding: 10px 12px; overflow-x: auto; margin: 8px 0 0; }
        .tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .tag { font-size: 12px; border: 1px solid var(--border); border-radius: 999px; padding: 2px 8px; }
        .back { display: inline-block; margin-bottom: 16px; font-size: 14px; }
    </style>
</head>
<body>
<main>
    {{ $slot }}
</main>
</body>
</html>
