@extends('layouts.app')
@section('content')
    <div class="relative">
        <div class="w-full mt-24 mb-12">

            <div class="flex flex-col md:flex-row items-start gap-12 w-full items-start justify-start ">
                <div class="flex flex-col w-full md:w-[45%] ">
                    <div class="flex flex-row h-full md:justify-between">
                        <div class="bg-stats4sd-red h-auto w-6 flex-shrink-0"></div>

                        <div class="pl-12 xl:pl-22 pr-12 h-auto max-w-2xl">
                            <div class="text-4xl md:text-5xl font-bold text-stats4sd-red ">
                                Stats4SD
                            </div>
                            <div class="text-5xl md:text-6xl font-bold pt-2 ">
                                {!! t('Resources Library') !!}
                            </div>
                        </div>
                    </div>
                    <div class=" flex flex-row pt-16 h-full md:justify-between">
                        <div class="bg-white h-auto w-6 flex-shrink-0"></div>

                        <p class="pl-12 2xl:pl-24 pr-12 h-auto w-max max-w-2xl mb-4 ">
                            {!! t(
                                'Welcome to the Stats4SD Resources Library - a carefully selected set of resources designed to support good practice in research methods. Select a starting point on the right to begin.',
                            ) !!}
                        </p>

                    </div>
                </div>

                <div class="w-full md:w-[55%] ">
                    <div class="bg-white max-w-2xl flex flex-wrap gap-8 justify-items-start">
                        @php
                            $cards = [
                                [
                                    'url' => '/resources',
                                    'title' => t('Search resources'),
                                    'desc' => t(
                                        'Guides, tools, videos, papers and other items that we use and recommend to others.',
                                    ),
                                    'image' => 'images/resources-card.jpg',
                                ],
                                [
                                    'url' => '/collections',
                                    'title' => t('Search collections'),
                                    'desc' => t(
                                        'A collection is a group of resources compiled together for a specific purpose, such as series of guides on a specific topic, or the reading for an online course.',
                                    ),
                                    'image' => 'images/theme-card.jpg',
                                ],
                                [
                                    'url' => '/browse-all',
                                    'title' => t('Browse All'),
                                    'desc' => t(
                                        'Browse the full library of resources and collections on a variety of topics.',
                                    ),
                                    'image' => 'images/browse-all-card.jpg',
                                ],
                                  [
                                    'url' => '/theme-pages',
                                    'title' => t('Theme pages'),
                                    'desc' => t(
                                        'Smaller offshoot libraries focused around a specific topic or featuring resources from partner organisations.',
                                    ),
                                    'image' => 'images/themes.jpg',
                                ],
                            ];
                        @endphp

                        @foreach ($cards as $card)
                            <a href="{{ $card['url'] }}"
                                class="relative group h-96 w-[18rem] overflow-hidden hover-effect rounded-t-3xl rounded-bl-3xl mx-auto md:mx-0">

                                <img src="{{ asset($card['image']) }}" alt="{{ $card['title'] }}"
                                    class="absolute inset-0 w-full h-full object-cover z-0">

                                <div
                                    class="absolute top-0 w-full z-10 bg-stats4sd-red text-white p-6 pb-3 rounded-t-3xl h-[11rem]">
                                    <div class="text-lg font-bold mb-2 uppercase">{{ $card['title'] }}</div>
                                    <p class="text-sm">{{ $card['desc'] }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

        </div>
    </div>
    <div class="pb-20"></div>
    </div>
@endsection
