<x-layouts.app :title="__('My Profile')">
    <x-ui.card>
        <x-ui.empty icon="user" :heading="__('Your login is not linked to an employee record')"
            :description="__('Ask HR to link it (Employees → profile → Login).')" />
    </x-ui.card>
</x-layouts.app>
