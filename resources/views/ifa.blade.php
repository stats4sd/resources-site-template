@php

    $collections = [
        [
            'url'  => '/collections/56',
            'img'  => '/images/ifa1.png',
            'icon' => 'images/ifa/teaching_icon.jpg',
            'title' => t('Introductions to agroecology'),
            'desc' => t(' '),
        ],
        [
            'url'  => '/collections/57',
            'img'  => '/images/ifa4.jpg',
            'icon' => 'images/ifa/reading_icon.jpg',
            'title' => t('Resources for program leads'),
            'desc' => t(' '),
        ],
        [
            'url'  => '/collections/58',
            'img'  => '/images/ifa3.jpg',
            'icon' => 'images/ifa/teaching_icon.jpg',
            'title' => t('Course syllabi'),
            'desc' => t(' '),
        ],
        [
            'url'  => '/collections/58',
            'img'  => '/images/ifa5.png',
            'icon' => 'images/ifa/teaching_icon.jpg',
            'title' => t('Teaching approaches and pedagogies'),
            'desc' => t(' '),
        ],
        [
            'url'  => '/collections/59',
            'img'  => '/images/ifa5.png',
            'icon' => 'images/ifa/webinar_icon.png',
            'title' => t('Case studies of agroecology programs'),
            'desc' => t(' '),
        ],
    ];

@endphp

@extends('layouts.app', ['hideHeader' => true, 'scrollButtonColor' => 'bg-ifa-green'])

@section('content')
    <header class="sticky top-0 z-50 bg-white   lg:pl-20 theme_ifa" x-data="{ open: false }">
        <div class=" w-full flex flex-col sm:flex-row justify-between items-center min-h-16 ">
            <!-- Logos -->
            
            <div class="flex justify-start w-full xl:w-2/3 items-center space-x-4 pl-4 lg:pl-0 lg:space-x-8 mb-4 sm:mb-0 py-1">
            
                <a href="https://www.uvm.edu/instituteforagroecology">
                    <img src="{{ asset('images/ifalogo1.png') }}" class="max-h-16  " alt="crfs">
                </a>

    


            </div>

            <nav class="flex w-full  justify-start sm:justify-end pr-6">
                <ul class="flex justify-between flex-col sm:flex-row sm:justify-end md:space-x-6 font-medium uppercase w-full text-xs md:text-sm">

                    <!-- Language Dropdown -->
                    <li class="relative nav-item dropdown flex   pl-4 pr-8 md:px-0 md:pl-0 min-w-40" x-data="{ langOpen: false }">
                        <a class="nav-link dropdown-toggle flex w-full justify-center sm:justify-end items-center py-4" role="button" aria-expanded="false"
                            x-on:click="langOpen = !langOpen">
                            {{ t('Change Language') }}
                            <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" class="h-5 w-5 ml-2"
                                fill="black" stroke-miterlimit="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="m16.843 10.211c.108-.141.157-.3.157-.456 0-.389-.306-.755-.749-.755h-8.501c-.445 0-.75.367-.75.755 0 .157.05.316.159.457 1.203 1.554 3.252 4.199 4.258 5.498.142.184.36.29.592.29.23 0 .449-.107.591-.291 1.002-1.299 3.044-3.945 4.243-5.498z" />
                            </svg>
                        </a>
                        <div class="language-dropdown-menu min-w-[11rem] top-full" x-show="langOpen" x-on:click.outside="langOpen = false"
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

<div class=" relative md:absolute md:top-0 z-50 w-full  md:items-right">

