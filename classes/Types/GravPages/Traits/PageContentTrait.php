<?php

namespace Grav\Plugin\FlexObjects\Types\GravPages\Traits;

use Grav\Common\Grav;
use Grav\Common\Page\Media;
use Grav\Common\Utils;

class_exists('Grav\\Common\\Page\\Page', true);

/**
 * Implements Grav Page content and header manipulation methods.
 */
trait PageContentTrait
{
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
     * @inheritdoc
     */
    public function header($var = null)
    {
        if (null !== $var) {
            $this->setProperty('header', $var);
        }

        return (object)$this->getProperty('header', []);
    }

    /**
     * @inheritdoc
     */
    public function summary($size = null, $textOnly = false)
    {
        return $this->processSummary($size, $textOnly);
    }

    /**
     * @inheritdoc
     */
    public function content($var = null)
    {
        if (null !== $var) {
            $this->setProperty('content', $var);
        }

        return $this->getProperty('content');
    }

    /**
     * @inheritdoc
     */
    public function getRawContent()
    {
        return $this->getArrayProperty('markdown');
    }

    /**
     * @inheritdoc
     */
    public function setRawContent($content)
    {
        $this->setArrayProperty('markdown', $content ?? '');
    }

    /**
     * @inheritdoc
     */
    public function rawMarkdown($var = null)
    {
        if ($var !== null) {
            $this->setRawContent($var);
        }

        return $this->getRawContent();
    }

    /**
     * @inheritdoc
     *
     * Implement by calling:
     *
     * $test = new \stdClass();
     * $value = $this->pageContentValue($name, $test);
     * if ($value !== $test) {
     *     return $value;
     * }
     * return parent::value($name, $default);
     */
    abstract public function value($name, $default = null, $separator = null);

    /**
     * Gets and sets the associated media as found in the page folder.
     *
     * @param  Media $var Representation of associated media.
     *
     * @return Media      Representation of associated media.
     */
    public function media($var = null)
    {
        if (null !== $var) {
            $this->setProperty('media', $var);
        }

        return $this->getProperty('media');
    }

    /**
     * @inheritdoc
     */
    public function title($var = null)
    {
        if (null !== $var) {
            $this->setProperty('title', $var);
        }

        return $this->getProperty('title') ?: ucfirst($this->slug());
    }

    /**
     * @inheritdoc
     */
    public function menu($var = null)
    {
        if (null !== $var) {
            $this->setProperty('menu', $var);
        }

        return $this->getProperty('menu') ?: $this->title();
    }

    /**
     * @inheritdoc
     */
    public function visible($var = null)
    {
        if (null !== $var) {
            $this->setProperty('visible', $var);
        }

        return $this->published() && ($this->getProperty('visible') ?? preg_match(PAGE_ORDER_PREFIX_REGEX, $this->folder()));
    }

    /**
     * @inheritdoc
     */
    public function published($var = null)
    {
        if (null !== $var) {
            $this->setProperty('published', $var);
        }

        return (bool)$this->getProperty('published', true) === true;
    }

    /**
     * @inheritdoc
     */
    public function publishDate($var = null)
    {
        if (null !== $var) {
            $this->setProperty('publish_date', $var);
        }

        return Utils::date2timestamp($this->getProperty('publish_date'), $this->getProperty('dateformat'));
    }

    /**
     * @inheritdoc
     */
    public function unpublishDate($var = null)
    {
        if (null !== $var) {
            $this->setProperty('unpublish_date', $var);
        }

        return Utils::date2timestamp($this->getProperty('unpublish_date'), $this->getProperty('dateformat'));
    }

    /**
     * @inheritdoc
     */
    public function process($var = null)
    {
        if (null !== $var) {
            $this->setProperty('process', $var);
        }

        return (array)($this->getProperty('process') ?? Grav::instance()['config']->get('system.pages.process'));
    }

    /**
     * @inheritdoc
     */
    public function slug($var = null)
    {
        if (null !== $var) {
            $this->setProperty('slug', $var);
        }

        return $this->getProperty('slug') ?: static::adjustRouteCase(preg_replace(PAGE_ORDER_PREFIX_REGEX, '', $this->folder()));
    }

    /**
     * @inheritdoc
     */
    public function order($var = null)
    {
        if (null !== $var) {
            // TODO:
            throw new \RuntimeException('Not Implemented');
        }

        preg_match(PAGE_ORDER_PREFIX_REGEX, $this->folder(), $order);

        return $order[0] ?? false;
    }

    /**
     * @inheritdoc
     */
    public function id($var = null)
    {
        if (null !== $var) {
            // TODO:
            throw new \RuntimeException('Not Implemented');
        }

        return $this->modified() . md5( 'flex-' . $this->getFlexType() . '-' . $this->getKey());
    }

    /**
     * @inheritdoc
     */
    public function modified($var = null)
    {
        if (null !== $var) {
            $this->setProperty('modified', $var);
        }

        // TODO: Initialize in the blueprints.
        return $this->getProperty('modified');
    }

    /**
     * @inheritdoc
     */
    public function lastModified($var = null)
    {
        if (null !== $var) {
            $this->setProperty('last_modified', $var);
        }

        return (bool)($this->getProperty('last_modified') ?? Grav::instance()['config']->get('system.pages.last_modified'));
    }

    /**
     * @inheritdoc
     */
    public function date($var = null)
    {
        if (null !== $var) {
            $this->setProperty('date', $var);
        }

        return Utils::date2timestamp($this->getProperty('date'), $this->getProperty('dateformat')) ?: $this->modified();
    }

    /**
     * @inheritdoc
     */
    public function dateformat($var = null)
    {
        if (null !== $var) {
            $this->setProperty('dateformat', $var);
        }

        return $this->getProperty('dateformat');
    }

    /**
     * @inheritdoc
     */
    public function taxonomy($var = null)
    {
        if (null !== $var) {
            $this->setProperty('taxonomy', $var);
        }

        return $this->getProperty('taxonomy', []);
    }

    /**
     * @inheritdoc
     */
    public function shouldProcess($process)
    {
        $test = $this->process();

        return !empty($test[$process]);
    }

    /**
     * @inheritdoc
     */
    public function isPage()
    {
        // TODO: add support
        return true;
    }

    /**
     * @inheritdoc
     */
    public function isDir()
    {
        // TODO: add support
        return false;
    }

    /**
     * @inheritdoc
     */
    abstract public function exists();

    abstract public function getProperty($property, $default = null);
    abstract public function setProperty($property, $value);
    abstract public function &getArrayProperty($property, $default = null, $doCreate = false);
    abstract public function setArrayProperty($property, $value);

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    protected function pageContentValue($name, $default = null)
    {
        switch ($name) {
            case 'frontmatter':
                return $this->getArrayProperty('frontmatter');
            case 'content':
                return $this->getArrayProperty('markdown');
            case 'order':
                $order = $this->order();
                return $order ? (int)$order : '';
            case 'menu':
                return $this->menu();
            case 'ordering':
                return (bool)$this->order();
            case 'folder':
                return preg_replace(PAGE_ORDER_PREFIX_REGEX, '', $this->folder());
            case 'slug':
                return $this->slug();
            case 'published':
                return $this->published();
            case 'visible':
                return $this->visible();
            case 'media':
                return $this->media()->all();
            case 'media.file':
                return $this->media()->files();
            case 'media.video':
                return $this->media()->videos();
            case 'media.image':
                return $this->media()->images();
            case 'media.audio':
                return $this->media()->audios();
        }

        return $default;
    }
}
