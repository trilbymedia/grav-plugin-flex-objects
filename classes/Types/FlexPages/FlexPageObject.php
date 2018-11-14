<?php

namespace Grav\Plugin\FlexObjects\Types\FlexPages;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Markdown\Parsedown;
use Grav\Common\Markdown\ParsedownExtra;
use Grav\Common\Twig\Twig;
use Grav\Common\Utils;
use Grav\Framework\Flex\FlexObject;
use Grav\Framework\Flex\Interfaces\FlexStorageInterface;
use Grav\Framework\Flex\Traits\FlexMediaTrait;
use Grav\Framework\Media\Interfaces\MediaManipulationInterface;
use Grav\Plugin\FlexObjects\Types\GravPages\Interfaces\PageContentInterface;
use Grav\Plugin\FlexObjects\Types\GravPages\Traits\PageContentTrait;

/**
 * Class FlexPageObject
 * @package Grav\Plugin\FlexObjects\Types\FlexPages
 */
class FlexPageObject extends FlexObject implements PageContentInterface, MediaManipulationInterface
{
    use PageContentTrait;
    use FlexMediaTrait;

    const ORDER_PREFIX_REGEX = PAGE_ORDER_PREFIX_REGEX;

    /**
     * @return array
     */
    public static function getCachedMethods()
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

