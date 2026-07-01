<!-- Footer - customise directly in this file -->
<footer class="bg-brand-footer-bg py-8">
    <div class="max-w-screen-lg 2xl:max-w-screen-xl px-12 sm:px-24 md:px-16 2xl:px-24 mx-auto">

        <div class="flex flex-col sm:flex-row justify-between items-center gap-6 text-brand-footer-text text-sm">

            <!-- Org name + attribution -->
            <div>
                <a href="{{ config('branding.home_url') }}" class="font-bold hover:underline text-brand-footer-text text-base">
                    {{ config('branding.org_name') }}
                </a>
                <p class="text-xs opacity-70 mt-1">
                    Built using the
                    <a href="https://stats4sd.org" target="_blank" rel="noopener" class="underline">Stats4SD</a>
                    Resources Repository
                    <a href="https://github.com/stats4sd/resources-site-template" target="_blank" rel="noopener" class="underline">Template</a>
                </p>
            </div>

            <!-- Social icons -->
            @if(config('branding.linkedin_url') || config('branding.youtube_url'))
                <div class="flex space-x-4">
                    @if(config('branding.linkedin_url'))
                        <a href="{{ config('branding.linkedin_url') }}" target="_blank" rel="noopener">
                            <img src="{{ asset('/images/linkedin_logo.png') }}" class="w-8 h-8" alt="LinkedIn">
                        </a>
                    @endif
                    @if(config('branding.youtube_url'))
                        <a href="{{ config('branding.youtube_url') }}" target="_blank" rel="noopener">
                            <img src="{{ asset('/images/youtube_logo.png') }}" class="w-8 h-8" alt="YouTube">
                        </a>
                    @endif
                </div>
            @endif

            <!-- Staff login -->
            <a href="/admin" class="bg-brand-footer-text text-brand-footer-bg py-2 px-8 rounded-full whitespace-nowrap">
                Staff Login
            </a>

        </div>
    </div>
</footer>
