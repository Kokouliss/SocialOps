<div>
    <x-input-label for="name" :value="__('Name')" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autocomplete="name" />
    <x-input-error class="mt-2" :messages="$errors->get('name')" />
</div>
<div>
    <x-input-label for="email" :value="__('Email')" />
    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required autocomplete="username" />
    <x-input-error class="mt-2" :messages="$errors->get('email')" />
</div>
<div>
    <x-input-label for="password" :value="__('Password (at least 12 characters)')" />
    <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
    <x-input-error class="mt-2" :messages="$errors->get('password')" />
</div>
