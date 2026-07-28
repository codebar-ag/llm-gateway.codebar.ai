@if($enabled)
    <div id="{{ $nameFieldName }}_wrap" @if($withCsp) @cspNonce @endif style="display: none" aria-hidden="true">
        <input id="{{ $nameFieldName }}"
               name="{{ $nameFieldName }}"
               type="text"
               value=""
               @if ($livewireModel ?? false) wire:model.defer="{{ $livewireModel }}.{{ $unrandomizedNameFieldName }}" @endif
               autocomplete="off"
               tabindex="-1"
               inputmode="none"
               data-lpignore="true"
               data-1p-ignore
               data-bwignore>
        <input name="{{ $validFromFieldName }}"
               type="text"
               value="{{ $encryptedValidFrom }}"
               @if ($livewireModel ?? false) wire:model.defer="{{ $livewireModel }}.{{ $validFromFieldName }}" @endif
               autocomplete="off"
               tabindex="-1"
               inputmode="none"
               data-lpignore="true"
               data-1p-ignore
               data-bwignore>
    </div>
@endif
