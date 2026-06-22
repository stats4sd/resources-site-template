<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        {{-- Fonts - open sans, lato, lora --}}
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400..700;1,400..700&family=Open+Sans:ital,wdth,wght@0,75..100,300..800;1,75..100,300..800&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

        <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css'])
{{--        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>--}}

        @if(config('app.env') != 'local')
            @include('layouts.analytics')
        @endif
    </head>
    <body>
        @if(empty($hideHeader))
            @include('header')
        @endif
        @isset($slot)
            {{ $slot }}
        @else
            @yield('content')
        @endisset
        <button id="scrollToTopButton" class="hidden fixed bottom-5 right-5 w-12 h-12 {{ $scrollButtonColor ?? 'bg-stats4sd-red hover:bg-teal' }} text-white rounded-full shadow-lg flex items-center justify-center transition-opacity duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15.75 12 12m0 0 3.75 3.75M12 12v9M21 3H3" />
            </svg>
        </button>
        @include('footer')
        @livewireScripts
    </body>
</html>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const scrollToTopButton = document.getElementById("scrollToTopButton");

        window.addEventListener("scroll", function () {
            if (window.scrollY > 300) {
                scrollToTopButton.classList.remove("hidden");
            } else {
                scrollToTopButton.classList.add("hidden");
            }
        });

        scrollToTopButton.addEventListener("click", function () {
            window.scrollTo({ top: 0, behavior: "smooth" });
        });
    });
</script>
