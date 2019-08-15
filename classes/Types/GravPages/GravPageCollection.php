<?php

namespace Grav\Plugin\FlexObjects\Types\GravPages;

use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageCollectionInterface;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Page;
use Grav\Common\Utils;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Plugin\FlexObjects\Types\FlexPages\FlexPageCollection;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class GravPageCollection
 * @package Grav\Plugin\FlexObjects\Types\GravPages
 *
 * Incompatibilities with Grav\Common\Page\Collection:
 *     $page = $collection->key()       will not work at all
 *     $clone = clone $collection       does not clone objects inside the collection, does it matter?
 *     $string = (string)$collection    returns collection id instead of comma separated list
 *     $collection->add()               incompatible method signature
 *     $collection->remove()            incompatible method signature
 *     $collection->filter()            incompatible method signature (takes closure instead of callable)
 * AND most methods are immutable; they do not update the current collection, but return updated one
 */
class GravPageCollection extends FlexPageCollection implements PageCollectionInterface
{
    protected $_root;
    protected $_params;

    public function getRoot()
    {
        if (null === $this->_root) {
            $grav = Grav::instance();

            /** @var UniformResourceLocator $locator */
            $locator = $grav['locator'];

            $page = new Page();
            $page->path($locator($this->getFlexDirectory()->getStorageFolder()));

            $this->_root = $page;
        }

        return $page;
    }

    /**
     * Get the collection params
     *
     * @return array
     */
    public function getParams()
    {
        return $this->_params;
    }

    /**
     * Set parameters to the Collection
     *
     * @param array $params
     *
     * @return $this
     */
    public function setParams(array $params)
    {
        $this->_params = array_merge($this->_params, $params);

        return $this;
    }

    /**
     * Get the collection params
     *
     * @return array
     */
    public function params()
    {
        return $this->getParams();
    }

    /**
     * Add a single page to a collection
     *
     * @param PageInterface $page
     *
     * @return $this
     */
    public function addPage(PageInterface $page)
    {
        if (!$page instanceof FlexObjectInterface) {
            throw new \InvalidArgumentException('$page is not a flex page.');
        }

        // FIXME: support other keys.
        $this->set($page->getKey(), $page);

        return $this;
    }

