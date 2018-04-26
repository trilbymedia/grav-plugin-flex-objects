<?php
namespace Grav\Plugin\FlexObjects\Storage;

/**
 * Class FileStorage
 * @package Grav\Plugin\FlexObjects\Storage
 */
class FileStorage extends FolderStorage
{
    /** @var array */
    protected $dataPattern = '%1s/%2s';

    /**
     * {@inheritdoc}
     */
    protected function getKeyFromPath($path)
    {
        return basename($path, $this->dataFormatter->getFileExtension());
    }

    /**
     * {@inheritdoc}
     */
    protected function findAllKeys()
    {
        $flags = \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS;
        $iterator = new \FilesystemIterator($this->dataFolder, $flags);
        $list = [];
        /** @var \SplFileInfo $info */
        foreach ($iterator as $filename => $info) {
            if ($info->isFile() || !($key = $this->getKeyFromPath($filename))) {
                continue;
            }

            $list[$key] = $info->getMTime();
        }

        return $list;
    }
}
