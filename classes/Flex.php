<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects;

use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;

/**
 * Class Flex
 * @package Grav\Plugin\FlexObjects
 */
class Flex extends \Grav\Framework\Flex\Flex
{
    /** @var array */
    protected $adminRoutes;

    /**
     * @return array
     */
    public function getAll() : array
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

    /**
     * @param string|object|null $type
     * @param array $params
     * @return string
     */
    public function adminRoute($type = null, array $params = []) : string
    {
        if (\is_object($type)) {
            $object = $type;
            $type = $type->getType(false);
        } else {
            $object = null;
        }

        $routes = $this->getAdminRoutes();

        $grav = Grav::instance();
        $route = $grav['base_url'] . '/admin';

        if ($type && isset($routes[$type])) {
            $route .= '/' .  $routes[$type];
        } elseif ($type) {
            $route .= '/' .  $routes[''] . '/' . $type;
        }

        if ($object instanceof FlexObject) {
            $route .= '/' . $object->getKey();
        }

        $p = [];
        foreach ($params as $key => $val) {
            // FIXME: use config
            $p[] = $key . ':' . $val;
        }

        return $route . ($p ? '/' . implode('/', $p) : '');
    }

    /**
     * @return array
     */
    protected function getAdminRoutes() : array
    {
        if (null === $this->adminRoutes) {
            $routes = [];

            $menu = (array)($this->config['admin']['menu'] ?? null);
            foreach ($menu as $slug => $menuItem) {
                $directory = $menuItem['directory'] ?? '';
                $routes[$directory] = $slug;
            }

            if (empty($routes)) {
                $routes[''] = 'flex-objects';
            }

            $this->adminRoutes = $routes;
        }

        return $this->adminRoutes;
    }
}
