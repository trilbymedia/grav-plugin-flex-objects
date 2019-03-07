<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects;

use Grav\Common\Config\Config;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Utils;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\FlexObject;
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

    /**
     * @return array
     */
    public function getAll(): array
    {
        $params = [
            'pattern' => '|\.yaml|',
            'value' => 'Url',
            'recursive' => false
        ];

        $directories = $this->getDirectories();
        $all = Folder::all('blueprints://flex-objects', $params);

        foreach ($all as $url) {
            $type = basename($url, '.yaml');
            if (!isset($directories[$type])) {
                $directories[$type] = new FlexDirectory($type, $url);
            }
        }

        ksort($directories);

        return $directories;
    }

    public function getDataTable(string $type, array $options = [])
    {
        $directory = $this->getDirectory($type);
        if (!$directory) {
            throw new \RuntimeException('Not Found', 404);
        }

        $table = new DataTable($options);
        $table->setCollection($directory->getCollection());

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
            $type = $type->getType(false);
        } else {
            $object = null;
        }

        $routes = $this->getAdminRoutes();

        $grav = Grav::instance();

        /** @var Config $config */
        $config = $grav['config'];
        $route = Utils::isAdminPlugin() ? '' : $grav['base_url'] . '/' . trim($config->get('plugins.admin.route'), '/');

        if ($type && isset($routes[$type])) {
            if (!$routes[$type]) {
                // Directory has empty route.
                return '';
            }

            // Directory has it's own menu item.
            $route .= $routes[$type];
        } else {
            if (empty($routes[''])) {
                // Default route has been disabled.
                return '';
            }

            // Use default route.
            $route .= '/' . $routes[''];
            if ($type) {
                $route .= '/' . $type;
            }
        }

        // Append object key if available.
        if ($object instanceof FlexObject) {
            if ($object->exists()) {
                $route .= "/{$object->getKey()}";
            } else {
                $params = ['action' => 'add'] + $params;
            }
        }

        $p = [];

        $separator = $config->get('system.param_sep');
        foreach ($params as $key => $val) {
            $p[] = $key . $separator . $val;
        }

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
                $type = $directory->getType();
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
