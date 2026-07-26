{{--
    A labelled read-only value with a copy button.

    navigator.clipboard is only defined in a secure context, so a textarea plus
    execCommand fallback keeps the button working when the panel is reached over plain
    HTTP or by IP. The value stays selectable either way.
--}}
<div>
    <span class="text-sm text-base/50">{{ $label }}</span>
    <div class="mt-1 flex items-stretch gap-2">
        <code class="flex-1 min-w-0 truncate bg-background border border-neutral rounded-md px-3 py-2 font-mono text-sm select-all">{{ $value }}</code>
        <button type="button"
            data-copy="{{ $value }}"
            x-data="{ copied: false, labels: @js(['copy' => $copy, 'copied' => $copied]) }"
            x-text="copied ? labels.copied : labels.copy"
            @click="
                const value = $el.dataset.copy;
                const done = () => { copied = true; setTimeout(() => copied = false, 1500) };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(value).then(done).catch(() => {});
                } else {
                    const field = document.createElement('textarea');
                    field.value = value;
                    field.style.position = 'fixed';
                    field.style.opacity = '0';
                    document.body.appendChild(field);
                    field.select();
                    try { document.execCommand('copy') } catch (e) {}
                    field.remove();
                    done();
                }
            "
            class="shrink-0 border border-neutral rounded-md px-3 text-sm text-base/70 hover:text-base hover:border-base/40 duration-300 cursor-pointer">{{ $copy }}</button>
    </div>
</div>
