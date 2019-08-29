<?php

namespace Grav\Plugin\FlexObjects\Types\FlexPages;

use DateTime;
use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Traits\PageFormTrait;
use Grav\Framework\File\Formatter\YamlFormatter;
use Grav\Framework\Flex\FlexObject;
use Grav\Framework\Flex\Traits\FlexMediaTrait;
use Grav\Framework\Media\Interfaces\MediaManipulationInterface;
use Grav\Plugin\FlexObjects\Types\FlexPages\Traits\PageContentTrait;
use Grav\Plugin\FlexObjects\Types\FlexPages\Traits\PageLegacyTrait;
use Grav\Plugin\FlexObjects\Types\FlexPages\Traits\PageRoutableTrait;
use Grav\Plugin\FlexObjects\Types\FlexPages\Traits\PageTranslateTrait;

/**
 * Class FlexPageObject
 * @package Grav\Plugin\FlexObjects\Types\FlexPages
 */
class FlexPageObject extends FlexObject implements PageInterface, MediaManipulationInterface
{
    use PageContentTrait;
    use PageFormTrait;
    use PageLegacyTrait;
    use PageTranslateTrait;
    use PageRoutableTrait;
    use FlexMediaTrait;

    const PAGE_ORDER_REGEX = '/^(\d+)\.(.*)$/u';

    /** @var array|null */
    protected $_reorder;

    /**
     * @return array
     */
    public static function getCachedMethods(): array
    {
        return [
            // Page Content Interface
            'header' => false,
            'summary' => true,
            'content' => true,
            'value' => false,
            'media' => false,
            'title' => true,
            'menu' => true,
            'visible' => true,
            'published' => true,
            'publishDate' => true,
            'unpublishDate' => true,
            'process' => true,
            'slug' => true,
            'order' => true,
            'id' => true,
            'modified' => true,
            'lastModified' => true,
            'folder' => true,
            'date' => true,
            'dateformat' => true,
            'taxonomy' => true,
            'shouldProcess' => true,
            'isPage' => true,
            'isDir' => true,
            'folderExists' => true,

            // Page
            'isPublished' => true,
            'isVisible' => true,
            'getCreated_Timestamp' => true,
            'getPublish_Timestamp' => true,
            'getUpdated_Timestamp' => true,
        ] + parent::getCachedMethods();
    }

    /**
     * @return bool
     */
    public function isPublished(): bool
    {
        return $this->published();
    }

    /**
     * @return bool
     */
    public function isVisible(): bool
    {
        return $this->published() && $this->visible();
    }

    /**
     * @return int
     */
    public function getCreated_Timestamp(): int
    {
        return $this->getFieldTimestamp('created_date') ?? 0;
    }

    /**
     * @return int
     */
    public function getPublish_Timestamp(): int
    {
        return $this->getFieldTimestamp('publish_date') ?? $this->getCreated_Timestamp();
    }

    /**
     * @return int
     */
    public function getUpdated_Timestamp(): int
    {
        return $this->getFieldTimestamp('updated_date') ?? $this->getPublish_Timestamp();
    }

    /**
     * @inheritdoc
     */
    public function getFormValue(string $name, $default = null, string $separator = null)
    {
        $test = new \stdClass();

        $value = $this->pageContentValue($name, $test);
        if ($value !== $test) {
            return $value;
        }

        switch ($name) {
            case 'name':
                return $this->hasKey() ? $this->getKey() : '';
            case 'route':
                return $this->hasKey() ? '/' . $this->getKey() : '';
        }

        return parent::getFormValue($name, $default, $separator);
    }

    /**
     * @param string|null $key
     * @return FlexObject
     */
    public function createCopy(string $key = null)
    {
        $this->copy();

        return parent::createCopy($key);
    }

    /**
     * @param array|bool $reorder
     * @return FlexObject|\Grav\Framework\Flex\Interfaces\FlexObjectInterface
     */
    public function save($reorder = true)
    {
        $key = $this->getMetaData()['storage_key'] ?? $this->getStorageKey();

        /** @var static $instance */
        $instance = parent::save();
        if ($reorder === true) {
            $reorder = $instance->_reorder ?? false;
        }
        if (is_array($reorder)) {
            $instance->doReorder($reorder, $key ?: $this->getStorageKey());
        }

        return $instance;
    }

    protected function doReorder(array $ordering, string $key = null)
    {
        $ordering = array_values($ordering);
        $slug = basename($key);
        $order = $this->order();
        $k = array_search($slug, $ordering, true);
        if ($order === false) {
            if ($k !== false) {
                unset($ordering[$k]);
            }
        } elseif ($k === false) {
            $ordering[999999] = $slug;
        }

        $parent = $this->parent();

        /** @var FlexPageCollection $children */
        $children = $parent ? $parent->children()->withVisible()->getCollection() : null;
        if (null !== $children) {
            $ordering = array_flip($ordering);
            if ($key !== null) {
                $children->remove($key);
                if (isset($ordering[basename($key)])) {
                    $children->set($key, $this);
                }
            }
            $count = count($ordering);
            foreach ($children as $child) {
                $order = $ordering[basename($child->getKey())] ?? null;
                $child->order(null !== $order ? $order + 1 : $child->order() + $count);
            }
            $children = $children->orderBy(['order' => 'ASC']);

            $i = 0;
            foreach ($children as $child) {
                $child->reorder(++$i);
            }
        }
    }

