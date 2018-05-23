<?php
namespace Grav\Plugin\FlexObjects\Types\Pages;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Markdown\Parsedown;
use Grav\Common\Markdown\ParsedownExtra;
use Grav\Common\Media\Interfaces\MediaInterface;
use Grav\Common\Media\Traits\MediaTrait;
use Grav\Common\Twig\Twig;
use Grav\Common\Utils;
use Grav\Plugin\FlexObjects\FlexObject;

class_exists('Grav\\Common\\Page\\Page', true);

/**
 * Class BuildObject
 * @package Grav\Plugin\RevKit\Repositories\Builds
 */
class PageObject extends FlexObject implements PageInterface, MediaInterface
{
    use MediaTrait;

    static protected $headerProperties = [
        'slug'              => 'trim',          // Page doesn't do trim.
        'routes'            => 'array',
        'title'             => 'trim',
        'summary'           => 'array',         // Oops, not in Page.
        'language'          => 'trim',
        'template'          => 'trim',
        'menu'              => 'trim',
        'routable'          => 'bool',
        'visible'           => 'bool',
        'redirect'          => 'trim',
        'external_url'      => 'trim',
        'order_dir'         => 'trim',
        'order_by'          => 'trim',
        'order_manual'      => 'array',
        'dateformat'        => 'dateformat()',
        'date'              => 'date()',
        'markdown'          => 'array',         // Oops, not in Page.
        'markdown_extra'    => 'bool',
        'taxonomy'          => 'array[array]',
        'max_count'         => 'int',
        'process'           => 'array[bool]',
        'published'         => 'bool',
        'publish_date'      => 'string',
        'unpublish_date'    => 'string',
        'expires'           => 'int',
        'cache_control'     => 'raw',
        'etag'              => 'bool',
        'last_modified'     => 'bool',
        'ssl'               => 'bool',
        'template_format'   => 'raw',
        'debugger'          => 'bool'
    ];

    protected $summary;
    protected $content;

    /**
     * @return array
     */
    public static function getCachedMethods()
    {
        return [
            'title' => true,
            'menu' => true,
            'folder' => true,
            'folderExists' => true,
            'slug' => true,
            'order' => true,
            'summary' => true,
            'content' => true,
            'visible' => true,
            'published' => true,
            'publishDate' => true,
            'unpublishDate' => true,
            'process' => true,
            'id' => true,
            'modified' => true,
            'lastModified' => true,
            'date' => true,
            'dateformat' => true,
            'taxonomy' => true,
            'shouldProcess' => true,
            'isPage' => true,
            'isDir' => true

        ] + parent::getCachedMethods();
    }

    /**
     * @param array $index
     * @return array
     */
    public static function createIndex(array $index)
    {
        $list = [];
        foreach ($index as $key => $timestamp) {
            $slug = static::adjustRouteCase(preg_replace(PAGE_ORDER_PREFIX_REGEX, '', $key));

            $list[$slug] = [$key, $timestamp];
        }

        return $list;
    }

    // Page Interface.

    public function header($var = null)
    {
        if (null !== $var) {
            $this->setProperty('header', $var);
        }

        return (object)$this->getProperty('header', []);
    }

    /**
     * Get value from a page variable (used mostly for creating edit forms).
     *
     * @param string $name Variable name.
     * @param mixed $default
     *
     * @return mixed
     */
    public function value($name, $default = null)
    {
        if ($name === 'content') {
            return $this->getElement('markdown');
        }
        if ($name === 'order') {
            $order = $this->order();

            return $order ? (int)$this->order() : '';
        }
        if ($name === 'ordering') {
            return (bool)$this->order();
        }
        if ($name === 'folder') {
            return preg_replace(PAGE_ORDER_PREFIX_REGEX, '', $this->folder());
        }
        if ($name === 'slug') {
            return $this->slug();
        }
        if ($name === 'media') {
            return $this->media()->all();
        }
        if ($name === 'media.file') {
            return $this->media()->files();
        }
        if ($name === 'media.video') {
            return $this->media()->videos();
        }
        if ($name === 'media.image') {
            return $this->media()->images();
        }
        if ($name === 'media.audio') {
            return $this->media()->audios();
        }

        return parent::value($name, $default);
    }

    public function title($var = null)
    {
        if (null !== $var) {
            $this->setProperty('title', $var);
        }

        return $this->getProperty('title') ?: ucfirst($this->slug());
    }

