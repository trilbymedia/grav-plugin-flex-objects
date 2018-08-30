<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Storage;

/**
 * Class FileStorage
 * @package Grav\Plugin\FlexObjects\Storage
 */
class FileStorage extends FolderStorage
{
    /**
     * {@inheritdoc}
     */
    public function __construct(array $options)
    {
        $this->dataPattern = '%1s/%2s';

        if (!isset($options['formatter']) && isset($options['pattern'])) {
            $options['formatter'] = $this->detectDataFormatter($options['pattern']);
        }

        parent::__construct($options);
    }

    /**
     * {@inheritdoc}
     */
    public function getMediaPath(string $key = null) : string
    {
        return $key ? \dirname($this->getStoragePath($key)) . '/' . $key : $this->getStoragePath();
    }

    /**
     * {@inheritdoc}
     */
    protected function getKeyFromPath(string $path) : string
    {
        return basename($path, $this->dataFormatter->getDefaultFileExtension());
    }

    /**
     * {@inheritdoc}
     */
    protected function findAllKeys() : array
    {
        $flags = \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS;
        $iterator = new \FilesystemIterator($this->getStoragePath(), $flags);
        $list = [];
        /** @var \SplFileInfo $info */
        foreach ($iterator as $filename => $info) {
            if ($info->isFile() || !($key = $this->getKeyFromPath($filename))) {
                continue;
            }

            $list[$key] = $info->getMTime();
        }

        ksort($list, SORT_NATURAL);

        return $list;
    }
}
