<?php

namespace Grav\Plugin\FlexObjects\Types\FlexPages\Traits;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Markdown\Parsedown;
use Grav\Common\Markdown\ParsedownExtra;
use Grav\Common\Page\Header;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Media;
use Grav\Common\Twig\Twig;
use Grav\Common\Utils;
use Grav\Framework\File\Formatter\YamlFormatter;
use RocketTheme\Toolbox\Event\Event;

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

    /** @var object */
    protected $header;

    /** @var string */
    protected $_summary;

    /** @var string */
    protected $_content;

    /**
     * Method to normalize the route.
     *
     * @param string $route
     * @return string
     * @internal
     */
    public static function normalizeRoute($route): string
    {
        $case_insensitive = Grav::instance()['config']->get('system.force_lowercase_urls');

        return $case_insensitive ? mb_strtolower($route) : (string)$route;
    }

    /**
     * @inheritdoc
     */
    public function header($var = null)
    {
        if (null !== $var) {
            $this->setProperty('header', $var);
        }

        return $this->getProperty('header');
    }

    /**
     * @inheritdoc
     */
    public function summary($size = null, $textOnly = false): string
    {
        return $this->processSummary($size, $textOnly);
    }

    /**
     * Sets the summary of the page
     *
     * @param string $summary Summary
     */
    public function setSummary($summary): void
    {
        $this->_summary = $summary;
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function content($var = null): string
    {
        if (null !== $var) {
            $this->_content = $var;
        }

        return $this->_content ?? $this->processContent($this->getRawContent());
    }

    /**
     * @inheritdoc
     */
    public function getRawContent(): string
    {
        return $this->_content ?? $this->getArrayProperty('markdown') ?? '';
    }

    /**
     * @inheritdoc
     */
    public function setRawContent($content): void
    {
        $this->_content = $content ?? '';
    }

    /**
     * @inheritdoc
     */
    public function rawMarkdown($var = null): string
    {
        if ($var !== null) {
            $this->setProperty('markdown', $var);
        }

        return $this->getProperty('markdown') ?? '';
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
    public function media($var = null): Media
    {
        if (null !== $var) {
            $this->setProperty('media', $var);
        }

        return $this->getProperty('media');
    }

    /**
     * @inheritdoc
     */
    public function title($var = null): string
    {
        if (null !== $var) {
            $this->setProperty('title', $var);
        }

        return $this->getProperty('title') ?: ucfirst($this->slug());
    }

    /**
     * @inheritdoc
     */
    public function menu($var = null): string
    {
        if (null !== $var) {
            $this->setProperty('menu', $var);
        }

        return $this->getProperty('menu') ?: $this->title();
    }

    /**
     * @inheritdoc
     */
    public function visible($var = null): bool
    {
        if (null !== $var) {
            $this->setProperty('visible', $var);
        }

        return $this->published() && ($this->getProperty('visible') ?? $this->order() !== false);
    }

    /**
     * @inheritdoc
     */
    public function published($var = null): bool
    {
        if (null !== $var) {
            $this->setProperty('published', $var);
        }

        return (bool)$this->getProperty('published', true) === true;
    }

    /**
     * @inheritdoc
     */
    public function publishDate($var = null): int
    {
        if (null !== $var) {
            $this->setProperty('publish_date', $var);
        }

        return Utils::date2timestamp($this->getProperty('publish_date'), $this->getProperty('dateformat'));
    }

    /**
     * @inheritdoc
     */
    public function unpublishDate($var = null): int
    {
        if (null !== $var) {
            $this->setProperty('unpublish_date', $var);
        }

        return Utils::date2timestamp($this->getProperty('unpublish_date'), $this->getProperty('dateformat'));
    }

    /**
     * @inheritdoc
     */
    public function process($var = null): array
    {
        if (null !== $var) {
            $this->setProperty('process', $var);
        }

        return (array)($this->getProperty('process') ?? Grav::instance()['config']->get('system.pages.process'));
    }

    /**
     * @inheritdoc
     */
    public function slug($var = null): string
    {
        if (null !== $var) {
            $this->setProperty('slug', $var);
        }

        return $this->getProperty('slug') ?: static::normalizeRoute(preg_replace(PAGE_ORDER_PREFIX_REGEX, '', $this->folder()));
    }

    /**
     * @inheritdoc
     */
    public function order($var = null)
    {
        if (null !== $var) {
            $this->setProperty('order', $var);
        }

        $var = $this->getProperty('order');
        if (null === $var) {
            preg_match(PAGE_ORDER_PREFIX_REGEX, $this->folder(), $order);

            $var = $order[0] ?? false;
        }

        return $var !== false ? sprintf('%02d.', $var) : false;
    }

    /**
     * @inheritdoc
     */
    public function id($var = null): string
    {
        if (null !== $var) {
            $this->setProperty('id', $var);
        }

        return $this->getProperty('id') ?? $this->modified() . md5( 'flex-' . $this->getFlexType() . '-' . $this->getKey());
    }

    /**
     * @inheritdoc
     */
    public function modified($var = null): int
    {
        if (null !== $var) {
            $this->setProperty('modified', $var);
        }

        // TODO: Initialize in the blueprints.
        return (int)$this->getProperty('modified');
    }

    /**
     * @inheritdoc
     */
    public function lastModified($var = null): bool
    {
        if (null !== $var) {
            $this->setProperty('last_modified', $var);
        }

        return (bool)($this->getProperty('last_modified') ?? Grav::instance()['config']->get('system.pages.last_modified'));
    }

    /**
     * @inheritdoc
     */
    public function date($var = null): int
    {
        if (null !== $var) {
            $this->setProperty('date', $var);
        }

        return Utils::date2timestamp($this->getProperty('date'), $this->getProperty('dateformat')) ?: $this->modified();
    }

    /**
     * @inheritdoc
     */
    public function dateformat($var = null): string
    {
        if (null !== $var) {
            $this->setProperty('dateformat', $var);
        }

        return $this->getProperty('dateformat') ?? '';
    }

    /**
     * @inheritdoc
     */
    public function taxonomy($var = null): array
    {
        if (null !== $var) {
            $this->setProperty('taxonomy', $var);
        }

        return $this->getProperty('taxonomy', []);
    }

    /**
     * @inheritdoc
     */
    public function shouldProcess($process): bool
    {
        $test = $this->process();

        return !empty($test[$process]);
    }

    /**
     * @inheritdoc
     */
    public function isPage(): bool
    {
        return !in_array($this->template(), ['', 'folder'], true);
    }

    /**
     * @inheritdoc
     */
    public function isDir(): bool
    {
        return !$this->isPage();
    }

    /**
     * @inheritdoc
     */
    abstract public function exists();

    abstract public function getProperty($property, $default = null);
    abstract public function setProperty($property, $value);
    abstract public function &getArrayProperty($property, $default = null, $doCreate = false);


    protected function offsetLoad_header($value)
    {
        if ($value instanceof Header) {
            return $value;
        }

        if (null === $value) {
            $value = [];
        } elseif ($value instanceof \stdClass) {
            $value = (array)$value;
        }

        return new Header($value);
    }

    protected function offsetPrepare_header($value)
    {
        return $this->offsetLoad_header($value);
    }

    protected function offsetSerialize_header(?Header $value)
    {
        return $value->toArray();
    }

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    protected function pageContentValue($name, $default = null)
    {
        switch ($name) {
            case 'frontmatter':
                $frontmatter = $this->getArrayProperty('frontmatter');
                if ($frontmatter === null) {
                    $header = $this->prepareStorage()['header'] ?? null;
                    if ($header) {
                        $formatter = new YamlFormatter();
                        $frontmatter = $formatter->encode($header);
                    } else {
                        $frontmatter = '';
                    }
                }
                return $frontmatter;
            case 'content':
                return $this->getProperty('markdown');
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


    /**
     * @param int|null $size
     * @param bool $textOnly
     * @return string
     */
    protected function processSummary($size = null, $textOnly = false): string
    {
        $config = (array)Grav::instance()['config']->get('site.summary');
        $config_page = (array)$this->getNestedProperty('header.summary');
        if ($config_page) {
            $config = array_merge($config, $config_page);
        }

        // Return summary based on settings in site config file.
        if (empty($config['enabled'])) {
            return $this->content();
        }

        $content = $this->_summary ?? $this->content();
        if ($textOnly) {
            $content =  strip_tags($content);
        }
        $content_size = mb_strwidth($content, 'utf-8');
        $summary_size = $this->_summary !== null ? $content_size : $this->getProperty('summary_size');

        // Return calculated summary based on summary divider's position.
        $format = $config['format'] ?? '';
        if ($format === 'short' && $summary_size) {
            // Slice the string on breakpoint.
            if ($content_size > $summary_size) {
                return mb_substr($content, 0, $summary_size);
            }

            return $content;
        }

        // Return entire page content on wrong/unknown format or if format=short and summary_size=0.
        if ($format !== 'long') {
            return $content;
        }

        // If needed, get summary size from the config.
        $size = $size ?? $config['size'] ?? null;

        // Return calculated summary based on defaults.
        $size = is_numeric($size) ? (int)$size : -1;
        if ($size < 0) {
            $size = 300;
        }

        // If the size is zero or smaller than the summary limit, return the entire page content.
        if ($size === 0 || $content_size <= $size) {
            return $content;
        }

        // Only return string but not html, wrap whatever html tag you want when using.
        if ($textOnly) {
            return mb_strimwidth($content, 0, $size, '...', 'utf-8');
        }

        $summary = Utils::truncateHTML($content, $size);

        return html_entity_decode($summary);
    }

    /**
     * Gets and Sets the content based on content portion of the .md file
     *
     * @param  string $content
     * @return string
     * @throws \Exception
     */
    protected function processContent($content): string
    {
        $grav = Grav::instance();

        /** @var Config $config */
        $config = $grav['config'];

        $process_markdown = $this->shouldProcess('markdown');
        $process_twig = $this->shouldProcess('twig') || $this->modularTwig();
        $cache_enable = $this->getNestedProperty('header.cache_enable') ?? $config->get('system.cache.enabled', true);

        $twig_first = $this->getNestedProperty('header.twig_first') ?? $config->get('system.pages.twig_first', true);
        $never_cache_twig = $this->getNestedProperty('header.never_cache_twig') ?? $config->get('system.pages.never_cache_twig', false);

        $cached = null;
        if ($cache_enable) {
            $cache = $this->getCache('render');
            $key = md5($this->getCacheKey() . '-content');
            $cached = $cache->get($key);
            if ($cached && $cached['checksum'] === $this->getCacheChecksum()) {
                $this->_content = $cached['content'] ?? '';
                $this->_content_meta = $cached['content_meta'] ?? null;

                if ($process_twig && $never_cache_twig) {
                    $this->_content = $this->processTwig($this->_content);
                }
            } else {
                $cached = null;
            }
        }

        if (!$cached) {
            $markdown_options = [];
            if ($process_markdown) {
                // Build markdown options.
                $markdown_options = (array)$config->get('system.pages.markdown');
                $markdown_page_options = (array)$this->getNestedProperty('header.markdown');
                if ($markdown_page_options) {
                    $markdown_options = array_merge($markdown_options, $markdown_page_options);
                }

                // pages.markdown_extra is deprecated, but still check it...
                if (!isset($markdown_options['extra'])) {
                    $extra = $this->getNestedProperty('markdown_extra') ?? $config->get('system.pages.markdown_extra');
                    if (null !== $extra) {
                        user_error('Configuration option \'system.pages.markdown_extra\' is deprecated since Grav 1.5, use \'system.pages.markdown.extra\' instead', E_USER_DEPRECATED);

                        $markdown_options['extra'] = $extra;
                    }
                }
            }

            $this->_content = $content;
            $grav->fireEvent('onPageContentRaw', new Event(['page' => $this]));

            if ($twig_first && !$never_cache_twig) {
                if ($process_twig) {
                    $this->_content = $this->processTwig($this->_content);
                }

                if ($process_markdown) {
                    $this->_content = $this->processMarkdown($this->_content, $markdown_options);
                }

                // Content Processed but not cached yet
                $grav->fireEvent('onPageContentProcessed', new Event(['page' => $this]));

            } else {
                if ($process_markdown) {
                    $this->_content = $this->processMarkdown($this->_content, $markdown_options);
                }

                // Content Processed but not cached yet
                $grav->fireEvent('onPageContentProcessed', new Event(['page' => $this]));

                if ($cache_enable && $never_cache_twig) {
                    $this->cachePageContent();
                }

                if ($process_twig) {
                    $this->_content = $this->processTwig($this->_content);
                }
            }

            if ($cache_enable && !$never_cache_twig) {
                $this->cachePageContent();
            }
        }

//        $this->_content = $this->processMarkdown($this->_content, $markdown_options);

        // Handle summary divider
        $delimiter = $config->get('site.summary.delimiter', '===');
        $divider_pos = mb_strpos($this->_content, "<p>{$delimiter}</p>");
        if ($divider_pos !== false) {
            $this->setProperty('summary_size', $divider_pos);
            $this->_content = str_replace("<p>{$delimiter}</p>", '', $this->_content);
        }

        // Fire event when Page::content() is called
        $grav->fireEvent('onPageContent', new Event(['page' => $this]));

        return $this->_content;
    }

    /**
     * Process the Twig page content.
     *
     * @param  string $content
     * @return string
     */
    protected function processTwig($content): string
    {
        /** @var Twig $twig */
        $twig = Grav::instance()['twig'];

        /** @var PageInterface $this */
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
    protected function processMarkdown($content, array $options = []): string
    {
        // Initialize the preferred variant of markdown parser.
        if (isset($defaults['extra'])) {
            $parsedown = new ParsedownExtra($this, $options);
        } else {
            $parsedown = new Parsedown($this, $options);
        }

        return $parsedown->text($content);
    }
}
