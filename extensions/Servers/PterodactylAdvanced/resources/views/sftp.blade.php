@php
    // Rendered inside the extension view container of the service page, which already
    // provides the card background. Only padding is added here.
    $rows = $sftp ? [
        ['label' => __('pterodactyladvanced::messages.sftp.address'), 'value' => 'sftp://' . $sftp['host'] . ':' . $sftp['port']],
        ['label' => __('pterodactyladvanced::messages.sftp.username'), 'value' => $sftp['username']],
    ] : [];

    $copy = __('pterodactyladvanced::messages.sftp.copy');
    $copied = __('pterodactyladvanced::messages.sftp.copied');
@endphp

<div class="p-4">
    <h4 class="text-lg font-semibold mb-1">{{ __('pterodactyladvanced::messages.sftp.title') }}</h4>
    <p class="text-base/60 text-sm mb-4">{{ __('pterodactyladvanced::messages.sftp.intro') }}</p>

    @if ($sftp)
        <div class="flex flex-col gap-2">
            @foreach ($rows as $row)
                <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3">
                    <span class="text-base/60 text-sm sm:w-28 shrink-0">{{ $row['label'] }}</span>
                    <div class="flex items-center gap-2 min-w-0 flex-1">
                        <code class="font-mono text-sm bg-primary-900/60 rounded px-2 py-1 truncate">{{ $row['value'] }}</code>
                        <button type="button"
                            data-copy="{{ $row['value'] }}"
                            data-copied="{{ $copied }}"
                            onclick="navigator.clipboard.writeText(this.dataset.copy);const t=this.textContent;this.textContent=this.dataset.copied;setTimeout(()=>this.textContent=t,1500)"
                            class="text-xs text-base/60 hover:text-base border border-base/20 rounded px-2 py-1 shrink-0">{{ $copy }}</button>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-base/70 text-sm">{{ __('pterodactyladvanced::messages.sftp.unavailable') }}</p>
    @endif

    @if ($password)
        <div class="mt-4 rounded border border-yellow-500/40 bg-yellow-500/10 p-3">
            <p class="text-sm font-semibold mb-1">{{ __('pterodactyladvanced::messages.sftp.new_password') }}</p>
            <p class="text-base/70 text-xs mb-2">{{ __('pterodactyladvanced::messages.sftp.new_password_hint') }}</p>
            <div class="flex items-center gap-2 min-w-0">
                <code class="font-mono text-sm bg-primary-900/60 rounded px-2 py-1 truncate flex-1">{{ $password }}</code>
                <button type="button"
                    data-copy="{{ $password }}"
                    data-copied="{{ $copied }}"
                    onclick="navigator.clipboard.writeText(this.dataset.copy);const t=this.textContent;this.textContent=this.dataset.copied;setTimeout(()=>this.textContent=t,1500)"
                    class="text-xs text-base/60 hover:text-base border border-base/20 rounded px-2 py-1 shrink-0">{{ $copy }}</button>
            </div>
        </div>
    @else
        <p class="text-base/60 text-xs mt-4">{{ __('pterodactyladvanced::messages.sftp.no_password_hint') }}</p>
    @endif
</div>
