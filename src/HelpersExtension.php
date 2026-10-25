<?php

declare(strict_types=1);

namespace Zana\View\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Zana\View\ViewHelpers;

/**
 * Every view helper as a Twig function ({{ url('about') }}, {{ csrf_field('contact') }}),
 * and t as a filter too ({{ 'nav.home'|t }}). Safe helpers are not escaped.
 * Read when Twig initialises, so helpers added at boot are included.
 */
final class HelpersExtension extends AbstractExtension
{
    public function __construct(private readonly ViewHelpers $helpers)
    {
    }

    public function getFunctions(): array
    {
        $functions = [];
        foreach ($this->helpers->callables() as $name => $helper) {
            $safe = $this->helpers->isSafe($name);
            $functions[] = $safe
                ? new TwigFunction($name, static fn (mixed ...$arguments): string => self::toString($helper(...$arguments)), ['is_safe' => ['html']])
                : new TwigFunction($name, $helper);
        }

        return $functions;
    }

    public function getFilters(): array
    {
        if (!$this->helpers->has('t')) {
            return [];
        }

        $helpers = $this->helpers;

        return [new TwigFilter('t', static fn (string $key, array $parameters = []): mixed => $helpers->call('t', [$key, $parameters]))];
    }

    private static function toString(mixed $value): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
    }
}
