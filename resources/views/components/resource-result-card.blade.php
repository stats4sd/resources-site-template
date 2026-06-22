@props([
    'item',
    'color' => 'stats4sd-red',
    'showTags' => true,
    'textcol' => 'white',
    'target' => '_blank',
    'origin',
])


<div
    class="card hover-effect resource-card relative flex shadow-xl flex-col justify-between bg-[#f2f2f2] overflow-hidden  rounded-t-3xl rounded-bl-3xl ">
    @if (isset($origin))
        <a href="{{ url('resources/' . $item['slug'] . '?origin=' . $origin) }}" class="absolute inset-0 z-0"
            target="{{ $target }}">
        </a>
    @else
        <a href="{{ url('resources/' . $item['slug']) }}" class="absolute inset-0 z-0" target="{{ $target }}">
        </a>
    @endif
    <!-- Content -->
    <div class="flex flex-col justify-start">

        <!-- Image -->
        <div class="h-52 bg-cover bg-center mb-4 overflow-y-hidden">
            <img src="{{ $item['cover_image_thumb'] }}" class="overflow-y-hidden card-img w-full" />
        </div>
        {{-- Prep for different icon colours for different levels --}}
        @php
         $levelicon = 'bg-black';
        
            foreach ($item['tags'] as $tag1) {
                if ($levelicon == 'bg-black' AND
                    $tag1->name == 'Undergraduate' or
                    $tag1->name == 'Graduate/Masters' or
                    $tag1->name == 'PhD' or
                    $tag1->name == 'Diploma'
                ) {
                    $levelicon = 'icon_' . substr($tag1->name, 0, 3);
                } 
            }
        @endphp

     <div
                class="absolute top-4 left-4 h-12 w-12 resource-card-icon rounded-full text-white text-center py-auto {{ $levelicon }} ">
        {{-- Laying groundwork for different icons by trove type --}}
        {{-- @if (isset($item['troveTypes']->sortBy('order')->first()->label) &&
                $item['troveTypes']->sortBy('order')->first()->label == 'list of Resources')
       
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="white"
                    class="mx-auto my-3">
                    <path
                        d="M15 3c1.104 0 2 .896 2 2v4l7-4v14l-7-4v4c0 1.104-.896 2-2 2h-13c-1.104 0-2-.896-2-2v-14c0-1.104.896-2 2-2h13zm0 17c.552 0 1-.448 1-1v-14c0-.551-.448-1-1-1h-13c-.551 0-1 .449-1 1v14c0 .552.449 1 1 1h13zm2-9.848v3.696l6 3.429v-10.554l-6 3.429z" />
                </svg>
           
        @else
             --}}
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="white"
                    class="mx-auto my-3">
                    <path
                        d="M4 22v-20h16v11.543c0 4.107-6 2.457-6 2.457s1.518 6-2.638 6h-7.362zm18-7.614v-14.386h-20v24h10.189c3.163 0 9.811-7.223 9.811-9.614z" />
                </svg>
          
        {{-- @endif --}}
          </div>
        <!-- Trove Types -->
        @if (!empty($item['troveTypes']))
            <p class="text-sm text-{{ $color }} uppercase font-semibold mx-6">
                {{ $item['troveTypes']->sortBy('order')->first()->label ?? '' }}
            </p>
        @endif

        <!-- Title -->
        <p class="text-lg font-bold mx-6 mb-3 xl:min-h-8 text-black"> {!! $item['title'] !!}</p>

        <!-- Description -->
        <p class="text-sm  mb-2  flex-grow mx-6 mb-4 text-gray-600">
            {{ \Illuminate\Support\Str::limit(html_entity_decode(strip_tags($item['description']), ENT_QUOTES, 'UTF-8'), 110, '...') }}
        </p>

        <!-- Tags -->
        @if ($showTags && !empty($item['tags']))
            <div class="flex flex-wrap gap-2 mb-2 mx-6 mb-4">
                @foreach ($item['tags']->sortBy(fn($tag) => strtolower($tag->name)) as $tag)
                    <div class="grey-badge ">{{ $tag->name }}</div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- View Button -->
    <div class="flex justify-end mb-2 pb-4 px-8 ">
        <button class="bg-{{ $color }} text-{{ $textcol }} text-center py-2 px-8 rounded-full">
            {{ t('VIEW') }}
        </button>
    </div>

</div>
