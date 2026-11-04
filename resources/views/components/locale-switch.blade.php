{{-- shortcut: a button to the other language, make it a select when a third interface language comes --}}
@php($other = collect(config('app.locales'))->except(app()->getLocale()))
<form method="POST" action="{{ route('locale') }}">
    @csrf
    <input type="hidden" name="locale" value="{{ $other->keys()->first() }}">
    <button type="submit" lang="{{ $other->keys()->first() }}" {{ $attributes }}>{{ $other->first() }}</button>
</form>
