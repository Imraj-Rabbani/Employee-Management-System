{{--
    Self Service → My Attendance (WebPunch). "Punch now" asks the browser for its location first
    (navigator.geolocation), then sends it to the server, which does all the checks.
--}}
<div class="space-y-4"
    x-data="{
        locating: false,
        needsLocation: @js($needsLocation),
        punch() {
            if (! navigator.geolocation) {
                return this.needsLocation ? $dispatch('toast', { message: @js(__('This browser cannot share its location.')), variant: 'danger' }) : $wire.punch();
            }
            this.locating = true;
            navigator.geolocation.getCurrentPosition(
                (pos) => { this.locating = false; $wire.punch(pos.coords.latitude, pos.coords.longitude) },
                () => {
                    this.locating = false;
                    this.needsLocation
                        ? $dispatch('toast', { message: @js(__('Your location is needed to punch. Allow location access in the browser and try again.')), variant: 'danger' })
                        : $wire.punch();
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
            );
        },
    }"
>
    @if (! $employee)
        <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
            <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                <flux:icon name="user" />
            </div>
            <flux:heading size="lg" class="mt-4">{{ __('No employee record') }}</flux:heading>
            <flux:text class="mt-1 max-w-sm">{{ __('Your login is not linked to an employee, so there is no attendance to show.') }}</flux:text>
        </div>
    @else
        <div class="glass overflow-hidden rounded-2xl">
            <div class="p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ now()->translatedFormat('l, j F') }}</flux:heading>
                        <flux:text>
                            @if ($shift)
                                {{ __('Shift: :shift', ['shift' => $shift->optionLabel()]) }}
                                @if ($shift->geofence_enabled)
                                    · {{ __('punch within :m m of your workplace', ['m' => number_format((int) $shift->geofence_radius_m)]) }}
                                @endif
                            @else
                                {{ __('No shift planned for today.') }}
                            @endif
                        </flux:text>
                    </div>
                    @if ($enabled)
                        <flux:button variant="primary" icon="finger-print" x-on:click="punch()" x-bind:disabled="locating" wire:loading.attr="disabled" wire:target="punch">
                            <span x-show="! locating">{{ __('Punch now') }}</span>
                            <span x-show="locating" x-cloak>{{ __('Finding your location…') }}</span>
                        </flux:button>
                    @else
                        <flux:text class="text-sm">{{ __('Web punch is switched off for your company. Use the attendance device.') }}</flux:text>
                    @endif
                </div>
            </div>
        </div>

        <div class="glass overflow-hidden rounded-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
                <div>
                    <flux:heading size="lg">{{ __('My punches, last 7 days') }}</flux:heading>
                </div>
            </div>

            <div class="p-6">
                @forelse ($days as $date => $punches)
                    <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1 border-b border-zinc-100 py-2 last:border-0 dark:border-white/5">
                        <div class="w-36 font-medium">{{ format_date($date) }}</div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($punches as $punch)
                                <x-ui.badge :title="$punch->source->label()">{{ $punch->punched_at->format('H:i') }}</x-ui.badge>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <flux:text>{{ __('No punches in the last 7 days.') }}</flux:text>
                @endforelse
            </div>
        </div>
    @endif
</div>
