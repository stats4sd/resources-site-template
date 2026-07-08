<div>
    <div class="h-4 bg-brand-bg"></div>
    <div class="relative">
    <!-- Background Image-->
    {{-- Banner image: replace public/images/banner.png with your own image --}}
    @if(file_exists(public_path('images/banner.png')))
        <img src="{{ asset('images/banner.png') }}" alt="Background Image" class="absolute inset-0 w-full h-[400px] sm:h-[35vh] object-cover filter brightness-50 z-0">
    @endif

    <!-- Overlay Content -->
    <div class="relative z-10 flex flex-col items-center justify-center  h-[400px] sm:h-[35vh] px-8 sm:px-20 xl:px-4 text-white">
        <div class="max-w-3xl w-full mx-auto text-center">
            <!-- Heading -->
            <div class="font-bold text-left md:text-center text-4xl sm:text-5xl md:text-5xl">
                {{ \App\Models\SiteContent::get('library_heading_line1') }} {{ \App\Models\SiteContent::get('library_heading_line2') }}
            </div>

            <!-- Description -->
            <div class="mt-6 text-left md:text-center pr-2 mx-auto">
                <p class="mb-4 text-xl">{{ \App\Models\SiteContent::get('library_hero_description') }}
                </p>
            </div>
        </div>
    </div>

    <div class="">
        <div class="flex flex-col lg:flex-row lg:gap-12">

            <!-- Sidebar (Search & Filters) -->
            <div class="lg:min-w-[280px] w-full lg:w-2/12 bg-[#f4f4f4] lg:bg-brand-bg self-start lg:pl-12 px-8 py-6 lg:py-8 ">
                <div class="pb-4 sm:pb-0 lg:pb-4 sm:hidden lg:block">
                    <div class="pb-4 sm:pb-0 lg:pb-4 text-xl font-bold">{{ t('Search and filter') }}</div>
                    <div class="divider hidden lg:block"></div>
                </div>  

                <!-- Search bar -->
                <div class="relative flex items-center mb-6">
                    <livewire:search-bar
                        inputClass="w-full py-2 pl-12 pr-4 bg-gray-200 border-none rounded-full focus:outline-none transition
                        duration-300 focus:bg-gray-100 focus:ring-0 text-gray-700"/>

                    <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-5 h-5 text-gray-600">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                        </svg>
                    </div>

                    <!-- Clear Button -->
                    @if($query)
                        <svg xmlns="http://www.w3.org/2000/svg" wire:click="clearSearch" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="gray" class="absolute right-3 top-1/2 transform -translate-y-1/2 w-5 h-5 cursor-pointer hover:stroke-gray-700 transition-colors duration-200">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
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
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="selectedLanguages" value="{{ $code }}" wire:change="search" class="mr-2 accent-brand-primary"/>
                                {{ $label }}
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
                            <label class="flex items-center rounded cursor-pointer">
                                <input type="checkbox"
                                    wire:model="selectedTagsByType.{{ $filterTagType->id }}"
                                    value="{{ $tag->id }}"
                                    class="mr-2 accent-brand-primary"
                                    wire:change="search"/>
                                {{ $tag->name }}
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
                        $hasActiveFilters = $query || !empty($selectedLanguages) || collect($selectedTagsByType)->flatten()->isNotEmpty();
                    @endphp
                    @if($searchUnavailable)
                        {{ t("Search is temporarily unavailable. Please try again later.") }}
                    @elseif($totalResourcesAndCollections === 0)
                        @if($hasActiveFilters)
                            {{ t("No resources or collections match your search or filters.") }}
                        @else
                            {{ t("No resources or collections have been added yet.") }}
                        @endif
                    @else
                        {{ t("Showing ") . $this->startOfPage . ' - ' . $this->endOfPage . ' ' . t("out of") . ' ' . $totalResourcesAndCollections . t(" resources and collections") }}
                    @endif
                    @if($hasActiveFilters)
                        <button wire:click="clearFilters" class="text-gray-500 hover:text-gray-700 underline text-sm">
                            {{ t("Clear Filters") }}
                        </button>
                    @endif
                </div>

                <div id="Items-content" class="p-8 rounded-lg">
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-8 max-w-6xl mx-auto">
                        @foreach ($this->renderedItems as $item)
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


                @if($pageCount > 1)
                <div class="max-w-6xl mx-auto my-5">
                        <nav class="rounded-md shadow-xs flex w-full justify-end" aria-label="Pagination" x-data="{currentPage: $wire.entangle('currentPage')}">

                            <button
                                :class="currentPage===1 ? 'bg-gray-50' : 'bg-white hover:text-brand-secondary'"
                                class="py-2 px-4 rounded-full"
                                x-on:click="$wire.loadPage(currentPage-1); window.scrollTo({ top: 0, behavior: 'smooth' });"
                                {{ $currentPage === 1 ? 'disabled="disabled"' : '' }}
                            >
                                Previous
                            </button>

                            @for($i=1; $i<=$pageCount; $i++)
                                <button
                                    :class="currentPage==={{$i}} ? 'text-white bg-brand-primary' : 'text-black hover:text-brand-primary'"
                                    class="py-2 px-4 rounded-full"

                                    x-on:click="$wire.loadPage({{$i}}); window.scrollTo({ top: 0, behavior: 'smooth' });"
                                >{{ $i }}</button>
                            @endfor

                            <button
                                :class="currentPage==={{$pageCount}} ? 'bg-gray-50' : 'bg-white hover:text-brand-primary'"
                                class="py-2 px-4 rounded-full"
                                x-on:click="$wire.loadPage(currentPage+1); window.scrollTo({ top: 0, behavior: 'smooth' });"
                                {{ $currentPage === $pageCount ? 'disabled="disabled"' : ''}}
                            >
                                Next
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
