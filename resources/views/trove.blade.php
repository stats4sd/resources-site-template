
@extends('layouts.app')

@section('content')
@php
    $availableLanguages = array_keys($resource->getTranslations('title'));
    $currentLocale = request('locale', app()->getLocale());
    $allLocales = config('branding.locales', ['en' => 'English']);
    $troveType = $resource->troveType;
    $authorTags = $resource->tags()->whereHas('tagType', function ($query) {
        $query->where('slug', 'authors');
    })->get();
    $authorLabel = $authorTags->count() === 1 ? t('Author') : t('Authors');
@endphp

<!-- Preview Banner -->
@if (!$resource->is_published)
    <div class="bg-brand-primary text-white py-4 px-6 font-semibold flex justify-center space-x-1">
        <x-heroicon-o-exclamation-circle class="w-6 h-6 text-white" />
        <span>{{ t('PREVIEW MODE: This resource has not been published and is only visible to authorised users') }}</span>
    </div>
@endif

<!-- Hero: colour panel / image -->
<div class="flex flex-col-reverse lg:flex-row lg:h-[500px]">

    <!-- Colour panel -->
    <div class="w-full lg:w-[45%] bg-brand-primary flex">

        <!-- Accent bar -->
        <div class="w-2 bg-white/60 flex-shrink-0"></div>

        <!-- Content: type at top, title + languages at bottom -->
        <div class="flex flex-col justify-between flex-1 p-8 lg:p-12">

            <div class="flex flex-wrap gap-2">
                @if($troveType)
                    <span class="text-xs uppercase tracking-widest bg-white/20 text-white px-3 py-1.5 rounded-full">
                        {{ $troveType->label }}
                    </span>
                @endif
            </div>

            <div>
                <h1 class="text-2xl lg:text-4xl text-white font-bold leading-tight mb-3">{{ $resource->title }}</h1>
                <div class="flex flex-wrap gap-x-5 gap-y-1 mb-4">
                    @if($resource->creation_date)
                        <span class="text-xs text-white/60">{{ \Carbon\Carbon::parse($resource->creation_date)->translatedFormat('F Y') }}</span>
                    @endif
                    @if($authorTags->isNotEmpty())
                        <span class="text-xs text-white/60">{{ $authorTags->pluck('name')->join(', ') }}</span>
                    @endif
                </div>
                @if(count($availableLanguages) > 1)
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-xs uppercase tracking-widest text-white/60">{{ t('Available in:') }}</p>
                        @foreach ($availableLanguages as $language)
                            <a href="{{ URL::current() . '?locale=' . $language }}"
                                class="px-3 py-1 rounded-full border border-white text-xs transition
                                {{ $currentLocale === $language ? 'bg-white text-black font-semibold' : 'text-white hover:bg-white hover:text-black' }}">
                                {{ $allLocales[$language] ?? $language }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>
    </div>

    <!-- Image -->
    <div class="w-full lg:w-[55%] h-56 lg:h-auto overflow-hidden">
        <img src="{{ $resource->getCoverImageUrl() }}" class="w-full h-full object-cover" alt="{{ $resource->title }}">
    </div>

</div>

<!-- Main Content -->
<div id="trove-content" class="container mx-auto py-12 px-8 lg:px-32">

    <div class="flex items-center justify-between mb-10">
        <a href="/browse-all" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-brand-primary transition">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
            </svg>
            {{ t('Browse Library') }}
        </a>

        <div class="flex items-center gap-3">
            <span class="text-xs uppercase tracking-widest text-gray-400">{{ t('Share') }}</span>
            <!-- Email -->
            <a href="mailto:?subject={{ rawurlencode($resource->title) }}&body={{ rawurlencode(request()->url()) }}"
                title="{{ t('Share by email') }}"
                class="w-8 h-8 rounded-full border border-gray-200 flex items-center justify-center text-gray-500 hover:border-brand-primary hover:text-brand-primary transition">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
            </a>
            <!-- WhatsApp -->
            <a href="https://wa.me/?text={{ urlencode($resource->title . ' ' . request()->url()) }}"
                target="_blank" rel="noopener noreferrer" title="{{ t('Share on WhatsApp') }}"
                class="w-8 h-8 rounded-full border border-gray-200 flex items-center justify-center text-gray-500 hover:border-brand-primary hover:text-brand-primary transition">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
            </a>
            <!-- LinkedIn -->
            <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(request()->url()) }}"
                target="_blank" rel="noopener noreferrer" title="{{ t('Share on LinkedIn') }}"
                class="w-8 h-8 rounded-full border border-gray-200 flex items-center justify-center text-gray-500 hover:border-brand-primary hover:text-brand-primary transition">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
            </a>
            <!-- Copy link -->
            <button onclick="copyTroveLink(this)" title="{{ t('Copy link') }}"
                class="w-8 h-8 rounded-full border border-gray-200 flex items-center justify-center text-gray-500 hover:border-brand-primary hover:text-brand-primary transition">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
            </button>
        </div>
    </div>

    @php
        $externalLinks = $resource->getTranslation('external_links', app()->getLocale());
        if (isset($externalLinks['link_url'])) {
            $externalLinks = [$externalLinks];
        }
        $mediaFiles = $resource->getContentMedia();

        $videoLinks = collect($resource->getTranslation('video_links', app()->getLocale()) ?? [])
            ->filter(fn ($link) => is_array($link) && ! empty($link['url']))
            ->values();
    @endphp


    <!-- Description -->
    <div class="divider"></div>
    <h2 class="text-2xl font-bold mb-4">{{ t('Description') }}</h2>
    <div class="text-gray-700 leading-relaxed">{!! t($resource->description) !!}</div>

    <!-- View / Download -->
    @if($videoLinks->isNotEmpty() || ($externalLinks && is_array($externalLinks)) || $mediaFiles->isNotEmpty())
        <div class="mt-16">
            <div class="divider"></div>
            <h2 class="text-2xl font-bold mb-4">{{ t('View / Download') }}</h2>

            <!-- Embedded Videos -->
            @if($videoLinks->isNotEmpty())
                <div class="mb-8 space-y-4">
                    @foreach($videoLinks as $link)
                        <x-video-link :link="$link" />
                    @endforeach
                </div>
            @endif

            @php
                $downloadItems = collect();
                if ($externalLinks && is_array($externalLinks)) {
                    foreach ($externalLinks as $link) {
                        if (isset($link['link_url'], $link['link_title'])) {
                            $downloadItems->push(['type' => 'link', 'data' => $link]);
                        }
                    }
                }
                foreach ($mediaFiles as $media) {
                    $downloadItems->push(['type' => 'file', 'data' => $media]);
                }
            @endphp

            <div class="flex flex-col gap-3">
                @foreach($downloadItems as $item)
                    @if($item['type'] === 'link')
                        <a href="{{ $item['data']['link_url'] }}" target="_blank" rel="noopener noreferrer"
                            class="flex items-center justify-between p-4 rounded-2xl bg-white shadow-sm border border-gray-100 hover:border-brand-primary transition group">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 rounded-full bg-brand-bg flex items-center justify-center flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-brand-primary">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/>
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-800">{{ $item['data']['link_title'] }}</p>
                                    <p class="text-xs text-gray-400 truncate">{{ $item['data']['link_url'] }}</p>
                                </div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-gray-300 group-hover:text-brand-primary transition flex-shrink-0 ml-3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
                            </svg>
                        </a>
                    @else
                        <a href="{{ $item['data']->getUrl() }}" target="_blank" rel="noopener noreferrer"
                            class="flex items-center justify-between p-4 rounded-2xl bg-white shadow-sm border border-gray-100 hover:border-brand-primary transition group">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 rounded-full bg-brand-bg flex items-center justify-center flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-brand-primary">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-800 truncate">{{ $item['data']->name }}</p>
                                    <p class="text-xs text-gray-400">{{ Number::fileSize($item['data']->size) }}</p>
                                </div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-gray-300 group-hover:text-brand-primary transition flex-shrink-0 ml-3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                            </svg>
                        </a>
                    @endif
                @endforeach
            </div>

            @if($downloadItems->isNotEmpty() || $videoLinks->isNotEmpty())
                <div class="flex justify-end mt-6">
                    <a href="{{ route('trove.download.zip', ['slug' => $resource->slug]) }}"
                        class="flex items-center gap-2 bg-brand-primary text-white font-semibold uppercase tracking-wide text-sm px-6 py-3 rounded-full hover:opacity-90 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                        </svg>
                        {{ t('Download all (ZIP)') }}
                    </a>
                </div>
            @endif
        </div>
    @endif

    @if($hasCollections)
    <!-- Collections -->
    <div class="mt-20">
        <div class="divider"></div>
        <h2 class="text-2xl font-bold mb-6">{{ t('Collections including this resource') }}</h2>
        <livewire:trove-collections :resource="$resource" />
    </div>

    <!-- Related Resources -->
    <div class="mt-16">
        <div class="divider"></div>
        <h2 class="text-2xl font-bold mb-6">{{ t('Related resources') }}</h2>
        <livewire:trove-related-troves :resource="$resource" />
    </div>
    @endif

</div>

<script>
    function scrollToSection(sectionId) {
        const target = document.getElementById(sectionId);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth' });
        }
    }

    function copyTroveLink(btn) {
        const url = '{{ request()->url() }}';
        const checkIcon = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-green-500"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>';
        const linkIcon = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>';
        const showCheck = () => { btn.innerHTML = checkIcon; setTimeout(() => btn.innerHTML = linkIcon, 2000); };
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(showCheck);
        } else {
            const el = document.createElement('textarea');
            el.value = url; document.body.appendChild(el); el.select(); document.execCommand('copy'); document.body.removeChild(el);
            showCheck();
        }
    }
</script>
@endsection
