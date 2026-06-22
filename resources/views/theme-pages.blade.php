@extends('layouts.app')
@section('content')
    <div class="relative">
        <!-- Background Image-->
        <img src="images/crops.png" alt="Background Image"
            class="absolute inset-0 w-full h-[35vh] sm:h-[30vh] object-cover filter brightness-50 z-0">

        <!-- Overlay Content -->
        <div class="relative z-10 flex flex-col items-center justify-center h-[35vh] sm:h-[30vh] px-4 text-white">
            <div class="max-w-3xl w-full mx-auto text-left">
                <!-- Heading -->
                <div class="font-bold text-4xl sm:text-5xl md:text-5xl">
                    {{ t('Theme pages') }}
                </div>

                <!-- Description -->
                <div class="mt-6 text-left pr-2 mx-auto">
                    <p class="mb-4 text-xl">{!! t(
                        'Smaller offshoot libraries focused around a specific topic or featuring resources from partner organisations.',
                    ) !!}
                    </p>
                </div>
            </div>
        </div>


        <div
            class="py-12  mx-auto  justify-items-center justify-center px-12 md:px-28 text-xl flex flex-col md:flex-row gap-8">

            <a href="frn"
                class="relative group h-[26rem] w-[23rem] overflow-hidden hover-effect rounded-t-3xl rounded-bl-3xl mx-auto md:mx-0">

                <img src="images/frn7small.jpg" alt="IFA Let's EAT" class="absolute inset-0 w-full h-full object-cover z-0">

                <div
                    class="absolute bottom-0 w-full z-10 bg-black bg-opacity-70 text-white p-6 pb-3 rounded-bl-3xl h-[12rem]">
                    <div class="text-xl font-bold mb-2 uppercase">{{ t('FRN Research Methods Hub') }}</div>
                    <p class="text-base">{{ t('A library of reference materials, tools, and useful information relevant to farmer research networks.') }}
                    </p>
                </div>
            </a>
            {{-- IFA hub hidden for now
            <a href="ifa"
                class="relative group h-[26rem] w-[23rem] overflow-hidden hover-effect rounded-t-3xl rounded-bl-3xl mx-auto md:mx-0">

                <img src="images/ifa1.png" alt="IFA Let's EAT" class="absolute inset-0 w-full h-full object-cover z-0">

                <div
                    class="absolute bottom-0 w-full z-10 bg-black bg-opacity-70 text-white p-6 pb-3 rounded-bl-3xl h-[12rem]">
                    <div class="text-xl font-bold mb-2 uppercase">{{ t('Let's Educate for Agroecological Transformations') }}</div>
                    <p class="text-sm">{{ t('The Let's Educate for Agroecological Transformations (EAT) project ...') }}
                    </p>
                </div>
            </a>
            --}}

        </div>

    </div>
@endsection
