<div>

    <!-- Topics filters -->
    <div class="flex flex-row w-full h-full justify-between md:gap-12 mt-4 ">
        <div class="bg-none w-6 flex-shrink-0 h-auto"></div>
        <div class="h-auto w-full max-w-7xl py-3 pr-4 pl-10 md:pl-12">

            <h3 class="text-black text-lg uppercase font-medium">
                {{ \App\Models\SiteContent::get('home_ifa_topics_heading') }}
            </h3>

        </div>
        <div class="bg-none w-6 flex-shrink-0 h-auto"></div>
    </div>

    <div class="w-full flex justify-center py-6 px-6 md:px-12">
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-8 justify-left max-w-7xl md:px-12">
            @php $featureCount = 1 @endphp
            @foreach ($this->FeatureTopics as $t)
            @if ($featureCount < 16)

                <label
                    onclick="document.getElementById('results').scrollIntoView({ behavior: 'smooth', block: 'start' })"
                    class="hover-effect cursor-pointer flex justify-between items-center relative bg-brand-secondary rounded-full  overflow-hidden group sm:max-w-[20rem] min-w-[80vw] sm:min-w-[13rem] text-black text-xs md:text-sm md:h-16 px-6 py-3"
                    wire:click="clearAndFilter('topic', {{ $t['id'] }})">
                    {{ $t['name'] }}
                    <input type="checkbox" class="hidden" value="{{ $t['id'] }}"/>
                    <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" class="h-6 w-6" stroke-miterlimit="2"  viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="m14.523 18.787s4.501-4.505 6.255-6.26c.146-.146.219-.338.219-.53s-.073-.383-.219-.53c-1.753-1.754-6.255-6.258-6.255-6.258-.144-.145-.334-.217-.524-.217-.193 0-.385.074-.532.221-.293.292-.295.766-.004 1.056l4.978 4.978h-14.692c-.414 0-.75.336-.75.75s.336.75.75.75h14.692l-4.979 4.979c-.289.289-.286.762.006 1.054.148.148.341.222.533.222.19 0 .378-.072.522-.215z" fill-rule="nonzero"/></svg>
                </label>
                @php $featureCount++ @endphp
              @endif
            @endforeach
        </div>
    </div>

    <!-- Institutions and syllabi filters 2 cols-->
    <div class="w-full flex justify-center py-6">
        <div class="w-full flex flex-col lg:flex-row gap-8 sm:gap-16 justify-between w-screen max-w-7xl p-12 ">
            <div class="lg:w-1/2">
                <h3 class="text-black text-lg uppercase font-medium">
                    {{ \App\Models\SiteContent::get('home_ifa_institutions_heading') }}
                </h3>
                <div class="sm:grid sm:grid-cols-2 gap-6  mt-4 sm:mt-12 items-left">
                    @foreach ($this->institutions as $i)
                        <label
                            onclick="document.getElementById('results').scrollIntoView({ behavior: 'smooth', block: 'start' })"
                            class="hover-effect cursor-pointer relative bg-brand-primary flex flex-row sm:flex-col justify-between sm:justify-around text-left rounded-t-[1.5rem] rounded-bl-[1.5rem] overflow-hidden group  min-w-[14rem] text-white text-sm px-6 py-4 sm:h-[9rem] mb-4"
                            wire:click="clearAndFilter('institution', {{ $i['id'] }})">
                            <div>
                                <h3> {{ $i['name'] }}</h3>
                            </div>
                            <input type="checkbox" class="hidden" value="{{ $i['id'] }}"/>
                            <div class="sm:w-full ">
                                <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" class="h-6 w-6" stroke-miterlimit="2" fill="white" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="m14.523 18.787s4.501-4.505 6.255-6.26c.146-.146.219-.338.219-.53s-.073-.383-.219-.53c-1.753-1.754-6.255-6.258-6.255-6.258-.144-.145-.334-.217-.524-.217-.193 0-.385.074-.532.221-.293.292-.295.766-.004 1.056l4.978 4.978h-14.692c-.414 0-.75.336-.75.75s.336.75.75.75h14.692l-4.979 4.979c-.289.289-.286.762.006 1.054.148.148.341.222.533.222.19 0 .378-.072.522-.215z" fill-rule="nonzero"/></svg>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="lg:w-1/2">
                <h3 class="text-black text-lg uppercase font-medium">
                    {{ \App\Models\SiteContent::get('home_ifa_syllabi_heading') }}
                </h3>
                <div class="sm:grid sm:grid-cols-2 sm:gap-6 gap-4 mt-4 sm:mt-12">
                    @foreach ($this->levels as $l)
                        <label
                            onclick="document.getElementById('results').scrollIntoView({ behavior: 'smooth', block: 'start' })"
                            class="hover-effect cursor-pointer relative bg-brand-primary flex flex-row sm:flex-col justify-between sm:justify-around rounded-t-[1.5rem] rounded-bl-[1.5rem] overflow-hidden group  min-w-[14rem] text-white text-sm px-6 py-4 sm:h-[9rem] mb-4"
                            wire:click="clearAndFilter('level', {{ $l['id'] }})">
                            <h3> {{ $l['name'] }}</h3>
                            <input type="checkbox" class="hidden" value="{{ $l['id'] }}"/>
                            <div class="sm:w-full">
                                <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" class="h-6 w-6" stroke-miterlimit="2" fill="white" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="m14.523 18.787s4.501-4.505 6.255-6.26c.146-.146.219-.338.219-.53s-.073-.383-.219-.53c-1.753-1.754-6.255-6.258-6.255-6.258-.144-.145-.334-.217-.524-.217-.193 0-.385.074-.532.221-.293.292-.295.766-.004 1.056l4.978 4.978h-14.692c-.414 0-.75.336-.75.75s.336.75.75.75h14.692l-4.979 4.979c-.289.289-.286.762.006 1.054.148.148.341.222.533.222.19 0 .378-.072.522-.215z" fill-rule="nonzero"/></svg>
                            </div>
                        </label>
                    @endforeach

                </div>
            </div>
        </div>
    </div>

    <!-- template browse all -->
     <div id="results">
        <div class="">
            <div class="flex flex-col lg:flex-row lg:gap-12">

                <!-- Sidebar (Search & Filters) -->
                <div class="lg:min-w-[280px] w-full lg:w-2/12 bg-[#f4f4f4] lg:bg-brand-bg self-start lg:pl-12 px-8 py-6 lg:py-8 ">
                    <div class="pb-4 sm:pb-0 lg:pb-4 sm:hidden lg:block">
                        <div class="pb-4 sm:pb-0 lg:pb-4 text-xl font-bold">{{ t('Search and filter') }}</div>
                        <div class="divider hidden lg:block"></div>
                    </div>

                    <!-- Search input -->
                    <div class="relative flex items-center mb-6">
                        <input
                            type="text"
                            class="w-full py-2 pl-12 pr-4 bg-gray-200 border-none rounded-full focus:outline-none transition duration-300 focus:bg-gray-100 focus:ring-0 text-gray-700"
                            placeholder="{{ t('Search here') }}"
                            aria-label="{{ t('Search resources and collections') }}"
                            wire:model.live.debounce.400ms="query"
                        >

                        <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-5 h-5 text-gray-600">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                            </svg>
                        </div>

                        <!-- Clear Button -->
                        @if($query)
                            <button
                                type="button"
                                wire:click="clearSearch"
                                aria-label="{{ t('Clear search') }}"
                                class="absolute right-3 top-1/2 transform -translate-y-1/2"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="gray" class="w-5 h-5 cursor-pointer hover:stroke-gray-700 transition-colors duration-200">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        @endif
                    </div>
                <div class="flex flex-col sm:flex-row lg:flex-col sm:mb-2 sm:mt-4 lg:my-0">
                <div class="pb-4 sm:pb-0 ml-2 mr-16 lg:pb-4 hidden sm:block lg:hidden">
                        <div class="pb-4 sm:pb-0 lg:pb-4 text-xl font-bold">{{ t('Filters:') }}</div>
                        <div class="divider hidden lg:block"></div>
                    </div>
                    <!-- Language Filter - hidden via the "Show language filter" site option or when only one locale is configured -->
                    @if(config('branding.features.show_language_filter', true) && count(config('branding.locales', ['en' => 'English'])) > 1)
                    <div class="" x-data="window.innerWidth >= 1024 ? { open: true } : { open: false }">
                        <div class="border-t border-gray-400 sm:border-0 lg:border-t mb-6 sm:my-0 lg:mb-6"></div>
                        <div class="flex justify-between items-center cursor-pointer" @click="open = !open">
                            <label class="text-base lg:font-bold">{{ t("Language:") }}</label>
                            <svg class="w-5 h-5 ml-2 transition-transform duration-300" :class="open ? 'rotate-90' : '-rotate-90'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </div>
                        <div class="space-y-2 mt-2 text-sm" x-show="open">
                            @foreach(collect(config('branding.locales', ['en' => 'English']))->sort() as $code => $label)
                                @php
                                    $localeCount = $localeCounts[$code] ?? 0;
                                    $localeSelected = in_array($code, $selectedLanguages);
                                    $localeMuted = $facetsAvailable && $localeCount === 0 && !$localeSelected;
                                @endphp
                                <label class="flex items-center {{ $localeMuted ? 'text-gray-400' : '' }}">
                                    <input type="checkbox" wire:model.live="selectedLanguages" value="{{ $code }}" class="mr-2 accent-brand-primary text-brand-primary focus:ring-brand-primary"/>
                                    <span class="flex-1">{{ $label }}</span>
                                    @if($facetsAvailable)
                                        <span class="ml-2 text-xs {{ $localeMuted ? 'text-gray-400' : 'text-gray-500' }}">{{ $localeCount }}</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Resource Type Filter - hidden via the "Show resource type filter" site option or when no resource types are configured -->
                    @if(config('branding.features.show_trove_type_filter', false) && $filterTroveTypes->isNotEmpty())
                    <div class="sm:ml-6 lg:ml-0" x-data="window.innerWidth >= 1024 ? { open: true } : { open: false }">
                        <div class="border-t border-gray-400 sm:border-0 lg:border-t my-6 sm:my-0 lg:my-6"></div>
                        <div class="flex justify-between items-center cursor-pointer" @click="open = !open">
                            <label class="text-base lg:font-bold">{{ t('Type:') }}</label>
                            <svg class="w-5 h-5 ml-2 transition-transform duration-300" :class="open ? 'rotate-90' : '-rotate-90'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </div>
                        <div class="space-y-2 mt-4 text-sm" x-show="open">
                            @foreach($filterTroveTypes as $filterTroveType)
                                @php
                                    $troveTypeCount = $troveTypeCounts[$filterTroveType->id] ?? 0;
                                    $troveTypeSelected = in_array($filterTroveType->id, array_map('intval', $selectedTroveTypes));
                                    $troveTypeMuted = $facetsAvailable && $troveTypeCount === 0 && !$troveTypeSelected;
                                @endphp
                                <label class="flex items-center rounded cursor-pointer {{ $troveTypeMuted ? 'text-gray-400' : '' }}">
                                    <input type="checkbox"
                                        wire:model.live="selectedTroveTypes"
                                        value="{{ $filterTroveType->id }}"
                                        class="mr-2 accent-brand-primary text-brand-primary focus:ring-brand-primary"/>
                                    <span class="flex-1">{{ $filterTroveType->label }}</span>
                                    @if($facetsAvailable)
                                        <span class="ml-2 text-xs {{ $troveTypeMuted ? 'text-gray-400' : 'text-gray-500' }}">{{ $troveTypeCount }}</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Tag Type Filters (configured via admin panel) -->
                    @foreach($filterTagTypes as $filterTagType)
                    <div class="sm:ml-6 lg:ml-0" x-data="window.innerWidth >= 1024 ? { open: true } : { open: false }">
                        <div class="border-t border-gray-400 sm:border-0 lg:border-t my-6 sm:my-0 lg:my-6"></div>
                        <div class="flex justify-between items-center cursor-pointer" @click="open = !open">
                            <label class="text-base lg:font-bold">{{ $filterTagType->label }}:</label>
                            <svg class="w-5 h-5 ml-2 transition-transform duration-300" :class="open ? 'rotate-90' : '-rotate-90'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </div>
                        <div class="space-y-2 mt-4 text-sm" x-show="open">
                            @foreach($filterTagType->tags as $tag)
                                @php
                                    $tagCount = $tagCounts[$tag->id] ?? 0;
                                    $tagSelected = in_array($tag->id, array_map('intval', $selectedTagsByType[$filterTagType->id] ?? []));
                                    $tagMuted = $facetsAvailable && $tagCount === 0 && !$tagSelected;
                                @endphp
                                <label class="flex items-center rounded cursor-pointer {{ $tagMuted ? 'text-gray-400' : '' }}">
                                    <input type="checkbox"
                                        wire:model.live="selectedTagsByType.{{ $filterTagType->id }}"
                                        value="{{ $tag->id }}"
                                        class="mr-2 accent-brand-primary text-brand-primary focus:ring-brand-primary"/>
                                    <span class="flex-1">{{ $tag->name }}</span>
                                    @if($facetsAvailable)
                                        <span class="ml-2 text-xs {{ $tagMuted ? 'text-gray-400' : 'text-gray-500' }}">{{ $tagCount }}</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
                </div>

                <!-- Resources and Collections Cards -->
                <div class="flex-1">
                    <div class="p-8">
                        @php
                            $hasActiveFilters = $query || !empty($selectedLanguages) || !empty($selectedTroveTypes) || collect($selectedTagsByType)->flatten()->isNotEmpty();
                        @endphp
                        @if($searchUnavailable)
                            {{ t("Text-based search is temporarily unavailable. You can still browse resources below, and use the filters to narrow your search.") }}
                        @endif
                        @if($totalHits === 0)
                            @if($hasActiveFilters)
                                {{ t("No resources or collections match your search or filters.") }}
                            @else
                                {{ t("No resources or collections have been added yet.") }}
                            @endif
                        @else
                            {{ t("Showing ") . $startOfPage . ' - ' . $endOfPage . ' ' . t("out of") . ' ' . $totalHits . t(" resources and collections") }}
                        @endif
                        @if($hasActiveFilters)
                            <button type="button" wire:click="clearFilters" class="text-gray-500 hover:text-gray-700 underline text-sm">
                                {{ t("Clear Filters") }}
                            </button>
                        @endif
                    </div>

                    <div id="Items-content" class="p-8 rounded-lg">
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-8 max-w-6xl mx-auto">
                            @foreach ($items as $item)
                                {{-- display:contents keeps the cards as effective grid children --}}
                                <div wire:key="{{ $item['type'] }}-{{ $item['id'] }}" class="contents">
                                    @if($item['type'] === 'resource')
                                        <x-resource-result-card :item="$item" color="brand-secondary" textcol="white" :show-tags="false"/>
                                    @elseif($item['type'] === 'collection')
                                        <x-collection-result-card :item="$item"/>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @if($totalPages > 1)
                    <div class="max-w-6xl mx-auto my-5">
                        <nav class="rounded-md shadow-xs flex w-full justify-end" aria-label="{{ t('Pagination') }}">
                            <button
                                type="button"
                                class="py-2 px-4 rounded-full {{ $page === 1 ? 'bg-gray-50 text-gray-400' : 'bg-white hover:text-brand-primary' }}"
                                wire:click="goToPage(1)"
                                x-on:click="window.scrollTo({ top: 0, behavior: 'smooth' })"
                                aria-label="{{ t('First page') }}"
                                @disabled($page === 1)
                            >
                                {{ t('First') }}
                            </button>

                            <button
                                type="button"
                                class="py-2 px-4 rounded-full {{ $page === 1 ? 'bg-gray-50 text-gray-400' : 'bg-white hover:text-brand-primary' }}"
                                wire:click="goToPage({{ $page - 1 }})"
                                x-on:click="window.scrollTo({ top: 0, behavior: 'smooth' })"
                                aria-label="{{ t('Previous page') }}"
                                @disabled($page === 1)
                            >
                                {{ t('Previous') }}
                            </button>

                            @if(($pageWindow[0] ?? 1) > 1)
                                <span class="py-2 px-2" aria-hidden="true">&hellip;</span>
                            @endif

                            @foreach($pageWindow as $pageNumber)
                                <button
                                    type="button"
                                    class="py-2 px-4 rounded-full {{ $page === $pageNumber ? 'text-white bg-brand-primary' : 'text-black hover:text-brand-primary' }}"
                                    wire:click="goToPage({{ $pageNumber }})"
                                    x-on:click="window.scrollTo({ top: 0, behavior: 'smooth' })"
                                    aria-label="{{ t('Page') }} {{ $pageNumber }}"
                                    @if($page === $pageNumber) aria-current="page" @endif
                                >{{ $pageNumber }}</button>
                            @endforeach

                            @if((end($pageWindow) ?: $totalPages) < $totalPages)
                                <span class="py-2 px-2" aria-hidden="true">&hellip;</span>
                            @endif

                            <button
                                type="button"
                                class="py-2 px-4 rounded-full {{ $page === $totalPages ? 'bg-gray-50 text-gray-400' : 'bg-white hover:text-brand-primary' }}"
                                wire:click="goToPage({{ $page + 1 }})"
                                x-on:click="window.scrollTo({ top: 0, behavior: 'smooth' })"
                                aria-label="{{ t('Next page') }}"
                                @disabled($page === $totalPages)
                            >
                                {{ t('Next') }}
                            </button>

                            <button
                                type="button"
                                class="py-2 px-4 rounded-full {{ $page === $totalPages ? 'bg-gray-50 text-gray-400' : 'bg-white hover:text-brand-primary' }}"
                                wire:click="goToPage({{ $totalPages }})"
                                x-on:click="window.scrollTo({ top: 0, behavior: 'smooth' })"
                                aria-label="{{ t('Last page') }}"
                                @disabled($page === $totalPages)
                            >
                                {{ t('Last') }}
                            </button>
                        </nav>
                    </div>
                    @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


</div>