            // Page
            'isPublished' => true,
            'folderExists' => true
        ] + parent::getCachedMethods();
    }
    /**
     * @param FlexStorageInterface $storage
     * @return array
     */
    public static function createIndex(FlexStorageInterface $storage)
    {
        $index = parent::createIndex($storage);

        $list = [];
        foreach ($index as $key => $timestamp) {
            $slug = static::adjustRouteCase(preg_replace(static::ORDER_PREFIX_REGEX, '', $key));

            $list[$slug] = [$key, $timestamp];
        }

        return $list;
    }

    /**
     * @return bool
     */
    public function isPublished()
    {
        return $this->published();
    }

    /**
     * @inheritdoc PageInterface
     */
    public function value($name, $default = null)
    {
        $test = new \stdClass();

        $value = $this->pageContentValue($name, $test);
        if ($value !== $test) {
            return $value;
        }

        switch ($name) {
            case 'name':
                return $this->getKey();
            case 'route':
                return '/' . $this->getKey();
        }

        return parent::value($name, $default);
    }

    /**
     * Get unknown header variables.
     *
     * @return array
     */
    public function extra()
    {
        return $this->getBlueprint()->extra($this->prepareStorage()['header'], 'header.');
    }

    public function folder($var = null)
    {
        if (null !== $var) {
            throw new \RuntimeException('Not Implemented');
        }

        return $this->getStorageKey();
    }

    public function folderExists()
    {
        return $this->exists();
    }

    /**
     * Returns the clean path to the page file
     * @deprecated Needed in admin for Page Media.
     */
    public function relativePagePath()
    {
        return $this->getMediaFolder();
    }

    /**
     * Get display order for the associated media.
     *
     * @return array
     */
    public function getMediaOrder()
    {
        return array_map('trim', explode(',', (string)$this->getNestedProperty('header.media_order')));
    }

    // Overrides for header properties.

    /**
     * @param string $property
     * @return bool
     */
    public function hasProperty($property)
    {
        if (isset(static::$headerProperties[$property])) {
            $property = "header.{$property}";

            return $this->hasNestedProperty($property);
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
        if (isset(static::$headerProperties[$property])) {
            $property = "header.{$property}";

            return $this->getNestedProperty($property, $default);
        }

        return parent::getProperty($property, $default);
    }

    /*
     * TODO: Add support for property filtering.
     *
     * @param string $property
     * @param mixed $default
     * @return mixed
     */
    public function setProperty($property, $value)
    {
        if (isset(static::$headerProperties[$property])) {
            $property = "header.{$property}";

            return $this->setNestedProperty($property, $value);
        }

        return parent::setProperty($property, $value);
    }

    /**
     * @param string $property
     */
    public function unsetProperty($property)
    {
        if (isset(static::$headerProperties[$property])) {
            $property = "header.{$property}";

            $this->unsetNestedProperty($property);
        }

        parent::unsetProperty($property);
    }

    protected function offsetLoad_summary()
    {
        return $this->processSummary();
    }

    protected function offsetSerialize_summary()
    {
        return null;
    }

    protected function offsetLoad_content($value)
    {
        return $this->processContent($value);
    }

    protected function offsetSerialize_content()
    {
        return $this->getElement('content');
    }

    protected function offsetLoad_media()
    {
        return $this->getMedia();
    }

    protected function offsetSerialize_media()
    {
        return null;
    }

    /**
     * @param int|null $size
     * @param bool $textOnly
     * @return string
     */
    protected function processSummary($size = null, $textOnly = false)
    {
        $config_global = (array)Grav::instance()['config']->get('site.summary');
        $config_page = (array)$this->getNestedProperty('header.summary');
        if ($config_page) {
            $config = array_merge($config_global, $config_page);
        }

        // Return summary based on settings in site config file.
        if (empty($config['enabled'])) {
            return $this->content();
        }

        $content = $textOnly ? strip_tags($this->content()) : $this->content();
        $summary_size = $this->getProperty('summary_size');

        // Return calculated summary based on summary divider's position.
        $format = $config['format'];
        // Return entire page content on wrong/unknown format.
        if (!\in_array($format, ['short', 'long'], true)) {
            return $content;
        }

        if ($format === 'short' && $summary_size) {
            // Use mb_strimwidth to slice the string.
            if (mb_strwidth($content, 'utf8') > $summary_size) {
                return mb_substr($content, 0, $summary_size);
            }

            return $content;
        }

        // Get summary size from site config's file.
        if ($size === null) {
            $size = $config['size'];
        }

        // If the size is zero, return the entire page content.
        if ($size === 0) {
            return $content;
        }

        // Return calculated summary based on defaults.
        if (!is_numeric($size) || ($size < 0)) {
            $size = 300;
        } else {
            $size = (int)$size;
        }

        // Only return string but not html, wrap whatever html tag you want when using.
        if ($textOnly) {
            if (mb_strwidth($content, 'utf-8') <= $size) {
                return $content;
            }

            return mb_strimwidth($content, 0, $size, '...', 'utf-8');
        }

        $summary = Utils::truncateHTML($content, $size);

        return html_entity_decode($summary);
    }

    /**
     * Gets and Sets the content based on content portion of the .md file
     *
     * TODO: Not yet fully compatible with Page class.
     *
     * @param  string $content
     * @return string
     * @throws \Exception
     */
    protected function processContent($content)
    {
        /** @var Config $config */
        $config = Grav::instance()['config'];

        $process_markdown = $this->shouldProcess('markdown');
        $process_twig = $this->shouldProcess('twig');
        $twig_first = $this->getNestedProperty('header.twig_first') ?? $config->get('system.pages.twig_first', true);

        $markdown_options = [];
        if ($process_markdown) {
            // Build markdown options.
            $markdown_options = (array)$config->get('system.pages.markdown');
            $markdown_page_options = (array)$this->getNestedProperty('header.markdown');
            if ($markdown_page_options) {
                $markdown_options = array_merge($markdown_options, $markdown_page_options);
            }
            if (!isset($markdown_options['extra'])) {
                // pages.markdown_extra is deprecated, but still check it...
                $markdown_options['extra'] = $this->getNestedProperty('markdown_extra') ?? $config->get('system.pages.markdown_extra');
            }
        }

        if ($twig_first) {
            /*
            if ($process_twig) {
                $content = $this->processTwig($content);
            }
            */
            if ($process_markdown) {
                $content = $this->processMarkdown($content, $markdown_options);
            }

        } else {
            if ($process_markdown) {
                $content = $this->processMarkdown($content, $markdown_options);
            }
            /*
            if ($process_twig) {
                $content = $this->processTwig($content);
            }
            */
        }

        // Handle summary divider
        $delimiter = $config->get('site.summary.delimiter', '===');
        $divider_pos = mb_strpos($this->content, "<p>{$delimiter}</p>");
        if ($divider_pos !== false) {
            $this->setProperty('summary_size', $divider_pos);
            $content = str_replace("<p>{$delimiter}</p>", '', $content);
        }

        return $content;
    }

    /**
     * @param string $route
     * @return string
     * @internal
     */
    static public function adjustRouteCase($route)
    {
        $case_insensitive = Grav::instance()['config']->get('system.force_lowercase_urls');

        return $case_insensitive ? mb_strtolower($route) : $route;
    }

    /**
     * Process the Twig page content.
     *
     * @param  string $content
     * @return string
     */
    protected function processTwig($content)
    {
        /** @var Twig $twig */
        $twig = Grav::instance()['twig'];

        return $twig->processPage($this, $content);
    }

    /**
     * Process the Markdown content.
     *
     * Uses Parsedown or Parsedown Extra depending on configuration.
     *
     * @param string $content
     * @param array  $options
     * @return string
     * @throws \Exception
     */
    protected function processMarkdown($content, array $options = [])
    {
        // Initialize the preferred variant of markdown parser.
        if (isset($defaults['extra'])) {
            $parsedown = new ParsedownExtra($this, $options);
        } else {
            $parsedown = new Parsedown($this, $options);
        }

        return $parsedown->text($content);
    }

    /**
     * @param array $elements
     */
    protected function filterElements(array &$elements)
    {
        if (isset($elements['content'])) {
            $elements['markdown'] = $elements['content'];
            unset($elements['content']);
        }

        $folder = !empty($elements['folder']) ? trim($elements['folder']) : '';

        if ($folder) {
            $order = !empty($elements['order']) ? (int)$elements['order'] : null;
            // TODO: broken
            $elements['storage_key'] = $order ? sprintf('%2d.%s', $order, $folder) : $folder;
        }

        unset($elements['order'], $elements['folder']);

        parent::filterElements($elements);
    }
}
