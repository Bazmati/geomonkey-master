<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Environment;
use Twig\TwigFilter;

/**
 * Mise en forme du contenu rédigé en texte brut structuré :
 *
 *   Paragraphe 1...
 *
 *   ## Un sous-titre
 *   Texte du paragraphe...
 *
 *   ### Un sous-sous-titre
 *   - élément de liste
 *   - autre élément
 *
 *   > Une citation
 *
 * Règles : une ligne vide sépare les blocs ; « ## » / « ### » créent des
 * titres H2/H3 ; « - » crée une liste ; « > » crée une citation.
 * Tout est échappé avant assemblage : aucun HTML ne peut être injecté.
 */
class ContentExtension extends AbstractExtension
{
    public function __construct(private Environment $twig) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('content_html', [$this, 'formatContent'], ['is_safe' => ['html']]),
            new TwigFilter('content_with_images', [$this, 'formatContentWithImages'], ['is_safe' => ['html']]),
        ];
    }

    public function formatContent(?string $raw): string
    {
        $raw = str_replace(["\r\n", "\r"], "\n", (string) $raw);
        $blocks = preg_split("/\n{2,}/", trim($raw)) ?: [];
        $html = '';

        foreach ($blocks as $block) {
            $lines = array_map('trim', explode("\n", $block));
            $first = $lines[0] ?? '';

            // Titres : « ## » → h2, « ### » → h3
            if (preg_match('/^(#{2,3})\s+(.+)$/', $first, $m)) {
                $tag = strlen($m[1]) === 2 ? 'h2' : 'h3';
                $html .= '<' . $tag . '>' . htmlspecialchars($m[2], ENT_QUOTES) . '</' . $tag . '>';
                array_shift($lines);
                $rest = array_filter($lines, static fn ($l) => $l !== '');
                if ($rest === []) {
                    continue;
                }
                $html .= '<p>' . htmlspecialchars(implode(' ', $rest), ENT_QUOTES) . '</p>';
                continue;
            }

            // Liste à puces : toutes les lignes commencent par « - »
            $isList = $first !== '' && !str_starts_with($first, '>');
            foreach ($lines as $l) {
                if ($l !== '' && !str_starts_with($l, '- ') && $l !== '-') {
                    $isList = false;
                    break;
                }
            }
            if ($isList) {
                $html .= '<ul>';
                foreach ($lines as $l) {
                    $item = trim(mb_substr($l, $l === '-' ? 1 : 2));
                    if ($item !== '') {
                        $html .= '<li>' . htmlspecialchars($item, ENT_QUOTES) . '</li>';
                    }
                }
                $html .= '</ul>';
                continue;
            }

            // Citation : « > »
            if (str_starts_with($first, '>')) {
                $quote = array_map(
                    static fn ($l) => htmlspecialchars(ltrim($l, '> '), ENT_QUOTES),
                    array_filter($lines, static fn ($l) => $l !== '')
                );
                $html .= '<blockquote>' . implode(' ', $quote) . '</blockquote>';
                continue;
            }

            // Paragraphe simple
            $html .= '<p>' . htmlspecialchars(implode(' ', $lines), ENT_QUOTES) . '</p>';
        }

        return $html;
    }

     /**
     * Comme content_html, mais insère les images positionnées de l'article :
     * - Start  : avant le premier bloc ;
     * - Middle : après le bloc du milieu (n/2) ;
     * - End    : après le dernier bloc.
     * Plusieurs images de même position s'affichent dans l'ordre sortOrder.
     *
     * @param \App\Entity\BlogImage[] $images
     */
    public function formatContentWithImages(?string $raw, array $images = []): string
    {
        if ($images === []) {
            return $this->formatContent($raw);
        }

        $byPos = ['start' => [], 'middle' => [], 'end' => []];
        foreach ($images as $img) {
            $byPos[$img->getPosition()->value][] = $img;
        }

        $render = static function (array $list, Environment $twig): string {
            return array_reduce($list, static function ($carry, $img) use ($twig) {
                return $carry . $twig->render('front/blog/_article_image.html.twig', [
                    'image' => $img->getGalleryImage(),
                    'caption' => $img->getCaption(),
                ]);
            }, '');
        };

        $raw = str_replace(["\r\n", "\r"], "\n", (string) $raw);
        $blocks = preg_split("/\n{2,}/", trim($raw)) ?: [];
        $htmlBlocks = array_map(fn ($b) => $this->formatContent($b), $blocks);
        $n = count($htmlBlocks);
        $middleAt = max(1, (int) floor($n / 2));

        $out = $render($byPos['start'], $this->twig);

        foreach ($htmlBlocks as $i => $blockHtml) {
            $out .= $blockHtml;
            if ($i + 1 === $middleAt) {
                $out .= $render($byPos['middle'], $this->twig);
            }
        }

        $out .= $render($byPos['end'], $this->twig);

        return $out;
    }
}