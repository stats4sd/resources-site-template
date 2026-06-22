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
                    <!-- Heading -->
                    <div class="pt-10 px-4 sm:pl-16 2xl:pl-32 flex-grow" style="text-wrap: balance">
                        <h1 class="font-bold text-2xl sm:text-3xl md:text-5xl mb-4 md:!leading-[3.5rem]">
                            {{ t('Resource Library: Education for Agroecological Transformations') }}
                        </h1>
                        <h2 class="font-normal text-lg sm:text-xl">{{ t('Information for students') }}</h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top section -->
        <div class="w-full bg-gray-100 flex justify-center py-6">
            <div class="flex flex-col lg:flex-row items-top justify-between gap-12 w-full max-w-7xl px-8 lg:px-12 py-6">
                <div class="text-sm md:text-base lg:w-3/6 px-4 lg:px-0">
                    {{ t('Below you will find information about the various programs and courses run by our member institutions.') }}
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
        @php
        $institutions = [
            [
                'name'        => 'University of Vermont',
                'country'     => 'USA',
                'course'      => '',
                'description' => 'Information about this programme and its agroecology courses will be added here soon.',
            ],
            [
                'name'        => 'Veracruzana University',
                'country'     => 'Mexico',
                'course'      => '',
                'description' => 'Information about this programme and its agroecology courses will be added here soon.',
            ],
            [
                'name'        => 'ECOSUR',
                'country'     => 'Mexico',
                'course'      => '',
                'description' => 'Information about this programme and its agroecology courses will be added here soon.',
            ],
            [
                'name'        => 'Norwegian University of Life Sciences (NMBU)',
                'country'     => 'Norway',
                'course'      => '',
                'description' => 'Information about this programme and its agroecology courses will be added here soon.',
            ],
            [
                'name'        => 'UNIA/UCO/UPO',
                'country'     => 'Spain',
                'course'      => '',
                'description' => 'Information about this programme and its agroecology courses will be added here soon.',
            ],
            [
                'name'        => 'UCAS & PTU-K',
                'country'     => 'Palestine',
                'course'      => '',
                'description' => 'Information about this programme and its agroecology courses will be added here soon.',
            ],
        ];
        @endphp

        <div class="flex justify-center w-full mt-12 mb-16">
            <div class="w-full max-w-5xl px-8 lg:px-16">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach($institutions as $inst)
                <div class="flex flex-col justify-between p-6 bg-gray-50 rounded-t-[2.5rem] rounded-bl-[2.5rem] border border-gray-200">
                    <div>
                        @if($inst['country'])
                        <p class="text-xs uppercase font-semibold text-ifa-green mb-1">{{ t($inst['country']) }}</p>
                        @endif
                        <h2 class="font-bold text-xl text-black mb-3 leading-snug">{{ t($inst['name']) }}</h2>
                        @if($inst['course'])
                        <p class="text-sm font-medium text-ifa-green mb-2">{{ t($inst['course']) }}</p>
                        @endif
                        @if($inst['description'])
                        <p class="text-sm text-gray-600 leading-relaxed">{{ t($inst['description']) }}</p>
                        @endif
                    </div>
                </div>
                @endforeach
                </div>
            </div>
        </div>



    </div>
@endsection
