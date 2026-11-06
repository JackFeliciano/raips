<?php

declare(strict_types=1);

namespace Raips;

/**
 * One page as a member describes it: a route, a title and a list of sections,
 * each a template name with its props.
 */
final class Page
{
    /** @param list<array<string, mixed>> $sections */
    private function __construct(
        public readonly string $route,
        public readonly string $title,
        public readonly array $sections,
        public readonly ?string $description,
        public readonly ?string $lang,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $sections = [];
        if (isset($data['sections']) && is_array($data['sections'])) {
            foreach ($data['sections'] as $section) {
                if (is_array($section)) {
                    $sections[] = $section;
                }
            }
        }

        return new self(
            route: is_string($data['route'] ?? null) ? $data['route'] : '',
            title: is_string($data['title'] ?? null) ? $data['title'] : '',
            sections: $sections,
            description: is_string($data['description'] ?? null) ? $data['description'] : null,
            lang: is_string($data['lang'] ?? null) ? $data['lang'] : null,
        );
    }

    /** @return list<string> */
    public function problems(): array
    {
        $problems = [];

        if (trim($this->route) === '') {
            $problems[] = 'page.route is required';
        } elseif (preg_match('#^/[a-z0-9\-/_.]*$#i', $this->route) !== 1) {
            $problems[] = 'page.route ' . $this->route . ' holds a character a route cannot carry';
        } elseif (str_contains($this->route, '..')) {
            $problems[] = 'page.route ' . $this->route . ' climbs above the root';
        }

        if (trim($this->title) === '') {
            $problems[] = 'page.title is required';
        }

        if ($this->sections === []) {
            $problems[] = 'page.sections is empty';
        }

        foreach ($this->sections as $i => $section) {
            $type = $section['type'] ?? null;
            if (!is_string($type) || $type === '') {
                $problems[] = 'page.sections[' . $i . '].type is required';
                continue;
            }
            if (!Sections::knows($type)) {
                $problems[] = 'page.sections[' . $i . '].type ' . $type . ' is not a template this build carries';
            }
        }

        return $problems;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'route' => $this->route,
            'title' => $this->title,
            'sections' => $this->sections,
            'description' => $this->description,
            'lang' => $this->lang,
        ];
    }
}
