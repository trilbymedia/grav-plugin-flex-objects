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
    protected $ignore_files;
    protected $ignore_folders;
    protected $ignore_hidden;
    protected $recurse;
    protected $base_path;

    protected $page_extensions;
    protected $flags;
    protected $regex;

    protected function initOptions(array $options): void
    {
        parent::initOptions($options);

        $this->flags = \FilesystemIterator::KEY_AS_FILENAME | \FilesystemIterator::CURRENT_AS_FILEINFO
            | \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS;

        $grav = Grav::instance();

        $config = $grav['config'];
        $this->ignore_hidden = (bool)$config->get('system.pages.ignore_hidden');
        $this->ignore_files = (array)$config->get('system.pages.ignore_files');
        $this->ignore_folders = (array)$config->get('system.pages.ignore_folders');
        $this->recurse = $options['recurse'] ?? true;

        /** @var Language $language */
        $language = $grav['language'];
        $this->page_extensions = $language->getPageExtensions();

        // Build regular expression for all the allowed page extensions.
        $exts = [];
        foreach ($this->page_extensions as $key => $ext) {
            $exts[] = '(' . preg_quote($ext, '/') . ')(*:' . $key . ')';
        }

        $this->regex = '/^[^\.]*(' . implode('|', $exts) . ')$/sD';
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::hasKey()
     */
    public function hasKey(string $key): bool
    {
        // Empty folders are valid, too.
        return $key && strpos($key, '@@') === false && file_exists($this->getStoragePath($key));
    }

    /**
     * Get filesystem path from the key.
     *
     * @param string $key
     * @return string
     */
    public function getPathFromKey(string $key): string
    {
        $key = trim($key, '/');

        $meta = $this->getObjectMeta($key);
        $file = basename($meta['storage_file'] ?? 'folder.md', $this->dataExt);

        $options = [
            $this->dataFolder,
            $key,
            \mb_substr($key, 0, 2),
            $file,
            $this->dataExt
        ];

        return sprintf($this->dataPattern, ...$options);
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
        return $this->getIndexMeta();
    }

    /**
     * @param string $key
     * @param bool $reload
     * @return array
     */
    protected function getObjectMeta(string $key, bool $reload = false): array
    {
        if (!$reload && isset($this->meta[$key])) {
            return $this->meta[$key];
        }

        $path = $this->getStoragePath($key);

        $modified = 0;
        $markdown = [];
        $children = [];

        if (file_exists($path)) {
            $modified = filemtime($path);
            $iterator = new \FilesystemIterator($path, $this->flags);

            /** @var \SplFileInfo $info */
            foreach ($iterator as $k => $info) {
                // Ignore all hidden files if set.
                if ($k === '' || ($this->ignore_hidden && $k[0] === '.')) {
                    continue;
                }

                if ($info->isDir()) {
                    // Ignore all folders in ignore list.
                    if ($this->ignore_folders && \in_array($k, $this->ignore_folders, true)) {
                        continue;
                    }

                    $children[$k] = false;
                } else {
                    // Ignore all files in ignore list.
                    if ($this->ignore_files && \in_array($k, $this->ignore_files, true)) {
                        continue;
                    }

                    $modified = max($modified, $info->getMTime());

                    // Page is the one that matches to $page_extensions list with the lowest index number.
                    if (preg_match($this->regex, $k, $matches)) {
                        $markdown[$matches['MARK']][] = $k;
                    }
                }
            }
        }

        $route = trim(GravPageIndex::normalizeRoute(preg_replace(GravPageIndex::PAGE_ROUTE_REGEX, '/', "/{$key}")), '/');

        ksort($markdown, SORT_NATURAL);
        ksort($children, SORT_NATURAL);

        $file = $markdown['-'][0] ?? null;
        if (!$file) {
            $first = reset($markdown) ?: [];
            $file = reset($first) ?: null;
            if ($file) {
                $k = '.' . key($markdown);
                $file = str_replace($k, '', $file);
            }
        }

        $meta = [
            'key' => $route,
            'storage_key' => $key,
            'storage_file' => $file,
            'storage_timestamp' => $modified,
        ];
        if ($markdown) {
            $meta['markdown'] = $markdown;
        }
        if ($children) {
            $meta['children'] = $children;
        }
        $meta['checksum'] = md5(json_encode($meta));

        $this->meta[$key] = $meta;

        return $meta;
    }

    protected function getIndexMeta(): array
    {
        $queue = [''];
        $list = [];
        do {
            $current = array_pop($queue);
            $meta = $this->getObjectMeta($current);
            $storage_key = $meta['storage_key'];

            if (!empty($meta['children'])) {
                $prefix = $storage_key . ($storage_key !== '' ? '/' : '');

                foreach ($meta['children'] as $child => $value) {
                    $queue[] = $prefix . $child;
                }
            }

            $list[$storage_key] = $meta;
        } while ($queue);

        ksort($list, SORT_NATURAL);

        // Update parent timestamps.
        foreach (array_reverse($list) as $storage_key => $meta) {
            if ($storage_key !== '') {
                $parentKey = dirname($storage_key);
                if ($parentKey === '.') {
                    $parentKey = '';
                }

                $parent = &$list[$parentKey];
                $basename = basename($storage_key);

                if (isset($parent['children'][$basename])) {
                    $timestamp = $meta['storage_timestamp'];
                    $parent['children'][$basename] = $timestamp;
                    if ($basename && $basename[0] === '_') {
                        $parent['storage_timestamp'] = max($parent['storage_timestamp'], $timestamp);
                    }
                }
            }
        }

        return $list;
    }

    /**
     * @return string
     */
    protected function getNewKey(): string
    {
        throw new \RuntimeException('Generating random key is disabled for pages');
    }
}
