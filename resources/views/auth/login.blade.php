<x-layouts.guest>
    <section class="w-full rounded-xl bg-surface p-8 shadow-sm">
        <h1 class="text-2xl font-semibold">Sign in</h1>
        <p class="mt-2 text-sm text-muted">Access the inventory workspace.</p><x-alert />
        <form class="mt-6 space-y-4" method="POST" action="{{ route('login.store') }}">
            @csrf
            <label class="block text-sm font-medium">
                Email
                <input class="form-input mt-1" name="email" type="email" value="{{ old('email') }}" required autofocus>
            </label>
            <label class="block text-sm font-medium">
                Password
                <input class="form-input mt-1" name="password" type="password" required>
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input name="remember" type="checkbox" value="1">Remember me
            </label>
            <button class="btn-primary w-full">
                Sign in
            </button>
        </form>
    </section>
</x-layouts.guest>
