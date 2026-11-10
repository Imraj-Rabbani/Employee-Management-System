{{-- GET /join/{user}?v=…&expires=…&signature=… (JoinController). No login: the invited person registers. --}}
<x-layouts.auth>
    <div class="flex flex-col gap-6">
        @if ($valid)
            <x-auth-header :title="__('Join :company', ['company' => $company->name])" :description="__('Fill in your details and choose a password.')" />

            <form method="POST" action="{{ $actionUrl }}" class="flex flex-col gap-5">
                @csrf
                <x-form.input name="email" type="email" :value="$user->email" :label="__('Email')" readonly autocomplete="username" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.input name="first_name" :value="old('first_name')" :label="__('First name')" required autofocus autocomplete="given-name" />
                    <x-form.input name="last_name" :value="old('last_name')" :label="__('Last name')" autocomplete="family-name" />
                </div>

                <x-form.input name="code" :value="old('code')" :label="__('Employee ID')" :hint="__('The ID your company gave you, e.g. EMP-012.')" required />

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.input name="mobile" :value="old('mobile')" :label="__('Mobile')" autocomplete="tel" />
                    <x-form.field :label="__('Gender')" name="gender">
                        <flux:select name="gender">
                            <flux:select.option value="">{{ __('Not set') }}</flux:select.option>
                            @foreach ($genders as $value => $label)
                                <flux:select.option :value="$value" :selected="(string) old('gender') === (string) $value">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </x-form.field>
                </div>

                <x-form.date name="birth_date" :value="old('birth_date')" :label="__('Date of birth')" max="{{ now()->subDay()->toDateString() }}" />

                <x-form.input name="password" type="password" :label="__('Password')" required autocomplete="new-password" viewable />
                <x-form.input name="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password" viewable />

                <flux:button type="submit" variant="primary" class="w-full">{{ __('Register and log in') }}</flux:button>
            </form>
        @else
            <x-auth-header :title="__('This link no longer works')"
                :description="__('It was already used, it expired, or a newer invitation was sent. If you have registered, just log in. Otherwise ask your company to send the invitation again.')" />
            <flux:button :href="route('login')" variant="primary" class="w-full">{{ __('Go to login') }}</flux:button>
        @endif
    </div>
</x-layouts.auth>
