<?php
namespace Grav\Plugin\FlexObjects;

use Grav\Common\Cache;
use Grav\Common\Data\Blueprint;
use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Framework\Cache\Adapter\DoctrineCache;
use Grav\Framework\Cache\CacheInterface;
use Grav\Plugin\FlexObjects\Storage\SimpleStorage;
use Grav\Plugin\FlexObjects\Storage\StorageInterface;
use Psr\SimpleCache\InvalidArgumentException;
use RuntimeException;

/**
 * Class FlexType
 * @package Grav\Plugin\FlexObjects\Entities
 */
class FlexType
{
    /** @var string */
    protected $type;
    /** @var string */
    protected $blueprint_file;
    /** @var Blueprint */
    protected $blueprint;
    /** @var FlexCollection */
    protected $collection;
    /** @var bool */
    protected $enabled;
    /** @var object */
    protected $storage;
    /** @var CacheInterface */
    protected $cache;

    /**
     * FlexType constructor.
     * @param string $type
     * @param string $blueprint_file
     * @param bool $enabled
     */
    public function __construct($type, $blueprint_file, $enabled = false)
    {
        $this->type = $type;
        $this->blueprint_file = $blueprint_file;
        $this->enabled = (bool) $enabled;
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->getBlueprint()->get('title', ucfirst($this->getType()));
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->getBlueprint()->get('description', '');
    }

    /**
     * @param string|null $name
     * @param mixed $default
     * @return mixed
     */
    public function getConfig($name = null, $default = null)
    {
        $path = 'config' . ($name ? '/' . $name : '');

        return $this->getBlueprint()->get($path, $default);
    }

    /**
     * @return Blueprint
     */
    public function getBlueprint()
    {
        if (null === $this->blueprint) {
            $this->blueprint = (new Blueprint($this->blueprint_file))->load();
            if ($this->blueprint->get('type') === 'flex-objects') {
                $blueprintBase = (new Blueprint('plugin://flex-objects/blueprints/flex-objects.yaml'))->load();
                $this->blueprint->extend($blueprintBase, true);
            }
            $this->blueprint->init();
            if (empty($this->blueprint->fields())) {
                throw new RuntimeException(sprintf('Blueprint for %s is missing', $this->type));
            }
        }

        return $this->blueprint;
    }

    public function getBlueprintFile()
    {
        return $this->blueprint_file;
    }

    /**
     * @return FlexCollection
     */
    public function getCollection()
    {
        return $this->load();
    }

    /**
     * @return FlexCollection
     */
    public function load()
    {
        if (null === $this->collection) {
            /** @var Debugger $debugger */
            $debugger = Grav::instance()['debugger'];
            $storage = $this->getStorage();
            try {
                $cache = $this->getCache();

                $debugger->startTimer('flex-keys', 'Load Flex Keys');
                $keys = $cache->get('__keys');
                if (null === $keys) {
                    $keys = $storage->getExistingKeys();
                    $cache->set('keys', $keys);
                }
                $debugger->stopTimer('flex-keys');

                $debugger->startTimer('flex-rows', 'Load Flex Rows');
                $updated = [];
                $rows = $cache->getMultiple(array_keys($keys));
                $rows = $storage->readRows($rows, $updated);
                if ($updated) {
                    $cache->setMultiple($updated);
                }
            } catch (InvalidArgumentException $e) {
                // Caching failed, but we can ignore that for now.
            }

            $debugger->stopTimer('flex-rows');

            $debugger->startTimer('flex-objects', 'Initialize Flex Collection');
            $entries = [];
            foreach ($rows as $key => $entry) {
                $object = $this->createObject($entry, $key);

                $entries[$key] = $object;
            }

            $this->collection = $this->createCollection($entries);
            $debugger->stopTimer('flex-objects');
        }

        return $this->collection;
    }

    /**
     * @param array $data
     * @param string|null $key
     * @return FlexObject
     */
    public function update(array $data, $key = null)
    {
        $object = null !== $key ? $this->getCollection()->get($key) : null;

        if (null === $object) {
            $key = null;

            $object = $this->createObject($data, $key);

            $this->getStorage()->createRows([$object->prepareStorage()]);
        } else {
            $object->update($data);

            $this->getStorage()->updateRows([$key => $object->prepareStorage()]);
        }

        try {
            $this->getCache()->clear();
        } catch (InvalidArgumentException $e) {
            // Caching failed, but we can ignore that for now.
        }

        return $object;
    }

    /**
     * @param string $key
     * @return FlexObject|null
     */
    public function remove($key)
    {
        $object = null !== $key ? $this->getCollection()->get($key) : null;
        if (!$object) {
            return null;
        }

        $this->getStorage()->deleteRows([$key => $object->prepareStorage()]);

        try {
            $this->getCache()->clear();
        } catch (InvalidArgumentException $e) {
            // Caching failed, but we can ignore that for now.
        }

        return $object;
    }

    /**
     * @return CacheInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getCache()
    {
        if (!$this->cache) {
            /** @var Cache $gravCache */
            $gravCache = Grav::instance()['cache'];

            $this->cache = new DoctrineCache($gravCache->getCacheDriver(), 'flex-objects-' . $this->getType(), 60);
        }

        return $this->cache;
    }


    /**
     * @param string|null $key
     * @return string
     */
    public function getStorageFolder($key = null)
    {
        return $this->getStorage()->getStoragePath($key);
    }

    /**
     * @param string|null $key
     * @return string
     */
    public function getMediaFolder($key = null)
    {
        return $this->getStorage()->getMediaPath($key);
    }

    /**
     * @return StorageInterface
     */
    public function getStorage()
    {
        if (!$this->storage) {
            $this->storage = $this->createStorage();
        }

        return $this->storage;
    }

    /**
     * @param array $data
     * @param $key
     * @return FlexObject
     */
    public function createObject(array $data, $key)
    {
        $className = $this->getConfig('data/object', 'Grav\\Plugin\\FlexObjects\\FlexObject');

        return new $className($data, $key, $this);
    }

    /**
     * @param array $entries
     * @return FlexCollection
     */
    public function createCollection(array $entries)
    {
        $className = $this->getConfig('data/collection', 'Grav\\Plugin\\FlexObjects\\FlexCollection');

        return new $className($entries, $this);
    }

    /**
     * @return StorageInterface
     */
    protected function createStorage()
    {
        $storage = $this->getConfig('data/storage');

        if (!\is_array($storage)) {
            $storage = ['options' => ['folder' => $storage]];
        }

        $className = isset($storage['class']) ? $storage['class'] : SimpleStorage::class;
        $options = isset($storage['options']) ? $storage['options'] : [];

        return new $className($options);
    }
}
