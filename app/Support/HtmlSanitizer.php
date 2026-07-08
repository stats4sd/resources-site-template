<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonyHtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitises rich-text HTML produced by (or pasted into) Filament's RichEditor
 * before it is persisted, stripping scripts, event handlers, iframes and any
 * other markup outside the curated formatting allow-list below.
 *
 * Backed by symfony/html-sanitizer (already a framework dependency). Used as the
 * dehydration step on the Trove/Collection `description` fields so the stored
 * value is always safe to render with {!! !!} on the public site.
 */
class HtmlSanitizer
{
    /**
     * Sanitise a single HTML string. Null/empty passes through untouched so
     * empty locale values stay empty rather than becoming "".
     */
    public static function clean(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        return static::sanitizer()->sanitize($html);
    }

    protected static function sanitizer(): SymfonyHtmlSanitizer
    {
        $config = (new HtmlSanitizerConfig())
            // Block-level & structural
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('hr')
            ->allowElement('blockquote')
            ->allowElement('pre')
            ->allowElement('h1')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('h4')
            ->allowElement('h5')
            ->allowElement('h6')
            // Lists
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            // Inline formatting
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('del')
            ->allowElement('sub')
            ->allowElement('sup')
            ->allowElement('code')
            ->allowElement('span')
            // Links: only these attributes survive; schemes are restricted below.
            ->allowElement('a', ['href', 'target', 'rel'])
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowRelativeLinks()
            // Force safe rel on every link (defends target="_blank" tab-nabbing).
            ->forceAttribute('a', 'rel', 'noopener noreferrer');

        return new SymfonyHtmlSanitizer($config);
    }
}
