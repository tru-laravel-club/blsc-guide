<?php

namespace TruLaravelClub\BlscGuide;

/**
 * Turns the small Markdown subset of guide text into safe HTML.
 *
 * Supported: paragraphs (a blank line), `code`, **bold**, "- " bullet lists and "1. " numbered lists.
 * The text is escaped first, so a feature file can never add raw HTML to the page.
 */
final class Prose
{
    public static function toHtml(string $text, ?string $class = null): string
    {
        $html = '';
        $paragraphClass = $class === null ? '' : ' class="'.e($class).'"';

        foreach (preg_split('/\n\s*\n/', trim($text)) ?: [] as $block) {
            foreach (self::runs($block) as [$type, $lines]) {
                $html .= match ($type) {
                    'ul', 'ol' => "<{$type} class=\"list\">".implode('', array_map(
                        fn (string $line): string => '<li>'.self::inline($line).'</li>',
                        $lines,
                    ))."</{$type}>",
                    default => "<p{$paragraphClass}>".self::inline(implode(' ', $lines)).'</p>',
                };
            }
        }

        return $html;
    }

    /**
     * Group the lines of one block into runs of text, bullet items and numbered items.
     *
     * @return list<array{0: string, 1: list<string>}>
     */
    private static function runs(string $block): array
    {
        $runs = [];

        foreach (explode("\n", trim($block)) as $line) {
            $line = trim($line);
            [$type, $content] = match (true) {
                (bool) preg_match('/^[-*] (.*)$/', $line, $match) => ['ul', $match[1]],
                (bool) preg_match('/^\d+\. (.*)$/', $line, $match) => ['ol', $match[1]],
                default => ['p', $line],
            };

            $last = array_key_last($runs);

            if ($last !== null && $runs[$last][0] === $type) {
                $runs[$last][1][] = $content;
            } else {
                $runs[] = [$type, [$content]];
            }
        }

        return $runs;
    }

    /**
     * Escape the text, then mark up `code` and **bold**. Bold never applies inside code.
     */
    private static function inline(string $text): string
    {
        $parts = preg_split('/(`[^`]+`)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        return implode('', array_map(function (string $part): string {
            if (str_starts_with($part, '`') && str_ends_with($part, '`') && strlen($part) > 1) {
                return '<code>'.e(substr($part, 1, -1)).'</code>';
            }

            return (string) preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', e($part));
        }, $parts));
    }
}
