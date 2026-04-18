@php
    use Illuminate\View\ComponentAttributeBag;
@endphp

@props([
    'debounce' => '500ms',
    'onBlur' => false,
    'placeholder' => __('filament-tables::table.fields.search.placeholder'),
    'wireModel' => 'tableSearch',
])

<div
    x-id="['input']"
    x-data="{ search: @entangle($wireModel) }"
    {{ $attributes->class(['fi-ta-search-field']) }}
    style="display: flex; flex-direction: row; align-items: center; gap: 0.5rem;"
>
    <label x-bind:for="$id('input')" class="fi-sr-only">
        {{ __('filament-tables::table.fields.search.label') }}
    </label>

    <x-filament::input.wrapper
        :wire:target="$wireModel"
    >
        <x-filament::input
            :attributes="
                (new ComponentAttributeBag)->merge([
                    'autocomplete' => 'off',
                    'maxlength' => 1000,
                    'placeholder' => $placeholder,
                    'type' => 'search',
                    'wire:key' => $this->getId() . '.table.' . $wireModel . '.field.input',
                    'x-model' => 'search',
                    'x-bind:id' => '$id(\'input\')',
                    'x-on:keydown.enter.prevent' => '$wire.set(\'' . $wireModel . '\', search)',
                ], escape: false)
            "
        />
    </x-filament::input.wrapper>

    <x-filament::button
        color="primary"
        size="sm"
        x-on:click="$wire.set('{{ $wireModel }}', search)"
        style="white-space: nowrap; flex-shrink: 0;"
    >
        Search
    </x-filament::button>
</div>
