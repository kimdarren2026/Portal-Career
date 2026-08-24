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

    @vite(['../web/src/app.ts'])
    @inertiaHead
</head>
<body class="antialiased">
    @inertia
</body>
</html>
