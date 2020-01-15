<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects;

use Grav\Common\Config\Config;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Utils;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\FlexObject;
use Grav\Framework\Flex\Interfaces\FlexCommonInterface;
use Grav\Plugin\FlexObjects\Table\DataTable;

/**
 * Class Flex
 * @package Grav\Plugin\FlexObjects
 */
class Flex extends \Grav\Framework\Flex\Flex
{
    /** @var array */
    protected $adminRoutes;
    protected $adminMenu;
    protected $managed;

    public function __construct(array $types, array $config)
    {
        parent::__construct($types, $config);

        $this->managed = array_keys($types);
    }

    /**
     * @return array
     */
    public function getAll(): array
    {
        $directories = $this->getDirectories($this->managed);
        $all = $this->getBlueprints();

        /** @var FlexDirectory $directory */
        foreach ($all as $type => $directory) {
            if (!isset($directories[$type])) {
                $directories[$type] = $directory;
            }
        }

        ksort($directories);

        return $directories;
    }

    /**
     * @return array
     */
    public function getBlueprints(): array
    {
        $params = [
            'pattern' => '|\.yaml|',
            'value' => 'Url',
            'recursive' => false,
            'folders' => false
        ];

        $all = Folder::all('blueprints://flex-objects', $params);
        foreach ($all as $url) {
            $type = basename($url, '.yaml');
            $directory = new FlexDirectory($type, $url);
            if ($directory->getConfig('hidden') !== true) {
                $directories[$type] = $directory;
            }
        }

        // Order blueprints by title.
        usort($directories, static function (FlexDirectory $a, FlexDirectory $b) {
            $at = $a->getTitle();
            $bt = $b->getTitle();
            if ($at === $bt) {
                return 0;
            }

            return $at < $bt ? -1 : 1;
        });

        return $directories;
    }

    /**
     * @param string|FlexDirectory $type
     * @param array $options
     * @return DataTable
     */
    public function getDataTable($type, array $options = [])
    {
        $directory = $type instanceof FlexDirectory ? $type : $this->getDirectory($type);
        if (!$directory) {
            throw new \RuntimeException('Not Found', 404);
        }

        $collection = $options['collection'] ?? $directory->getCollection();
        if (isset($options['filters']) && is_array($options['filters'])) {
            $collection = $collection->filterBy($options['filters']);
        }
        $table = new DataTable($options);
        $table->setCollection($collection);

        return $table;
    }

    /**
     * @param string|object|null $type
     * @param array $params
     * @param string $extension
     * @return string
     */
    public function adminRoute($type = null, array $params = [], string $extension = ''): string
    {
        if (\is_object($type)) {
            $object = $type;
            if ($object instanceof FlexCommonInterface || $object instanceof FlexDirectory) {
                $type = $type->getFlexType();
            } else {
                return '';
            }
        } else {
            $object = null;
        }

        $routes = $this->getAdminRoutes();

        $grav = Grav::instance();

        /** @var Config $config */
        $config = $grav['config'];
        if (!Utils::isAdminPlugin()) {
            $parts = [
                trim($grav['base_url'], '/'),
                trim($config->get('plugins.admin.route'), '/')
            ];
        }

        if ($type && isset($routes[$type])) {
            if (!$routes[$type]) {
                // Directory has empty route.
                return '';
            }

            // Directory has it's own menu item.
            $parts[] = trim($routes[$type], '/');
        } else {
            if (empty($routes[''])) {
                // Default route has been disabled.
                return '';
            }

            // Use default route.
            $parts[] = trim($routes[''], '/');
            if ($type) {
                $parts[] = $type;
            }
        }

        // Append object key if available.
        if ($object instanceof FlexObject) {
            if ($object->exists()) {
                $parts[] = trim($object->getKey(), '/');
            } else {
                if ($object->hasKey()) {
                    $parts[] = trim($object->getKey(), '/');
                }
                $params = ['action' => 'add'] + $params;
            }
        }

        $p = [];
        $separator = $config->get('system.param_sep');
        foreach ($params as $key => $val) {
            $p[] = $key . $separator . $val;
        }

        $parts = array_filter($parts, function ($val) { return $val !== ''; });
        $route = '/' . implode('/', $parts);
        $extension = $extension ? '.' . $extension : '';

        return $route . $extension . ($p ? '/' . implode('/', $p) : '');
    }

    /**
     * @return array
     */
    public function getAdminRoutes(): array
    {
        if (null === $this->adminRoutes) {
            $routes = [];
            foreach ($this->getAdminMenuItems() as $name => $item) {
                $routes[$name] = !isset($item['disabled']) || $item['disabled'] !== true ? $item['route'] : null;
            }

            $this->adminRoutes = $routes;
        }

        return $this->adminRoutes;
    }

    public function getAdminMenuItems(): array
    {
        if (null === $this->adminMenu) {
            $routes = [];
            $count = 0;

            $directories = $this->getDirectories();
            foreach ($directories as $directory) {
                $type = $directory->getFlexType();
                $items = $directory->getConfig('admin.menu') ?? [];
                if ($items) {
                    foreach ($items as $view => $item) {
                        $item += [
                            'route' => '/' . $type,
                            'title' => $directory->getTitle(),
                            'icon' => 'fa fa-file',
                            'directory' => $type
                        ];
                        $routes[$type] = $item;
                    }
                } else {
                    $count++;
                }
            }

            $menu = (array)($this->config['admin']['menu'] ?? []);
            foreach ($menu as $slug => $menuItem) {
                $directory = $menuItem['directory'] ?? '';
                $routes[$directory] = $menuItem + ['route' => '/' . $slug];
            }

            if ($count && !isset($routes[''])) {
                $routes[''] = ['route' => '/flex-objects'];
            }

            $this->adminMenu = $routes;
        }

        return $this->adminMenu;
    }
}
