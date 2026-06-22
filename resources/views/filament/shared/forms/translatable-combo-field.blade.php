<x-filament::section
    :aside="false"
    :collapsed="$isCollapsed()"
    :collapsible="$isCollapsible()"
    :compact="$isCompact()"
    :content-before="false"
    :description="$getDescription()"
    :footer-actions="$getFooterActions()"
    :footer-actions-alignment="$getFooterActionsAlignment()"
    :header-actions="$getHeaderActions()"
    :heading="$getHeading()"
    :icon="$getIcon()"
    :icon-color="$getIconColor()"
    :icon-size="$getIconSize()"
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
