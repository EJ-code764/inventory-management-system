<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
    [x-cloak] {
        display: none !important;
    }
</style>
</head>

<body class="min-h-screen bg-slate-100 text-slate-900">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="hidden w-64 shrink-0 bg-slate-900 p-6 text-slate-100 md:block">
            <a class="text-xl font-semibold" href="{{ route('dashboard') }}">
                {{ config('app.name') }}
            </a>
            <p class="mt-1 text-sm text-slate-400">Inventory System</p>
            {{-- <nav class="mt-10">
                <a class="block rounded-lg bg-slate-800 px-3 py-2 text-sm font-medium" href="{{ route('dashboard') }}">
                    Dashboard
                </a>
                <x-classification-navigation />
                <x-product-navigation />
                <x-warehouse-navigation />
                <x-inventory-navigation />
                <x-supplier-navigation />
                <x-purchase-navigation />
            </nav> --}}
            <div class="mt-10">
                <x-sidebar-navigation />
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="min-w-0 flex-1">
            <!-- Header -->
            <header class="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-6">
                <a class="font-semibold md:hidden" href="{{ route('dashboard') }}">
                    {{ config('app.name') }}
                </a>
                <span class="ml-auto text-sm text-slate-600">
                    {{ auth()->user()->name }}
                </span>
                <form class="ml-4" method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-sm font-medium text-slate-700 hover:text-slate-950">
                        Sign out
                    </button>
                </form>
            </header>

            <!-- Mobile Navigation -->
            <nav aria-label="Module navigation" class="flex flex-wrap gap-2 border-b border-slate-200 bg-white px-3 py-2 md:hidden">
                <x-classification-navigation />
                <x-product-navigation />
                <x-warehouse-navigation />
                <x-inventory-navigation />
                <x-supplier-navigation />
                <x-purchase-navigation />
            </nav>

            <!-- Main Yield Slot -->
            <main class="p-6">
                <x-alert />
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>

</html>
