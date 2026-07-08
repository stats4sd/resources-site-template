@props(['link'])

@php
    $url = $link['url'] ?? null;
    $embedUrl = ($link['embeddable'] ?? false) ? ($link['embed_url'] ?? null) : null;
    $host = $url ? preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST)) : null;
@endphp

@if($embedUrl)
    <div class="rounded-2xl overflow-hidden shadow-sm max-w-2xl mx-auto">
        <iframe class="w-full aspect-video" src="{{ $embedUrl }}"
            frameborder="0" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
    </div>
@elseif($url)
    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
        class="flex items-center justify-between p-4 rounded-2xl bg-white shadow-sm border border-gray-100 hover:border-brand-primary transition group max-w-2xl mx-auto">
        <div class="flex items-center gap-3 min-w-0">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 shrink-0 text-gray-400 group-hover:text-brand-primary transition">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 0 1 0 .656l-5.603 3.113a.375.375 0 0 1-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112Z"/>
            </svg>
            <div class="min-w-0">
                <p class="font-medium truncate">{{ $link['title'] ?? $host }}</p>
                <p class="text-sm text-gray-500 truncate">{{ $host }}</p>
            </div>
        </div>
        <span class="text-sm text-gray-500 group-hover:text-brand-primary whitespace-nowrap ml-4">{{ t('Watch on') }} {{ $host }} ↗</span>
    </a>
@endif
