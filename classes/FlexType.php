<?php
namespace Grav\Plugin\FlexObjects;

use Grav\Common\Cache;
use Grav\Common\Data\Blueprint;
use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Framework\Cache\Adapter\DoctrineCache;
use Grav\Framework\Cache\Adapter\MemoryCache;
use Grav\Framework\Cache\CacheInterface;
use Grav\Plugin\FlexObjects\Storage\SimpleStorage;
use Grav\Plugin\FlexObjects\Storage\StorageInterface;
use Psr\SimpleCache\InvalidArgumentException;
use RuntimeException;

/**
 * Class FlexType
 * @package Grav\Plugin\FlexObjects
 */
class FlexType
{
    /** @var string */
    protected $type;
    /** @var string */
    protected $blueprint_file;
    /** @var Blueprint */
    protected $blueprint;
    /** @var FlexIndex */
    protected $index;
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
     * @param array|null $keys
     * @return FlexCollection|FlexIndex
     */
    public function getCollection(array $keys = null)
    {
        if (null !== $keys) {
            return $this->createCollection($this->getObjects($keys));
        }

        return clone $this->getIndex();
    }

    public function getObject($key)
    {
        $objects = $this->getObjects([$key]);

        return $objects ? reset($objects) : null;
    }

    public function getObjects($keys)
    {
        /** @var Debugger $debugger */
        $debugger = Grav::instance()['debugger'];
        $debugger->startTimer('flex-objects', sprintf('Initializing %d Flex Objects', \count($keys)));

        $storage = $this->getStorage();
        $cache = $this->getCache();

        try {
            $rows = $cache->getMultiple($keys);
        } catch (InvalidArgumentException $e) {
            $rows = [];
        }

        $updated = [];
        $rows = $storage->readRows($rows, $updated);

        if ($updated) {
            try {
                $cache->setMultiple($updated);
            } catch (InvalidArgumentException $e) {
                // TODO: log about the issue.
            }
        }

        $list = [];
        foreach ($rows as $key => $row) {
            $list[$key] = $this->createObject($row, $key, false);
        }

        $debugger->stopTimer('flex-objects');

        return $list;
    }

    protected function getIndex()
    {
        if (null === $this->index) {
            /** @var Debugger $debugger */
            $debugger = Grav::instance()['debugger'];
            $debugger->startTimer('flex-keys', 'Loading Flex Index');

            $storage = $this->getStorage();
            $cache = $this->getCache();

            try {
                $keys = $cache->get('__keys');
            } catch (InvalidArgumentException $e) {
                $keys = null;
            }

            if (null === $keys) {
                $keys = $storage->getExistingKeys();
                 try {
                    $cache->set('__keys', $keys);
                } catch (InvalidArgumentException $e) {
                     // TODO: log about the issue.
                }
            }

            $this->index = new FlexIndex($keys, $this);

            $debugger->stopTimer('flex-keys');
        }

        return $this->index;
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
     */
    public function getCache()
    {
        if (null === $this->cache) {
            try {
                /** @var Cache $gravCache */
                $gravCache = Grav::instance()['cache'];

                $this->cache = new DoctrineCache($gravCache->getCacheDriver(), 'flex-objects-' . $this->getType(), 60);
            } catch (\Exception $e) {
                $this->cache = new MemoryCache('flex-objects-' . $this->getType());
            }
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
     * @param string $key
     * @param bool $validate
     * @return FlexObject
     */
    public function createObject(array $data, $key, $validate = true)
    {
        $className = $this->getConfig('data/object', 'Grav\\Plugin\\FlexObjects\\FlexObject');

        return new $className($data, $key, $this, $validate);
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
