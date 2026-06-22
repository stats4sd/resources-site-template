<div class="bg-lightgrey py-8 px-16">
    @if ($relatedTroves->isNotEmpty())
        <div class="container mx-auto">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
            @php $relatedCount = 0 @endphp
                @foreach ($relatedTroves as $resource)
                @if ($relatedCount < 9) 
                    <x-resource-result-card :item="$resource" :show-tags="false"/>
                @php $relatedCount ++ @endphp
                @endif    
                @endforeach
            </div>
        </div>
    @else
        <p class="text-gray-500">{{ t("No related resources found.") }}</p>
    @endif
</div>
