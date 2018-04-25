<?php
namespace Grav\Plugin\FlexObjects;

use Grav\Common\File\CompiledJsonFile;
use Grav\Common\File\CompiledMarkdownFile;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Plugin\FlexObjects\FlexCollection;
use Grav\Plugin\FlexObjects\FlexObject;
use Grav\Plugin\FlexObjects\FlexType;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;

/**
 * Class FlexStorage
 * @package Grav\Plugin\FlexObjects
 */
class FlexStorage
{
    /** @var FlexCollection */
    protected $collection;
    /** @var FlexType */
    protected $flexType;
    /** @var string */
    protected $storage;
    /** @var string */
    protected $pattern;
    /** @var string */
    protected $extension;

    /**
     * FileStorage constructor.
     *
     * @param FlexType $flexType
     */
    public function __construct(FlexType $flexType)
    {
        $this->flexType = $flexType;
        $this->storage = $this->getStorage();
        $this->pattern = '%1s/%2s/build-item.md';
        $this->extension = 'md';
    }

    /**
     * @return FlexCollection
     */
    public function getCollection()
    {
        if (null === $this->collection) {
            $this->loadCollection();
        }

        return clone $this->collection;
    }

    protected function loadCollection()
    {
        $flexType = $this->flexType;

        // Load all objects in the storage into the collection.
        $raw = (array) $this->getFile()->content();

        // Create individual objects.
        $entries = [];
        foreach ($raw as $key => $entry) {
            $entries[$key] = $flexType->createObject($entry, $key);
        }

        // Create collection.A
        $this->collection = $flexType->createCollection($entries);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function hasObject($key)
    {
        return isset($this->collection[$key]);
    }

    /**
     * @param string $key
     * @return FlexObject|null
     */
    public function readObject($key)
    {
        $object = $this->collection[$key];
        if (null === $object) {
            $data = $this->loadFile($key);
            if (null === $data) {
                return null;
            }

            $object = $this->flexType->createObject($data, $key);
            $this->collection->add($data);
        }

        return $object;
    }
}
