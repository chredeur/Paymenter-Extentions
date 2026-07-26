@php
    $copy = __('pterodactyladvanced::messages.sftp.copy');
    $copied = __('pterodactyladvanced::messages.sftp.copied');
@endphp

<div class="p-6">
    <h4 class="text-lg font-semibold">{{ __('pterodactyladvanced::messages.sftp.title') }}</h4>
    <p class="text-base/50 text-sm mt-1">{{ __('pterodactyladvanced::messages.sftp.intro') }}</p>

    @if ($sftp)
        <div class="grid gap-3 mt-5 md:grid-cols-2">
            @include('pterodactyladvanced::partials.copyable', [
                'label' => __('pterodactyladvanced::messages.sftp.address'),
                'value' => 'sftp://' . $sftp['host'] . ':' . $sftp['port'],
                'copy' => $copy,
                'copied' => $copied,
            ])
            @include('pterodactyladvanced::partials.copyable', [
                'label' => __('pterodactyladvanced::messages.sftp.username'),
                'value' => $sftp['username'],
                'copy' => $copy,
                'copied' => $copied,
            ])
        </div>
    @else
        <p class="text-base/70 text-sm mt-5">{{ __('pterodactyladvanced::messages.sftp.unavailable') }}</p>
    @endif

    @if ($password)
        <div class="mt-5 rounded-lg border border-yellow-500/40 bg-yellow-600/20 p-4">
            <p class="text-sm font-semibold text-yellow-300">{{ __('pterodactyladvanced::messages.sftp.new_password') }}</p>
            <p class="text-yellow-300/70 text-sm mt-1 mb-3">{{ __('pterodactyladvanced::messages.sftp.new_password_hint') }}</p>
            @include('pterodactyladvanced::partials.copyable', [
                'label' => __('pterodactyladvanced::messages.sftp.password'),
                'value' => $password,
                'copy' => $copy,
                'copied' => $copied,
            ])
        </div>
    @endif

    {{--
        Plain form posting to the extension route rather than a Livewire action, so the
        confirmation step is fully under our control and the reset can never be triggered
        by a stray navigation.
    --}}
    <form method="POST"
        action="{{ route('extensions.servers.pterodactyladvanced.sftp-password', $service) }}"
        x-data="{ confirming: false }"
        class="mt-6">
        @csrf

        <div x-show="!confirming">
            <button type="button" @click="confirming = true"
                class="flex items-center gap-2 justify-center bg-primary text-white text-sm font-semibold hover:bg-primary/80 py-2 px-4.5 rounded-md duration-300 cursor-pointer">
                {{ __('pterodactyladvanced::messages.sftp.generate') }}
            </button>
        </div>

        <div x-show="confirming" style="display: none"
            class="rounded-lg border border-neutral bg-background p-4">
            <p class="text-sm font-semibold">{{ __('pterodactyladvanced::messages.sftp.confirm_title') }}</p>
            <p class="text-base/50 text-sm mt-1 mb-4">{{ __('pterodactyladvanced::messages.sftp.confirm_body') }}</p>
            <div class="flex flex-wrap gap-2">
                <button type="submit"
                    class="bg-primary text-white text-sm font-semibold hover:bg-primary/80 py-2 px-4.5 rounded-md duration-300 cursor-pointer">
                    {{ __('pterodactyladvanced::messages.sftp.confirm') }}
                </button>
                <button type="button" @click="confirming = false"
                    class="border border-neutral text-sm text-base/70 hover:text-base hover:border-base/40 py-2 px-4.5 rounded-md duration-300 cursor-pointer">
                    {{ __('pterodactyladvanced::messages.sftp.cancel') }}
                </button>
            </div>
        </div>
    </form>
</div>
