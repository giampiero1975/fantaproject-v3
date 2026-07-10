<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex h-12 items-center justify-center rounded-md bg-gradient-to-r from-[#8B2CFF] to-[#2962FF] px-5 text-sm font-semibold text-white shadow-lg shadow-[#2962FF]/25 transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-[#7B2CFF]/60 focus:ring-offset-2 focus:ring-offset-[#071225] disabled:cursor-not-allowed disabled:opacity-70']) }}>
    {{ $slot }}
</button>
