<?php

namespace Grav\Plugin\FlexObjects\Types\GravPages;

use Grav\Common\Data\Blueprint;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Pages;
use Grav\Framework\Route\Route;
use Grav\Framework\Route\RouteFactory;
use Grav\Plugin\Admin\Admin;
use Grav\Plugin\FlexObjects\Types\FlexPages\FlexPageObject;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class GravPageObject
 * @package Grav\Plugin\FlexObjects\Types\GravPages
 *
 * @property string $name
 * @property string $route
 * @property string $folder
 * @property int|false $order
 * @property string $template
 * @property string $language
 */
class GravPageObject extends FlexPageObject
{
    const PAGE_ORDER_REGEX = '/^(\d+)\.(.*)$/u';

    /** @var string Route to the page excluding the page itself, eg: '/blog/2019' */
    protected $parent_route;

    /** @var string Folder of the page, eg: 'article-title' */
    protected $folder;

    /** @var string|false Numeric order of the page, eg. 3 */
    protected $order;

    /** @var string Template name, eg: 'article' */
    protected $template;

    /** @var string Language code, eg: 'en' */
    protected $language;

    /** @var string File format, eg. 'md' */
    protected $format;

    /** @var string Filename, eg: 'article.md' */
    protected $name;

    /**
     * @return array
     */
    public static function getCachedMethods(): array
    {
        return [
            'isVisible' => true,
            'path' => true,
            'full_order' => true
        ] + parent::getCachedMethods();
    }

    /**
     * @param string|array $query
     * @return Route
     */
    public function getRoute($query = []): Route
    {
        $route = RouteFactory::createFromString($this->route());
        if (\is_array($query)) {
            foreach ($query as $key => $value) {
                $route = $route->withQueryParam($key, $value);
            }
        } else {
            $route = $route->withAddedPath($query);
        }

        return $route;
    }

    /**
     * Gets the route for the page based on the route headers if available, else from
     * the parents route and the current Page's slug.
     *
     * @param  string $var Set new default route.
     *
     * @return string  The route for the Page.
     */
    public function route($var = null): string
    {
        if (null !== $var) {
            if ($var !== '/' && $var !== Grav::instance()['config']->get('system.home.alias')) {
                throw new \RuntimeException(__METHOD__ . '(\'' . $var . '\'): Not Implemented');
            }
        }

        if ($this->home()) {
            return '/';
        }

        // TODO: implement rest of the routing:
        return $this->rawRoute();
    }

    /**
     * @return bool
     */
    public function isVisible(): bool
    {
        return $this->isPublished() && $this->visible();
    }

    /**
     * @inheritdoc PageInterface
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
                // TODO: this should not be template!
                return $this->getProperty('template');
            case 'folder':
                return $this->getProperty('folder');
            case 'route':
                return $this->getProperty('route');
            case 'full_route':
                return $this->hasKey() ? '/' . $this->getKey() : '';
            case 'full_order':
                return $this->full_order();
        }

        return parent::getFormValue($name, $default, $separator);
    }

    public function folder($var = null): string
    {
        if (null !== $var) {
            $this->setProperty('folder', $var);
        }

        return $this->getProperty('folder');
    }

    public function order($var = null)
    {
        if (null !== $var) {
            $this->setProperty('order', $var);
        }

        $var = $this->getProperty('order');

        return $var !== false ? sprintf('%02d.', $var) : false;
    }

    public function path($var = null): string
    {
        if (null !== $var) {
            throw new \RuntimeException('Not Implemented');
        }

        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        return $locator($this->getFlexDirectory()->getStorageFolder()) . $this->location();
    }

    protected function location(): string
    {
        return '/' . ($this->hasKey() ? $this->getStorageKey() : '');
    }

    public function parent(PageInterface $var = null)
    {
        if (null !== $var) {
            throw new \RuntimeException('Not Implemented');
        }

        /** @var Pages $pages */
        $pages = Grav::instance()['pages'];

