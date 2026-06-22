<div>

    <!-- Topics filters -->
    <div class="flex flex-row w-full h-full justify-between md:gap-12 mt-4 ">
        <div class="bg-none w-6 flex-shrink-0 h-auto"></div>
        <div class="h-auto w-full max-w-7xl py-3 pr-4 pl-10 md:pl-12">

            <h3 class="text-black text-lg uppercase font-medium">
                {{ t('Explore selected topics') }}
            </h3>

        </div>
        <div class="bg-none w-6 flex-shrink-0 h-auto"></div>
    </div>

    <div class="w-full flex justify-center py-6 px-6 md:px-12">
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4 justify-left max-w-7xl md:px-12">
             @php $featureCount = 1 @endphp
            @foreach ($this->FeatureTopics as $t)
            @if ($featureCount < 16)
        
                <label
                    onclick="document.getElementById('results').scrollIntoView({ behavior: 'smooth', block: 'start' })"
                    class="hover-effect cursor-pointer flex justify-between items-center relative bg-ifa-yellow rounded-full  overflow-hidden group sm:max-w-[20rem] min-w-[80vw] sm:min-w-[13rem] text-black text-xs md:text-sm md:h-16 px-6 py-3"
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
                    {{ t('Explore Institutions') }}
                </h3>
                <div class="sm:grid sm:grid-cols-2 gap-6  mt-4 sm:mt-12 items-left">
                    @foreach ($this->institutions as $i)
                        <label
                            onclick="document.getElementById('results').scrollIntoView({ behavior: 'smooth', block: 'start' })"
                            class="hover-effect cursor-pointer relative bg-ifa-green flex flex-row sm:flex-col justify-between sm:justify-around text-left rounded-t-[1.5rem] rounded-bl-[1.5rem] overflow-hidden group  min-w-[14rem] text-white text-sm px-6 py-4 sm:h-[9rem] mb-4"
                            wire:click="clearAndFilter('institution', {{ $i['id'] }})">
                            <div>
                                <h3> {{ $i['name'] }}</h3>
                                <p>{{ $i['location'] }}</p>
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
                    {{ t('Browse by programme curricula or course syllabi') }}
                </h3>
                <div class="sm:grid sm:grid-cols-2 sm:gap-6 gap-4 mt-4 sm:mt-12">
                    @foreach ($this->levels as $l)
                        <label
                            onclick="document.getElementById('results').scrollIntoView({ behavior: 'smooth', block: 'start' })"
                            class="hover-effect cursor-pointer relative bg-ifa-green flex flex-row sm:flex-col justify-between sm:justify-around rounded-t-[1.5rem] rounded-bl-[1.5rem] overflow-hidden group  min-w-[14rem] text-white text-sm px-6 py-4 sm:h-[9rem] mb-4"
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

    <div id="results" class="flex flex-col lg:flex-row lg:gap-12 bg-gray-50">
        <!-- Sidebar (Search & Filters) -->
        <div class="lg:min-w-[280px] w-full lg:w-2/12 bg-[#f4f4f4] bg-white self-start lg:pl-8 px-8 py-6 lg:py-8 lg:m-6 lg:shadow-xl">
            <div class="pb-4 sm:pb-0 lg:pb-4 sm:hidden lg:block">
                <div class=" text-xl font-bold">{{ t('Search and filter') }}</div>
                <div class="h-3 w-12 bg-ifa-yellow my-3"></div>
            </div>  

            <!-- Search bar -->
            <div class="relative flex items-center mb-6"  >
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
                <!-- Language Filter -->
                <div class="" x-data="window.innerWidth >= 1024 ? { openLanguage: true } : { openLanguage: false }">
                    <div class="border-t border-gray-400 sm:border-0 lg:border-t mb-6 sm:my-0 lg:mb-6"></div>
                    <div class="flex justify-between items-center cursor-pointer" @click="openLanguage = !openLanguage">
                        <label class="text-base  lg:font-bold">{{ t("Language:") }}</label>
                        <svg class="w-5 h-5 ml-2 transition-transform duration-300" :class="openLanguage ? 'rotate-90' : '-rotate-90'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </div>
                    <div class="space-y-2 mt-2 text-sm" x-show="openLanguage" x-show>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="selectedLanguages" value="es" wire:change="search" class="mr-2 accent-ifa-yellow"/>
                            {{ t("Spanish") }}
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="selectedLanguages" value="en" wire:change="search" class="mr-2 accent-ifa-yellow"/>
                            {{ t("English") }}
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="selectedLanguages" value="fr" wire:change="search" class="mr-2 accent-ifa-yellow"/>
                            {{ t("French") }}
                        </label>
                    </div>
                </div>

                <!-- Levels Filter -->
                <div class="sm:ml-6 lg:ml-0" x-data="window.innerWidth >= 1024 ? { openLevels: true } : { openLevels: false }">
                    <div class="border-t border-gray-400 sm:border-0 lg:border-t my-6 sm:my-0 lg:my-6"></div>
                    <div class="flex justify-between items-center cursor-pointer" @click="openLevels = !openLevels">
                        <label class="text-base lg:font-bold">{{ t("Level:") }}</label>
                        <svg class="w-5 h-5 ml-2 transition-transform duration-300" :class="openLevels ? 'rotate-90' : '-rotate-90'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </div>
                    <div class="space-y-2 mt-4 text-sm " x-show="openLevels" x-show>
                        @foreach($this->levels as $level)
                            <label class="flex items-center rounded cursor-pointer">
                                <input type="checkbox" wire:model="selectedLevels" value="{{ $level->id }}" class="mr-2 accent-ifa-yellow" wire:change="search"/>
                                {{ $level->name }}
                            </label>
                        @endforeach
                    </div>
                </div>

                
                <!-- Institutions Filter -->
                <div class="sm:ml-6 lg:ml-0" x-data="window.innerWidth >= 1024 ? { openInstitutions: true } : { openInstitutions: false }">
                    <div class="border-t border-gray-400 sm:border-0 lg:border-t my-6 sm:my-0 lg:my-6"></div>
                    <div class="flex justify-between items-center cursor-pointer" @click="openInstitutions = !openInstitutions">
                        <label class="text-base  lg:font-bold">{{ t("Institution:") }}</label>
                        <svg class="w-5 h-5 ml-2 transition-transform duration-300" :class="openInstitutions ? 'rotate-90' : '-rotate-90'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </div>
                    <div class="space-y-2 mt-4 text-sm" x-show="openInstitutions" x-show>
                        @foreach($this->institutions as $institution)
                            <label class="flex items-center rounded cursor-pointer">
                                <input type="checkbox" wire:model="selectedInstitutions" value="{{ $institution->id }}" class="mr-2 accent-ifa-yellow" wire:change="search"/>
                                {{ $institution->name }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Topics Filter -->
                <div class="sm:ml-6 lg:ml-0" x-data="window.innerWidth >= 1024 ? { openTopics: true } : { openTopics: false }">
                    <div class="border-t border-gray-400 sm:border-0 lg:border-t my-6 sm:my-0 lg:my-6"></div>
                    <div class="flex justify-between items-center cursor-pointer" @click="openTopics = !openTopics">
                        <label class="text-base  lg:font-bold">{{ t("Topic:") }}</label>
                        <svg class="w-5 h-5 ml-2 transition-transform duration-300" :class="openTopics ? 'rotate-90' : '-rotate-90'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </div>
                    <div class="space-y-2 mt-4 text-sm" x-show="openTopics" x-show>
                        @foreach($this->topics as $topic)
                            <label 
                                class="flex items-center rounded cursor-pointer">
                                <input type="checkbox" wire:model="selectedTopics" value="{{ $topic->id }}" class="mr-2 accent-ifa-yellow" wire:change="search"/>
                                {{ $topic->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Resources Cards -->
        <div class="flex-1" id="browse_all">
            <div class="p-8">
                {{ t("Showing ") . $totalResources . t(" resources") }}
                @if($query || !empty($selectedLanguages) || !empty($selectedLevels) || !empty($selectedInstitutions) || !empty($selectedTopics) )
                    <button wire:click="clearFilters" class="text-gray-500 hover:text-gray-700 underline text-sm">
                        {{ t("Clear Filters") }}
                    </button>
                @endif
            </div>

            <div id="Resources-content" class="p-8 rounded-lg">
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-8 max-w-6xl mx-auto">
                    @foreach ($resources as $resource)
                     <x-resource-result-card :item="$resource" color="ifa-yellow" textcol="black" target="_self" :show-tags="false" origin="ifa"/>
                    
                    @endforeach
                </div>
            </div>

        </div>

    </div>

</div>