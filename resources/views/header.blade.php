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