    public function menu($var = null)
    {
        if (null !== $var) {
            $this->setProperty('menu', $var);
        }

        return $this->getProperty('menu') ?: $this->title();
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

    public function slug($var = null)
    {
        if (null !== $var) {
            $this->setProperty('slug', $var);
        }

        return $this->getProperty('slug') ?: static::adjustRouteCase(preg_replace(PAGE_ORDER_PREFIX_REGEX, '', $this->folder()));
    }

    public function order($var = null)
    {
        if (null !== $var) {
            throw new \RuntimeException('Not Implemented');
        }

        preg_match(PAGE_ORDER_PREFIX_REGEX, $this->folder(), $order);

        return $order[0] ?? false;
    }

    public function summary($size = null, $textOnly = false)
    {
        return $this->processSummary($size, $textOnly);
    }

    public function content($var = null)
    {
        if (null !== $var) {
            $this->setProperty('content', $var);
        }

        return $this->getProperty('content');
    }

    public function visible($var = null)
    {
        if (null !== $var) {
            $this->setProperty('visible', $var);
        }

        return $this->published() && $this->getProperty('visible') ?? preg_match(PAGE_ORDER_PREFIX_REGEX, $this->folder());
    }

    public function published($var = null)
    {
        if (null !== $var) {
            $this->setProperty('published', $var);
        }

        return (bool)$this->getProperty('published', true);
    }

    public function publishDate($var = null)
    {
        if (null !== $var) {
            $this->setProperty('publish_date', $var);
        }

        return Utils::date2timestamp($this->getProperty('publish_date'), $this->getProperty('dateformat'));
    }

    public function unpublishDate($var = null)
    {
        if (null !== $var) {
            $this->setProperty('unpublish_date', $var);
        }

        return Utils::date2timestamp($this->getProperty('unpublish_date'), $this->getProperty('dateformat'));
    }

    public function process($var = null)
    {
        if (null !== $var) {
            $this->setProperty('process', $var);
        }

        return (array)($this->getProperty('process') ?? Grav::instance()['config']->get('system.pages.process'));
    }

    public function media($var = null)
    {
        if (null !== $var) {
            $this->setProperty('media', $var);
        }

        return $this->getProperty('media');
    }

    public function id($var = null)
    {
        if (null !== $var) {
            throw new \RuntimeException('Not Implemented');
        }

        return $this->modified() . md5( 'flex-' . $this->getType() . '-' . $this->getKey());
    }

    public function modified($var = null)
    {
        if (null !== $var) {
            $this->setProperty('modified', $var);
        }

        // TODO: Initialize in the blueprints.
        return $this->getProperty('modified');
    }

    public function lastModified($var = null)
    {
        if (null !== $var) {
            $this->setProperty('last_modified', $var);
        }

        return (bool)($this->getProperty('last_modified') ?? Grav::instance()['config']->get('system.pages.last_modified'));
    }

    public function date($var = null)
    {
        if (null !== $var) {
            $this->setProperty('date', $var);
        }

        return Utils::date2timestamp($this->getProperty('date'), $this->getProperty('dateformat')) ?: $this->modified();
    }

    public function dateformat($var = null)
    {
        if (null !== $var) {
            $this->setProperty('dateformat', $var);
        }

        return $this->getProperty('dateformat');
    }

    public function taxonomy($var = null)
    {
        if (null !== $var) {
            $this->setProperty('taxonomy', $var);
        }

        return $this->getProperty('taxonomy', []);
    }

    public function shouldProcess($process)
    {
        return (bool)$this->getNestedProperty("process.{$process}", false);
    }

    public function isPage()
    {
        return true;
    }

    public function isDir()
    {
        return false;
    }

    /**
     * Returns the clean path to the page file
     * @deprecated Needed in admin for Page Media.
     */
    public function relativePagePath()
    {
        return $this->getMediaFolder();
    }

    /*
    public function getNewStorageKey()
    {
        $order = $this->getElement('order');
        $folder = $this->getElement('folder') ?? $this->value('folder');

        $key = $order ? sprintf('%2d.%s', $order, $folder) : $folder;

        if ($key !== $this->getStorageKey()) {
            return $key;
        }

        return null;
    }
    */

    // Overrides for header properties.

    /**
     * @param string $property
     * @return bool
     */
    public function hasProperty($property)
    {
        if (isset(static::$headerProperties[$property])) {
            $property = "header.{$property}";
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
     */
    static protected function adjustRouteCase($route)
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
        $folder = !empty($elements['folder']) ? trim($elements['folder']) : '';

        if ($folder) {
            $order = !empty($elements['order']) ? (int)$elements['order'] : null;
            $elements['storage_key'] = $order ? sprintf('%2d.%s', $order, $folder) : $folder;
        }

        unset($elements['order'], $elements['folder']);

        parent::filterElements($elements);
    }
}
