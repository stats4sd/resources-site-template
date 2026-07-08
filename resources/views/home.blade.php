@extends('layouts.app')
@section('content')
    <div class="relative">
        <div class="w-full mt-24 mb-12">

            <div class="flex flex-col md:flex-row items-start gap-12 w-full">
                <div class="flex flex-col w-full md:w-[45%]">
                    <div class="flex flex-row">
                        <div class="bg-brand-primary w-6 flex-shrink-0"></div>

                        <div class="pl-32 pr-8 max-w-2xl">
                            <div class="text-4xl md:text-5xl font-bold text-brand-primary">
                                {{ \App\Models\SiteContent::get('home_heading_line1') }}
                            </div>
                            <div class="text-5xl md:text-6xl font-bold pt-2">
                                {{ \App\Models\SiteContent::get('home_heading_line2') }}
                            </div>

                            <p class="pt-16 mb-4">
                                {{ \App\Models\SiteContent::get('home_intro') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="w-full md:w-[55%] flex items-start pt-44 pl-12 xl:pl-20">
                    <a href="/browse-all"
                        class="group inline-flex items-center gap-6 bg-brand-primary hover:opacity-90 transition-opacity duration-200 text-white px-12 py-8 rounded-full shadow-lg">
                        <div class="flex flex-col">
                            <span class="font-bold text-xl leading-snug">{{ t('Browse Library') }}</span>
                            <span class="text-sm font-normal opacity-80 leading-snug">{{ t('Browse the full library of resources and collections.') }}</span>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 flex-shrink-0 transition-transform duration-300 group-hover:translate-x-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </a>
                </div>
            </div>

        </div>
    </div>
    <div class="pb-20"></div>
@endsection
