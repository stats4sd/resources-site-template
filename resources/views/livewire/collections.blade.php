<div class="relative">
    <!-- Background Image -->
    <img src="images/crops.png" alt="Background Image"
         class="absolute inset-0 w-full h-[500px] sm:h-[40vh] object-cover filter brightness-[65%] z-0">

    <!-- Overlay Content -->
    <div class="relative z-10 flex flex-col items-center justify-center h-[500px] sm:h-[40vh] px-8 sm:px-20 xl:px-4 text-white">
        <div class="max-w-5xl w-full mx-auto text-left">
            <!-- Heading -->
            <div class="font-bold text-4xl sm:text-5xl md:text-5xl">
                {{ t('Search Collections') }}
            </div>

            <!-- Description -->
            <div class="mt-6 text-left pr-2 mx-auto">
                <p class="mb-4 text-base lg:text-xl">{!! t("A collection is a group of resources compiled together for a specific purpose, such as series of guides on a specific topic, or the reading for an online course.") !!}</p>
            </div>
        </div>
        <div class="relative flex items-center mb-6 max-w-3xl w-full mt-6 md:mt-16 ">
            <livewire:search-bar
                inputClass="w-full py-2 pl-12 pr-4 border-none rounded-full focus:outline-none transition
                        duration-300 focus:bg-gray-100 focus:ring-0 text-gray-700"/>

            <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3"
                     stroke="currentColor" class="w-5 h-5 text-gray-600">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                </svg>
            </div>

            <!-- Clear Button -->
            @if ($query)
                <svg xmlns="http://www.w3.org/2000/svg" wire:click="clearSearch" fill="none" viewBox="0 0 24 24"
                     stroke-width="2" stroke="gray"
                     class="absolute right-3 top-1/2 transform -translate-y-1/2 w-5 h-5 cursor-pointer hover:stroke-gray-700 transition-colors duration-200">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            @endif
        </div>
    </div>


    <div class="">
        <div class="flex flex-col lg:flex-row lg:gap-12">

            <!-- Sidebar (Search & Filters) -->
            <div class="lg:min-w-[220px] w-full lg:w-2/12 bg-[#f4f4f4] lg:bg-white self-start pl-6 md:pl-12 py-6 lg:py-8 flex flex-row lg:flex-col">
                <div class="lg:pb-4">
                    <div class="lg:pb-4 text-xl font-bold">{{ t("Filters") }}</div>
                    <div class="divider hidden lg:block"></div>
                </div>

                <!-- Search bar -->
                {{-- <div class="relative flex items-center mb-6">
                    <livewire:search-bar
                        inputClass="w-full py-2 pl-12 pr-4 bg-gray-100 rounded-full focus:outline-none transition
                        duration-300 focus:border-stats4sd-red focus:ring-1 focus:ring-stats4sd-red"/>

                    <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"
                            class="w-5 h-5 text-gray-600">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>

                    <!-- Clear Button -->
                    @if($query)
                        <svg xmlns="http://www.w3.org/2000/svg" wire:click="clearSearch" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="gray"
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 w-5 h-5 cursor-pointer hover:stroke-gray-700 transition-colors duration-200">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    @endif
                </div> --}}

                <!-- Language Filter -->
                <div class=" md:mx-6 px-6 lg:hidden" x-data="{ openLanguage: false }">
                    {{-- <div class="border-t border-gray-400 my-4"></div> --}}
                    <div class="flex justify-between items-center cursor-pointer" @click="openLanguage = !openLanguage">
                        <label class="text-base lg:font-bold">{{ t("Language:") }}</label>
                        <svg class="w-4 h-4 transition-transform duration-300 ml-2 sm:ml-4 :class="openLanguage ? 'rotate-00' : 'rotate-0'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </div>
                    <div class="space-y-2 mt-4 text-sm" x-show="openLanguage" x-show>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="selectedLanguages" value="es" wire:change="search" class="mr-2 accent-stats4sd-red"/>
                            {{ t("Spanish") }}
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="selectedLanguages" value="en" wire:change="search" class="mr-2 accent-stats4sd-red"/>
                            {{ t("English") }}
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="selectedLanguages" value="fr" wire:change="search" class="mr-2 accent-stats4sd-red"/>
                            {{ t("French") }}
                        </label>
                    </div>
                </div>

                <div class="mb-6 hidden lg:block" x-data="{ openLanguage: true }">
                    {{-- <div class="border-t border-gray-400 my-4"></div> --}}
                    <div class="flex justify-between items-center cursor-pointer" @click="openLanguage = !openLanguage">
                        <label class="text-base font-bold">{{ t("Language:") }}</label>
                        <svg class="w-5 h-5 transition-transform duration-300" :class="openLanguage ? 'rotate-90' : 'rotate-0'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </div>
                    <div class="space-y-2 mt-2 text-sm" x-show="openLanguage" x-show>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="selectedLanguages" value="es" wire:change="search" class="mr-2 accent-stats4sd-red"/>
                            {{ t("Spanish") }}
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="selectedLanguages" value="en" wire:change="search" class="mr-2 accent-stats4sd-red"/>
                            {{ t("English") }}
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="selectedLanguages" value="fr" wire:change="search" class="mr-2 accent-stats4sd-red"/>
                            {{ t("French") }}
                        </label>
                    </div>
                </div>


            </div>

            <!-- Main Content -->
            <div class="flex-1 p-8 sm:px-12">
                @if($query || !empty($selectedLanguages))
                    <div class="pt-2">
                        {{ t("Showing ") . $totalCollections . t(" collections") }}
                        <button wire:click="clearFilters" class="text-gray-500 hover:text-gray-700 underline text-sm ml-2">
                            {{ t("Clear Filters") }}
                        </button>
                    </div>

                    <!-- Collections Result Cards -->
                    <div id="Collections-content" class="py-8 lg:px-8">
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-8 max-w-6xl mx-auto">
                            @foreach ($this->collections as $index => $collection)
                                <x-collection-result-card :item="$collection"/>
                            @endforeach
                        </div>
                    </div>
                @else
                    <!-- Display when no search term -->
                    <div class="text-center text-semibold py-20 px-4 xl:pl-8">
                        {{ t("Use the search bar to find collections") }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