    protected function reorder(int $order)
    {
        $oldKey = $this->getStorageKey();
        $newKey = $this->buildStorageKey($order);
        $storage = $this->getFlexDirectory()->getStorage();
        if ($oldKey !== $newKey) {
            $storage->renameRow($oldKey, $newKey);
            if (method_exists($this, 'clearMediaCache')) {
                $this->clearMediaCache();
            }
        }
    }

    protected function buildStorageKey(int $order)
    {
        $this->order($order);
        $key = $this->getStorageKey();
        $slug = basename($this->getKey());
        $parent = trim(dirname("/{$key}"), '/');
        $folder = $order ? sprintf('%02d.%s', $order, $slug) : $slug;

        return ($parent ? $parent . '/' : '') . $folder;
    }

    /**
     * Get display order for the associated media.
     *
     * @return array
     */
    public function getMediaOrder(): array
    {
        $order = $this->getNestedProperty('header.media_order');

        if (is_array($order)) {
            return $order;
        }

        if (!$order) {
            return [];
        }

        return array_map('trim', explode(',', $order));
    }

    // Overrides for header properties.

    /**
     * Common logic to load header properties.
     *
     * @param string $property
     * @param $var
     * @param callable $filter
     * @return |null
     */
    protected function loadHeaderProperty(string $property, $var, callable $filter)
    {
        // We have to use parent methods in order to avoid loops.
        $value = null === $var ? parent::getProperty($property) : null;
        if (null === $value) {
            $value = $filter($var ?? $this->getProperty('header')->get($property));

            parent::setProperty($property, $value);
            if ($this->doHasProperty($property)) {
                $value = parent::getProperty($property);
            }
        }

        return $value;
    }

    /**
     * Common logic to load header properties.
     *
     * @param string $property
     * @param $var
     * @param callable $filter
     * @return |null
     */
    protected function loadProperty(string $property, $var, callable $filter)
    {
        // We have to use parent methods in order to avoid loops.
        $value = null === $var ? parent::getProperty($property) : null;
        if (null === $value) {
            $value = $filter($var);

            parent::setProperty($property, $value);
            if ($this->doHasProperty($property)) {
                $value = parent::getProperty($property);
            }
        }

        return $value;
    }

    /**
     * @param string $property
     * @param mixed $default
     * @return mixed
     */
    public function getProperty($property, $default = null)
    {
        $method = static::$headerProperties[$property] ??static::$calculatedProperties[$property] ??  null;
        if ($method && method_exists($this, $method)) {
            return $this->{$method}();
        }

        return parent::getProperty($property, $default);
    }

    /*
     * @param string $property
     * @param mixed $default
     */
    public function setProperty($property, $value): void
    {
        $method = static::$headerProperties[$property] ?? static::$calculatedProperties[$property] ?? null;
        if ($method && method_exists($this, $method)) {
            $this->{$method}($value);

            return;
        }

        parent::setProperty($property, $value);
    }

    public function setNestedProperty($property, $value, $separator = null)
    {
        if (strpos($property, 'header.') === 0) {
            $this->getProperty('header')->set(str_replace('header.', '', $property), $value);

            return;
        }

        parent::setNestedProperty($property, $value, $separator);
    }

    public function unsetNestedProperty($property, $separator = null)
    {
        if (strpos($property, 'header.') === 0) {
            $this->getProperty('header')->undef(str_replace('header.', '', $property));

            return;
        }

        parent::unsetNestedProperty($property, $separator);
    }

    /**
     * @param array $elements
     * @param bool $extended
     */
    protected function filterElements(array &$elements, bool $extended = false): void
    {
        // Markdown storage conversion to page structure.
        if (isset($elements['content'])) {
            $elements['markdown'] = $elements['content'];
            unset($elements['content']);
        }

        // RAW frontmatter support.
        if (isset($elements['frontmatter'])) {
            $formatter = new YamlFormatter();
            try {
                // Replace the whole header except for media order, which is used in admin.
                $media_order = $elements['media_order'] ?? null;
                $elements['header'] = $formatter->decode($elements['frontmatter']);
                if ($media_order) {
                    $elements['header']['media_order'] = $media_order;
                }
            } catch (\RuntimeException $e) {
                throw new \RuntimeException('Badly formatted markdown');
            }

            unset($elements['frontmatter']);
        }

        if (!$extended) {
            $folder = !empty($elements['folder']) ? trim($elements['folder']) : '';

            if ($folder) {
                $order = !empty($elements['order']) ? (int)$elements['order'] : null;
                // TODO: broken
                $elements['storage_key'] = $order ? sprintf('%02d.%s', $order, $folder) : $folder;
            }
        }

        parent::filterElements($elements);
    }

    /**
     * @param string $field
     * @return int|null
     */
    protected function getFieldTimestamp(string $field): ?int
    {
        $date = $this->getFieldDateTime($field);

        return $date ? $date->getTimestamp() : null;
    }

    /**
     * @param string $field
     * @return DateTime|null
     */
    protected function getFieldDateTime(string $field): ?DateTime
    {
        try {
            $value = $this->getProperty($field);
            $date = $value ? new DateTime($value) : null;
        } catch (\Exception $e) {
            /** @var Debugger $debugger */
            $debugger = Grav::instance()['debugger'];
            $debugger->addException($e);

            $date = null;
        }

        return $date;
    }
}
