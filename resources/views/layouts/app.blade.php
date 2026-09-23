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

<body class="min-h-screen bg-background text-slate-900">

    <div
        x-data="{ mobileSidebarOpen: false }"
        class="min-h-screen"
    >

        {{-- ============================================================
            MOBILE BACKDROP
        ============================================================ --}}

        <div
            x-cloak
            x-show="mobileSidebarOpen"
            x-transition.opacity
            x-on:click="mobileSidebarOpen = false"
            class="fixed inset-0 z-40 bg-slate-950/30 backdrop-blur-sm lg:hidden"
            aria-hidden="true"
        ></div>


        {{-- ============================================================
            SIDEBAR
        ============================================================ --}}

        <aside
            x-cloak
            :class="mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            class="
                fixed inset-y-0 left-0 z-50
                flex w-72 flex-col
                border-r border-slate-200
                bg-white
                transition-transform duration-200 ease-out
                lg:translate-x-0
            "
        >

            {{-- Brand --}}
            <div class="flex h-16 shrink-0 items-center border-b border-slate-200 px-5">

                <a
                    href="{{ route('dashboard') }}"
                    class="flex min-w-0 items-center gap-3"
                >
                    <div
                        class="
                            flex h-9 w-9 shrink-0 items-center justify-center
                            rounded-lg
                            bg-primary-600
                            text-sm font-bold text-white
                            shadow-sm
                        "
                    >
                        IS
                    </div>

                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-900">
                            {{ config('app.name') }}
                        </p>

                        <p class="truncate text-xs text-slate-500">
                            Inventory System
                        </p>
                    </div>
                </a>


                {{-- Mobile close button --}}
                <button
                    type="button"
                    x-on:click="mobileSidebarOpen = false"
                    class="
                        ml-auto flex h-9 w-9 items-center justify-center
                        rounded-lg text-slate-500
                        hover:bg-slate-100 hover:text-slate-900
                        lg:hidden
                    "
                    aria-label="Close navigation"
                >
                    <svg
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M6 18 18 6M6 6l12 12"
                        />
                    </svg>
                </button>

            </div>


            {{-- Navigation --}}
            <div class="flex-1 overflow-y-auto px-3 py-5">

                <x-sidebar-navigation />

            </div>


            {{-- Sidebar footer --}}
            <div class="border-t border-slate-200 p-4">

                <div class="flex items-center gap-3">

                    <div
                        class="
                            flex h-9 w-9 shrink-0 items-center justify-center
                            rounded-full
                            bg-primary-50
                            text-sm font-semibold text-primary-700
                        "
                    >
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>

                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-900">
                            {{ auth()->user()->name }}
                        </p>

                        <p class="text-xs text-slate-500">
                            Signed in
                        </p>
                    </div>

                </div>

            </div>

        </aside>


        {{-- ============================================================
            MAIN APPLICATION
        ============================================================ --}}

        <div class="min-h-screen lg:pl-72">

            {{-- Header --}}
            <header
                class="
                    sticky top-0 z-30
                    flex h-16 items-center
                    border-b border-slate-200
                    bg-white/95 px-4
                    backdrop-blur
                    sm:px-6
                    lg:px-8
                "
            >

                {{-- Mobile menu --}}
                <button
                    type="button"
                    x-on:click="mobileSidebarOpen = true"
                    class="
                        mr-3 flex h-9 w-9 items-center justify-center
                        rounded-lg
                        text-slate-600
                        hover:bg-slate-100 hover:text-slate-900
                        lg:hidden
                    "
                    aria-label="Open navigation"
                >
                    <svg
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M4 6h16M4 12h16M4 18h16"
                        />
                    </svg>
                </button>


                {{-- Mobile brand --}}
                <a
                    href="{{ route('dashboard') }}"
                    class="font-semibold text-slate-900 lg:hidden"
                >
                    {{ config('app.name') }}
                </a>


                {{-- Right side --}}
                <div class="ml-auto flex items-center gap-2">

                    <div class="hidden text-right sm:block">
                        <p class="text-sm font-medium text-slate-800">
                            {{ auth()->user()->name }}
                        </p>
                    </div>

                    <div
                        class="
                            flex h-9 w-9 items-center justify-center
                            rounded-full
                            bg-primary-50
                            text-sm font-semibold text-primary-700
                        "
                        aria-hidden="true"
                    >
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <button
                            type="submit"
                            class="
                                rounded-lg px-3 py-2
                                text-sm font-medium text-slate-600
                                hover:bg-slate-100 hover:text-slate-900
                            "
                        >
                            Sign out
                        </button>
                    </form>

                </div>

            </header>


            {{-- Main page --}}
            <main class="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">

                <div class="mx-auto w-full max-w-screen-2xl">

                    <x-alert />

                    {{ $slot }}

                </div>

            </main>

        </div>

    </div>

    @livewireScripts

</body>
</html>