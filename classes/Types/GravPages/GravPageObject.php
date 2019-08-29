<?php

namespace Grav\Plugin\FlexObjects\Types\GravPages;

use Grav\Common\Data\Blueprint;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Pages;
use Grav\Common\Utils;
use Grav\Framework\Route\Route;
use Grav\Framework\Route\RouteFactory;
use Grav\Plugin\Admin\Admin;
use Grav\Plugin\FlexObjects\Types\FlexPages\FlexPageObject;
use Grav\Plugin\FlexObjects\Types\GravPages\Traits\PageLegacyTrait;
use Grav\Plugin\FlexObjects\Types\GravPages\Traits\PageRoutableTrait;

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
    use PageLegacyTrait;
    use PageRoutableTrait;

    /** @var string Language code, eg: 'en' */
    protected $language;

    /** @var string File format, eg. 'md' */
    protected $format;

    /**
     * @return array
     */
    public static function getCachedMethods(): array
    {
        return [
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
            case 'route':
                $key = dirname($this->hasKey() ? '/' . $this->getKey() : '/');
                return $key !== '/' ? $key : '';
            case 'full_route':
                return $this->hasKey() ? '/' . $this->getKey() : '';
            case 'full_order':
                return $this->full_order();
        }

        return parent::getFormValue($name, $default, $separator);
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

            $blueprint = $this->getFlexDirectory()->getBlueprint($template, 'blueprints://pages');
        } catch (\RuntimeException $e) {
            $template = 'default' . ($name ? '.' . $name : '');

            $blueprint = $this->getFlexDirectory()->getBlueprint($template, 'blueprints://pages');
        }

        return $blueprint;
    }

    public function getLevelListing(array $options): array
    {
        $default_filters = [
            'type'=> ['root', 'dir'],
            'name' => null,
            'extension' => null
        ];

        $filters = $default_filters + json_decode($options['filters'] ?? '{}', true);
        $filter_type = (array)$filters['type'];

        $route = $options['route'] ?? null;
        $leaf_route = $options['leaf_route'] ?? null;
        $sortby = $options['sortby'] ?? 'filename';
        $order = $options['order'] ?? SORT_ASC;

        $status = 'error';
        $msg = null;
        $response = [];
        $children = null;
        $sub_route = null;
        $extra = null;

        // Handle leaf_route
        if ($leaf_route && $route !== $leaf_route) {
            $nodes = explode('/', $leaf_route);
            $sub_route =  '/' . implode('/', array_slice($nodes, 1, $options['level']++));
            $options['route'] = $sub_route;

            [$status,,$leaf,$extra] = $this->getLevelListing($options);
        }

        /** @var GravPageCollection|GravPageIndex $collection */
        $collection = $this->getFlexDirectory()->getIndex();

        // Handle no route, assume page tree root
        if (!$route) {
            $page = $collection->getRoot();
        } else {
            $page = $collection->get(trim($route, '/'));
        }
        $path = $page ? $page->path() : null;

        $settings = $this->getBlueprint()->schema()->getProperty($options['field']);

        $filters = array_merge([], $filters, $settings['filters'] ?? []);
        $filter_type = $filters['type'] ?? $filter_type;

        if ($page) {
            if ($page->root() && (!$filters['type'] || in_array('root', $filter_type, true))) {
                $response[] = [
                    'name' => '<root>',
                    'value' => '/',
                    'item-key' => '',
                    'filename' => '.',
                    'extension' => '',
                    'type' => 'root',
                    'modified' => $page->modified(),
                    'size' => 0,
                    'symlink' => false
                ];
            }

            $status = 'success';
            $msg = 'PLUGIN_ADMIN.PAGE_ROUTE_FOUND';

            $children = $page->children();

            /** @var PageInterface $child */
            foreach ($children as $child) {
                $payload = [
                    'name' => $child->title(),
                    'value' => $child->rawRoute(),
                    'item-key' => basename($child->rawRoute()),
                    'filename' => $child->folder(),
                    'extension' => $child->extension(),
                    'type' => 'dir',
                    'modified' => $child->modified(),
                    'size' => count($child->children()),
                    'symlink' => false
                ];

                // filter types
                if ($filter_type && !in_array($payload['type'], $filter_type, true)) {
                    continue;
                }

                // Simple filter for name or extension
                if (($filters['name'] && Utils::contains($payload['basename'], $filters['name']))
                    || ($filters['extension'] && Utils::contains($payload['extension'], $filters['extension']))) {
                    continue;
                }

                // Add children if any
                if ($child->path() === $extra && \is_array($leaf)) {
                    $payload['children'] = array_values($leaf);
                }

                $response[] = $payload;
            }
        } else {
            $msg = 'PLUGIN_ADMIN.PAGE_ROUTE_NOT_FOUND';
        }

        // Sorting
        $response = Utils::sortArrayByKey($response, $sortby, $order);

        $temp_array = [];
        foreach ($response as $index => $item) {
            $temp_array[$item['type']][$index] = $item;
        }

        $sorted = Utils::sortArrayByArray($temp_array, $filter_type);
        $response = Utils::arrayFlatten($sorted);

        return [$status, $msg ?? 'PLUGIN_ADMIN.NO_ROUTE_PROVIDED', $response, $path];
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
     * @param bool $extended
     */
    protected function filterElements(array &$elements, bool $extended = false): void
    {
        // Deal with ordering=bool and order=page1,page2,page3.
        if (array_key_exists('ordering', $elements) && array_key_exists('order', $elements)) {
            $ordering = (bool)($elements['ordering'] ?? false);
            $slug = preg_replace(PAGE_ORDER_PREFIX_REGEX, '', $this->getProperty('folder'));
            $list = !empty($elements['order']) ? explode(',', $elements['order']) : [];
            if ($ordering) {
                $order = array_search($slug, $list, true);
                if ($order !== false) {
                    $order++;
                } else {
                    $order = $this->getProperty('order') ?: 1;
                }
            } else {
                $order = false;
            }

            $this->_reorder = $list;
            $elements['order'] = $order;
        }

        // Change storage location if needed.
        if (array_key_exists('route', $elements) && isset($elements['folder'], $elements['name'])) {
            $parentRoute = $elements['route'];
            $folder = trim($elements['folder']) ?: preg_replace(PAGE_ORDER_PREFIX_REGEX, '', $this->getProperty('folder'));
            $elements['template'] = $elements['name'];
            unset($elements['route']);

            $parts = [];
            $parentKey = trim($parentRoute, '/');

            // Figure out storage path to the new route.
            if ($parentKey !== '') {
                // Make sure page isn't being moved under itself.
                $key = $this->getKey();
                if ($key === $parentKey || strpos($parentKey, $key . '/') === 0) {
                    throw new \RuntimeException(sprintf('Page %s cannot be moved to %s', '/' . $key, $parentRoute));
                }

                $parent = $this->getFlexDirectory()->getObject($parentKey);
                if (!$parent) {
                    // Page cannot be moved to non-existing location.
                    throw new \RuntimeException(sprintf('Page %s cannot be moved to non-existing path %s', '/' . $key, $parentRoute));
                }

                $parts[] = $parent->getStorageKey();
            }

            // Get the folder name.
            $order = $elements['order'] ?? false;
            $folder = $order ? sprintf('%02d.%s', $order, $folder) : $folder;
            $parts[] = $folder;

            // Finally update the storage key.
            $storage_key = implode('/', $parts);
            if ($storage_key !== $this->getStorageKey()) {
                $this->setStorageKey($storage_key);
                $this->setKey($parentKey ? "{$parentKey}/$folder" : $folder);
            }
        }

        parent::filterElements($elements, true);
    }

    /**
     * @return array
     */
    public function prepareStorage(): array
    {
        $elements = [
            '__META' => $this->getStorage(),
            'storage_key' => $this->getStorageKey(),
            'folder' => $this->getProperty('folder'),
            'order' => $this->getProperty('order'),
            'format' => $this->getProperty('format'),
            'language' => $this->getProperty('language')
        ] + parent::prepareStorage();

        unset($elements['name']);

        return $elements;
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
