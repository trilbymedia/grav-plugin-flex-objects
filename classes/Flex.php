<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects;

use Grav\Common\Config\Config;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Utils;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\FlexObject;
use Grav\Framework\Flex\Interfaces\FlexCollectionInterface;
use Grav\Framework\Flex\Interfaces\FlexCommonInterface;
use Grav\Framework\Flex\Interfaces\FlexInterface;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Plugin\FlexObjects\Table\DataTable;

/**
 * Class Flex
 * @package Grav\Plugin\FlexObjects
 */
class Flex implements FlexInterface
{
    /** @var FlexInterface */
    protected $flex;
    /** @var array */
    protected $adminRoutes;
    /** @var array */
    protected $adminMenu;
    /** @var array */
    protected $managed;

    /**
     * Flex constructor.
     * @param FlexInterface $flex
     * @param array $types
     */
    public function __construct(FlexInterface $flex, array $types)
    {
        $this->flex = $flex;
        $this->managed = [];

        foreach ($types as $blueprint) {
            $type = basename((string)$blueprint, '.yaml');
            if ($type) {
                $this->managed[] = $type;
            }
        }
    }

    /**
     * @param string $type
     * @param string $blueprint
     * @param array  $config
     * @return $this
     */
    public function addDirectoryType(string $type, string $blueprint, array $config = [])
    {
        $this->flex->addDirectoryType($type, $blueprint, $config);

        return $this;
    }

    /**
     * @param FlexDirectory $directory
     * @return $this
     */
    public function addDirectory(FlexDirectory $directory)
    {
        $this->flex->addDirectory($directory);

        return $this;
    }

    /**
     * @param string $type
     * @return bool
     */
    public function hasDirectory(string $type): bool
    {
        return $this->flex->hasDirectory($type);
    }

    /**
     * @param array|string[]|null $types
     * @param bool $keepMissing
     * @return array<FlexDirectory|null>
     */
    public function getDirectories(array $types = null, bool $keepMissing = false): array
    {
        return $this->flex->getDirectories($types, $keepMissing);
    }

    /**
     * @param string $type
     * @return FlexDirectory|null
     */
    public function getDirectory(string $type): ?FlexDirectory
    {
        return $this->flex->getDirectory($type);
    }

    /**
     * @param string $type
     * @param array|null $keys
     * @param string|null $keyField
     * @return FlexCollectionInterface|null
     */
    public function getCollection(string $type, array $keys = null, string $keyField = null): ?FlexCollectionInterface
    {
        return $this->flex->getCollection($type, $keys, $keyField);
    }

    /**
     * @param array $keys
     * @param array $options            In addition to the options in getObjects(), following options can be passed:
     *                                  collection_class:   Class to be used to create the collection. Defaults to ObjectCollection.
     * @return FlexCollectionInterface
     * @throws \RuntimeException
     */
    public function getMixedCollection(array $keys, array $options = []): FlexCollectionInterface
    {
        return $this->flex->getMixedCollection($keys, $options);
    }

    /**
     * @param array $keys
     * @param array $options    Following optional options can be passed:
     *                          types:          List of allowed types.
     *                          type:           Allowed type if types isn't defined, otherwise acts as default_type.
     *                          default_type:   Set default type for objects given without type (only used if key_field isn't set).
     *                          keep_missing:   Set to true if you want to return missing objects as null.
     *                          key_field:      Key field which is used to match the objects.
     * @return array
     */
    public function getObjects(array $keys, array $options = []): array
    {
        return $this->flex->getObjects($keys, $options);
    }

    /**
     * @param string $key
     * @param string|null $type
     * @param string|null $keyField
     * @return FlexObjectInterface|null
     */
    public function getObject(string $key, string $type = null, string $keyField = null): ?FlexObjectInterface
    {
        return $this->flex->getObject($keyField, $type, $keyField);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return $this->flex->count();
    }

    public function isManaged(string $type): bool
    {
        return in_array($type, $this->managed, true);
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
            return $a->getTitle() <=> $b->getTitle();
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

        $parts = array_filter($parts, static function ($val) { return $val !== ''; });
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
            /** @var FlexDirectory $directory */
            foreach ($this->getDirectories() as $directory) {
                $config = $directory->getConfig('admin');
                if (!$directory->isEnabled() || !empty($config['disabled'])) {
                    continue;
                }

                // Resolve route.
                $route = $config['router']['path']
                    ?? $config['menu']['list']['route']
                    ?? "/flex-objects/{$directory->getFlexType()}";

                $routes[$directory->getFlexType()] = $route;
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
            /** @var FlexDirectory $directory */
            foreach ($directories as $directory) {
                if (!$directory->isEnabled() || !empty($config['disabled'])) {
                    continue;
                }
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
