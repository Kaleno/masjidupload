<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#0e221f">
        <meta name="turbo-visit-control" content="reload">
        <title>Sedang offline · {{ config('app.name') }}</title>
        <style>
            *, *::before, *::after { box-sizing: border-box; }
            body {
                margin: 0;
                min-height: 100vh;
                min-height: 100dvh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1.5rem;
                background: #fbf8f1;
                color: #1e293b;
                font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            }
            main {
                width: 100%;
                max-width: 22rem;
                padding: 2rem 1.5rem;
                border-radius: 1.5rem;
                background: #fff;
                box-shadow: 0 10px 30px -12px rgba(14, 34, 31, .25);
                text-align: center;
            }
            img { width: 4.5rem; height: 4.5rem; border-radius: 1.25rem; }
            h1 { margin: 1.25rem 0 .5rem; font-size: 1.35rem; color: #0e221f; }
            p { margin: 0; font-size: .95rem; line-height: 1.55; color: #475569; }
            .retry {
                display: flex;
                align-items: center;
                justify-content: center;
                margin-top: 1.5rem;
                min-height: 3rem;
                border-radius: .85rem;
                background: #115e59;
                color: #fff;
                font-weight: 600;
                text-decoration: none;
            }
            .retry:focus-visible { outline: 3px solid #dbb86d; outline-offset: 2px; }
        </style>
    </head>
    <body>
        <main>
            <img src="/icons/icon-192.png" alt="">
            <h1>Sedang offline</h1>
            <p>Koneksi internet terputus. Cek sinyal atau Wi-Fi, lalu coba lagi. Data yang sudah tersimpan tetap aman.</p>
            <a href="" class="retry">Coba lagi</a>
        </main>
    </body>
</html>
