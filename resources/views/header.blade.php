@if(isset($_GET["origin"]) && $_GET["origin"] === "ifa")
     <header class="sticky top-0 z-50 bg-white  lg:pl-20 theme_ifa" x-data="{ open: false }">
        <div class=" w-full flex flex-col md:flex-row justify-between items-center min-h-16 ">
            <!-- Logos -->
            
            <div class="flex justify-start w-full xl:w-2/3 items-center space-x-4 pl-4 lg:pl-0 lg:space-x-8 mb-4 sm:mb-0">
            
                <a href="https://www.uvm.edu/instituteforagroecology">
                    <img src="{{ asset('images/ifalogo1.png') }}" class="max-h-16  " alt="crfs">
                </a>

    


            </div>

            <nav class="flex w-full  justify-end">
                <ul class="flex justify-between flex-col md:flex-row lg:justify-end md:space-x-6 font-medium uppercase w-full text-xs md:text-sm">

                    <!-- Language Dropdown -->
                    <li class="relative nav-item dropdown flex   pl-4 pr-8 md:px-0 md:pl-0 min-w-40" x-data="{ langOpen: false }">
                        <a class="nav-link dropdown-toggle flex w-full justify-end m items-center py-4" role="button" aria-expanded="false"
                            x-on:click="langOpen = !langOpen">
                            {{ t('Change Language') }}
                            <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" class="h-5 w-5 ml-2"
                                fill="black" stroke-miterlimit="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="m16.843 10.211c.108-.141.157-.3.157-.456 0-.389-.306-.755-.749-.755h-8.501c-.445 0-.75.367-.75.755 0 .157.05.316.159.457 1.203 1.554 3.252 4.199 4.258 5.498.142.184.36.29.592.29.23 0 .449-.107.591-.291 1.002-1.299 3.044-3.945 4.243-5.498z" />
                            </svg>
                        </a>
                        <div class="language-dropdown-menu min-w-[11rem] top-9 md:top-16" x-show="langOpen" x-on:click.outside="langOpen = false"
                           style="display:none">
                            <a class="dropdown-item" href="{{ URL::current() . '?locale=en' }}">English</a>
                            <a class="dropdown-item" href="{{ URL::current() . '?locale=es' }}">Español</a>
                            <a class="dropdown-item" href="{{ URL::current() . '?locale=fr' }}">Français</a>
                        </div>
                    </li>
                    <li>
                    
                     <a class=" py-2  sm:px-8 bg-stats4sd-red text-white w-full flex flex-col items-center sm:items-start w-full md:w-80  justify-left text-left  "
                            href="https://stats4sd.org/resources">


                            <div class="flex flex-row  gap-2 justify-left ">
                                <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" fill="#ffffff"
                                    stroke-miterlimit="2" viewBox="0 0 24 24" height="1.5rem" class="w-min my-auto md:mr-4 flex-none "
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="m9.474 5.209s-4.501 4.505-6.254 6.259c-.147.146-.22.338-.22.53s.073.384.22.53c1.752 1.754 6.252 6.257 6.252 6.257.145.145.336.217.527.217.191-.001.383-.074.53-.221.293-.293.294-.766.004-1.057l-4.976-4.976h14.692c.414 0 .75-.336.75-.75s-.336-.75-.75-.75h-14.692l4.978-4.979c.289-.289.287-.761-.006-1.054-.147-.147-.339-.221-.53-.221-.191-.001-.38.071-.525.215z"
                                        fill-rule="nonzero" />
                                </svg>
                                <div class=" ml-2 md:ml-0">
                                    <span class="uppercase text-xs font-normal ">{{ t('Part of the') }}</span>
                                    <h2 class="text-sm md:text-lg normal-case font-bold ">{{ t('Stats4SD Resources Library') }}</h2>
                                </div>

                            </div>
                        </a>

                    </li>
                </ul>
            </nav>
        </div>
        
    </header>

@else
@php 
    $origin = 'default';
