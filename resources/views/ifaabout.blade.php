@extends('layouts.app', ['hideHeader' => true, 'scrollButtonColor' => 'bg-ifa-green'])

@section('content')
    <header class="sticky top-0 z-50 bg-white lg:pl-20 theme_ifa" x-data="{ open: false }">
        <div class="w-full flex flex-col sm:flex-row justify-between items-center min-h-16">
            <!-- Logos -->
            <div class="flex justify-start w-full xl:w-2/3 items-center space-x-4 pl-4 lg:pl-0 lg:space-x-8 mb-4 sm:mb-0 py-1">
                <a href="https://www.uvm.edu/instituteforagroecology">
                    <img src="{{ asset('images/ifalogo1.png') }}" class="max-h-16" alt="crfs">
                </a>
            </div>

            <nav class="flex w-full justify-start sm:justify-end pr-6">
                <ul class="flex justify-between flex-col sm:flex-row sm:justify-end md:space-x-6 font-medium uppercase w-full text-xs md:text-sm">
                    <!-- Language Dropdown -->
                    <li class="relative nav-item dropdown flex pl-4 pr-8 md:px-0 md:pl-0 min-w-40" x-data="{ langOpen: false }">
                        <a class="nav-link dropdown-toggle flex w-full justify-center sm:justify-end items-center py-4" role="button" aria-expanded="false"
                            x-on:click="langOpen = !langOpen">
                            {{ t('Change Language') }}
                            <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" class="h-5 w-5 ml-2"
                                fill="black" stroke-miterlimit="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="m16.843 10.211c.108-.141.157-.3.157-.456 0-.389-.306-.755-.749-.755h-8.501c-.445 0-.75.367-.75.755 0 .157.05.316.159.457 1.203 1.554 3.252 4.199 4.258 5.498.142.184.36.29.592.29.23 0 .449-.107.591-.291 1.002-1.299 3.044-3.945 4.243-5.498z" />
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

    <div class="relative theme_ifa">

        <div class="relative">
            <!-- Background Image -->
            <img src="images/ifacover.png" alt="Background Image"
                class="absolute inset-0 w-full h-[20rem] sm:h-[12rem] md:h-[20rem] object-cover filter brightness-[70%] z-0">

            <!-- Overlay Content -->
            <div class="relative z-10 flex flex-col items-start w-full h-[20rem] sm:h-[12rem] md:h-[20rem] text-white">
                <div class="h-[18rem] sm:h-[15rem] md:h-[20rem] pb-16 flex flex-col sm:flex-row items-end w-full 2xl:pr-32">
                    <div class="pt-10 px-4 sm:pl-16 2xl:pl-32 flex-grow" style="text-wrap: balance">
                        <h1 class="font-bold text-2xl sm:text-3xl md:text-5xl mb-4 md:!leading-[3.5rem]">
                            {{ t('Resource Library: Education for Agroecological Transformations') }}
                        </h1>
                        <h2 class="font-normal text-lg sm:text-xl">{{ t('About us') }}</h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top section -->
        <div class="w-full bg-gray-100 flex justify-center py-6">
            <div class="flex flex-col lg:flex-row items-top justify-between gap-12 w-full max-w-7xl px-8 lg:px-12 py-6">
                <div class="text-sm md:text-base lg:w-3/6 px-4 lg:px-0">
                    <p class="mb-4">{{ t("Welcome to our Let's E.A.T (Educate for Agroecological Transformations) resource library. This is a co-created resource offered freely by our community of practice consisting of people working to develop transformative agroecology programmes in higher education.") }}</p>
                    <p>{{ t("Let's EAT was formed in 2024 as an international community of practice, currently stewarded by the Institute for Agroecology at the University of Vermont. Together, our programmes consist of undergraduate, graduate and/or post-graduate courses, and extend to continuing education aimed practitioners and professionals in the field of agroecology. We are committed to co-creating and inspiring transformative agroecological learning that transgresses formal-informal education boundaries to create learning spaces that connect academic knowledge with community and movement building practices across multiple contexts.") }}</p>
                </div>

                <div class="w-full lg:w-3/6 px-4">
                    <h2 class="text-black text-2xl mb-4">{{ t('Quick links') }}</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <a href="{{ url('/ifa') }}"
                            class="px-6 pt-3 pb-2 text-white bg-ifa-green flex flex-row justify-between
                                        hover:bg-black
                                        font-semibold text-xs rounded-full uppercase text-center transition">
                            <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" class="h-6 w-6 mr-3"
                                stroke-miterlimit="2" fill="white" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M2.117 12l7.527 6.235-.644.765-9-7.521 9-7.479.645.764-7.529 6.236h21.884v1h-21.883z" />
                            </svg>
                            <span>{{ t('Resource Library') }}</span>
                        </a>
                        <a href="mailto:georgemca20@gmail.com"
                            class="px-6 pt-3 pb-2 text-white bg-ifa-green flex flex-row justify-between
                                        hover:bg-black
                                        font-semibold text-xs rounded-full uppercase text-center transition">
                            <span>{{ t('Contact us') }}</span>
                            <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" class="h-6 w-6"
                                stroke-miterlimit="2" fill="white" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M24 21h-24v-18h24v18zm-23-16.477v15.477h22v-15.477l-10.999 10-11.001-10zm21.089-.523h-20.176l10.088 9.171 10.088-9.171z" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Video section -->
        <div class="flex flex-row w-full h-full justify-between gap-12 mt-12">
            <div class="bg-ifa-green w-6 flex-shrink-0 h-auto"></div>
            <div class="h-auto w-full max-w-7xl py-3 px-4 md:pl-12">
                <h2 class="text-black text-3xl">{{ t('Video: Introduction to Let\'s EAT') }}</h2>
                <p class="text-sm md:text-base mt-2 text-gray-700">{{ t('Watch Colin Anderson introduce the Let\'s EAT community of practice and its approach to transformative agroecology education.') }}</p>
            </div>
            <div class="bg-none w-6 flex-shrink-0 h-auto"></div>
        </div>

        <div class="flex justify-center w-full py-8">
            <div class="w-full max-w-3xl px-8 lg:px-16">
                <div class="aspect-video bg-gray-200 rounded overflow-hidden">
                    <iframe
                        class="w-full h-full"
                        src="https://www.youtube.com/embed/8plwv25NYRo"
                        title="{{ t('Colin talking about Let\'s EAT - AEC Seminar') }}"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen>
                    </iframe>
                </div>
            </div>
        </div>

        <!-- Reading & Webinar -->
        <div class="flex justify-center w-full py-8 mb-12 mt-4">
            <div class="w-full max-w-7xl px-8 lg:px-16">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <!-- Blog card -->
                    <div class="flex flex-col justify-between p-6 bg-gray-50 rounded-t-[2.5rem] rounded-bl-[2.5rem] border border-gray-200">
                        <div>
                            <h2 class="text-black text-2xl font-bold mb-3">{{ t('Reading') }}</h2>
                            <p class="text-xs uppercase font-semibold text-ifa-green mb-1">{{ t('Blog · AgroecologyNow!') }}</p>
                            <h3 class="font-semibold text-base mb-2 min-h-[2.5rem]">{{ t('Transforming Food Systems Through Agroecology Education: Head, Hands, and Heart') }}</h3>
                            <p class="text-sm text-gray-700">{{ t('This short blog piece on AgroecologyNow! explores the signature pedagogies — transdisciplinary, experiential, and critical learning — that we consider important for agroecology education to be transformative.') }}</p>
                        </div>
                        <a href="https://agroecologynow.net/agroecology-education-head-heart-hands/" target="_blank" rel="noopener noreferrer"
                            class="mt-5 self-start px-6 py-3 text-white bg-ifa-green flex flex-row items-center gap-3
                                        hover:bg-black font-semibold text-xs rounded-full uppercase transition">
                            <span>{{ t('Read') }}</span>
                            <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" class="h-5 w-5"
                                stroke-miterlimit="2" fill="white" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="m14.523 18.787s4.501-4.505 6.255-6.26c.146-.146.219-.338.219-.53s-.073-.383-.219-.53c-1.753-1.754-6.255-6.258-6.255-6.258-.144-.145-.334-.217-.524-.217-.193 0-.385.074-.532.221-.293.292-.295.766-.004 1.056l4.978 4.978h-14.692c-.414 0-.75.336-.75.75s.336.75.75.75h14.692l-4.979 4.979c-.289.289-.286.762.006 1.054.148.148.341.222.533.222.19 0 .378-.072.522-.215z" fill-rule="nonzero" />
                            </svg>
                        </a>
                    </div>

                    <!-- Webinar card -->
                    <div class="flex flex-col justify-between p-6 bg-gray-50 rounded-t-[2.5rem] rounded-bl-[2.5rem] border border-gray-200">
                        <div>
                            <h2 class="text-black text-2xl font-bold mb-3">{{ t('Webinar') }}</h2>
                            <p class="text-xs uppercase font-semibold text-ifa-green mb-1">{{ t('Agroecology Coalition · December 2025') }}</p>
                            <h3 class="font-semibold text-base mb-2 min-h-[2.5rem]">{{ t('Let\'s EAT: Community of Practice on Agroecology Education') }}</h3>
                            <p class="text-sm text-gray-700">{{ t('Members of our community of practice discuss their programmes in relation to the signature pedagogies they use. Presented in English and Spanish.') }}</p>
                        </div>
                        <a href="https://www.youtube.com/watch?v=9J9qexS15w0" target="_blank" rel="noopener noreferrer"
                            class="mt-5 self-start px-6 py-3 text-white bg-ifa-green flex flex-row items-center gap-3
                                        hover:bg-black font-semibold text-xs rounded-full uppercase transition">
                            <span>{{ t('Watch') }}</span>
                            <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" class="h-5 w-5"
                                stroke-miterlimit="2" fill="white" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="m14.523 18.787s4.501-4.505 6.255-6.26c.146-.146.219-.338.219-.53s-.073-.383-.219-.53c-1.753-1.754-6.255-6.258-6.255-6.258-.144-.145-.334-.217-.524-.217-.193 0-.385.074-.532.221-.293.292-.295.766-.004 1.056l4.978 4.978h-14.692c-.414 0-.75.336-.75.75s.336.75.75.75h14.692l-4.979 4.979c-.289.289-.286.762.006 1.054.148.148.341.222.533.222.19 0 .378-.072.522-.215z" fill-rule="nonzero" />
                            </svg>
                        </a>
                    </div>

                </div>
            </div>
        </div>

    </div>
@endsection
