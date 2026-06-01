<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Billing — Workbench' }}</title>
    @vite(['workbench/resources/css/app.css', 'workbench/resources/js/app.js'])
    @livewireStyles
    @fluxAppearance
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-white">
    <main class="mx-auto max-w-4xl px-4 py-10">
        {{ $slot }}
    </main>
    @livewireScripts
    @fluxScripts
</body>
</html>
