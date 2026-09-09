<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Authenticated and document routes are never indexed (FSD §10.4). --}}
    @if (! ($isPublicRoute ?? false))
        <meta name="robots" content="noindex">
    @endif

    {{-- This source intentionally lives outside the Laravel root. Development
         uses a canonical absolute path so Vite serves it correctly; production
         keeps the stable manifest key generated from the relative path. --}}
    @if (file_exists(public_path('hot')))
        @vite([realpath(base_path('../web/src/app.ts'))])
    @else
        @vite(['../web/src/app.ts'])
    @endif
    @inertiaHead
</head>
<body class="antialiased">
    @inertia
</body>
</html>
