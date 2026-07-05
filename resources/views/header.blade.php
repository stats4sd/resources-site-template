<header class="sticky top-0 z-50 bg-brand-bg px-8 sm:px-20" x-data="{ open: false }">
    <div class="container mx-auto flex justify-between items-center py-4">
        <!-- Logo: place your logo at public/images/logo.png -->
        <div class="flex items-center">
            @if(file_exists(public_path('images/logo.png')))
                <a href="{{ config('branding.home_url') }}">
                    <img src="/images/logo.png" alt="{{ config('branding.org_name') }} logo" class="h-10 w-auto">
                </a>
            @endif
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
                        class=" hover:text-brand-primary {{ request()->is('home') ? 'border-b-[6px] pb-5 border-brand-primary pb-1' : '' }} ">
                        {{ t('Library Home') }}
                    </a></li>
                <li><a href="/browse-all"
                        class=" hover:text-brand-primary  {{ request()->is('browse-all') ? 'border-b-[6px] pb-5 border-brand-primary pb-1' : '' }} !hover:text-red">
                        {{ t('Browse Library') }}
                    </a></li>

                <!-- Language Dropdown - hidden automatically when only one locale is configured -->
                @if(count(config('branding.locales')) > 1)
                <li class="relative nav-item dropdown" x-data="{ langOpen: false }">
                    <a class="nav-link dropdown-toggle" role="button" aria-expanded="false"
                        x-on:click="langOpen = !langOpen">
                        {{ t('Change Language') }}
                    </a>
                    <div class="language-dropdown-menu" x-show="langOpen" x-on:click.outside="langOpen = false"
                        style="display:none">
                        @foreach(config('branding.locales') as $code => $label)
                            <a class="dropdown-item" href="{{ URL::current() . '?locale=' . $code }}">{{ $label }}</a>
                        @endforeach
                    </div>
                </li>
                @endif
            </ul>
        </nav>

    </div>

    <!-- Nav Items (visible on small screens) -->
    <div class="lg:hidden" x-show="open" x-on:click.outside="open = false" style="display: none;">
        <nav class="bg-brand-bg text-right">
            <ul class="flex flex-col space-y-2 px-6 pb-4">
                <li><a href="/home" class="text-gray-800 hover:text-gray-600">{{ t('Library Home') }}</a></li>
                <li><a href="/browse-all" class="text-gray-800 hover:text-gray-600">{{ t('Browse Library') }}</a></li>
                @if(count(config('branding.locales')) > 1)
                <li class="relative nav-item pt-2 text-gray-800" x-data="{ langOpen: false }">
                    <a class="nav-link" role="button" x-on:click="langOpen = !langOpen">
                        {{ t('Change Language') }}
                    </a>
                    <ul class="language-options" x-show="langOpen" x-on:click.outside="langOpen = false"
                        style="display:none">
                        @foreach(config('branding.locales') as $code => $label)
                            <li><a class="py-2" href="{{ URL::current() . '?locale=' . $code }}">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </li>
                @endif
            </ul>
        </nav>
    </div>
</header>
