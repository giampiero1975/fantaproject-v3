<div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#050B18] px-5 py-8 text-white">
    <div class="absolute inset-0" aria-hidden="true">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_16%,rgba(123,44,255,0.24),transparent_30%),radial-gradient(circle_at_82%_12%,rgba(41,98,255,0.17),transparent_34%),linear-gradient(135deg,#050B18_0%,#071226_52%,#0B1026_100%)]"></div>
        <div class="absolute left-1/2 top-1/2 h-[34rem] w-[34rem] -translate-x-1/2 -translate-y-1/2 rounded-full border border-[#7B2CFF]/15 bg-[#0D1B3D]/20 shadow-[0_0_130px_rgba(123,44,255,0.22)]"></div>
    </div>

    <div class="relative z-10 w-full max-w-md">
        <div class="mb-6 flex justify-center">
            {{ $logo }}
        </div>

        <div class="overflow-hidden rounded-lg border border-white/15 bg-[#071225]/86 p-7 shadow-2xl shadow-black/35 backdrop-blur-xl sm:p-8">
            {{ $slot }}
        </div>
    </div>
</div>
