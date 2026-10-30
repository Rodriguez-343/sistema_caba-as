@props(['grande' => false])
<span {{ $attributes->class(['marca', 'marca-grande' => $grande]) }} aria-hidden="true">
    <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 27 16 5l12.5 22"/><path d="M12 27l4-7 4 7"/></svg>
</span>