</div>
    <div class="relative theme_ifa">

        <div class="relative">
            <!-- Background Image -->
            <img src="images/ifacover.png" alt="Background Image"
                class="absolute inset-0 w-full h-[20rem] sm:h-[12rem] md:h-[20rem] object-cover filter brightness-[70%] z-0">

            <!-- Overlay Content -->
            <div class="relative z-10 flex flex-col items-start w-full h-[20rem] sm:h-[12rem] md:h-[20rem] text-white">
                <div class="h-[18rem] sm:h-[15rem] md:h-[20rem] pb-16 flex flex-col sm:flex-row items-end w-full 2xl:pr-32">
                    <!-- Heading -->
                    <div class="pt-10 px-4 sm:pl-16  2xl:pl-32 flex-grow" style="text-wrap: balance">
                    <h1 class="font-bold text-2xl sm:text-3xl  md:text-5xl mb-4 md:!leading-[3.5rem]">
                        {{ t('Resource Library: Education for Agroecological Transformations') }}
                    </h1>
                    <h2 class="font-normal text-lg sm:text-xl">{{ t('Agroecology in higher education') }}</h2>
                    
                    </div>
                    
                </div>
            </div>

        </div>

        <!-- Top section -->
        <div class="w-full bg-gray-100 flex justify-center py-6">
            <div class="flex flex-col lg:flex-row items-top justify-between gap-12 w-full max-w-7xl px-8 lg:px-12 py-6">
                <div class="text-sm md:text-base lg:w-3/6 px-4 lg:px-0" >
                    <p class="mb-4">{{ t("Let's Educate for Agroecological Transformations is an international community of practice focusing on transformative learning for agroecology in higher education consisting of people who are either already teaching, or who are building agroecology programmes/courses in HE. Programmes consist of undergraduate, graduate and/or post-graduate courses, and extend to continuing education aimed practitioners and professionals in the field of agroecology. We are committed to co-creating and inspiring transformative agroecological learning that transgresses formal-informal education boundaries to create learning spaces that connect academic knowledge with community and movement building practices across multiple contexts.") }}</p>
                    <p>{{ t("In this resource library you can find courses, modules and reading to guide and inform theory, methods and hands-on practice-based pedagogies applicable for adaptation across different territories and cultural contexts. These are made freely available by") }} <a href="{{ url('/ifaabout') }}" class="underline font-medium text-ifa-green hover:no-underline">{{ t("Let's EAT community of practice") }}</a>.</p>
                </div>
                
                <div class="w-full lg:w-3/6 px-4 ">
                    <h2 class=" text-black text-2xl mb-4">{{ t('Quick links') }}</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <a href="{{ url('/ifaabout') }}"
                            class="px-6 pt-3 pb-2 text-white bg-ifa-green flex flex-row justify-between
                                        hover:bg-black
                                        font-semibold text-xs  rounded-full uppercase text-center transition">
                            <span>{{ t('About us') }}</span>
                            <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" class="h-6 w-6"
                                stroke-miterlimit="2" fill="white" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="m14.523 18.787s4.501-4.505 6.255-6.26c.146-.146.219-.338.219-.53s-.073-.383-.219-.53c-1.753-1.754-6.255-6.258-6.255-6.258-.144-.145-.334-.217-.524-.217-.193 0-.385.074-.532.221-.293.292-.295.766-.004 1.056l4.978 4.978h-14.692c-.414 0-.75.336-.75.75s.336.75.75.75h14.692l-4.979 4.979c-.289.289-.286.762.006 1.054.148.148.341.222.533.222.19 0 .378-.072.522-.215z" fill-rule="nonzero" />
                            </svg>
                        </a>
                        <a href="https://www.uvm.edu/instituteforagroecology/lets-educate-agroecological-transformations" target="_blank" rel="noopener noreferrer"
                            class="px-6 pt-3 pb-2 text-white bg-ifa-green flex flex-row justify-between
                                        hover:bg-black
                                        font-semibold text-xs  rounded-full uppercase text-center transition">
                            <span>{{ t('UVM Let\'s EAT Page') }}</span>
                            <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" class="h-6 w-6"
                                stroke-miterlimit="2" fill="white" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="m14.523 18.787s4.501-4.505 6.255-6.26c.146-.146.219-.338.219-.53s-.073-.383-.219-.53c-1.753-1.754-6.255-6.258-6.255-6.258-.144-.145-.334-.217-.524-.217-.193 0-.385.074-.532.221-.293.292-.295.766-.004 1.056l4.978 4.978h-14.692c-.414 0-.75.336-.75.75s.336.75.75.75h14.692l-4.979 4.979c-.289.289-.286.762.006 1.054.148.148.341.222.533.222.19 0 .378-.072.522-.215z" fill-rule="nonzero" />
                            </svg>
                        </a>
                        <a href="mailto:georgemca20@gmail.com"
                            class="px-6 pt-3 pb-2 text-white bg-ifa-green flex flex-row justify-between
                                        hover:bg-black
                                        font-semibold text-xs  rounded-full uppercase text-center transition">
                            <span>{{ t('Contact us') }}</span>
                            <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" class="h-6 w-6"
                                stroke-miterlimit="2" fill="white" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M24 21h-24v-18h24v18zm-23-16.477v15.477h22v-15.477l-10.999 10-11.001-10zm21.089-.523h-20.176l10.088 9.171 10.088-9.171z"/>
                            </svg>
                        </a>
                        <a href="{{ url('/ifastudent') }}"
                            class="px-6 pt-3 pb-2 text-white bg-ifa-green flex flex-row justify-between
                                        hover:bg-black
                                        font-semibold text-xs  rounded-full uppercase text-center transition">
                            <span>{{ t('Information for students') }}</span>
                            <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" class="h-6 w-6"
                                stroke-miterlimit="2" fill="white" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="m14.523 18.787s4.501-4.505 6.255-6.26c.146-.146.219-.338.219-.53s-.073-.383-.219-.53c-1.753-1.754-6.255-6.258-6.255-6.258-.144-.145-.334-.217-.524-.217-.193 0-.385.074-.532.221-.293.292-.295.766-.004 1.056l4.978 4.978h-14.692c-.414 0-.75.336-.75.75s.336.75.75.75h14.692l-4.979 4.979c-.289.289-.286.762.006 1.054.148.148.341.222.533.222.19 0 .378-.072.522-.215z" fill-rule="nonzero" />
                            </svg>
                        </a>
                        <a href="#collections"
                            class="px-6 pt-3 pb-2 text-white bg-ifa-green flex flex-row justify-between
                                        hover:bg-black
                                        font-semibold text-xs  rounded-full uppercase text-center transition">
                            <span>{{ t('Browse by topic') }}</span>
                            <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" class="h-6 w-6"
                                stroke-miterlimit="2" fill="white" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="m14.523 18.787s4.501-4.505 6.255-6.26c.146-.146.219-.338.219-.53s-.073-.383-.219-.53c-1.753-1.754-6.255-6.258-6.255-6.258-.144-.145-.334-.217-.524-.217-.193 0-.385.074-.532.221-.293.292-.295.766-.004 1.056l4.978 4.978h-14.692c-.414 0-.75.336-.75.75s.336.75.75.75h14.692l-4.979 4.979c-.289.289-.286.762.006 1.054.148.148.341.222.533.222.19 0 .378-.072.522-.215z" fill-rule="nonzero" />
                            </svg>
                        </a>
                        <a href="#browse_all"
                            class="px-6 pt-3 pb-2 text-white bg-ifa-green flex flex-row justify-between
                                        hover:bg-black
                                        font-semibold text-xs  rounded-full uppercase text-center transition">
                            <span>{{ t('View all') }}</span>
                            <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" class="h-6 w-6"
                                stroke-miterlimit="2" fill="white" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="m14.523 18.787s4.501-4.505 6.255-6.26c.146-.146.219-.338.219-.53s-.073-.383-.219-.53c-1.753-1.754-6.255-6.258-6.255-6.258-.144-.145-.334-.217-.524-.217-.193 0-.385.074-.532.221-.293.292-.295.766-.004 1.056l4.978 4.978h-14.692c-.414 0-.75.336-.75.75s.336.75.75.75h14.692l-4.979 4.979c-.289.289-.286.762.006 1.054.148.148.341.222.533.222.19 0 .378-.072.522-.215z" fill-rule="nonzero" />
                            </svg>
                        </a>
                        </div>
                </div>
            </div>
        </div>

        <div class="flex flex-row w-full h-full justify-between gap-12 mt-12" id="collections">
            <div class="bg-ifa-green w-6 flex-shrink-0 h-auto"></div>
            <div class="h-auto w-full max-w-7xl py-3 px-4 md:pl-12">

                <h2 class="text-black text-3xl">
                    {{ t('Explore collections') }}
                </h2>
            </div>
            <div class="bg-none w-6 flex-shrink-0 h-auto"></div>
        </div>
        <div class="flex flex-row w-full h-full justify-between gap-12 " id="collections">
            <div class="bg-none w-6 flex-shrink-0 h-auto"></div>
            <div class="h-auto w-full max-w-7xl py-3 px-4 md:pl-12">
                <p class="max-w-7xl ">
                    {{ t('The collections below have been carefully chosen to highlight specific topics or themes present in the
                    resources within the Let\'s EAT hub. They may be a useful starting point to get an idea of what this hub
                    contains.') }}
                    </p>
            </div>
            <div class="bg-none w-6 flex-shrink-0 h-auto"></div>
        </div>
        

        <!-- View by topic - collections -->
        <div class="w-full flex justify-center py-6">
            <div class="flex flex-wrap justify-center gap-6 w-full max-w-5xl p-12">
                @foreach ($collections as $c)
                    <a href="{{ url($c['url'].'?origin=ifa') }}"
                        class="hover-effect relative bg-ifa-green rounded-t-[2.5rem] rounded-bl-[2.5rem] overflow-hidden group w-full sm:w-64">
                        <div class="h-48 bg-cover bg-center"
                            style="background-image: linear-gradient(to bottom, rgba(255,255,255,0), rgba(0,0,0,0.14)), url('{{ $c['img'] }}');">
                        </div>
                        <div class="p-8 text-white min-h-[10rem] relative">
                            <div class="absolute top-3 right-3 h-10 w-10 rounded-full overflow-hidden bg-ifa-yellow">
                                <img src="{{ asset($c['icon']) }}" alt="" class="w-full h-full object-cover">
                            </div>
                            <p class="text-xs uppercase font-semibold mb-2">{{ t('Collection') }}</p>
                            <h3 class="font-bold text-lg mb-2">
                                {{ $c['title'] }}
                            </h3>
                            <p class="text-sm">
                                {{ $c['desc'] }}
                            </p>
                        </div>
                        <div class="w-full px-10 pb-5">
                            <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" class="h-6 w-6"
                                stroke-miterlimit="2" fill="white" viewBox="0 0 24 24"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="m14.523 18.787s4.501-4.505 6.255-6.26c.146-.146.219-.338.219-.53s-.073-.383-.219-.53c-1.753-1.754-6.255-6.258-6.255-6.258-.144-.145-.334-.217-.524-.217-.193 0-.385.074-.532.221-.293.292-.295.766-.004 1.056l4.978 4.978h-14.692c-.414 0-.75.336-.75.75s.336.75.75.75h14.692l-4.979 4.979c-.289.289-.286.762.006 1.054.148.148.341.222.533.222.19 0 .378-.072.522-.215z"
                                    fill-rule="nonzero" />
                            </svg>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        <!-- Search bar -->
        <div class="flex flex-row w-full h-full justify-between md:gap-12 mt-12">
            <div class="bg-ifa-green w-6 flex-shrink-0 h-auto"></div>
            <div class="h-auto w-full max-w-7xl py-3 pr-4 pl-10 md:pl-12">
                <h2 class="text-black text-3xl">
                    {{ t('Explore resources') }}
                </h2>
            </div>
            <div class="bg-none w-6 flex-shrink-0 h-auto"></div>
        </div>

        <!-- Browse all -->
        <div class="w-full">
            @livewire('ifa-hub-browse-resources')
        </div>

    </div>
@endsection
