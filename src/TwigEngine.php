<?php

declare(strict_types=1);

namespace Zana\View\Twig;

use Twig\Environment;
use Twig\Error\Error;
use Twig\Markup as TwigMarkup;
use Zana\View\EngineInterface;
use Zana\View\Markup;
use Zana\View\ViewException;

/**
 * Renders Twig templates. Markup values in the context reach Twig as Twig
 * markup, so they are not escaped again.
 */
final readonly class TwigEngine implements EngineInterface
{
    public function __construct(private Environment $twig)
    {
    }

    public function render(string $template, array $context): string
    {
        $name = str_ends_with($template, TwigLoader::EXTENSION) ? $template : $template . TwigLoader::EXTENSION;

        try {
            return $this->twig->render($name, self::convert($context));
        } catch (Error $e) {
            throw new ViewException("Template {$template} failed: {$e->getMessage()}", previous: $e);
        }
    }

    public function exists(string $template): bool
    {
        return $this->twig->getLoader()->exists($template);
    }

    public function environment(): Environment
    {
        return $this->twig;
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    private static function convert(array $values): array
    {
        foreach ($values as $key => $value) {
            if ($value instanceof Markup) {
                $values[$key] = new TwigMarkup($value->html, 'UTF-8');
            } elseif (is_array($value)) {
                $values[$key] = self::convert($value);
            }
        }

        return $values;
    }
}
