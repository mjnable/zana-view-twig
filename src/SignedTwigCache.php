<?php

declare(strict_types=1);

namespace Zana\View\Twig;

use Twig\Cache\CacheInterface;
use Twig\Cache\FilesystemCache;

/**
 * Twig's file cache, with each compiled template signed (2.35). Compiled
 * templates are PHP files that Twig includes: someone who can write to the
 * cache directory could otherwise put code in one, which the next page that
 * renders it runs.
 *
 * The signature is the file's second line, an HMAC-SHA256 of the rest and of
 * the file's path within the directory, so one template cannot stand for
 * another. A file without it, or with another, is not loaded: Twig compiles
 * the template again from its source and writes it over.
 *
 * A signed file is read once, checked and evaluated, so the bytes checked are
 * the bytes that run. With $include (app.include_compiled_caches, set by
 * ApplicationBuilder::includeCompiledCaches()), it is checked, then included,
 * for OPcache, which leaves a moment in which the file could be swapped.
 */
final readonly class SignedTwigCache implements CacheInterface
{
    private const string PREFIX = "<?php\n// zana-signature: ";

    private FilesystemCache $files;

    private string $directory;

    public function __construct(string $directory, private string $key, private bool $include = false)
    {
        $this->directory = rtrim(str_replace('\\', '/', $directory), '/') . '/';
        $this->files = new FilesystemCache($this->directory, FilesystemCache::FORCE_BYTECODE_INVALIDATION);
    }

    /**
     * The key for a secret: one derived from security.secret, as the cache,
     * the queue and the compiled container derive theirs.
     */
    public static function keyFor(string $secret): string
    {
        return hash_hmac('sha256', 'zana.view.twig', $secret);
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public function generateKey(string $name, string $className): string
    {
        return $this->files->generateKey($name, $className);
    }

    public function write(string $key, string $content): void
    {
        if (preg_match('/\A<\?php\r?\n/', $content, $open) !== 1) {
            throw new \LogicException('A compiled Twig template starts with "<?php" and a line break.');
        }
        $body = substr($content, strlen($open[0]));

        $this->files->write($key, self::PREFIX . $this->mac($key, $body) . "\n" . $body);
    }

    public function load(string $key): void
    {
        if ($this->include) {
            if ($this->signed($key)) {
                @include_once $key;
            }

            return;
        }

        $source = is_file($key) ? @file_get_contents($key) : false;
        if (is_string($source) && $this->verify($key, $source)) {
            eval('?>' . $source);
        }
    }

    public function getTimestamp(string $key): int
    {
        return $this->files->getTimestamp($key);
    }

    /**
     * Whether the file carries this key's signature.
     */
    public function signed(string $key): bool
    {
        $source = is_file($key) ? @file_get_contents($key) : false;

        return is_string($source) && $this->verify($key, $source);
    }

    private function verify(string $key, string $source): bool
    {
        if (!str_starts_with($source, self::PREFIX)) {
            return false;
        }
        $end = strpos($source, "\n", strlen(self::PREFIX));
        if ($end === false) {
            return false;
        }

        return hash_equals($this->mac($key, substr($source, $end + 1)), substr($source, strlen(self::PREFIX), $end - strlen(self::PREFIX)));
    }

    private function mac(string $key, string $body): string
    {
        $path = str_replace('\\', '/', $key);
        $relative = str_starts_with($path, $this->directory) ? substr($path, strlen($this->directory)) : $path;

        return hash_hmac('sha256', "zana-twig|{$relative}|" . $body, $this->key);
    }
}
