<?php

declare(strict_types=1);

namespace Zana\View\Twig;

use Twig\Environment;
use Zana\Core\ApplicationInterface;
use Zana\Core\Cache\CacheClearerInterface;
use Zana\Core\Cache\CacheWarmerInterface;
use Zana\View\TemplatePaths;

/**
 * Clears and warms the compiled Twig templates for cache:clear and
 * cache:warm. Warming compiles every .html.twig template of every folder,
 * when the Twig cache is enabled (production).
 */
final readonly class TwigCache implements CacheClearerInterface, CacheWarmerInterface
{
    public function __construct(
        private Environment $twig,
        private TemplatePaths $paths,
    ) {
    }

    public function clear(ApplicationInterface $application): array
    {
        $cache = $this->twig->getCache(true);
        if (!is_string($cache) || !is_dir($cache)) {
            return [];
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($cache, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            assert($file instanceof \SplFileInfo);
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        return ["compiled Twig templates ({$cache})"];
    }

    public function warm(ApplicationInterface $application): array
    {
        if ($this->twig->getCache(true) === false) {
            return [];
        }

        $count = 0;
        foreach ($this->paths->all() as $namespace => $folders) {
            foreach ($folders as $folder) {
                if (!is_dir($folder)) {
                    continue;
                }
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS)) as $file) {
                    assert($file instanceof \SplFileInfo);
                    if (!str_ends_with($file->getFilename(), TwigLoader::EXTENSION)) {
                        continue;
                    }
                    $name = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($folder))), '/');
                    $this->twig->load($namespace === '' ? $name : "@{$namespace}/{$name}");
                    $count++;
                }
            }
        }

        return ["{$count} Twig templates compiled"];
    }
}
