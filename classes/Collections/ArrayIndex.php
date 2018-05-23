<?php
namespace Grav\Plugin\FlexObjects\Collections;

use ArrayIterator;
use Closure;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Grav\Framework\Collection\CollectionInterface;
use Grav\Framework\Object\Interfaces\ObjectCollectionInterface;
use Grav\Framework\Object\Interfaces\ObjectInterface;

abstract class ArrayIndex implements CollectionInterface, Selectable, \Serializable
{
    /** @var array */
    private $entries;

    /**
     * Initializes a new IndexCollection.
     *
     * @param array $entries
     */
    public function __construct(array $entries)
    {
        $this->entries = $entries;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray()
    {
        return $this->loadObjects($this->entries);
    }

    /**
     * {@inheritDoc}
     */
    public function first()
    {
        $value = reset($this->entries);
        $key = key($this->entries);

        return $this->loadObject($key, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function last()
    {
        $value = end($this->entries);
        $key = key($this->entries);

        return $this->loadObject($key, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function key()
    {
        return key($this->entries);
    }

    /**
     * {@inheritDoc}
     */
    public function next()
    {
        $value = next($this->entries);
        $key = key($this->entries);

        return $this->loadObject($key, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function current()
    {
        $value = current($this->entries);
        $key = key($this->entries);

        return $this->loadObject($key, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function remove($key)
    {
        if (!array_key_exists($key, $this->entries)) {
            return null;
        }

        $value = $this->entries[$key];
        unset($this->entries[$key]);

        return $this->loadObject($key, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function removeElement($element)
    {
        $key = $this->isAllowedObject($element) ? $element->getKey() : null;

        if (!$key || !isset($this->entries[$key])) {
            return false;
        }

        unset($this->entries[$key]);

        return true;
    }

    /**
     * Required by interface ArrayAccess.
     *
     * {@inheritDoc}
     */
    public function offsetExists($offset)
    {
        return $this->containsKey($offset);
    }

    /**
     * Required by interface ArrayAccess.
     *
     * {@inheritDoc}
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * Required by interface ArrayAccess.
     *
     * {@inheritDoc}
     */
    public function offsetSet($offset, $value)
    {
        if (null === $offset) {
            $this->add($value);
        }

        $this->set($offset, $value);
    }

    /**
     * Required by interface ArrayAccess.
     *
     * {@inheritDoc}
     */
    public function offsetUnset($offset)
    {
        return $this->remove($offset);
    }

    /**
     * {@inheritDoc}
     */
    public function containsKey($key)
    {
        return isset($this->entries[$key]) || array_key_exists($key, $this->entries);
    }

    /**
     * {@inheritDoc}
     */
    public function contains($element)
    {
        $key = $this->isAllowedObject($element) ? $element->getKey() : null;

        return $key && isset($this->entries[$key]);
    }

    /**
     * {@inheritDoc}
     */
    public function exists(Closure $p)
    {
        return $this->loadCollection($this->entries)->exists($p);
    }

    /**
     * {@inheritDoc}
     */
    public function indexOf($element)
    {
        $key = $this->isAllowedObject($element) ? $element->getKey() : null;

        return $key && isset($this->entries[$key]) ? $key : null;
    }

    /**
     * {@inheritDoc}
     */
    public function get($key)
    {
        if (!isset($this->entries[$key])) {
            return null;
        }

        return $this->loadObject($key, $this->entries[$key]);
    }

    /**
     * {@inheritDoc}
     */
    public function getKeys()
    {
        return array_keys($this->entries);
    }

    /**
     * {@inheritDoc}
     */
    public function getValues()
    {
        return array_values($this->loadObjects($this->entries));
    }

    /**
     * {@inheritDoc}
     */
    public function count()
    {
        return \count($this->entries);
    }

    /**
     * {@inheritDoc}
     */
    public function set($key, $value)
    {
        if (!$this->isAllowedObject($value)) {
            throw new \InvalidArgumentException('Invalid argument $value');
        }

        // TODO:
        $this->entries[$key] = $value->setKey($key)->getTimestamp();
    }

    /**
     * {@inheritDoc}
     */
    public function add($element)
    {
        if (!$this->isAllowedObject($element)) {
            throw new \InvalidArgumentException('Invalid argument $element');
        }

        // TODO:
        $this->entries[$element->getKey()] = $element->getTimestamp();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function isEmpty()
    {
        return empty($this->entries);
    }

    /**
     * Required by interface IteratorAggregate.
     *
     * {@inheritDoc}
     */
    public function getIterator()
    {
        return new ArrayIterator($this->loadObjects());
    }

    /**
     * {@inheritDoc}
     */
    public function map(Closure $func)
    {
        return $this->loadCollection($this->entries)->map($func);
    }

    /**
     * {@inheritDoc}
     */
    public function filter(Closure $p)
    {
        return $this->loadCollection($this->entries)->filter($p);
    }

    /**
     * {@inheritDoc}
     */
    public function forAll(Closure $p)
    {
        return $this->loadCollection($this->entries)->forAll($p);
    }

    /**
     * {@inheritDoc}
     */
    public function partition(Closure $p)
    {
        return $this->loadCollection($this->entries)->partition($p);
    }

    /**
     * Returns a string representation of this object.
     *
     * @return string
     */
    public function __toString()
    {
        return __CLASS__ . '@' . spl_object_hash($this);
    }

    /**
     * {@inheritDoc}
     */
    public function clear()
    {
        $this->entries = [];
    }

    /**
     * {@inheritDoc}
     */
    public function slice($offset, $length = null)
    {
        return $this->loadObjects(\array_slice($this->entries, $offset, $length, true));
    }

    /**
     * {@inheritDoc}
     */
    public function matching(Criteria $criteria)
    {
        return $this->loadCollection($this->entries)->matching($criteria);
    }

    /**
     * @param int $start
     * @param int|null $limit
     * @return static
     */
    public function limit($start, $limit = null)
    {
        return $this->createFrom(\array_slice($this->entries, $start, $limit, true));
    }

    /**
     * Reverse the order of the items.
     *
     * @return static
     */
    public function reverse()
    {
        return $this->createFrom(array_reverse($this->entries));
    }

    /**
     * Shuffle items.
     *
     * @return static
     */
    public function shuffle()
    {
        $keys = $this->getKeys();
        shuffle($keys);

        return $this->createFrom(array_replace(array_flip($keys), $this->entries));
    }

    /**
     * Select items from collection.
     *
     * Collection is returned in the order of $keys given to the function.
     *
     * @param array $keys
     * @return ArrayIndex
     */
    public function select(array $keys)
    {
        $list = [];
        foreach ($keys as $key) {
            if (isset($this->entries[$key])) {
                $list[$key] = $this->entries[$key];
            }
        }

        return $this->createFrom($list);
    }

    /**
     * Split collection into chunks.
     *
     * @param int $size     Size of each chunk.
     * @return array
     */
    public function chunk($size)
    {
        return $this->loadCollection($this->entries)->chunk($size);
    }

    /**
     * @return string
     */
    public function serialize()
    {
        return serialize(['entries' => $this->entries]);
    }

    /**
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized);

        $this->entries = $data['entries'];
    }

    /**
     * Implementes JsonSerializable interface.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->loadCollection()->jsonSerialize();
    }

    /**
     * @param array $entries
     * @return static
     */
    protected function createFrom(array $entries)
    {
        return new static($entries);
    }

    /**
     * @return array
     */
    protected function getEntries()
    {
        return $this->entries;
    }

    /**
     * @param array $entries
     */
    protected function setEntries(array $entries)
    {
        $this->entries = $entries;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return ObjectInterface|null
     */
    abstract protected function loadObject($key, $value);

    /**
     * @param array|null $entries
     * @return ObjectInterface[]
     */
    abstract protected function loadObjects(array $entries = null);

    /**
     * @param array|null $entries
     * @return ObjectCollectionInterface
     */
    abstract protected function loadCollection(array $entries = null);

    /**
     * @param mixed $value
     * @return bool
     */
    abstract protected function isAllowedObject($value);
}
