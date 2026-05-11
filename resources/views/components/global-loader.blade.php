<div
    id="global-loader"
    class="global-loader fixed inset-0 z-[100000] hidden items-center justify-center bg-slate-900/35 backdrop-blur-[1px]"
    role="status"
    aria-live="polite"
    aria-label="Loading"
>
    <div class="global-loader__card flex flex-col items-center gap-3 rounded-xl bg-white/95 px-6 py-5 shadow-xl">
        <span class="inline-block h-10 w-10 animate-spin rounded-full border-4 border-slate-200 border-t-[#102B3C]"></span>
        <p class="text-sm font-medium text-slate-700">Loading, please wait...</p>
    </div>
</div>

<style>
    /* Smooth fade-in so the loader doesn't pop. The JS still toggles `hidden` / `flex`,
       but when `flex` is applied the CSS animation runs once on display:flex transition. */
    .global-loader.flex .global-loader__card {
        animation: global-loader-card-in 200ms ease-out both;
    }
    .global-loader.flex {
        animation: global-loader-bg-in 200ms ease-out both;
    }
    @keyframes global-loader-bg-in {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    @keyframes global-loader-card-in {
        from { opacity: 0; transform: translateY(4px) scale(0.98); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
</style>
