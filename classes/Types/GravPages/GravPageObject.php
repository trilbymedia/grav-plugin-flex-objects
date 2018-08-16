<?php
namespace Grav\Plugin\FlexObjects\Types\GravPages;

use Grav\Common\Grav;
use Grav\Common\Page\Pages;
use Grav\Plugin\FlexObjects\Types\FlexPages\FlexPageObject;

/**
 * Class GravPageObject
 * @package Grav\Plugin\FlexObjects\Types\GravPages
 */
class GravPageObject extends FlexPageObject
{
    const ORDER_PREFIX_REGEX = PAGE_ORDER_PREFIX_REGEX;
    const ORDER_LIST_REGEX = '/(\/\d+)\.[^\/]+/u';
    const PAGE_ROUTE_REGEX = '/\/\d+\./u';

    /**
     * @return array
     */
    public static function getCachedMethods()
    {
        return [
            'isVisible' => true,
            'path' => true,
            'full_order' => true
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
            if ($key === '') {
                continue;
            }
            if (!\is_array($timestamp)) {
                // General Storage.
                $slug = static::adjustRouteCase(preg_replace(static::ORDER_PREFIX_REGEX, '', $key));

                $list[$slug] = [$key, $timestamp];
            } else {
                // Page Storage.
                if (!empty($timestamp[2])) {
                    $first = reset($timestamp[2]) ?: [];

                    $timestamp[0] = ltrim($timestamp[0] . '/' .  reset($first), '/');
                } else {
                    // TODO: Folders do not show up yet in the list.
                    $timestamp[0] = ltrim($timestamp[0] . '/folder.md', '/');
                }
                $list[$key] = $timestamp;
            }
        }

        return $list;
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
    public function value($name, $default = null)
    {
        $test = new \stdClass();

        $value = $this->pageContentValue($name, $test);
        if ($value !== $test) {
            return $value;
        }

        switch ($name) {
            case 'name':
                // TODO: language
                $language = '';
                $name_val = str_replace($language . '.md', '', $this->name());

                return $this->modular() ? 'modular/' . $name_val : $name_val;
            case 'route':
                return \dirname('/' . $this->getKey());
            case 'full_route':
                return '/' . $this->getKey();
            case 'full_order':
                return $this->full_order();
        }

        return parent::value($name, $default);
    }

    public function folder($var = null)
    {
        if (null !== $var) {
            throw new \RuntimeException('Not Implemented');
        }

        $parts = explode('/', '/' . $this->getStorageKey());
        array_pop($parts);

        return end($parts);
    }

    public function path($var = null)
    {
        if (null !== $var) {
            throw new \RuntimeException('Not Implemented');
        }

        $parts = explode('/', '/' . $this->getStorageKey());
        array_pop($parts);
        array_pop($parts);

        return '/' . implode('/', $parts);
    }

    protected function location()
    {
        $parts = explode('/', '/' . $this->getStorageKey());
        array_pop($parts);

        return '/' . implode('/', $parts);
    }

    public function parent()
    {
        $parentKey = \dirname($this->getKey());

        return $this->getFlexDirectory()->getObject($parentKey);
    }

    /**
     * @return \Grav\Common\Page\Collection
     */
    public function children()
    {
        // TODO: may need better solution.

         /** @var Pages $pages */
        $pages = Grav::instance()['pages'];

        return $pages->children($this->path());
    }

    public function rawRoute()
    {
        return '/' . $this->getKey();
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
            throw new \RuntimeException('Not Implemented');
        }

        return basename($this->getStorageKey());
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
            throw new \RuntimeException('Not Implemented');
        }

        return ($this->modular() ? 'modular/' : '') . str_replace($this->extension(), '', $this->name());
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
            $this->modular_twig = (bool)$var;
            if ($var) {
                $this->visible(false);
                // some routable logic
                if (empty($this->header->routable)) {
                    $this->routable = false;
                }
            }
        }

        return $this->modular_twig;
    }

    public function folderExists()
    {
        // TODO: also check folder
        return $this->exists();
    }

    public function full_order()
    {
        $path = $this->path();

        return preg_replace(static::ORDER_LIST_REGEX, '\\1', $path . '/' . $this->folder());
    }

    /**
     * @return \Grav\Common\Data\Blueprint
     */
    public function getBlueprint()
    {
        $types = Pages::getTypes();
        $type = $types[$this->template()][0] ?? '';

        return $this->getFlexDirectory()->getBlueprint($type, 'blueprints://pages');
    }

    /**
     * @param array $elements
     */
    protected function filterElements(array &$elements)
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
            $order = !empty($elements['ordering']) ? (int)$elements['ordering'] : null;
            $parts[] = $order ? sprintf('%02d.%s', $order, $folder) : $folder;

            // Get the template name.
            $parts[] = isset($elements['name']) ? $elements['name'] . '.md' : $this->name();

            // Finally update the storage key.
            $elements['storage_key'] = implode('/', $parts);
        }

        unset($elements['order'], $elements['folder']);

        parent::filterElements($elements);
    }
}