    /**
     *
     * Merge another collection with the current collection
     *
     * @param PageCollectionInterface $collection
     * @return $this
     */
    public function merge(PageCollectionInterface $collection)
    {
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Intersect another collection with the current collection
     *
     * @param PageCollectionInterface $collection
     * @return $this
     */
    public function intersect(PageCollectionInterface $collection)
    {
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Return previous item.
     *
     * @return mixed
     */
    public function prev()
    {
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Return nth item.
     *
     * @param int $key
     *
     * @return mixed|bool
     */
    public function nth($key)
    {
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Pick one or more random entries.
     *
     * @param int $num Specifies how many entries should be picked.
     *
     * @return $this
     */
    public function random($num = 1)
    {
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Append new elements to the list.
     *
     * @param array $items Items to be appended. Existing keys will be overridden with the new values.
     *
     * @return $this
     */
    public function append($items)
    {
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Split collection into array of smaller collections.
     *
     * @param int $size
     * @return PageCollectionInterface[]
     */
    public function batch($size)
    {
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Reorder collection.
     *
     * @param string $by
     * @param string $dir
     * @param array  $manual
     * @param string $sort_flags
     *
     * @return $this
     */
    public function order($by, $dir = 'asc', $manual = null, $sort_flags = null)
    {
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Check to see if this item is the first in the collection.
     *
     * @param  string $path
     *
     * @return bool True if item is first.
     */
    public function isFirst($path): bool
    {
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Check to see if this item is the last in the collection.
     *
     * @param  string $path
     *
     * @return bool True if item is last.
     */
    public function isLast($path): bool
    {
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Gets the previous sibling based on current position.
     *
     * @param  string $path
     *
     * @return PageInterface  The previous item.
     */
    public function prevSibling($path)
    {
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Gets the next sibling based on current position.
     *
     * @param  string $path
     *
     * @return PageInterface The next item.
     */
    public function nextSibling($path)
    {
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Returns the adjacent sibling based on a direction.
     *
     * @param  string  $path
     * @param  int $direction either -1 or +1
     *
     * @return PageInterface|PageCollectionInterface    The sibling item.
     */
    public function adjacentSibling($path, $direction = 1)
    {
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Returns the item in the current position.
     *
     * @param  string $path the path the item
     *
     * @return int   the index of the current page.
     */
    public function currentPosition($path): int
    {
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Returns the items between a set of date ranges of either the page date field (default) or
     * an arbitrary datetime page field where end date is optional
     * Dates can be passed in as text that strtotime() can process
     * http://php.net/manual/en/function.strtotime.php
     *
     * @param string $startDate
     * @param string|bool $endDate
     * @param string|null $field
     *
     * @return $this
     * @throws \Exception
     */
    public function dateRange($startDate, $endDate = false, $field = null)
    {
        $start = Utils::date2timestamp($startDate);
        $end = $endDate ? Utils::date2timestamp($endDate) : false;

        $entries = [];
        foreach ($this as $key => $object) {
            if (!$object) {
                continue;
            }

            $date = $field ? strtotime($object->getNestedProperty($field)) : $object->date();

            if ($date >= $start && (!$end || $date <= $end)) {
                $entries[$key] = $object;
            }
        }

        return $this->createFrom($entries);
    }

    /**
     * Creates new collection with only visible pages
     *
     * @return GravPageCollection The collection with only visible pages
     */
    public function visible()
    {
        $entries = [];
        foreach ($this as $key => $object) {
            if ($object && $object->visible()) {
                $entries[$key] = $object;
            }
        }

        return $this->createFrom($entries);
    }

    /**
     * Creates new collection with only non-visible pages
     *
     * @return GravPageCollection The collection with only non-visible pages
     */
    public function nonVisible()
    {
        $entries = [];
        foreach ($this as $key => $object) {
            if ($object && !$object->visible()) {
                $entries[$key] = $object;
            }
        }

        return $this->createFrom($entries);
    }

    /**
     * Creates new collection with only modular pages
     *
     * @return GravPageCollection The collection with only modular pages
     */
    public function modular()
    {
        $entries = [];
        foreach ($this as $key => $object) {
            if ($object && $object->modular()) {
                $entries[$key] = $object;
            }
        }

        return $this->createFrom($entries);
    }

    /**
     * Creates new collection with only non-modular pages
     *
     * @return GravPageCollection The collection with only non-modular pages
     */
    public function nonModular()
    {
        $entries = [];
        foreach ($this as $key => $object) {
            if ($object && !$object->modular()) {
                $entries[$key] = $object;
            }
        }

        return $this->createFrom($entries);
    }

    /**
     * Creates new collection with only published pages
     *
     * @return GravPageCollection The collection with only published pages
     */
    public function published()
    {
        $entries = [];
        foreach ($this as $key => $object) {
            if ($object && $object->published()) {
                $entries[$key] = $object;
            }
        }

        return $this->createFrom($entries);
    }

    /**
     * Creates new collection with only non-published pages
     *
     * @return GravPageCollection The collection with only non-published pages
     */
    public function nonPublished()
    {
        $entries = [];
        foreach ($this as $key => $object) {
            if ($object && !$object->published()) {
                $entries[$key] = $object;
            }
        }

        return $this->createFrom($entries);
    }

    /**
     * Creates new collection with only routable pages
     *
     * @return GravPageCollection The collection with only routable pages
     */
    public function routable()
    {
        $entries = [];
        foreach ($this as $key => $object) {
            if ($object && $object->routable()) {
                $entries[$key] = $object;
            }
        }

        return $this->createFrom($entries);
    }

    /**
     * Creates new collection with only non-routable pages
     *
     * @return GravPageCollection The collection with only non-routable pages
     */
    public function nonRoutable()
    {
        $entries = [];
        foreach ($this as $key => $object) {
            if ($object && !$object->routable()) {
                $entries[$key] = $object;
            }
        }

        return $this->createFrom($entries);
    }

    /**
     * Creates new collection with only pages of the specified type
     *
     * @param string $type
     *
     * @return GravPageCollection The collection
     */
    public function ofType($type)
    {
        $entries = [];
        foreach ($this as $key => $object) {
            if ($object && $object->template() === $type) {
                $entries[$key] = $object;
            }
        }

        return $this->createFrom($entries);
    }

    /**
     * Creates new collection with only pages of one of the specified types
     *
     * @param string[] $types
     *
     * @return GravPageCollection The collection
     */
    public function ofOneOfTheseTypes($types)
    {
        $entries = [];
        foreach ($this as $key => $object) {
            if ($object && \in_array($object->template(), $types, true)) {
                $entries[$key] = $object;
            }
        }

        return $this->createFrom($entries);
    }

    /**
     * Creates new collection with only pages of one of the specified access levels
     *
     * @param array $accessLevels
     *
     * @return GravPageCollection The collection
     */
    public function ofOneOfTheseAccessLevels($accessLevels)
    {
        $entries = [];
        foreach ($this as $key => $object) {
            if ($object && isset($object->header()->access)) {
                if (\is_array($object->header()->access)) {
                    //Multiple values for access
                    $valid = false;

                    foreach ($object->header()->access as $index => $accessLevel) {
                        if (\is_array($accessLevel)) {
                            foreach ($accessLevel as $innerIndex => $innerAccessLevel) {
                                if (\in_array($innerAccessLevel, $accessLevels)) {
                                    $valid = true;
                                }
                            }
                        } else {
                            if (\in_array($index, $accessLevels)) {
                                $valid = true;
                            }
                        }
                    }
                    if ($valid) {
                        $entries[$key] = $object;
                    }
                } else {
                    //Single value for access
                    if (\in_array($object->header()->access, $accessLevels)) {
                        $entries[$key] = $object;
                    }
                }

            }
        }

        return $this->createFrom($entries);
    }

    /**
     * Get the extended version of this Collection with each page keyed by route
     *
     * @return array
     * @throws \Exception
     */
    public function toExtendedArray()
    {
        $entries  = [];
        foreach ($this as $key => $object) {
            if ($object) {
                $entries[$object->route()] = $object->toArray();
            }
        }
        return $entries;
    }
}
