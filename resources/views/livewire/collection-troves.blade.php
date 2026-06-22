<div id="Resources-content" class="bg-lightgrey py-8 px-16">
    <div class="container mx-auto">
        @if ($resources->isNotEmpty())

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach ($resources as $resource)
                    @if (isset($origin))
                        <x-resource-result-card :item="$resource" color="ifa-yellow" textcol="black" target="_self"
                            :show-tags="false" origin="ifa" />
                    @else
                        <x-resource-result-card :item="$resource" />
                    @endif
                @endforeach
            </div>
        @else
            <p class="text-gray-600">{{ t('No resources available for this collection.') }}</p>
        @endif
    </div>
</div>
