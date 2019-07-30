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

/**
 * Class GravPageObject
 * @package Grav\Plugin\FlexObjects\Types\GravPages
 *
 * @property string $name
 * @property string $folder
 * @property string $route
 * @property string $template
 */
class GravPageObject extends FlexPageObject
{
    /** @var string Route to the page excluding order and folder, eg: '/blog/2019' */
    protected $route;

    /** @var string Folder of the page, eg: 'article-title' */
    protected $folder;

    /** @var string|false Numeric order of the page, eg. 3 */
    protected $order;

    /** @var string Template name, eg: 'article' */
    protected $template;

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
     * @return bool
     */
    public function isVisible()
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
                // FIXME: remove filename from key.
                return $this->getProperty('route');
            case 'full_route':
                return '/' . $this->getKey();
            case 'full_order':
                return $this->full_order();
        }

        return parent::getFormValue($name, $default, $separator);
    }

    public function folder($var = null)
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

    public function path($var = null)
    {
        if (null !== $var) {
            throw new \RuntimeException('Not Implemented');
        }

        $key = $this->hasKey() ? '/' . $this->getStorageKey() : '';
        $parts = explode('/', '/' . $key);
        array_pop($parts);
        array_pop($parts);

        return '/' . implode('/', $parts);
    }

    protected function location()
    {
        $key = $this->hasKey() ? '/' . $this->getStorageKey() : '';
        $parts = explode('/', '/' . $key);
        array_pop($parts);

        return '/' . implode('/', $parts);
    }

    public function parent(PageInterface $var = null)
    {
        $parentKey = \dirname($this->getKey());
        if ($parentKey === '.') {
            // FIXME: needs a proper solution

            /** @var Pages $pages */
            $pages = Grav::instance()['pages'];

            return $pages->root();
        }

        return $this->getFlexDirectory()->getObject($parentKey);
    }

    /**
     * @return \Grav\Common\Page\Collection
     */
    public function children()
    {
        // FIXME: needs a proper solution

         /** @var Pages $pages */
        $pages = Grav::instance()['pages'];

        return $pages->children($this->path());
    }

    /**
     * Gets and sets the name field.  If no name field is set, it will return 'default.md'.
     *
     * @param  string $var The name of this page.
     *
     * @return string      The name of this page.
     */
    public function name($var = null)
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
    public function template($var = null)
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
     * @return null|string
     */
    public function extension($var = null)
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
    public function modular($var = null)
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
    public function modularTwig($var = null)
    {
        if ($var !== null) {
            $this->setProperty('modular_twig', (bool)$var);
            if ($var) {
                $this->visible(false);
            }
        }

        return $this->getProperty('modular_twig');
    }

    public function full_order()
    {
        $path = $this->path();

        return preg_replace(GravPageIndex::ORDER_LIST_REGEX, '\\1', $path . '/' . $this->folder());
    }

    /**
     * @param string $name
     * @return Blueprint
     */
    public function getBlueprint(string $name = '')
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

    /**
     * @param array $elements
     */
    protected function filterElements(array &$elements): void
    {
        // FIXME: need better logic here.
        if (isset($elements['route'], $elements['folder'], $elements['name'])) {
            $parts = [];
            $route = $elements['route'];

            // Make sure page isn't being moved under itself.
            if (strpos($route, '/' . $this->getKey() . '/') === 0) {
                throw new \RuntimeException(sprintf('Page %s cannot be moved to %s', '/' . $this->getKey(), $route));
            }

            // Figure out storage path to the new route.
            if ($route !== '/') {
                $parentKey = trim($route, '/');
                $parent = $this->getKey() !== $parentKey ? $this->getFlexDirectory()->getObject($parentKey) : $this;
                if ($parent) {
                    $path =  trim($parent->getKey() === $this->getKey() ? $this->path() : $parent->location(), '/');
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

            // Get the template name.
            $parts[] = isset($elements['name']) ? $elements['name'] . '.md' : $this->name();

            // Finally update the storage key.
            $elements['storage_key'] = implode('/', $parts);
        }

        unset($elements['order'], $elements['folder']);

        parent::filterElements($elements);
    }

    /**
     * @param string $value
     * @return string
     */
    protected function offsetLoad_route($value)
    {
        return $value ?? $this->hasKey() ? \dirname('/' . $this->getKey()) : '/';
    }

    /**
     * @param string $value
     * @return string
     */
    protected function offsetLoad_folder($value)
    {
        return $value ?? $this->hasKey() ? \basename($this->getKey()) : '';
    }

    protected function offsetLoad_order($value)
    {
        if (null === $value) {
            preg_match(PAGE_ORDER_PREFIX_REGEX, \basename(\dirname($this->getStorageKey())), $order);

            if (isset($order[0])) {
                $value = (int)$order[0];
            } else {
                $value = false;
            }
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
    protected function offsetLoad_name($value)
    {
        return $value ?? $this->getStorage()['storage_file'] ?? 'folder.md';
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
        $pattern = '%(' . preg_quote($language, '%') . ')?\.md$%';

        return preg_replace($pattern, '', $value);
    }
}
