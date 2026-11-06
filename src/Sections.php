<?php

declare(strict_types=1);

namespace Raips;

/**
 * The section templates. Each one takes props and returns markup, and none of
 * them reads a file, a clock or a session.
 */
final class Sections
{
    /** @var array<string, callable(array<string, mixed>): string> */
    private const TEMPLATES = [
        'header' => 'header',
        'hero' => 'hero',
        'features' => 'features',
        'overview' => 'overview',
        'pricing' => 'pricing',
        'faq' => 'faq',
        'about' => 'about',
        'gallery' => 'gallery',
        'blog' => 'blog',
        'contact' => 'contact',
        'cta' => 'callToAction',
        'testimonials' => 'testimonials',
        'footer' => 'footer',
    ];

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::TEMPLATES);
    }

    public static function knows(string $type): bool
    {
        return isset(self::TEMPLATES[$type]);
    }

    /** @param array<string, mixed> $section */
    public static function render(array $section): string
    {
        $type = (string) ($section['type'] ?? '');
        if (!self::knows($type)) {
            throw new BuildError(['section type ' . $type . ' is not a template this build carries']);
        }

        $props = is_array($section['props'] ?? null) ? $section['props'] : [];

        return self::{self::TEMPLATES[$type]}($props);
    }

    /** @param array<string, mixed> $props */
    private static function text(array $props, string $name, string $fallback = ''): string
    {
        $value = $props[$name] ?? $fallback;

        return is_scalar($value) ? (string) $value : $fallback;
    }

    /** @param array<string, mixed> $props */
    private static function list(array $props, string $name): array
    {
        $value = $props[$name] ?? [];

        return is_array($value) ? $value : [];
    }

    /** @param array<string, mixed> $props */
    private static function header(array $props): string
    {
        $brand = Html::esc(self::text($props, 'brand', 'Home'));
        $links = [];
        foreach (self::list($props, 'links') as $link) {
            if (!is_array($link)) {
                continue;
            }
            $href = Html::safeUrl($link['href'] ?? null);
            if ($href === null) {
                continue;
            }
            $links[] = '<a href="' . Html::esc($href) . '">' . Html::esc($link['label'] ?? '') . '</a>';
        }

        $cta = null;
        $ctaHref = Html::safeUrl($props['cta']['href'] ?? null);
        if ($ctaHref !== null) {
            $cta = '<a class="cta" href="' . Html::esc($ctaHref) . '">'
                . Html::esc($props['cta']['label'] ?? 'Contact') . '</a>';
        }

        return Html::lines(
            '<header class="r-header">',
            '  <a class="brand" href="' . $brand . '">' . $brand . '</a>',
            $links === [] ? '' : '  <nav>' . implode('', $links) . '</nav>',
            $cta === null ? '' : '  ' . $cta,
            '</header>',
        );
    }

    /** @param array<string, mixed> $props */
    private static function hero(array $props): string
    {
        $eyebrow = self::text($props, 'eyebrow');
        $ctaHref = Html::safeUrl($props['cta']['href'] ?? null);

        return Html::lines(
            '<section class="r-hero">',
            $eyebrow === '' ? '' : '  <p class="eyebrow">' . Html::esc($eyebrow) . '</p>',
            '  <h1>' . Html::esc(self::text($props, 'heading')) . '</h1>',
            self::text($props, 'subheading') === '' ? '' : '  <p class="lede">' . Html::esc(self::text($props, 'subheading')) . '</p>',
            $ctaHref === null ? '' : '  <a class="cta" href="' . Html::esc($ctaHref) . '">'
                . Html::esc($props['cta']['label'] ?? 'Start') . '</a>',
            '</section>',
        );
    }

    /** @param array<string, mixed> $props */
    private static function features(array $props): string
    {
        $items = [];
        foreach (self::list($props, 'items') as $item) {
            if (!is_array($item)) {
                continue;
            }
            $items[] = Html::lines(
                '  <li>',
                '    <h3>' . Html::esc($item['title'] ?? '') . '</h3>',
                '    <p>' . Html::esc($item['text'] ?? '') . '</p>',
                '  </li>',
            );
        }

        return Html::lines(
            '<section class="r-section r-features">',
            '  <h2>' . Html::esc(self::text($props, 'heading', 'What we do')) . '</h2>',
            '  <ul class="grid">',
            implode("\n", $items),
            '  </ul>',
            '</section>',
        );
    }

    /** @param array<string, mixed> $props */
    private static function overview(array $props): string
    {
        $paragraphs = [];
        foreach (self::list($props, 'paragraphs') as $p) {
            if (is_scalar($p)) {
                $paragraphs[] = '  <p>' . Html::esc($p) . '</p>';
            }
        }

        return Html::lines(
            '<section class="r-section r-overview">',
            '  <h2>' . Html::esc(self::text($props, 'heading', 'Overview')) . '</h2>',
            implode("\n", $paragraphs),
            '</section>',
        );
    }

    /** @param array<string, mixed> $props */
    private static function pricing(array $props): string
    {
        $plans = [];
        foreach (self::list($props, 'plans') as $plan) {
            if (!is_array($plan)) {
                continue;
            }
            $href = Html::safeUrl($plan['href'] ?? null);
            $plans[] = Html::lines(
                '  <li class="plan">',
                '    <h3>' . Html::esc($plan['name'] ?? '') . '</h3>',
                '    <p class="price">' . Html::esc($plan['price'] ?? '') . '</p>',
                '    <p>' . Html::esc($plan['text'] ?? '') . '</p>',
                $href === null ? '' : '    <a class="cta" href="' . Html::esc($href) . '">'
                    . Html::esc($plan['cta'] ?? 'Choose') . '</a>',
                '  </li>',
            );
        }

        return Html::lines(
            '<section class="r-section r-pricing">',
            '  <h2>' . Html::esc(self::text($props, 'heading', 'Plans')) . '</h2>',
            '  <ul class="grid">',
            implode("\n", $plans),
            '  </ul>',
            '</section>',
        );
    }

    /** @param array<string, mixed> $props */
    private static function faq(array $props): string
    {
        $items = [];
        foreach (self::list($props, 'items') as $item) {
            if (!is_array($item)) {
                continue;
            }
            $items[] = Html::lines(
                '  <details>',
                '    <summary>' . Html::esc($item['question'] ?? '') . '</summary>',
                '    <p>' . Html::esc($item['answer'] ?? '') . '</p>',
                '  </details>',
            );
        }

        return Html::lines(
            '<section class="r-section r-faq">',
            '  <h2>' . Html::esc(self::text($props, 'heading', 'Questions')) . '</h2>',
            implode("\n", $items),
            '</section>',
        );
    }

    /** @param array<string, mixed> $props */
    private static function about(array $props): string
    {
        return Html::lines(
            '<section class="r-section r-about">',
            '  <h2>' . Html::esc(self::text($props, 'heading', 'About')) . '</h2>',
            self::text($props, 'text') === '' ? '' : '  <p>' . Html::esc(self::text($props, 'text')) . '</p>',
            '</section>',
        );
    }

    /** @param array<string, mixed> $props */
    private static function gallery(array $props): string
    {
        $figures = [];
        foreach (self::list($props, 'items') as $item) {
            if (!is_array($item)) {
                continue;
            }
            $src = Html::safeUrl($item['src'] ?? null);
            if ($src === null) {
                continue;
            }
            $caption = self::text($item, 'caption');
            $figures[] = Html::lines(
                '  <figure>',
                '    <img src="' . Html::esc($src) . '" alt="' . Html::esc($item['alt'] ?? '') . '" loading="lazy">',
                $caption === '' ? '' : '    <figcaption>' . Html::esc($caption) . '</figcaption>',
                '  </figure>',
            );
        }

        return Html::lines(
            '<section class="r-section r-gallery">',
            '  <h2>' . Html::esc(self::text($props, 'heading', 'Gallery')) . '</h2>',
            '  <div class="grid">',
            implode("\n", $figures),
            '  </div>',
            '</section>',
        );
    }

    /** @param array<string, mixed> $props */
    private static function blog(array $props): string
    {
        $posts = [];
        foreach (self::list($props, 'posts') as $post) {
            if (!is_array($post)) {
                continue;
            }
            $href = Html::safeUrl($post['href'] ?? null);
            $title = Html::esc($post['title'] ?? '');
            $heading = $href === null ? '<h3>' . $title . '</h3>' : '<h3><a href="' . Html::esc($href) . '">' . $title . '</a></h3>';
            $posts[] = Html::lines(
                '  <li>',
                '    ' . $heading,
                '    <p class="note">' . Html::esc($post['date'] ?? '') . '</p>',
                '    <p>' . Html::esc($post['text'] ?? '') . '</p>',
                '  </li>',
            );
        }

        return Html::lines(
            '<section class="r-section r-blog">',
            '  <h2>' . Html::esc(self::text($props, 'heading', 'Writing')) . '</h2>',
            '  <ul class="grid">',
            implode("\n", $posts),
            '  </ul>',
            '</section>',
        );
    }

    /** @param array<string, mixed> $props */
    private static function contact(array $props): string
    {
        $href = Html::safeUrl($props['href'] ?? null);

        return Html::lines(
            '<section class="r-section r-contact">',
            '  <h2>' . Html::esc(self::text($props, 'heading', 'Contact')) . '</h2>',
            self::text($props, 'text') === '' ? '' : '  <p>' . Html::esc(self::text($props, 'text')) . '</p>',
            $href === null ? '' : '  <a class="cta" href="' . Html::esc($href) . '">'
                . Html::esc($props['cta'] ?? 'Write to us') . '</a>',
            '</section>',
        );
    }

    /** @param array<string, mixed> $props */
    private static function callToAction(array $props): string
    {
        $href = Html::safeUrl($props['href'] ?? null);

        return Html::lines(
            '<section class="r-section r-cta">',
            '  <h2>' . Html::esc(self::text($props, 'heading', 'Ready when you are')) . '</h2>',
            self::text($props, 'text') === '' ? '' : '  <p>' . Html::esc(self::text($props, 'text')) . '</p>',
            $href === null ? '' : '  <a class="cta" href="' . Html::esc($href) . '">'
                . Html::esc($props['cta'] ?? 'Get started') . '</a>',
            '</section>',
        );
    }

    /** @param array<string, mixed> $props */
    private static function testimonials(array $props): string
    {
        $items = [];
        foreach (self::list($props, 'items') as $item) {
            if (!is_array($item)) {
                continue;
            }
            $items[] = Html::lines(
                '  <figure>',
                '    <blockquote>' . Html::esc($item['text'] ?? '') . '</blockquote>',
                '    <figcaption>' . Html::esc($item['name'] ?? '') . '</figcaption>',
                '  </figure>',
            );
        }

        return Html::lines(
            '<section class="r-section r-testimonials">',
            '  <h2>' . Html::esc(self::text($props, 'heading', 'What people say')) . '</h2>',
            '  <div class="grid">',
            implode("\n", $items),
            '  </div>',
            '</section>',
        );
    }

    /** @param array<string, mixed> $props */
    private static function footer(array $props): string
    {
        $links = [];
        foreach (self::list($props, 'links') as $link) {
            if (!is_array($link)) {
                continue;
            }
            $href = Html::safeUrl($link['href'] ?? null);
            if ($href === null) {
                continue;
            }
            $links[] = '<a href="' . Html::esc($href) . '">' . Html::esc($link['label'] ?? '') . '</a>';
        }

        return Html::lines(
            '<footer class="r-footer">',
            '  <p>' . Html::esc(self::text($props, 'text', '')) . '</p>',
            $links === [] ? '' : '  <nav>' . implode('', $links) . '</nav>',
            '</footer>',
        );
    }
}
