{{--
    Page-wide "thinking" loader, modelled on TallStackUI's <x-spinner thinking> variant
    (cycling braille glyphs + label). It is plain JS so it works on every layout, with or
    without a Livewire component on the page. It appears only after a short delay so fast
    navigations never flash, and stops animating for users who prefer reduced motion.
    Add data-no-loader to a link or form to opt out (e.g. file downloads).
--}}
<div id="st-page-loader" class="st-page-loader is-active" role="status" aria-live="polite" aria-hidden="false">
    <div class="st-page-loader__panel">
        <span class="st-page-loader__glyph" aria-hidden="true">⠋</span>
        <span class="st-page-loader__label">{{ $label ?? 'Thinking…' }}</span>
    </div>
</div>
<script>
    (() => {
        const loader = document.getElementById('st-page-loader');
        if (!loader || loader.dataset.ready) return;
        loader.dataset.ready = '1';

        const glyph = loader.querySelector('.st-page-loader__glyph');
        const frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let frame = 0, timer = null, safety = null;

        const stop = () => {
            loader.classList.remove('is-active');
            loader.setAttribute('aria-hidden', 'true');
            clearInterval(timer);
            clearTimeout(safety);
            timer = null;
        };
        const start = () => {
            loader.classList.add('is-active');
            loader.setAttribute('aria-hidden', 'false');
            if (!reduced && !timer) {
                timer = setInterval(() => { glyph.textContent = frames[frame = (frame + 1) % frames.length]; }, 80);
            }
            clearTimeout(safety);
            // Downloads and aborted navigations never unload the page; don't leave the overlay up.
            safety = setTimeout(stop, 12000);
        };

        start();
        if (document.readyState === 'complete') stop(); else window.addEventListener('load', stop, { once: true });
        window.addEventListener('pageshow', stop);

        document.addEventListener('click', (event) => {
            const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
            if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            if (link.hasAttribute('download') || link.closest('[data-no-loader]') || (link.target && link.target !== '_self')) return;
            const url = new URL(link.href, window.location.href);
            if (url.origin !== window.location.origin || !/^https?:$/.test(url.protocol)) return;
            if (url.pathname === window.location.pathname && url.search === window.location.search) return;
            if (/\/(export|pdf|html|labels|download)(\/|$)/.test(url.pathname)) return;
            start();
        });

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || form.closest('[data-no-loader]') || (form.target && form.target !== '_self')) return;
            // Let other handlers run first; if they cancel the submit there is nothing to wait for.
            setTimeout(() => { if (!event.defaultPrevented) start(); }, 0);
        });

        document.addEventListener('livewire:navigate', start);
        document.addEventListener('livewire:navigated', stop);
    })();
</script>
