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
use Grav\Plugin\FlexObjects\Types\GravPages\Traits\PageContentTrait;
use Grav\Plugin\FlexObjects\Types\GravPages\Traits\PageLegacyTrait;
use Grav\Plugin\FlexObjects\Types\GravPages\Traits\PageRoutableTrait;
use Grav\Plugin\FlexObjects\Types\GravPages\Traits\PageTranslateTrait;

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

    public function save($reorder = true)
    {
        // FIXME: I guess we want to support reordering?
        //if ($reorder === true) {
        //    throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
        //}

        return parent::save();
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
     * @param string $property
     * @return bool
     */
    public function hasProperty($property): bool
    {
        if (isset(static::$headerProperties[$property]) && !$this->doHasProperty($property)) {
            return $this->getProperty('header')->get($property) !== null;
        }

        return parent::hasProperty($property);
    }

    /**
     * TODO: Add support for property filtering.
     *
     * @param string $property
     * @param mixed $default
     * @return mixed
     */
    public function getProperty($property, $default = null)
    {
        if (isset(static::$headerProperties[$property]) && !$this->doHasProperty($property)) {
            return $this->getProperty('header')->get($property, $default);
        }

        return parent::getProperty($property, $default);
    }

    /*
     * TODO: Add support for property filtering.
     *
     * @param string $property
     * @param mixed $default
     */
    public function setProperty($property, $value): void
    {
        if (isset(static::$headerProperties[$property]) && !$this->doHasProperty($property)) {
            $this->getProperty('header')->set($property, $value);

            return;
        }

        parent::setProperty($property, $value);
    }

    /**
     * @param string $property
     */
    public function unsetProperty($property): void
    {
        if (isset(static::$headerProperties[$property]) && !$this->doHasProperty($property)) {
            $this->getProperty('header')->undef($property);

            return;
        }

        parent::unsetProperty($property);
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
     */
    protected function filterElements(array &$elements): void
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

        $folder = !empty($elements['folder']) ? trim($elements['folder']) : '';

        if ($folder) {
            $order = !empty($elements['order']) ? (int)$elements['order'] : null;
            // TODO: broken
            $elements['storage_key'] = $order ? sprintf('%2d.%s', $order, $folder) : $folder;
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
