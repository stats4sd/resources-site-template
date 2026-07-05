@extends('layouts.app')

@section('content')

@php
    $availableLanguages = array_keys($collection->getTranslations('title'));
    $currentLocale = request('locale', app()->getLocale());
    $allLocales = config('branding.locales', ['en' => 'English']);
    $resourceCount = $collection->troves()->whereNotNull('published_at')->count();
@endphp

<!-- Hero: full-width typography on brand-primary -->
<div class="bg-brand-primary">
    <div class="container mx-auto px-8 lg:px-32 py-16 lg:py-20">
        <div>
            <p class="text-xs uppercase tracking-widest text-white/60 mb-4">{{ t('Collection') }}</p>
            <h1 class="text-4xl lg:text-6xl font-bold text-white leading-tight max-w-4xl">{{ $collection->title }}</h1>
            <p class="text-sm text-white/60 mt-4">{{ $resourceCount }} {{ t('resources') }}</p>

            @if(count($availableLanguages) > 1)
                <div class="flex flex-wrap items-center gap-2 mt-6">
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

<!-- Main Content -->
<div class="container mx-auto py-12 px-8 lg:px-32">

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
            <a href="mailto:?subject={{ rawurlencode($collection->title) }}&body={{ rawurlencode(request()->url()) }}"
                title="{{ t('Share by email') }}"
                class="w-8 h-8 rounded-full border border-gray-200 flex items-center justify-center text-gray-500 hover:border-brand-primary hover:text-brand-primary transition">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
            </a>
            <!-- WhatsApp -->
            <a href="https://wa.me/?text={{ urlencode($collection->title . ' ' . request()->url()) }}"
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
            <button onclick="copyCollectionLink(this)" title="{{ t('Copy link') }}"
                class="w-8 h-8 rounded-full border border-gray-200 flex items-center justify-center text-gray-500 hover:border-brand-primary hover:text-brand-primary transition">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
            </button>
        </div>
    </div>

    <!-- Description -->
    <div class="divider"></div>
    <h2 class="text-2xl font-bold mb-4">{{ t('Description') }}</h2>
    @if($collection->created_at)
        <p class="text-xs text-gray-400 mb-4">{{ \Carbon\Carbon::parse($collection->created_at)->translatedFormat('F Y') }}</p>
    @endif
    <div class="text-gray-700 leading-relaxed">{!! t($collection->description) !!}</div>

    <!-- Resources -->
    <div id="collection-resources" class="mt-16">
        <div class="divider"></div>
        <h2 class="text-2xl font-bold mb-6">{{ t('Resources in this collection') }}</h2>
        <livewire:collection-troves :collection="$collection"/>
    </div>

</div>

@endsection

<script>
    function scrollToSection(sectionId) {
        const target = document.getElementById(sectionId);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth' });
        }
    }

    function copyCollectionLink(btn) {
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
