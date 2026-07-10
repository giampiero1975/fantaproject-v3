@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'block h-12 rounded-md border border-white/15 bg-[#07101F] px-4 text-base text-white shadow-sm outline-none transition placeholder:text-slate-500 focus:border-[#7B2CFF] focus:ring-2 focus:ring-[#7B2CFF]/45 disabled:cursor-not-allowed disabled:opacity-70']) !!}>
