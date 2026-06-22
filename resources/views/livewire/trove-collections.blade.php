<div id="Collections-content" class="bg-lightgrey py-8 px-16">
    <div class="container mx-auto">
        @if($collections->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">

                @foreach ($collections as $collection)
                    <x-collection-result-card :item="$collection"/>
                @endforeach
            </div>
        @else
            <p class="text-gray-600">{{ t("No collections available for this resource.") }}</p>
        @endif
    </div>
</div>
