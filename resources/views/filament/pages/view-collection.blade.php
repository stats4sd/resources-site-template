<x-filament-panels::page
    @class([
        'fi-resource-view-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>

    @php
        $relationManagers = $this->getRelationManagers();
    @endphp

    {{ $this->infolist }}

    @if($showAllTroves)
        <livewire:all-troves-table
            :record="$record"
            :resource="$this->getResource()"
            :active-locale="$activeLocale ?? null"
        />
    @else
        <x-filament-panels::resources.relation-managers
            :active-locale="$activeLocale ?? null"
            :active-manager="$this->activeRelationManager ?? array_key_first($relationManagers)"
            :content-tab-label="$this->getContentTabLabel()"
            :content-tab-icon="$this->getContentTabIcon()"
            :content-tab-position="$this->getContentTabPosition()"
            :managers="$relationManagers"
            :owner-record="$record"
            :page-class="static::class"
        >
        </x-filament-panels::resources.relation-managers>
    @endif
</x-filament-panels::page>
