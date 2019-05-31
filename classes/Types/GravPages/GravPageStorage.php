<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Types\GravPages;

use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Framework\Flex\Storage\FolderStorage;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class GravPageStorage
 * @package Grav\Plugin\FlexObjects\Types\GravPages
 */
class GravPageStorage extends FolderStorage
{
    /** @var string */
    protected $dataFolder;
    /** @var string */
    protected $dataPattern = '%1s/%2s';

    protected $ignore_files;
    protected $ignore_folders;
    protected $ignore_hidden;
    protected $recurse;
    protected $base_path;
    protected $base_route;

    protected $page_extensions;
    protected $flags;
    protected $regex;

    protected function initOptions(array $options): void
    {
        $grav = Grav::instance();

        $config = $grav['config'];
        $this->ignore_hidden = (bool)$config->get('system.pages.ignore_hidden');
        $this->ignore_files = (array)$config->get('system.pages.ignore_files');
        $this->ignore_folders = (array)$config->get('system.pages.ignore_folders');
        $this->recurse = $options['recurse'] ?? true;
        $this->base_path = $options['base_path'] ?? '';
        $this->base_route = trim(GravPageIndex::adjustRouteCase(preg_replace(GravPageIndex::PAGE_ROUTE_REGEX, '/', '/' . $this->base_path)), '/');

        /** @var Language $language */
        $language = $grav['language'];
        $this->page_extensions = $language->getFallbackPageExtensions();

        // Build regular expression for all the allowed page extensions.
        $exts = [];
        foreach ($this->page_extensions as $key => $ext) {
            $exts[] = '(' . preg_quote($ext, '/') . ')(*:' . $key . ')';
        }

        $this->regex = '/^[^\.]*(' . implode('|', $exts) . ')$/sD';

        $this->dataFolder = $options['folder'];
    }


    /**
     * {@inheritdoc}
     */
    public function getStoragePath(string $key = null): string
    {
        if (null === $key) {
            $path = $this->dataFolder;
        } else {
            $path = $this->getPathFromKey($key);
        }

        return $path;
    }

    /**
     * {@inheritdoc}
     */
    public function getMediaPath(string $key = null): string
    {
        return \dirname($this->getStoragePath($key));
    }

    /**
     * Get filesystem path from the key.
     *
     * @param string $key
     * @return string
     */
    public function getPathFromKey(string $key): string
    {
        if ($this->base_path) {
            $key = substr($key,  \strlen($this->base_path) + 1);
        }

        $dataFolder = substr($this->dataFolder, -1) === '/' ? substr($this->dataFolder, 0, -1) : $this->dataFolder;

        return sprintf($this->dataPattern, $dataFolder, $key);
    }

    /**
     * Get key from the filesystem path.
     *
     * @param  string $path
     * @return string
     */
    protected function getKeyFromPath(string $path): string
    {
        if ($this->base_path) {
            $path = $this->base_path . '/' . $path;
        }

        return $path;
    }

    /**
     * Returns list of all stored keys in [key => timestamp] pairs.
     *
     * @return array
     */
    protected function buildIndex(): array
    {
        $folder = $this->getStoragePath();
        $this->flags = \FilesystemIterator::KEY_AS_FILENAME | \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS;

        $list = $this->buildIndexRecurse($folder, '', $modified);

        ksort($list, SORT_NATURAL);

        return $list;
    }

    protected function buildIndexRecurse(string $folder, string $prefix, &$modified)
    {
        $iterator = new \FilesystemIterator($folder . $prefix, $this->flags);

        $file = null;
        $markdown = [];
        $list = [];
        /** @var \SplFileInfo $info */
        foreach ($iterator as $key => $info) {
            // Ignore all hidden files if set.
            if ($key === '' || ($this->ignore_hidden && $key[0] === '.')) {
                continue;
            }

            if (!$info->isDir()) {
                // Ignore all files in ignore list.
                if ($this->ignore_files && \in_array($key, $this->ignore_files, true)) {
                    continue;
                }

                $modified = max($modified, $info->getMTime());

                // Page is the one that matches to $page_extensions list with the lowest index number.
                if (preg_match($this->regex, $key, $matches)) {
                    $markdown[$matches['MARK']][] = $key;
                }

                continue;
            }

            // Ignore all folders in ignore list.
            if ($this->ignore_folders && \in_array($key, $this->ignore_folders, true)) {
                continue;
            }

            $updated = $info->getMTime();

            if ($this->recurse) {
                $list += $this->buildIndexRecurse($folder, $prefix . '/' . $key, $updated);
            }

            if (strpos($key[0], '_') === 0) {
                // Update modified only for modular pages.
                $modified = max($modified, $updated);
            }
        }

        $path = trim(GravPageIndex::adjustRouteCase(preg_replace(GravPageIndex::PAGE_ROUTE_REGEX, '/', $prefix)), '/');
        if (isset($list[$path])) {
            $debugger = Grav::instance()['debugger'];
            $debugger->addMessage('Page name conflict: ' . $path);
        }

        ksort($markdown, SORT_NATURAL);

        if ($this->base_path) {
            $path = ($path ? $path . '/' : $path) . $this->base_route;
            $name = $prefix ? '/'. $this->base_path . '/' . $prefix : '/'. $this->base_path;
        } else {
            $name = $prefix;
        }

        $list[$name] = [
            'storage_key' => $name,
            'storage_timestamp' => $modified,
            'key' => $path,
            'markdown' => $markdown
        ];

        return $list;
    }

    /**
     * @return string
     */
    protected function getNewKey(): string
    {
        throw new \RuntimeException('Generating random key is disabled for pages');
    }

    /**
     * @param string $path
     * @return string
     */
    protected function resolvePath(string $path): string
    {
        /** @var UniformResourceLocator $locator
         */
        $locator = Grav::instance()['locator'];

        if (!$locator->isStream($path)) {
            return $path;
        }

        return (string) $locator->findResource($path) ?: $locator->findResource($path, true, true);
    }
}
