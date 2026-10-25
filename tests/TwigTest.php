<?php

declare(strict_types=1);

namespace Zana\View\Twig\Tests;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Twig\Environment;
use Zana\Container\Contracts\ContainerInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Core\Application;
use Zana\Core\Module;
use Zana\Http\Exception\NotFoundHttpException;
use Zana\Http\HttpKernel;
use Zana\Http\HttpServiceProvider;
use Zana\Routing\Router;
use Zana\Routing\RoutingServiceProvider;
use Zana\Translation\LocaleMiddleware;
use Zana\Translation\TranslationServiceProvider;
use Zana\View\EngineInterface;
use Zana\View\Markup;
use Zana\View\Twig\TwigEngine;
use Zana\View\Twig\TwigServiceProvider;
use Zana\View\View;
use Zana\View\ViewException;
use Zana\View\ViewHelpers;
use Zana\View\ViewServiceProvider;

final class TwigTest extends TestCase
{
    private ContainerInterface $container;

    private HttpKernel $kernel;

    protected function setUp(): void
    {
        $this->container = Application::configure(__DIR__ . '/app')
            ->withEnvironment('testing')
            ->withConfiguration([
                'translation.locales' => ['en', 'fr'],
                'view.globals' => ['site' => 'Zana & Co'],
                'http.middleware' => [LocaleMiddleware::class],
            ])
            ->withProviders(HttpServiceProvider::class, RoutingServiceProvider::class, TranslationServiceProvider::class, ViewServiceProvider::class, TwigServiceProvider::class)
            ->withModules(ContactModule::class)
            ->build()
            ->container();

        $router = $this->container->get(Router::class);
        assert($router instanceof Router);
        $router->localized(['en' => '/', 'fr' => '/'], static fn (): string => 'home')->name('home');
        $router->localized(['en' => '/about', 'fr' => '/a-propos'], static fn (): View => View::make('page', [
            'heading' => 'Tom & <Jerry>',
            'intro' => new Markup('<em>safe</em>'),
        ]))->name('about');
        $router->get('/fr/contact', static fn (): View => View::make('@Contact/form', ['marker' => '<b>']));
        $router->get('/gone', static fn () => throw new NotFoundHttpException('Moved <away>.'));

        $kernel = $this->container->get(HttpKernel::class);
        assert($kernel instanceof HttpKernel);
        $this->kernel = $kernel;
    }

    public function testTwigBecomesTheEngine(): void
    {
        $this->assertInstanceOf(TwigEngine::class, $this->container->get(EngineInterface::class));
    }

    public function testPagesAreAutoEscapedAndMarkupIsKept(): void
    {
        $html = (string) $this->get('/fr/a-propos')->getBody();

        $this->assertStringContainsString('<html lang="fr">', $html);
        $this->assertStringContainsString('<title>About | Site</title>', $html);
        $this->assertStringContainsString('<h1>Tom &amp; &lt;Jerry&gt;</h1>', $html);
        $this->assertStringContainsString('<p><em>safe</em></p>', $html);
        $this->assertStringContainsString('<p class="site">Zana &amp; Co</p>', $html);
        $this->assertStringContainsString('<nav><a href="/fr">Accueil</a></nav>', $html, 'url() and the t filter');
    }

    public function testModuleNamespacesAndHelperFunctions(): void
    {
        $html = (string) $this->get('/fr/contact')->getBody();

        $this->assertStringContainsString('<form method="post">&lt;b&gt;<button>Envoyer &lt;vite&gt;</button></form>', $html);
    }

    public function testErrorPagesUseTheGenericTemplate(): void
    {
        $response = $this->get('/gone');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('<p class="error">404: Moved &lt;away&gt;.</p>', (string) $response->getBody());
    }

    public function testSafeHelpersAreNotEscaped(): void
    {
        $helpers = $this->container->get(ViewHelpers::class);
        assert($helpers instanceof ViewHelpers);
        $helpers->add('badge', static fn (string $text): Markup => new Markup('<span>' . htmlspecialchars($text) . '</span>'), safe: true);

        $twig = $this->container->get(Environment::class);
        assert($twig instanceof Environment);
        $twig->setLoader(new \Twig\Loader\ArrayLoader(['badge.html.twig' => '{{ badge("<new>") }}']));

        $this->assertSame('<span>&lt;new&gt;</span>', (new TwigEngine($twig))->render('badge', []));
    }

    public function testMissingTemplatesAreViewExceptions(): void
    {
        $engine = $this->container->get(EngineInterface::class);
        assert($engine instanceof EngineInterface);

        $this->assertFalse($engine->exists('nope'));
        $this->assertFalse($engine->exists('../secret'));
        $this->expectException(ViewException::class);
        $engine->render('nope', []);
    }

    private function get(string $path): ResponseInterface
    {
        return $this->kernel->handle(new ServerRequest('GET', $path, ['Accept' => 'text/html']));
    }
}

final class ContactModule extends Module
{
    public function name(): string
    {
        return 'Contact';
    }

    public function path(string ...$segments): string
    {
        return implode('/', [__DIR__ . '/app/modules/Contact', ...$segments]);
    }

    public function register(ServiceCollectionInterface $services): void
    {
    }
}
