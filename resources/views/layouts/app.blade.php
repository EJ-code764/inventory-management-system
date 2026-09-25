<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title ?? config('app.name') }}</title>

    <script>
        (() => {
            const theme = localStorage.getItem('theme') ?? 'system';

            const prefersDark = window.matchMedia(
                '(prefers-color-scheme: dark)'
            ).matches;

            const dark =
                theme === 'dark' ||
                (theme === 'system' && prefersDark);

            document.documentElement.classList.toggle('dark', dark);
            document.documentElement.dataset.theme = theme;
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="min-h-screen bg-background text-foreground">

        <div
            x-data="{
                mobileSidebarOpen: false,

                theme: localStorage.getItem('theme') ?? 'system',

                setTheme(value) {
                    this.theme = value;

                    localStorage.setItem('theme', value);

                    const prefersDark = window.matchMedia(
                        '(prefers-color-scheme: dark)'
                    ).matches;

                    const dark =
                        value === 'dark' ||
                        (value === 'system' && prefersDark);

                    document.documentElement.classList.toggle('dark', dark);
                    document.documentElement.dataset.theme = value;
                }
            }"
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
                border-r border-border
                bg-surface
                transition-transform duration-200 ease-out
                lg:translate-x-0
            "
        >

            {{-- Brand --}}
            <div class="flex h-16 shrink-0 items-center border-b border-border px-5">

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
                        <p class="truncate text-sm font-semibold text-foreground">
                            {{ config('app.name') }}
                        </p>

                        <p class="truncate text-xs text-muted">
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
                        rounded-lg text-muted
                        hover:bg-surface-muted hover:text-foreground
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
            <div class="border-t border-border p-4">

                <div class="flex items-center gap-3">

                    <div
                        class="
                            flex h-9 w-9 items-center justify-center
                            rounded-full
                            bg-primary-600
                            text-sm font-semibold text-white
                            shadow-sm
                        "
                        aria-hidden="true"
                    >
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>

                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-foreground">
                            {{ auth()->user()->name }}
                        </p>

                        <p class="text-xs text-muted">
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
                    border-b border-border
                    bg-surface/95 px-4
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
                        text-muted
                        hover:bg-surface-muted hover:text-foreground
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
                    class="font-semibold text-foreground lg:hidden"
                >
                    {{ config('app.name') }}
                </a>


                {{-- Right side --}}
                <div class="ml-auto flex items-center gap-2">

                    <div
                        x-data="{ open: false }"
                        class="relative"
                    >
                        <button
                            type="button"
                            x-on:click="open = !open"
                            class="
                                flex h-9 w-9 items-center justify-center
                                rounded-lg
                                text-muted
                                transition-colors
                                hover:bg-surface-muted
                                hover:text-foreground
                            "
                            aria-label="Change appearance"
                        >
                            {{-- Sun --}}
                            <svg
                                x-show="theme === 'light'"
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <circle cx="12" cy="12" r="4" />
                                <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
                            </svg>

                            {{-- Moon --}}
                            <svg
                                x-show="theme === 'dark'"
                                x-cloak
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                            </svg>

                            {{-- System --}}
                            <svg
                                x-show="theme === 'system'"
                                x-cloak
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <rect x="3" y="4" width="18" height="12" rx="2" />
                                <path d="M8 20h8M12 16v4" />
                            </svg>
                        </button>

                        {{-- Dropdown --}}
                        <div
                            x-show="open"
                            x-cloak
                            x-transition
                            x-on:click.outside="open = false"
                            class="
                                absolute right-0 z-50 mt-2 w-44
                                overflow-hidden rounded-xl
                                border border-border
                                bg-surface
                                p-1.5
                                shadow-lg
                            "
                        >

                            <button
                                type="button"
                                x-on:click="setTheme('light'); open = false"
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm transition-colors"
                                :class="theme === 'light'
                                    ? 'bg-primary-600 text-white'
                                    : 'text-muted hover:bg-surface-muted hover:text-foreground'"
                            >
                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <circle cx="12" cy="12" r="4" />
                                    <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2" />
                                </svg>

                                Light
                            </button>

                            <button
                                type="button"
                                x-on:click="setTheme('dark'); open = false"
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm transition-colors"
                                :class="theme === 'dark'
                                    ? 'bg-primary-600 text-white'
                                    : 'text-muted hover:bg-surface-muted hover:text-foreground'"
                            >
                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                                </svg>

                                Dark
                            </button>

                            <button
                                type="button"
                                x-on:click="setTheme('system'); open = false"
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm transition-colors"
                                :class="theme === 'system'
                                    ? 'bg-primary-600 text-white'
                                    : 'text-muted hover:bg-surface-muted hover:text-foreground'"
                            >
                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <rect x="3" y="4" width="18" height="12" rx="2" />
                                    <path d="M8 20h8M12 16v4" />
                                </svg>

                                System
                            </button>

                        </div>
                    </div>

                    <div class="hidden text-right sm:block">
                        <p class="text-sm font-medium text-foreground">
                            {{ auth()->user()->name }}
                        </p>
                    </div>

                    <div
                        class="
                            flex h-9 w-9 items-center justify-center
                            rounded-full
                            bg-primary-600
                            text-sm font-semibold text-white
                            shadow-sm
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
                                text-sm font-medium text-muted
                                hover:bg-surface-muted hover:text-foreground
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