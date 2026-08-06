@php
    $bannerImagePath = \App\Models\SiteContent::get('banner_image');
    $bannerImageUrl = $bannerImagePath
        ? \Illuminate\Support\Facades\Storage::disk(config('media-library.disk_name'))->url($bannerImagePath)
        : (file_exists(public_path('images/banner.png')) ? asset('images/banner.png') : null);

    $aboutContentItemsRaw = \App\Models\SiteContent::get('about_content_items');
    $aboutContentItems = $aboutContentItemsRaw
        ? json_decode($aboutContentItemsRaw, true)
        : \App\Filament\Pages\SiteContentIfaPage::defaultAboutContentItems();
@endphp
@extends('layouts.app')
@section('content')
    <div class="relative">

        <div class="relative">
            <!-- Background Image -->
            @if ($bannerImageUrl)
                <img src="{{ $bannerImageUrl }}" alt="Background Image"
                    class="absolute inset-0 w-full h-[20rem] sm:h-[12rem] md:h-[20rem] object-cover filter brightness-[70%] z-0">
            @endif

            <!-- Overlay Content -->
            <div class="relative z-10 flex flex-col items-start w-full h-[20rem] sm:h-[12rem] md:h-[20rem] text-white">
                <div class="h-[18rem] sm:h-[15rem] md:h-[20rem] pb-16 flex flex-col sm:flex-row items-end w-full 2xl:pr-32">
                    <div class="pt-10 px-4 sm:pl-16 2xl:pl-32 flex-grow" style="text-wrap: balance">
                        <h1 class="font-bold text-2xl sm:text-3xl md:text-5xl mb-4 md:!leading-[3.5rem]">
                            {{ \App\Models\SiteContent::get('shared_hero_heading') }}
                        </h1>
                        <h2 class="font-normal text-lg sm:text-xl">{{ \App\Models\SiteContent::get('about_ifa_heading_line2') }}</h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top section -->
        <div class="w-full bg-gray-100 flex justify-center py-6">
            <div class="flex flex-col lg:flex-row items-top justify-between gap-12 w-full max-w-7xl px-8 lg:px-12 py-6">
                <div class="text-sm md:text-base lg:w-3/6 px-4 lg:px-0">
                    <div class="[&_p:not(:last-child)]:mb-4 [&_a]:underline [&_a]:font-medium [&_a]:text-brand-primary [&_a:hover]:no-underline">
                        {!! \Illuminate\Support\Str::markdown(
                            \App\Models\SiteContent::get('about_ifa_body') ?? '',
                            ['html_input' => 'escape', 'allow_unsafe_links' => false]
                        ) !!}
                    </div>
                </div>

                <div class="w-full lg:w-3/6 px-4">
                    <h2 class="text-black text-2xl mb-4">{{ t('Quick links') }}</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <a href="{{ url('/home') }}"
                            class="px-6 pt-3 pb-2 text-white bg-brand-primary flex flex-row justify-between
                                        hover:bg-black
                                        font-semibold text-xs rounded-full uppercase text-center transition">
                            <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" class="h-6 w-6 mr-3"
                                stroke-miterlimit="2" fill="white" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M2.117 12l7.527 6.235-.644.765-9-7.521 9-7.479.645.764-7.529 6.236h21.884v1h-21.883z" />
                            </svg>
                            <span>{{ t('Resource Library') }}</span>
                        </a>
                        <a href="mailto:georgemca20@gmail.com"
                            class="px-6 pt-3 pb-2 text-white bg-brand-primary flex flex-row justify-between
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

        @foreach ($aboutContentItems as $item)
            @php
                $itemTitle = \App\Filament\Pages\SiteContentIfaPage::pickLocaleValue($item['title'] ?? null);
                $itemType = \App\Filament\Pages\SiteContentIfaPage::pickLocaleValue($item['type'] ?? null);
                $itemDescription = \App\Filament\Pages\SiteContentIfaPage::pickLocaleValue($item['description'] ?? null);
                $itemButtonLabel = \App\Filament\Pages\SiteContentIfaPage::pickLocaleValue($item['button_label'] ?? null) ?: t('Read');
                $itemKicker = collect([$itemType, $item['source'] ?? null])->filter()->implode(' · ');
            @endphp

            <!-- {{ $itemType }} section -->
            <div class="flex flex-row w-full h-full justify-between gap-12 mt-12">
                <div class="bg-brand-primary w-6 flex-shrink-0 h-auto"></div>
                <div class="h-auto w-full max-w-7xl py-3 px-4 md:pl-12">
                    <h2 class="text-black text-3xl">{{ $itemTitle }}</h2>
                    @if ($itemKicker)
                        <p class="text-xs uppercase font-semibold text-brand-primary mt-2">{{ $itemKicker }}</p>
                    @endif
                    <p class="text-sm md:text-base mt-2 text-gray-700">{{ $itemDescription }}</p>
                </div>
                <div class="bg-none w-6 flex-shrink-0 h-auto"></div>
            </div>

            <div class="flex justify-center w-full py-8 {{ $loop->last ? 'mb-12' : '' }}">
                <div class="w-full max-w-3xl px-8 lg:px-16 text-center">
                    @if (! empty($item['embeddable']) && ! empty($item['embed_url']))
                        <div class="aspect-video bg-gray-200 rounded overflow-hidden text-left">
                            <iframe
                                class="w-full h-full"
                                src="{{ $item['embed_url'] }}"
                                title="{{ $itemTitle }}"
                                frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen>
                            </iframe>
                        </div>
                    @else
                        <a href="{{ $item['url'] ?? '#' }}" target="_blank" rel="noopener noreferrer"
                            class="inline-flex px-6 py-3 text-white bg-brand-primary flex-row items-center gap-3
                                        hover:bg-black font-semibold text-xs rounded-full uppercase transition">
                            <span>{{ $itemButtonLabel }}</span>
                            <svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" class="h-5 w-5"
                                stroke-miterlimit="2" fill="white" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="m14.523 18.787s4.501-4.505 6.255-6.26c.146-.146.219-.338.219-.53s-.073-.383-.219-.53c-1.753-1.754-6.255-6.258-6.255-6.258-.144-.145-.334-.217-.524-.217-.193 0-.385.074-.532.221-.293.292-.295.766-.004 1.056l4.978 4.978h-14.692c-.414 0-.75.336-.75.75s.336.75.75.75h14.692l-4.979 4.979c-.289.289-.286.762.006 1.054.148.148.341.222.533.222.19 0 .378-.072.522-.215z" fill-rule="nonzero" />
                            </svg>
                        </a>
                    @endif
                </div>
            </div>
        @endforeach

    </div>
@endsection
