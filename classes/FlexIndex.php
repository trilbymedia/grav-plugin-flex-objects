<?php
namespace Grav\Plugin\FlexObjects;

use ArrayIterator;
use Closure;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Grav\Framework\Cache\Exception\InvalidArgumentException;

class FlexIndex implements Collection, Selectable
{
    /** @var array */
    private $entries;

    /** @var FlexType */
    private $flexType;

    /**
     * Initializes a new IndexCollection.
     *
     * @param array $entries
     * @param array $indexes
     */
    public function __construct(array $entries, FlexType $flexType)
    {
        $this->entries = $entries;
        $this->flexType = $flexType;
    }

    /**
     * @param array $entries
     * @param array $indexes
     * @return static
     */
    protected function createFrom(array $entries)
    {
        return new static($entries, $this->flexType);
    }

    /**
     * {@inheritDoc}
     */
    public function toArray()
    {
        return $this->flexType->getObjects($this->getKeys());
    }

    /**
     * {@inheritDoc}
     */
    public function first()
    {
        reset($this->entries);
        $key = key($this->entries);

        return $this->flexType->getObject($key);
    }

    /**
     * {@inheritDoc}
     */
    public function last()
    {
        end($this->entries);
        $key = key($this->entries);

        return $this->flexType->getObject($key);
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
        next($this->entries);
        $key = key($this->entries);

        return $this->flexType->getObject($key);
    }

    /**
     * {@inheritDoc}
     */
    public function current()
    {
        $key = key($this->entries);

        return $this->flexType->getObject($key);
    }

    /**
     * {@inheritDoc}
     */
    public function remove($key)
    {
        if (!array_key_exists($key, $this->entries)) {
            return null;
        }

        $removed = $this->entries[$key];
        unset($this->entries[$key]);

        return $removed;
    }

    /**
     * {@inheritDoc}
     */
    public function removeElement($element)
    {
        $key = $element instanceof FlexObject ? $element->getKey() : null;

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
        $key = $element instanceof FlexObject ? $element->getKey() : null;

        return $key && isset($this->entries[$key]);
    }

    /**
     * {@inheritDoc}
     */
    public function exists(Closure $p)
    {
        return $this->flexType->getCollection($this->getKeys())->exists($p);
    }

    /**
     * {@inheritDoc}
     */
    public function indexOf($element)
    {
        $key = $element instanceof FlexObject ? $element->getKey() : null;

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

        return $this->flexType->getObject($key);
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
        return array_values($this->flexType->getObjects($this->getKeys()));
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
        if (!$value instanceof FlexObject) {
            throw new \InvalidArgumentException('First parameter needs to be FlexObject');
        }

        $this->entries[$key] = $value->setKey($key)->getModifiedTime();
    }

    /**
     * {@inheritDoc}
     */
    public function add($element)
    {
        if (!$element instanceof FlexObject) {
            throw new \InvalidArgumentException('First parameter needs to be FlexObject');
        }

        $this->entries[$element->getKey()] = $element->getModifiedTime();

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
        return new ArrayIterator($this->getValues());
    }

    /**
     * {@inheritDoc}
     */
    public function map(Closure $func)
    {
        return $this->flexType->getCollection($this->getKeys())->map($func);
    }

    /**
     * {@inheritDoc}
     */
    public function filter(Closure $p)
    {
        return $this->flexType->getCollection($this->getKeys())->filter($p);
    }

    /**
     * {@inheritDoc}
     */
    public function forAll(Closure $p)
    {
        return $this->flexType->getCollection($this->getKeys())->forAll($p);
    }

    /**
     * {@inheritDoc}
     */
    public function partition(Closure $p)
    {
        return $this->flexType->getCollection($this->getKeys())->partition($p);
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
        return $this->flexType->getObjects(array_keys(\array_slice($this->entries, $offset, $length, true)));
    }

    /**
     * {@inheritDoc}
     */
    public function matching(Criteria $criteria)
    {
        return $this->flexType->getCollection($this->getKeys())->matching($criteria);
    }

    // FlexObject interface

    /**
     * {@inheritDoc}
     */
    public function call($method, array $arguments = [])
    {
        $key = 'call-' . md5($method . json_encode($arguments));
        $cache = $this->flexType->getCache();

        $test = new \stdClass;
        try {
            $result = $cache->get($key, $test);
        } catch (InvalidArgumentException $e) {
            $result = $test;
        }

        if ($result === $test) {
            $result = $this->flexType->getCollection($this->getKeys())->call($method, $arguments);

            try {
                $cache->set($key, $result);
            } catch (InvalidArgumentException $e) {
                // TODO: log error.
            }
        }

        return $result;
    }

    public function __call($name, $arguments)
    {
        return $this->flexType->getCollection($this->getKeys())->{$name}(...$arguments);
    }
}
