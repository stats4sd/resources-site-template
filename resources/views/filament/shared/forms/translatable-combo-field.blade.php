<x-filament::section
    :aside="false"
    :collapsed="$isCollapsed()"
    :collapsible="$isCollapsible()"
    :compact="$isCompact()"
    :content-before="false"
    :description="$getDescription()"
    :heading="$getHeading()"
    :icon="$getIcon()"
    :icon-color="$getIconColor()"
    :persist-collapsed="$shouldPersistCollapsed()"
    :attributes="
        \Filament\Support\prepare_inherited_attributes($attributes)
            ->merge([
                'id' => $getId(),
            ], escape: false)
            ->merge($getExtraAttributes(), escape: false)
            ->merge($getExtraAlpineAttributes(), escape: false)
    "
>

    {{ $getChildComponentContainer() }}
</x-filament::section>
