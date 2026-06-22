<input 
    type="text" 
    class="{{ $inputClass }}"
    placeholder="{{ t('Search here') }}"
    wire:model="query"
    wire:keydown.enter="search"
>