        return $pages->get($this->parent_route);
    }

    /**
     * @return \Grav\Common\Page\Collection
     */
    public function children()
    {
        /** @var Pages $pages */
        $pages = Grav::instance()['pages'];

        return $pages->children($this->path());
    }

    /**
     * Gets and sets the name field.  If no name field is set, it will return 'default.md'.
     *
     * @param  string $var The name of this page.
     * @return string      The name of this page.
     */
    public function name($var = null): string
    {
        if ($var !== null) {
            $this->setProperty('name', $var);
        }

        return $this->getProperty('name');
    }

    /**
     * Gets and sets the template field. This is used to find the correct Twig template file to render.
     * If no field is set, it will return the name without the .md extension
     *
     * @param  string $var the template name
     *
     * @return string      the template name
     */
    public function template($var = null): string
    {
        if ($var !== null) {
            $this->setProperty('template', $var);
        }

        return $this->getProperty('template');
    }

    /**
     * Gets and sets the extension field.
     *
     * @param null $var
     *
     * @return string
     */
    public function extension($var = null): string
    {
        if ($var !== null) {
            throw new \RuntimeException('Not Implemented');
        }

        return '.' . pathinfo($this->name(), PATHINFO_EXTENSION);
    }

    /**
     * Gets and sets the modular var that helps identify this page is a modular child
     *
     * @param  bool $var true if modular_twig
     *
     * @return bool      true if modular_twig
     */
    public function modular($var = null): bool
    {
        return $this->modularTwig($var);
    }

    /**
     * Gets and sets the modular_twig var that helps identify this page as a modular child page that will need
     * twig processing handled differently from a regular page.
     *
     * @param  bool $var true if modular_twig
     *
     * @return bool      true if modular_twig
     */
    public function modularTwig($var = null): bool
    {
        if ($var !== null) {
            $this->setProperty('modular_twig', (bool)$var);
            if ($var) {
                $this->visible(false);
            }
        }

        return (bool)$this->getProperty('modular_twig');
    }

    /**
     * @inheritdoc
     */
    public function isPage(): bool
    {
        return $this->getProperty('template') !== 'folder';
    }

    public function full_order(): string
    {
        $path = $this->path();

        return preg_replace(GravPageIndex::ORDER_LIST_REGEX, '\\1', $path . '/' . $this->folder());
    }

    /**
     * @param string $name
     * @return Blueprint
     */
    public function getBlueprint(string $name = ''): Blueprint
    {
        try {
            // Make sure that pages has been initialized.
            Pages::getTypes();

            if ($name === 'raw') {
                // Admin RAW mode.
                /** @var Admin $admin */
                $admin = Grav::instance()['admin'];
                $template = $this->modular() ? 'modular_raw' : 'raw';

                return $admin->blueprints("admin/pages/{$template}");
            }

            $template = $this->getProperty('template') . ($name ? '.' . $name : '');

            return $this->getFlexDirectory()->getBlueprint($template, 'blueprints://pages');
        } catch (\RuntimeException $e) {
            $template = 'default' . ($name ? '.' . $name : '');

            return $this->getFlexDirectory()->getBlueprint($template, 'blueprints://pages');
        }
    }

    public function __debugInfo(): array
    {
        $list = parent::__debugInfo();

        return $list + [
            '_content_meta:private' => $this->getContentMeta(),
            '_content:private' => $this->getRawContent()
        ];
    }

    /**
     * @param array $elements
     */
    protected function filterElements(array &$elements): void
    {
        // FIXME: need better logic here.
        if (isset($elements['route'], $elements['folder'], $elements['name'])) {
            $parts = [];
            $route = $elements['parent_route'] = $elements['route'];
            unset($elements['route']);

            $key = $this->getKey();
            $parentKey = trim($route, '/');

            // Make sure page isn't being moved under itself.
            if (strpos($parentKey, $key . '/') === 0) {
                throw new \RuntimeException(sprintf('Page %s cannot be moved to %s', '/' . $key, $route));
            }

            // Figure out storage path to the new route.
            if ($parentKey) {
                $parent = $key !== $parentKey ? $this->getFlexDirectory()->getObject($parentKey) : $this;
                if ($parent) {
                    $path = trim($parent->getKey() === $key ? $this->path() : $parent->location(), '/');
                    if ($path) {
                        $parts[] = $path;
                    }
                } else {
                    // Page cannot be moved to non-existing location.
                    throw new \RuntimeException(sprintf('Parent page %s not found', $route));
                }
            }

            // Get the folder name.
            $folder = !empty($elements['folder']) ? trim($elements['folder']) : $this->folder();
            $ordering = (bool)($elements['ordering'] ?? false);
            if ($ordering) {
                $list = !empty($elements['order']) ? explode(',', $elements['order']) : [];
                $order = array_search($folder, $list, true);
                if ($order === false) {
                    $order = (int)$this->order() - 1;
                }

                $parts[] = $ordering ? sprintf('%02d.%s', $order + 1, $folder) : $folder;
            } else {
                $parts[] = $folder;
            }

            // Finally update the storage key.
            $elements['storage_key'] = implode('/', $parts);
        }

        unset($elements['order'], $elements['folder']);

        parent::filterElements($elements);
    }

    /**
     * @param string $offset
     * @param mixed $value
     * @return mixed
     */
    protected function offsetLoad($offset, $value)
    {
        if (in_array($offset, ['parent_route', 'folder', 'order', 'name', 'format', 'language'])) {
            return $this->{$offset} ?? $value ?? $this->extractStorageInformation() ?? $this->{$offset};
        }

        return parent::offsetLoad($offset, $value);
    }

    /**
     * @param string $value
     * @return string
     */
    protected function offsetLoad_template($value): string
    {
        $value = $value ?? $this->getNestedProperty('header.template');
        if (!$value) {
            $value = $this->stripNameExtension($this->getProperty('name'));
            $value = $this->modular() ? 'modular/' . $value : $value;
        }

        return $value;
    }

    protected function offsetPrepare_order($value)
    {
        return false !== $value ? (int)$value : false;
    }

    /**
     * @param string $value
     * @return string
     */
    protected function offsetPrepare_name($value): string
    {
        // Setting name will reset page template.
        $this->unsetProperty('template');

        if ($value && !preg_match('/\.md$/', $value)) {
            // FIXME: missing language support.
            $value .= '.md';
        }

        return $value ?: 'default.md';
    }

    /**
     * @return mixed|null
     */
    protected function extractStorageInformation()
    {
        if (null === $this->parent_route || null === $this->folder) {
            $key = $this->hasKey() ? $this->getKey() : '';

            $this->parent_route = $this->parent_route ?? (($route = \dirname('/' . $key)) && $route !== '/' ? $route : '');
            $this->folder = $this->folder ?? \basename($key);
        }
        if (null === $this->order) {
            preg_match(static::PAGE_ORDER_REGEX, \basename($this->getStorageKey()), $parts);

            $this->order = $this->order ?? (isset($parts[1]) ? (int)$parts[1] : false);
        }

        $this->name = $this->name ?? $this->getStorage()['storage_file'] ?? 'default.md';
        $this->format = $this->format ?? 'md';

        // Allows us to make code more readable. :)
        return null;
    }

    /**
     * Strip filename from its extensions.
     *
     * @param string $value
     * @return string
     */
    protected function stripNameExtension(string $value): string
    {
        // Also accept name with file extension: .en.md
        $language = $this->language() ? '.' . $this->language() : '';
        $format = $this->getProperty('format');
        $pattern = '%(' . preg_quote($language, '%') . ')?\.' . preg_quote($format, '%'). '$%';

        return preg_replace($pattern, '', $value);
    }
}
