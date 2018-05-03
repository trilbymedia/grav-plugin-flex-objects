<?php
namespace Grav\Plugin\FlexObjects\Storage;

use Grav\Common\Filesystem\Folder;
use Grav\Framework\File\Formatter\FormatterInterface;
use InvalidArgumentException;

/**
 * Class FolderStorage
 * @package Grav\Plugin\FlexObjects\Storage
 */
class SimpleStorage extends AbstractFilesystemStorage
{
    /** @var string */
    protected $dataFolder;
    /** @var string */
    protected $dataPattern;
    /** @var FormatterInterface */
    protected $dataFormatter;
    /** @var array */
    protected $data;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $options)
    {
        if (!isset($options['folder'])) {
            throw new InvalidArgumentException("Argument \$options is missing 'folder'");
        }

        $this->initDataFormatter(isset($options['formatter']) ? $options['formatter'] : []);

        $extension = $this->dataFormatter->getFileExtension();
        $pattern = basename($options['folder']);

        $this->dataPattern = basename($pattern, $extension) . $extension;
        $this->dataFolder = dirname($options['folder']);

        // Make sure that the data folder exists.
        if (!file_exists($this->dataFolder)) {
            Folder::create($this->dataFolder);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getExistingKeys()
    {
        return $this->findAllKeys();
    }

    /**
     * {@inheritdoc}
     */
    public function hasKey($key)
    {
        return isset($this->data[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function createRows(array $rows)
    {
        $list = [];
        foreach ($rows as $key => $row) {
            $key = $this->getNewKey();
            $this->data[$key] = $list[$key] = $row;
        }

        $list && $this->save();

        return $list;
    }

    /**
     * {@inheritdoc}
     */
    public function readRows(array $rows, &$fetched = null)
    {
        $list = [];
        foreach ($rows as $key => $row) {
            if (null === $row || (!\is_object($row) && !\is_array($row))) {
                // Only load rows which haven't been loaded before.
                $list[$key] = $this->hasKey($key) ? $this->data[$key] : null;
                if (null !== $fetched) {
                    $fetched[$key] = $list[$key];
                }
            } else {
                // Keep the row if it has been loaded.
                $list[$key] = $row;
            }
        }

        return $list;
    }

    /**
     * {@inheritdoc}
     */
    public function updateRows(array $rows)
    {
        $list = [];
        foreach ($rows as $key => $row) {
            if ($this->hasKey($key)) {
                $this->data[$key] = $list[$key] = $row;
            }
        }

        $list && $this->save();

        return $list;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteRows(array $rows)
    {
        $list = [];
        foreach ($rows as $key => $row) {
            if ($this->hasKey($key)) {
                unset($this->data[$key]);
                $list[$key] = $row;
            }
        }

        $list && $this->save();

        return $list;
    }

    /**
     * {@inheritdoc}
     */
    public function replaceRows(array $rows)
    {
        $list = [];
        foreach ($rows as $key => $row) {
            $this->data[$key] = $list[$key] = $row;
        }

        $list && $this->save();

        return $list;
    }

    /**
     * {@inheritdoc}
     */
    public function getStoragePath($key = null)
    {
        return $this->dataFolder . '/' . $this->dataPattern;
    }

    /**
     * {@inheritdoc}
     */
    public function getMediaPath($key = null)
    {
        return sprintf('%s/%s/%s', $this->dataFolder, basename($this->dataPattern, $this->dataFormatter->getFileExtension()), $key);
    }

    protected function save()
    {
        $file = $this->getFile($this->getStoragePath());
        $file->save($this->data);
        $file->free();
    }

    /**
     * Get key from the filesystem path.
     *
     * @param  string $path
     * @return string
     */
    protected function getKeyFromPath($path)
    {
        return basename($path);
    }
    /**
     * Returns list of all stored keys in [key => timestamp] pairs.
     *
     * @return array
     */
    protected function findAllKeys()
    {
        $file = $this->getFile($this->getStoragePath());
        $modified = $file->modified();

        $this->data = (array) $file->content();

        $list = [];
        foreach ($this->data as $key => $info) {
            $list[$key] = $modified;
        }

        return $list;
    }

    /**
     * @return string
     */
    protected function getNewKey()
    {
        if (null === $this->data) {
            $this->findAllKeys();
        }

        // Make sure that the key doesn't exist.
        do {
            $key = $this->generateKey();
        } while (isset($this->data[$key]));

        return $key;
    }
}
