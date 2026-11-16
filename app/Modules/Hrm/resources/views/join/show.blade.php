{{-- GET /join/{user}?v=…&expires=…&signature=… (JoinController). No login: the invited person registers. --}}
<x-layouts.auth>
    <div class="flex flex-col gap-6">
        @if ($valid)
            <div class="flex w-full flex-col gap-2 text-center">
                <h1 class="text-xl font-medium dark:text-zinc-200">{{ __('Join :company', ['company' => $company->name]) }}</h1>
                <p class="text-sm dark:text-zinc-400">{{ __('Fill in your details and choose a password.') }}</p>
            </div>

            <form method="POST" action="{{ $actionUrl }}" class="flex flex-col gap-5">
                @csrf
                <flux:field>
                    <flux:label>{{ __('Email') }}</flux:label>
                    <flux:input name="email" type="email" :value="$user->email" readonly autocomplete="username" />
                    <flux:error name="email" />
                </flux:field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('First name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                        <flux:input aria-required="true" name="first_name" :value="old('first_name')" autofocus autocomplete="given-name" />
                        <flux:error name="first_name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Last name') }}</flux:label>
                        <flux:input name="last_name" :value="old('last_name')" autocomplete="family-name" />
                        <flux:error name="last_name" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>{{ __('Employee ID') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('The ID your company gave you, e.g. EMP-012.') }}</flux:description>
                    <flux:input aria-required="true" name="code" :value="old('code')" />
                    <flux:error name="code" />
                </flux:field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Mobile') }}</flux:label>
                        <flux:input name="mobile" :value="old('mobile')" autocomplete="tel" />
                        <flux:error name="mobile" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Gender') }}</flux:label>
                        <flux:select name="gender">
                            <flux:select.option value="">{{ __('Not set') }}</flux:select.option>
                            @foreach ($genders as $value => $label)
                                <flux:select.option :value="$value" :selected="(string) old('gender') === (string) $value">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="gender" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>{{ __('Date of birth') }}</flux:label>
                    <flux:input type="date" name="birth_date" :value="old('birth_date')" max="{{ now()->subDay()->toDateString() }}" />
                    <flux:error name="birth_date" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Password') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" name="password" type="password" autocomplete="new-password" viewable />
                    <flux:error name="password" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Confirm password') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" name="password_confirmation" type="password" autocomplete="new-password" viewable />
                    <flux:error name="password_confirmation" />
                </flux:field>

                <flux:button type="submit" variant="primary" class="w-full">{{ __('Register and log in') }}</flux:button>
            </form>
        @else
            <div class="flex w-full flex-col gap-2 text-center">
                <h1 class="text-xl font-medium dark:text-zinc-200">{{ __('This link no longer works') }}</h1>
                <p class="text-sm dark:text-zinc-400">{{ __('It was already used, it expired, or a newer invitation was sent. If you have registered, just log in. Otherwise ask your company to send the invitation again.') }}</p>
            </div>
            <flux:button :href="route('login')" variant="primary" class="w-full">{{ __('Go to login') }}</flux:button>
        @endif
    </div>
</x-layouts.auth>
