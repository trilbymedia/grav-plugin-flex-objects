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
                return $this->getProperty('parent_route');
            case 'full_route':
                return $this->hasKey() ? '/' . $this->getKey() : '';
            case 'full_order':
                return $this->full_order();
        }

        return parent::getFormValue($name, $default, $separator);
    }

    public function parent(PageInterface $var = null)
    {
        if (null !== $var) {
            throw new \RuntimeException('Not Implemented');
        }

        /** @var Pages $pages */
        $pages = Grav::instance()['pages'];

        return $this->parent_route ? $pages->find($this->parent_route) : $pages->root();
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

    public function getLevelListing(array $options): array
    {
        /** @var Admin $admin */
        $admin = Grav::instance()['admin'] ?? null;
        /** @var Pages $pages */
        $pages = $admin ? $admin::enablePages() : Grav::instance()['pages'];
        $page_instances = $pages->instances();

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
        $initial = $options['initial'] ?? null;

        $status = 'error';
        $msg = null;
        $response = [];
        $children = null;
        $sub_route = null;
        $extra = null;
        $root = false;

        // Handle leaf_route
        if ($leaf_route && $route !== $leaf_route) {
            $nodes = explode('/', $leaf_route);
            $sub_route =  '/' . implode('/', array_slice($nodes, 1, $options['level']++ ));
            $options['route'] = $sub_route;

            [$status, $msg, $children, $extra] = $this->getLevelListing($options);
        }

        /** @var GravPageCollection|GravPageIndex $collection */
        $collection = $this->getFlexDirectory()->getIndex();

        // Handle no route, assume page tree root
        if (!$route) {
            $page = $collection->getRoot();
            $root = true;
        } else {
            $page = $collection->get(trim($route, '/'));
        }

        $path = $page ? $page->path() : null;

        $settings = $this->getBlueprint()->schema()->getProperty($options['field']);

        $filters = array_merge([], $filters, $settings['filters'] ?? []);
        $filter_type = $filters['type'] ?? $filter_type;

        if ($path) {
            /** @var \SplFileInfo $fileInfo */
            $status = 'success';
            $msg = 'PLUGIN_ADMIN.PAGE_ROUTE_FOUND';
            foreach (new \DirectoryIterator($path) as $fileInfo) {
                $fileName = $fileInfo->getFilename();
                $filePath = str_replace('\\', '/', $fileInfo->getPathname());

                if (($fileInfo->isDot() && $fileName !== '.' && $initial) || (Utils::startsWith($fileName, '.') && strlen($fileName) > 1)) {
                    continue;
                }

                if ($fileInfo->isDot()) {
                    if ($root) {
                        $payload = [
                            'name' => '<root>',
                            'value' => '',
                            'item-key' => '',
                            'filename' => '.',
                            'extension' => '',
                            'type' => 'root',
                            'modified' => $fileInfo->getMTime(),
                            'size' => 0
                        ];
                    } else {
                        continue;
                    }
                } else {
                    $file_page = $page_instances[$filePath] ?? null;
                    $file_path = Utils::replaceFirstOccurrence(GRAV_ROOT, '', $filePath);
                    $type = $fileInfo->getType();

                    $payload = [
                        'name' => $file_page ? $file_page->title() : $fileName,
                        'value' => $file_page ? $file_page->rawRoute() : $file_path,
                        'item-key' => basename($file_page ? $file_page->route() : $file_path),
                        'filename' => $fileName,
                        'extension' => $type === 'dir' ? '' : $fileInfo->getExtension(),
                        'type' => $type,
                        'modified' => $fileInfo->getMTime(),
                        'size' => $fileInfo->getSize(),
                        'symlink' => false
                    ];
                }

                // Fix for symlink
                if ($payload['type'] === 'link') {
                    $payload['symlink'] = true;
                    $physical_path = $fileInfo->getRealPath();
                    $payload['type'] = is_dir($physical_path) ? 'dir' : 'file';
                }

                // filter types
                if ($filters['type']) {
                    if (!in_array($payload['type'], $filter_type, true)) {
                        continue;
                    }
                }

                // Simple filter for name or extension
                if (($filters['name'] && Utils::contains($payload['basename'], $filters['name'])) ||
                    ($filters['extension'] && Utils::contains($payload['extension'], $filters['extension']))) {
                    continue;
                }

                // Add children if any
                if ($filePath === $extra && is_array($children)) {
                    $payload['children'] = array_values($children);
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
        // Deal with ordering=1 and order=page1,page2,page3.
        $ordering = (bool)($elements['ordering'] ?? false);
        if ($ordering) {
            $list = !empty($elements['order']) ? explode(',', $elements['order']) : [];
            $order = array_search($this->getProperty('folder'), $list, true);
            if ($order !== false) {
                $order++;
            } else {
                $order = $this->getProperty('order');
            }

            $elements['order'] = $order;
        } else {
            unset($elements['order']);
        }
        unset($elements['ordering']);

        // Change storage location if needed.
        if (array_key_exists('route', $elements) && isset($elements['folder'], $elements['name'])) {
            $route = $elements['parent_route'] = $elements['route'];
            unset($elements['route']);

            $parts = [];
            $key = $this->getKey();
            $parentKey = trim($route, '/');

            // Figure out storage path to the new route.
            if ($parentKey !== '') {
                // Make sure page isn't being moved under itself.
                if ($key === $parentKey || strpos($parentKey, $key . '/') === 0) {
                    throw new \RuntimeException(sprintf('Page %s cannot be moved to %s', '/' . $key, $route));
                }

                $parent = $this->getFlexDirectory()->getObject($parentKey);
                if (!$parent) {
                    // Page cannot be moved to non-existing location.
                    throw new \RuntimeException(sprintf('Page %s cannot be moved to non-existing path %s', '/' . $key, $route));
                }

                $parts[] = $parent->getStorageKey();
            }

            // Get the folder name.
            $folder = !empty($elements['folder']) ? trim($elements['folder']) : $this->getProperty('folder');
            $order = $elements['order'] ?? false;
            $parts[] = $order ? sprintf('%02d.%s', $order, $folder) : $folder;

            // Finally update the storage key.
            $elements['storage_key'] = implode('/', $parts);
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