@endphp
    <header class="sticky top-0 z-50 bg-white  px-8 sm:px-20" x-data="{ open: false }">
        <div class="container mx-auto flex justify-between items-center py-4">
            <!-- Logo -->
            <div class="flex items-center space-x-4">
                {{-- <a href="https://stats4sd.org/">
                    <img src="/images/Stats4SD_logo.png" alt="Stats4SD logo" class="h-4 w-auto">
                </a> --}}
                <a class="py-2 px-4 bg-stats4sd-red hover:bg-black text-white flex font-bold rounded-full w-max"
                    href="{{ config('app.front_end_url') }}">
                    <span class="inline"><svg xmlns="http://www.w3.org/2000/svg" class="mr-2" width="20" height="20"
                            fill="white" viewBox="0 0 20 20">
                            <path d="M0 12l9-8v6h15v4h-15v6z" />
                        </svg></span> Stats4SD Home
                </a>

            </div>

            <!-- Hamburger Menu (visible on small screens) -->
            <button class="lg:hidden text-gray-800 focus:outline-none" x-on:click="open = !open">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                    stroke="currentColor" class="h-6 w-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <!-- Nav Items (hidden on small screens) -->
            <nav class="hidden lg:flex">
                <ul class="flex space-x-6 font-medium uppercase text-base">
                    <li><a href="/home"
                            class=" hover:text-stats4sd-red {{ request()->is('home') ? 'border-b-[6px] pb-5 border-stats4sd-red pb-1' : '' }} ">
                            {{ t('Library Home') }}
                        </a></li>
                    <li><a href="/resources"
                            class=" hover:text-stats4sd-red  {{ request()->is('resources') ? 'border-b-[6px] pb-5 border-stats4sd-red pb-1' : '' }} !hover:text-red">
                            {{ t('Resources') }}
                        </a></li>
                    <li><a href="/collections"
                            class=" hover:text-stats4sd-red  {{ request()->is('collections') ? 'border-b-[6px] pb-5 border-stats4sd-red pb-1' : '' }} !hover:text-red">
                            {{ t('Collections') }}
                        </a></li>
                    <li><a href="/browse-all"
                            class=" hover:text-stats4sd-red  {{ request()->is('browse-all') ? 'border-b-[6px] pb-5 border-stats4sd-red pb-1' : '' }} !hover:text-red">
                            {{ t('Browse all') }}
                        </a></li>
                    <li><a href="/theme-pages"
                            class=" hover:text-stats4sd-red {{ request()->is('theme-pages') ? 'border-b-[6px] pb-5 border-stats4sd-red pb-1' : '' }}">

                            {{ t('Theme Pages') }}

                        </a></li>

                    <!-- Language Dropdown -->
                    <li class="relative nav-item dropdown" x-data="{ langOpen: false }">
                        <a class="nav-link dropdown-toggle" role="button" aria-expanded="false"
                            x-on:click="langOpen = !langOpen">
                            {{ t('Change Language') }}
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

        <!-- Nav Items (visible on small screens) -->
        <div class="lg:hidden" x-show="open" x-on:click.outside="open = false" style="display: none;">
            <nav class="bg-white text-right">
                <ul class="flex flex-col space-y-2 px-6 pb-4">
                    <li><a href="/home" class="text-gray-800 hover:text-gray-600">{{ t('Library Home') }}</a></li>
                    <li><a href="/resources" class="text-gray-800 hover:text-gray-600">{{ t('Resources') }}</a></li>
                    <li><a href="/collections" class="text-gray-800 hover:text-gray-600">{{ t('Collections') }}</a></li>
                    <li><a href="/browse-all" class="text-gray-800 hover:text-gray-600">{{ t('Browse All') }}</a></li>
                    <li><a href="/theme-pages" class="text-gray-800 hover:text-gray-600">{{ t('Theme Pages') }}</a></li>
                    <li class="relative nav-item pt-2 text-gray-800" x-data="{ langOpen: false }">
                        <a class="nav-link" role="button" x-on:click="langOpen = !langOpen">
                            {{ t('Change Language') }}
                        </a>
                        <ul class="language-options" x-show="langOpen" x-on:click.outside="langOpen = false"
                            style="display:none">
                            <li><a class="pt-2" href="{{ URL::current() . '?locale=en' }}">English</a></li>
                            <li><a class="py-2" href="{{ URL::current() . '?locale=es' }}">Español</a></li>
                            <li><a href="{{ URL::current() . '?locale=fr' }}">Français</a></li>
                        </ul>
                    </li>
                </ul>
            </nav>
        </div>
</header>
                @endif