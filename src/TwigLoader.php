<?php

declare(strict_types=1);

namespace Zana\View\Twig;

use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;
use Twig\Source;
use Zana\View\TemplatePaths;
use Zana\View\ViewException;

/**
 * Loads Twig templates from the view package's TemplatePaths, so module and
 * application folders work the same in both engines: "pages/home" is
 * pages/home.html.twig, "@Contact/form" is in the Contact module.
 */
final readonly class TwigLoader implements LoaderInterface
{
    public const string EXTENSION = '.html.twig';

    public function __construct(private TemplatePaths $paths)
    {
    }

    public function getSourceContext(string $name): Source
    {
        $file = $this->file($name);

        return new Source((string) file_get_contents($file), $name, $file);
    }

    public function getCacheKey(string $name): string
    {
        return $this->file($name);
    }

    public function isFresh(string $name, int $time): bool
    {
        return filemtime($this->file($name)) < $time;
    }

    public function exists(string $name): bool
    {
        try {
            return $this->paths->exists($name, self::EXTENSION);
        } catch (ViewException) {
            return false;
        }
    }

    private function file(string $name): string
    {
        try {
            return $this->paths->find($name, self::EXTENSION);
        } catch (ViewException $e) {
            throw new LoaderError($e->getMessage());
        }
    }
}
