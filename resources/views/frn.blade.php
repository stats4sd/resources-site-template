@php
    $featuredResourceIds = [
        'en' => [658, 659, 604],
        'es' => [474, 367, 604],
        'fr' => [625, 604],
    ];

    $collections = [
        [
            'url' => '/collections/49',
            'img' => '/images/frn5.jpg',
            'title' => t('What is an FRN?'),
            'desc' => t('Discover more about farmer research networks and how they work.'),
        ],
        [
            'url' => '/collections/50',
            'img' => '/images/frn2.jpg',
            'title' => t('Tools for FRNs'),
            'desc' => t('Guides, methods and references for FRNs.'),
        ],
        [
            'url' => '/collections/51',
            'img' => '/images/frn4.jpg',
            'title' => t('Research in FRNs'),
            'desc' => t('Resources related to research methods, and how to include farmers in the research process.'),
        ],
        [
            'url' => '/collections/52',
            'img' => '/images/frn3.jpg',
            'title' => t('FRN principles'),
            'desc' => t('Important considerations for ethical and inclusive research practices.'),
        ],
        [
            'url' => '/collections/53',
            'img' => '/images/frn6.jpg',
            'title' => t('Case Studies'),
            'desc' => t('Insights into what FRNs have been doing.'),
        ],
    ];

    $locale = app()->currentLocale();
    $featuredResources = \App\Models\Trove::whereIn('id', $featuredResourceIds[$locale])->get();
@endphp

@extends('layouts.app', ['hideHeader' => true])

