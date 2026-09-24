const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');

systemTheme.addEventListener('change', (event) => {
    const theme = localStorage.getItem('theme') ?? 'system';

    if (theme !== 'system') {
        return;
    }

    document.documentElement.classList.toggle(
        'dark',
        event.matches
    );
});