<?php

declare(strict_types=1);

namespace Zana\View\Twig\Tests;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Zana\Core\Application;
use Zana\Http\HttpServiceProvider;
use Zana\Routing\RoutingServiceProvider;
use Zana\Translation\TranslationServiceProvider;
use Zana\View\Twig\SignedTwigCache;
use Zana\View\Twig\TwigCache;
use Zana\View\Twig\TwigServiceProvider;
use Zana\View\ViewServiceProvider;

/**
 * 2.35: compiled Twig templates are signed, so code written to the cache
 * directory by anything but the application does not run. Each render is a
 * request of its own, in a new PHP process.
 */
final class SignedTwigCacheTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        $this->directory = str_replace('\\', '/', sys_get_temp_dir()) . '/zana-twig-signed-' . bin2hex(random_bytes(6));
    }

    #[After]
    public function cleanUp(): void
    {
        is_dir($this->directory) && exec(PHP_OS_FAMILY === 'Windows' ? 'rmdir /s /q ' . escapeshellarg(str_replace('/', '\\', $this->directory)) : 'rm -rf ' . escapeshellarg($this->directory));
    }

    public function testCompiledTemplatesAreSignedAndReadBack(): void
    {
        $this->assertSame('Hello Ada', $this->render());
        $file = $this->compiled();
        $this->assertMatchesRegularExpression('/\A<\?php\n\/\/ zana-signature: [0-9a-f]{64}\n/', (string) file_get_contents($file));
        $written = file_get_contents($file);

        $this->assertSame('Hello Ada', $this->render());
        $this->assertSame('Hello Ada', $this->render(include: true));
        $this->assertSame($written, file_get_contents($file), 'loaded, not compiled again');
    }

    /**
     * @return iterable<string, array{bool, bool}>
     */
    public static function signatures(): iterable
    {
        yield 'the signature kept' => [true, false];
        yield 'the signature removed' => [false, false];
        yield 'the signature kept, included' => [true, true];
        yield 'the signature removed, included' => [false, true];
    }

    #[DataProvider('signatures')]
    public function testCodeAddedToACompiledTemplateDoesNotRun(bool $keepSignature, bool $include): void
    {
        $this->render(include: $include);
        $this->plant($keepSignature);

        $this->assertSame('Hello Ada', $this->render(include: $include), 'the template, compiled again from its source');
        $this->assertStringNotContainsString('planted', (string) file_get_contents($this->compiled()), 'and written over');
    }

    public function testASignatureForAnotherTemplateDoesNotHold(): void
    {
        $this->render();
        $this->render('other');
        [$hello, $other] = [$this->compiled('hello'), $this->compiled('other')];
        $this->assertNotSame($hello, $other);

        $cache = new SignedTwigCache($this->directory, SignedTwigCache::keyFor('secret-one'));
        copy($hello, $other);
        $this->assertFalse($cache->signed($other), 'the path within the directory is signed');
        $this->assertTrue($cache->signed($hello));
        $this->assertFalse((new SignedTwigCache($this->directory, SignedTwigCache::keyFor('secret-two')))->signed($hello), 'another secret');
    }

    /**
     * The bytes checked are the bytes that run: a signed template is evaluated, unless including is asked.
     */
    public function testSignedTemplatesAreEvaluatedUnlessIncludingIsAsked(): void
    {
        $file = "{$this->directory}/ab/template.php";
        foreach ([false => "eval()'d code", true => 'template.php'] as $include => $expected) {
            $cache = new SignedTwigCache($this->directory, SignedTwigCache::keyFor('secret-one'), (bool) $include);
            $cache->write($file, "<?php\n\$GLOBALS['zana_twig_file'] = basename(__FILE__);\n");
            unset($GLOBALS['zana_twig_file']);
            $cache->load($file);
            $this->assertIsString($GLOBALS['zana_twig_file'] ?? null);
            $this->assertStringContainsString($expected, $GLOBALS['zana_twig_file']);
        }
        unset($GLOBALS['zana_twig_file']);
    }

    public function testWithoutAKeyTwigIncludesWhateverIsThere(): void
    {
        $this->render(signed: false);
        $this->plant(keepSignature: false);

        $this->assertSame('planted Hello Ada', $this->render(signed: false), 'the limit without security.secret');
    }

    public function testTheProviderSignsInProductionWhenThereIsASecret(): void
    {
        foreach (['very-secret-value-0123456789abcdef' => SignedTwigCache::class, '' => 'string'] as $secret => $expected) {
            $app = Application::configure(__DIR__ . '/app')
                ->withEnvironment('production')
                ->withConfiguration(['translation.locales' => ['en', 'fr'], 'view.cache' => $this->directory, 'security.secret' => $secret, 'app.deployment_checks' => false])
                ->withProviders(HttpServiceProvider::class, RoutingServiceProvider::class, TranslationServiceProvider::class, ViewServiceProvider::class, TwigServiceProvider::class)
                ->withModules(ContactModule::class)
                ->build();
            $twig = $app->container()->get(Environment::class);
            assert($twig instanceof Environment);
            $this->assertSame($expected, get_debug_type($twig->getCache(true)), $secret === '' ? 'no secret' : 'a secret');

            // Warming is TwigTest's (compiling the templates here would declare their classes in this process)
            @mkdir("{$this->directory}/ab", 0777, true);
            file_put_contents("{$this->directory}/ab/compiled.php", '<?php');
            $cache = $app->container()->get(TwigCache::class);
            assert($cache instanceof TwigCache);
            $this->assertSame(["compiled Twig templates ({$this->directory})"], $cache->clear($app));
            $this->assertSame([], glob("{$this->directory}/*") ?: []);
        }
    }

    public function testTheProviderIncludesWhenTheApplicationAsks(): void
    {
        foreach ([true, false] as $include) {
            $twig = Application::configure(__DIR__ . '/app')
                ->withEnvironment('production')
                ->withConfiguration(['translation.locales' => ['en', 'fr'], 'view.cache' => $this->directory, 'security.secret' => 'very-secret-value-0123456789abcdef', 'app.deployment_checks' => false])
                ->withProviders(HttpServiceProvider::class, RoutingServiceProvider::class, TranslationServiceProvider::class, ViewServiceProvider::class, TwigServiceProvider::class)
                ->withModules(ContactModule::class)
                ->includeCompiledCaches($include)
                ->build()
                ->container()
                ->get(Environment::class);
            assert($twig instanceof Environment);
            $cache = $twig->getCache(true);
            $this->assertInstanceOf(SignedTwigCache::class, $cache);
            $this->assertSame($include, (new \ReflectionProperty(SignedTwigCache::class, 'include'))->getValue($cache));
        }
    }

    /**
     * Render a template in a PHP process of its own: its output, preceded by "planted" when planted code ran.
     */
    private function render(string $template = 'hello', bool $signed = true, bool $include = false): string
    {
        @mkdir($this->directory, 0777, true);
        $script = "{$this->directory}/render.php";
        file_put_contents($script, '<?php
require ' . var_export(dirname(__DIR__) . '/vendor/autoload.php', true) . ';
$cache = ' . ($signed ? 'new Zana\View\Twig\SignedTwigCache(' . var_export($this->directory, true) . ', Zana\View\Twig\SignedTwigCache::keyFor("secret-one"), ' . var_export($include, true) . ')' :var_export($this->directory, true)) . ';
$twig = new Twig\Environment(new Twig\Loader\ArrayLoader(["hello" => "Hello {{ name }}", "other" => "Other {{ name }}"]), ["cache" => $cache, "auto_reload" => false]);
$html = $twig->render(' . var_export($template, true) . ', ["name" => "Ada"]);
echo (isset($GLOBALS["zana_planted"]) ? "planted " : "") . $html;');

        return (string) shell_exec(escapeshellarg(PHP_BINARY) . ' -d xdebug.mode=off ' . escapeshellarg($script) . ' 2>&1');
    }

    private function compiled(string $template = 'hello'): string
    {
        foreach (glob("{$this->directory}/*/*.php") ?: [] as $file) {
            if (str_contains((string) file_get_contents($file), "/* {$template} */")) {
                return $file;
            }
        }
        $this->fail("{$template} was not compiled");
    }

    /**
     * Add code to the compiled template, as someone who can write to the cache directory could.
     */
    private function plant(bool $keepSignature): void
    {
        $file = $this->compiled();
        $source = (string) file_get_contents($file);
        if (!$keepSignature) {
            $source = (string) preg_replace('/\A(<\?php\n)\/\/ zana-signature: [0-9a-f]{64}\n/', '$1', $source);
        }
        $planted = preg_replace('/\A(<\?php\r?\n(\/\/ zana-signature: [0-9a-f]{64}\n)?)/', '$1$GLOBALS[\'zana_planted\'] = true;' . "\n", $source, 1);
        $this->assertIsString($planted);
        $this->assertNotSame($source, $planted);
        file_put_contents($file, $planted);
    }
}