@section('content')
    <header class="sticky top-0 z-50 bg-white  px-8 sm:px-20" x-data="{ open: false }">
        <div class="container mx-auto flex flex-col sm:flex-row justify-between items-center py-4">
            <!-- Logos -->
            <div class="flex justify-start w-full sm:w-max  items-center space-x-6 mb-8 sm:mb-0">
                <a href="https://stats4sd.org/">
                    <img src="/images/Stats4SD_logo.png" alt="Stats4SD logo" class="max-h-5 ">
                </a>
                <a href="https://ccrp.org">
                    <img src="{{ asset('images/CRFSlogo.png') }}" class="max-h-12  " alt="crfs">
                </a>

            </div>

            <nav class="flex w-full justify-end">
                <ul class="flex space-x-6 font-medium uppercase text-sm">

                    <!-- Language Dropdown -->
                    <li class="relative nav-item dropdown" x-data="{ langOpen: false }">
                        <a class="nav-link dropdown-toggle flex items-center" role="button" aria-expanded="false"
                            x-on:click="langOpen = !langOpen">
                            {{ t('Change Language') }}
                            <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" class="h-5 w-5 ml-2"
                                fill="black" stroke-miterlimit="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="m16.843 10.211c.108-.141.157-.3.157-.456 0-.389-.306-.755-.749-.755h-8.501c-.445 0-.75.367-.75.755 0 .157.05.316.159.457 1.203 1.554 3.252 4.199 4.258 5.498.142.184.36.29.592.29.23 0 .449-.107.591-.291 1.002-1.299 3.044-3.945 4.243-5.498z" />
                            </svg>
                        </a>
                        <div class="language-dropdown-menu" x-show="langOpen" x-on:click.outside="langOpen = false"
                            style="display:none">
                            <a class="dropdown-item" href="{{ URL::current() . '?locale=en' }}">English</a>
                            <a class="dropdown-item" href="{{ URL::current() . '?locale=es' }}">Español</a>
                            <a class="dropdown-item" href="{{ URL::current() . '?locale=fr' }}">Français</a>
                        </div>
                    </li>
                </ul>
            </nav>

        </div>
    </header>




    <div class="relative">


        <div class="relative">
            <!-- Background Image -->
            <img src="images/frn7.jpg" alt="Background Image"
                class="absolute inset-0 w-full h-[20rem] object-cover filter brightness-[65%] z-0">

            <!-- Overlay Content -->
            <div class="relative z-10 flex flex-col items-start w-full h-[20rem]  text-white">
                <div class="h-[20rem] pb-16 flex flex-col gap-4 sm:flex-row items-end w-full 2xl:pr-32">
                    <!-- Heading -->
                    <h1 class="font-bold text-4xl sm:text-5xl md:text-5xl pt-10 px-8 sm:pl-16  2xl:pl-32 flex-grow">
                        {{ t('FRN Research Methods Hub') }}
                    </h1>

                    <div class="flex items-start h-full w-full sm:w-[18rem]">
                        <a class="p-8 bg-stats4sd-red text-white sm:rounded-bl-[3rem] w-full font-opensans 2xl:rounded-b-[3rem] flex flex-col items-start justify-left text-left  "
                            href="https://stats4sd.org/resources">
                            <span class="uppercase text-sm font-normal ">{{ t('Part of the') }}</span>
                            <h2 class="text-xl font-bold mb-8">{{ t('Stats4SD Resources Library') }}</h2>
                            <div class="flex">
                                <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" fill="#ffffff"
                                    stroke-miterlimit="2" viewBox="0 0 24 24" height="1.5rem"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="m9.474 5.209s-4.501 4.505-6.254 6.259c-.147.146-.22.338-.22.53s.073.384.22.53c1.752 1.754 6.252 6.257 6.252 6.257.145.145.336.217.527.217.191-.001.383-.074.53-.221.293-.293.294-.766.004-1.057l-4.976-4.976h14.692c.414 0 .75-.336.75-.75s-.336-.75-.75-.75h-14.692l4.978-4.979c.289-.289.287-.761-.006-1.054-.147-.147-.339-.221-.53-.221-.191-.001-.38.071-.525.215z"
                                        fill-rule="nonzero" />
                                </svg>
                                <span class="uppercase text-sm font-normal ml-3 ">{{ t('Library home') }}</span>
                            </div>
                        </a>
                    </div>
                </div>


            </div>

        </div>




        <!-- Top section -->
        <div class="w-full bg-gray-100 flex justify-center py-6">
            <div class="flex flex-col md:flex-row items-start  gap-12 w-screen max-w-7xl p-12">
                <div class="text-base w-3/5">
                    {{ t('A library of reference materials, tools, and useful information relevant to farmer research networks. Provided by the') }}
                    <span class="font-bold">{{ t('Research Methods Support project') }}</span>
                    {{ t('of CRFS.') }}
                </div>
                <div class="flex flex-col w-1/5 gap-y-4">
                    <h2 class=" text-black text-2xl mb-2">{{ t('Quick links') }}</h2>
                    <a href="#collections"
                        class="px-6 py-3 text-white bg-frn-green 
                                    hover:bg-black 
                                    font-semibold  text-sm rounded-full uppercase text-center transition">
                        {{ t('Browse by topic') }}
                    </a>
                    <a href="#browse_all"
                        class="px-6 py-3 text-white bg-frn-green 
                                    hover:bg-black 
                                    font-semibold  text-sm rounded-full uppercase text-center transition">
                        {{ t('View all resources') }}
                    </a>
                </div>
            </div>
        </div>

        <div class="flex flex-row w-full h-full justify-between gap-12 mt-12 ">
            <div class="bg-stats4sd-red  w-6 flex-shrink-0 h-auto"></div>
            <div class="h-auto w-full max-w-7xl py-3">



                <h2 class="text-black text-2xl" id="collections">

                    {{ t('Browse by topic') }}

                </h2>



            </div>
            <div class="bg-none  w-6 flex-shrink-0 h-auto"></div>
        </div>



        <div class="w-full  flex justify-center py-6">

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 justify-center max-w-7xl p-12">
                @foreach ($collections as $c)
                    <a href="{{ url($c['url']) }}" target="_blank"
                        class="hover-effect relative bg-frn-green rounded-t-[2.5rem] rounded-bl-[2.5rem] overflow-hidden group sm:max-w-[20rem] min-w-[15rem]">
                        <div
                            class="absolute top-4 left-4 h-12 w-12  rounded-full text-white text-center py-auto bg-frn-green">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                                fill="white" class="mx-auto my-3">
                                <path
                                    d="M21.698 10.658l2.302 1.342-12.002 7-11.998-7 2.301-1.342 9.697 5.658 9.7-5.658zm-9.7 10.657l-9.697-5.658-2.301 1.343 11.998 7 12.002-7-2.302-1.342-9.7 5.657zm0-19l8.032 4.685-8.032 4.685-8.029-4.685 8.029-4.685zm0-2.315l-11.998 7 11.998 7 12.002-7-12.002-7z" />
                            </svg>
                        </div>
                        <div class="h-48 bg-cover bg-center"
                            style="background-image: linear-gradient(to bottom, rgba(255,255,255,0), rgba(0,0,0,0.14)), url('{{ $c['img'] }}">
                        </div>
                        <div class="p-10 text-white">
                            <p class="text-xs  uppercase font-semibold ">
                                {{ t('Collection') }}
                            </p>
                            <h3 class="font-bold text-xl mb-2 ">
                                {{ $c['title'] }}
                            </h3>
                            <p class="text-sm">
                                {{ $c['desc'] }}
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>


        <!-- Featured Resources -->
        <div class="flex flex-row w-full h-full justify-between gap-12 mt-12 ">
            <div class="bg-stats4sd-red  w-6 flex-shrink-0 h-auto"></div>
            <div class="h-auto w-full max-w-7xl py-3">

                <h2 class="text-black text-2xl">
                    {{ t('Featured resources') }}
                </h2>

            </div>
            <div class="bg-none  w-6 flex-shrink-0 h-auto"></div>
        </div>


        <div class="max-w-7xl mx-auto px-32 -mt-56">

            <div class="carousel w-full ">
                @php $slideCount = 1 @endphp
                @foreach ($featuredResources as $resource)
                    <div id="slide{{ $slideCount }}" class="pt-64 carousel-item relative w-full ">

                        <div class="w-full h-80  h-[25rem] bg-cover bg-center flex flex-col-reverse"
                            style="background-image: linear-gradient(to bottom, rgba(255,255,255,0), rgba(0,0,0,0.44)), url('{{ $resource->getCoverImageUrl() }}')">
                            <div class="py-8 px-24 bg-black bg-opacity-75 text-white">
                                <h3 class="font-bold text-lg group-hover:text-stats4sd-green mb-2">
                                    {{ $resource->getTranslation('title', $locale) ?? $resource->getTranslation('title', 'en') }}
                                </h3>
                                <p class="text-xs">
                                    {!! $resource->getTranslation('description', $locale)
                                        ? \Illuminate\Support\Str::limit($resource->getTranslation('description', $locale), 200)
                                        : \Illuminate\Support\Str::limit($resource->getTranslation('description', 'en'), 200) !!}
                                </p>
                            </div>
                            @php
                                if (count($featuredResources) == $slideCount) {
                                    $nextSlide = 1;
                                    $prevSlide = $slideCount - 1;
                                } elseif ($slideCount === 1) {
                                    $prevSlide = count($featuredResources);
                                    $nextSlide = $slideCount + 1;
                                } else {
                                    $nextSlide = $slideCount + 1;
                                    $prevSlide = $slideCount - 1;
                                }

                            @endphp

                            <div
                                class="absolute left-5 right-5 top-1/2 pt-64 flex -translate-y-1/2 transform justify-between">


                                <a href="#slide{{ $prevSlide }}"
                                    class="btn btn-circle border-0 border-none bg-white hover:bg-black hover:text-white">❮</a>
                                <a href="#slide{{ $nextSlide }}"
                                    class="btn btn-circle border-0 border-none bg-white hover:bg-black hover:text-white">❯</a>
                            </div>
                        </div>
                    </div>
                    @php $slideCount++ @endphp
                @endforeach

            </div>


        </div>

        <!-- Browse all -->

        <div class="flex flex-row w-full h-full justify-between gap-12 mt-32">

            <div class="bg-stats4sd-red w-6 flex-shrink-0 h-auto"></div>

            <div class="h-auto w-full max-w-7xl py-3">



                <h2 class="text-black text-2xl" id="browse_all">

                    {{ t('Search or explore resources') }}

                </h2>


            </div>
            <div class="bg-none  w-6 flex-shrink-0 h-auto"></div>
        </div>
        <div class="w-full  flex justify-center py-6">

            <div class="relative  items-center mb-6 w-full max-w-7xl p-12 hidden lg:flex">
                <livewire:search-bar
                    inputClass="w-full py-2 pl-12 pr-4 bg-gray-200 border-none rounded-full focus:outline-none transition
                duration-300 focus:bg-gray-100 focus:ring-0 text-gray-700" />

                <div class="absolute left-16 top-1/2 transform -translate-y-1/2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3"
                        stroke="currentColor" class="w-5 h-5 text-gray-600">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="w-full  ">

            @livewire('frn-hub-browse-resources')
        </div>
    </div>
@endsection
