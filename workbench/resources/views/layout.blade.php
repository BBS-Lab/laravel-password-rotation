<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Password Rotation — demo</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            font: 16px/1.5 system-ui, -apple-system, sans-serif;
            margin: 0; min-height: 100vh; display: grid; place-items: center;
            background: #f4f4f5; color: #18181b;
        }
        @media (prefers-color-scheme: dark) { body { background: #18181b; color: #f4f4f5; } }
        main {
            width: 100%; max-width: 26rem; margin: 2rem; padding: 2rem;
            background: Canvas; border-radius: .75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.1), 0 8px 24px rgba(0,0,0,.08);
        }
        h1 { margin: 0 0 1rem; font-size: 1.35rem; }
        label { display: block; margin: .75rem 0; font-weight: 600; font-size: .9rem; }
        input { width: 100%; margin-top: .3rem; padding: .6rem .7rem; font: inherit;
            border: 1px solid GrayText; border-radius: .5rem; background: Field; color: FieldText; }
        button { margin-top: 1rem; padding: .6rem 1rem; font: inherit; font-weight: 600;
            border: 0; border-radius: .5rem; background: #4f46e5; color: #fff; cursor: pointer; }
        button.link { background: none; color: GrayText; padding: .6rem 0; font-weight: 400; text-decoration: underline; }
        .error { color: #b91c1c; } .error li { margin: .2rem 0; }
        .ok { color: #15803d; font-weight: 600; }
        .warn { color: #b45309; font-weight: 600; }
        .hint { margin-top: 1.25rem; font-size: .8rem; color: GrayText; }
        code { font-size: .85em; }
    </style>
</head>
<body>
    <main>@yield('content')</main>
</body>
</html>
