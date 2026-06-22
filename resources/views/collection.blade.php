@extends('layouts.app')

@section('content')
    <div class="relative">
        <!-- Image Header -->
        <div class="overflow-hidden w-100vw ">
            <div class="relative bg-repeat-x bg-center -ml-24 blur-xl  w-[120vw] bg-cover"
                style="height: 500px; background-image: url('{{ $collection->cover_image }}');">
            </div>
        </div>
        <div class="absolute top-0 h-full w-full bg-center bg-no-repeat">
            <div class="w-full flex items-center overflow-hidden" style="height: 500px">
                <img src="{{ $collection->cover_image }}" style="min-height: 500px;  " class="mx-auto object-cover"
                    alt="cover image">
            </div>
            <div class="absolute bottom-0 w-full py-8 lg:py-12  h-fit  " style="min-height: 25%; background-color: #D32229dd">
                <div
                    class="text-left px-8  lg:px-32 mx-auto container flex flex-col gap-8 lg:gap-12   md:flex-row items-center justify-between">
                    <div class="md:w-1/2 lg:w-2/3">

                    <div class="text-lg md:text-2xl text-white">{{ t("COLLECTION") }}</div>
                    <div class="text-3xl lg:text-4xl text-white font-bold ">{{ $collection->title }}</div>
                    </div>
                    <button onclick="scrollToSection('collection-resources')" class="border-2 border-white  px-6 py-2 mt-2 font-semibold uppercase rounded-full text-white hover:bg-white hover:text-stats4sd-red transition">
                    {{ t("Jump to resources") }} </button>



                </div>

                <!-- Language Selection -->
                @php
                    $languages = [
                        'en' => 'English',
                        'es' => 'Español',
                        'fr' => 'Français',
                    ];
                    $availableLanguages = array_keys($collection->getTranslations('title'));
                    $currentLocale = request('locale', app()->getLocale()); // Get the current locale
                @endphp

                <div class="container mx-auto px-8 pt-6 lg:px-32 flex flex-col md:flex-row items-center">

                    <p class="text-lg text-white mr-6">{{ t('Available Languages:') }}</p>
                    <div class="flex gap-2 mt-2">
                        @foreach ($availableLanguages as $language)
                            <a href="{{ URL::current() . '?locale=' . $language }}"
                                class="px-4 py-1 rounded-full border border-white border
               {{ $currentLocale == $language || count($availableLanguages) == 1 ? 'bg-white text-stats4sd-red' : 'text-white  hover:bg-white hover:text-text-stats4sd-red' }}">
                                {{ $languages[$language] }}
                            </a>
                        @endforeach
                    </div>


                </div>

            </div>
        </div>
    </div>



    <!-- IFA Hub Link -->
 <!-- IFA Hub Link -->
    @if(isset($_GET["origin"]) && $_GET["origin"] === "ifa")
        <div class="container mx-auto py-6 px-8 lg:px-32">
        <a href="{{ url('/ifa') }}"
                        class="px-6 py-3 text-white bg-ifa-green flex flex-row justify-between
                                    hover:bg-black 
                                    font-semibold text-sm rounded-full uppercase text-center transition
                                    w-max">
                        <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" class="h-6 w-6 mr-6"
                            stroke-miterlimit="2" fill="white" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M2.117 12l7.527 6.235-.644.765-9-7.521 9-7.479.645.764-7.529 6.236h21.884v1h-21.883z"/>
                        </svg>
                        <span> {{ t('Institute for Agroecology Resource Library') }}</span>
                       
                    </a>
        </div>
    @endif

    <!-- Description -->
    <div class="container mx-auto py-6 px-8 lg:px-32 my-12">
        <div class="divider"></div>
        <h2 class="text-2xl font-bold pb-2">{{ t('Description') }}</h2>
        <p>{{ t("Dated: ") }} {{ \Carbon\Carbon::parse($collection->created_at)->translatedFormat('F Y') }}</p>
        <div class="description mt-6">{!! t($collection->description) !!}</div>
    </div>



    <!-- Troves -->
    <div id="collection-resources" class="container mx-auto py-6 px-8 lg:px-32">
        <div class="divider"></div>
        <h2 class="text-2xl font-bold pb-2">{{ t("Resources in this collection") }}</h2>

        <livewire:collection-troves :collection="$collection"/>
    </div>

@endsection


<script>
    function scrollToSection(sectionId) {
        const target = document.getElementById(sectionId);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth' });
        }
    }
</script>
