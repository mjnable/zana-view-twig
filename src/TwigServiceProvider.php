<?php

declare(strict_types=1);

namespace Zana\View\Twig;

use Twig\Environment;
use Zana\Configuration\ConfigurationInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Container\Contracts\ServiceResolverInterface;
use Zana\Core\ApplicationInterface;
use Zana\Core\ServiceProvider;
use Zana\View\EngineInterface;
use Zana\View\TemplatePaths;
use Zana\View\ViewHelpers;

/**
 * Makes Twig the view engine. Register it with ViewServiceProvider.
 *
 * Templates end in .html.twig and use the same folders; autoescape is html.
 * In production, compiled templates are cached in view.cache (default
 * <base>/var/cache/twig); elsewhere templates recompile when they change.
 * Add Twig extensions in a provider's boot() through Twig\Environment.
 */
final class TwigServiceProvider extends ServiceProvider
{
    public function register(ServiceCollectionInterface $services): void
    {
        $services
            ->singleton(Environment::class, static function (ServiceResolverInterface $r): Environment {
                $application = $r->get(ApplicationInterface::class);
                $paths = $r->get(TemplatePaths::class);
                $helpers = $r->get(ViewHelpers::class);
                assert($application instanceof ApplicationInterface && $paths instanceof TemplatePaths && $helpers instanceof ViewHelpers);

                $production = $application->environment()->isProduction();
                $cache = $r->get(ConfigurationInterface::class)->string('view.cache', $application->path('var', 'cache', 'twig'));

                $twig = new Environment(new TwigLoader($paths), [
                    'autoescape' => 'html',
                    'cache' => $production ? $cache : false,
                    'auto_reload' => !$production,
                    'debug' => $application->environment()->debug,
                ]);
                $twig->addExtension(new HelpersExtension($helpers));

                return $twig;
            })
            ->singleton(TwigEngine::class)
            ->singleton(EngineInterface::class, static fn (ServiceResolverInterface $r): EngineInterface => $r->get(TwigEngine::class));
    }

    public function origin(): string
    {
        return 'package:mj-zana/view-twig';
    }
}
