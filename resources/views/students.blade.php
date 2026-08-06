@php
    $bannerImagePath = \App\Models\SiteContent::get('banner_image');
    $bannerImageUrl = $bannerImagePath
        ? \Illuminate\Support\Facades\Storage::disk(config('media-library.disk_name'))->url($bannerImagePath)
        : (file_exists(public_path('images/banner.png')) ? asset('images/banner.png') : null);
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
                    <!-- Heading -->
                    <div class="pt-10 px-4 sm:pl-16 2xl:pl-32 flex-grow" style="text-wrap: balance">
                        <h1 class="font-bold text-2xl sm:text-3xl md:text-5xl mb-4 md:!leading-[3.5rem]">
                            {{ \App\Models\SiteContent::get('shared_hero_heading') }}
                        </h1>
                        <h2 class="font-normal text-lg sm:text-xl">{{ \App\Models\SiteContent::get('students_ifa_heading_line2') }}</h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top section -->
        <div class="w-full bg-gray-100 flex justify-center py-6">
            <div class="flex flex-col lg:flex-row items-top justify-between gap-12 w-full max-w-7xl px-8 lg:px-12 py-6">
                <div class="text-sm md:text-base lg:w-3/6 px-4 lg:px-0 [&_p:not(:last-child)]:mb-4 [&_a]:underline [&_a]:font-medium [&_a]:text-brand-primary [&_a:hover]:no-underline">
                    {!! \Illuminate\Support\Str::markdown(
                        \App\Models\SiteContent::get('students_ifa_intro') ?? '',
                        ['html_input' => 'escape', 'allow_unsafe_links' => false]
                    ) !!}
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
                        <p class="text-xs uppercase font-semibold text-brand-primary mb-1">{{ t($inst['country']) }}</p>
                        @endif
                        <h2 class="font-bold text-xl text-black mb-3 leading-snug">{{ t($inst['name']) }}</h2>
                        @if($inst['course'])
                        <p class="text-sm font-medium text-brand-primary mb-2">{{ t($inst['course']) }}</p>
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
