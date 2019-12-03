<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Types;
use Grav\Common\Plugin;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Plugin\FlexObjects\FlexFormFactory;
use Grav\Plugin\Form\Forms;
use Grav\Plugin\FlexObjects\Admin\AdminController;
use Grav\Plugin\FlexObjects\Flex;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class FlexObjectsPlugin
 * @package Grav\Plugin
 */
class FlexObjectsPlugin extends Plugin
{
    const MIN_GRAV_VERSION = '1.7.0-beta.7';

    public $features = [
        'blueprints' => 1000,
    ];

    /** @var AdminController */
    protected $controller;

    protected $directory;

    /**
     * @return bool
     */
    public static function checkRequirements(): bool
    {
        return version_compare(GRAV_VERSION, static::MIN_GRAV_VERSION, '>=');
    }

    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents(): array
    {
        if (!static::checkRequirements()) {
            return [];
        }

        return [
            'onCliInitialize' => [
                ['autoload', 100000],
                ['initializeFlex', 10]
            ],
            'onPluginsInitialized' => [
                ['autoload', 100000],
                ['onPluginsInitialized', 0],
                ['initializeFlex', 10]
            ],
            'onFormRegisterTypes' => [
                ['onFormRegisterTypes', 0]
            ],
            'onAdminRegisterPermissions' => [
                ['onAdminRegisterPermissions', -10]
            ],
        ];
    }

    /**
     * [onPluginsInitialized:100000] Composer autoload.
     *
     * @return ClassLoader
     */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized(): void
    {
        if ($this->isAdmin()) {
            /** @var UserInterface $user */
            $user = $this->grav['user'] ?? null;

            if (!$user || !$user->authorize('login', 'admin')) {
                return;
            }

            $this->enable([
                'onAdminTwigTemplatePaths' => [
                    ['onAdminTwigTemplatePaths', 10]
                ],
                'onAdminMenu' => [
                    ['onAdminMenu', 0]
                ],
                'onAdminPage' => [
                    ['onAdminPage', 0]
                ],
                'onDataTypeExcludeFromDataManagerPluginHook' => [
                    ['onDataTypeExcludeFromDataManagerPluginHook', 0]
                ],
                'onAdminControllerInit' => [
                    ['onAdminControllerInit', 0]
                ],
                'onPageInitialized' => [
                    ['onAdminPageInitialized', 0]
                ],
                'onTwigSiteVariables' => [
                    ['onTwigAdminVariables', 0]
                ],
                'onGetPageTemplates' =>
                    ['onGetPageTemplates', 0]

            ]);
            /** @var AdminController controller */
            $this->controller = new AdminController($this);

        } else {
            $this->enable([
                'onTwigTemplatePaths' => [
                    ['onTwigTemplatePaths', 0]
                ],
            ]);
        }
    }

    public function initializeFlex(): void
    {
        $config = $this->config->get('plugins.flex-objects');

        // Add to DI container
        $this->grav['flex_objects'] = static function (Grav $grav) use ($config) {
            $blueprints = $config['directories'] ?: [];

            $list = [];
            foreach ($blueprints as $blueprint) {
                if ($blueprint) {
                    $list[basename($blueprint, '.yaml')] = $blueprint;
                }
            }

            $flex = new Flex($list, $config);

            $grav->fireEvent('onFlexInit', new Event(['flex' => $flex]));

            return $flex;
        };
    }

    /**
     * Initial stab at registering permissions (WIP)
     *
     * @param Event $e
     */
    public function onAdminRegisterPermissions(Event $e)
    {
        $admin = $e['admin'];
        if (!$admin) {
            return;
        }

        $permissions = [
            'admin.flex-object.list' => 'boolean',
            'admin.flex-object.create' => 'boolean',
            'admin.flex-object.read' => 'boolean',
            'admin.flex-object.update' => 'boolean',
            'admin.flex-object.delete' => 'boolean',
        ];

        $admin->addPermissions($permissions);
    }

    public function onFormRegisterTypes(Event $event): void
    {
        /** @var Forms $forms */
        $forms = $event['forms'];
        $forms->registerType('flex', new FlexFormFactory());
    }

    public function onAdminPage(Event $event): void
    {
        if ($this->controller->isActive()) {
            $event->stopPropagation();

            /** @var PageInterface $page */
            $page = $event['page'];
            $page->init(new \SplFileInfo(__DIR__ . '/admin/pages/flex-objects.md'));
            $page->slug($this->controller->getLocation());

            $menu = $this->controller->menu;

            $header = $page->header();
            $header->access = $menu['authorize'] ?? ['admin.flex-objects', 'admin.super'];
        }
    }

    public function onAdminPageInitialized(): void
    {
        if ($this->controller->isActive()) {
            $this->controller->execute();
            $this->controller->redirect();
        }
    }

    public function onAdminControllerInit(Event $event): void
    {
        $eventController = $event['controller'];

        foreach ($this->getAdminMenu() as $route => $item) {
            $eventController->blacklist_views[] = $route;
        }
    }

    public function onGetPageTemplates(Event $event): void
    {
        /** @var Types $types */
        $types = $event->types;
        $types->register('flex-objects', 'plugins://flex-objects/blueprints/pages/flex-objects.yaml');
    }

    /**
     * Form select options listing all enabled directories.
     *
     * @return array
     */
    public static function directoryOptions(): array
    {
        /** @var Flex $flex */
        $flex = Grav::instance()['flex_objects'];
        $directories = $flex->getDirectories();

        $list = [];
        /**
         * @var string $type
         * @var FlexDirectory $directory
         */
        foreach ($directories as $type => $directory) {
            if (!$directory->getConfig('site.hidden')) {
                $list[$type] = $directory->getTitle();
            }
        }

        return $list;
    }

    /**
     * @return array
     */
    public function getAdminMenu(): array
    {
        /** @var Flex $flex */
        $flex = $this->grav['flex_objects'];

        $list = [];
        foreach ($flex->getAdminMenuItems() as $name => $item) {
            $route = trim($item['route'] ?? $name, '/');
            $list[$route] = $item;
        }

        return $list;
    }

    /**
     * Add Flex Directory to admin menu
     */
    public function onAdminMenu(): void
    {
        /** @var Flex $flex */
        $flex = $this->grav['flex_objects'];

        foreach ($this->getAdminMenu() as $route => $item) {
            $directory = null;
            if (isset($item['directory'])) {
                $directory = $flex->getDirectory($item['directory']);
                if (!$directory || !$directory->isEnabled()) {
                    continue;
                }
            }

            $hidden = $item['hidden'] ?? false;
            $title = $item['title'] ?? 'PLUGIN_FLEX_OBJECTS.TITLE';
            $icon = $item['icon'] ?? 'fa-list';
            $authorize = $item['authorize'] ?? ($directory ? null : ['admin.flex-objects', 'admin.super']);
            if ($hidden || (null === $authorize && $directory->isAuthorized('list', 'admin'))) {
                continue;
            }
            $cache = $directory ? $directory->getCache('index') : null;
            $count = $cache ? $cache->get('admin-count') : false;
            if (null === $count) {
                try {
                    $collection = $directory->getCollection();
                    $count = $collection->isAuthorized('list')->count();
                    $cache->set('admin-count', $count);
                } catch (\InvalidArgumentException $e) {
                    continue;
                }
            }
            $badge = $directory ? ['badge' => ['count' => $count]] : [];
            $priority = $item['priority'] ?? 0;

            $this->grav['twig']->plugins_hooked_nav[$title] = [
                'route' => $route,
                'icon' => $icon,
                'authorize' => $authorize,
                'priority' => $priority
            ] + $badge;
        }
    }

    /**
     * Exclude Flex Directory data from the Data Manager plugin
     */
    public function onDataTypeExcludeFromDataManagerPluginHook(): void
    {
        $this->grav['admin']->dataTypesExcludedFromDataManagerPlugin[] = 'flex-objects';
    }

    /**
     * Add current directory to twig lookup paths.
     */
    public function onTwigTemplatePaths(): void
    {
        $extra_site_twig_path = $this->config->get('plugins.flex-objects.extra_site_twig_path');
        $extra_path = $extra_site_twig_path ? $this->grav['locator']->findResource($extra_site_twig_path) : null;
        if ($extra_path) {
            $this->grav['twig']->twig_paths[] = $extra_path;
        }

        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    /**
     * Add plugin templates path
     */
    public function onAdminTwigTemplatePaths(Event $event): void
    {
        $extra_admin_twig_path = $this->config->get('plugins.flex-objects.extra_admin_twig_path');
        $extra_path = $extra_admin_twig_path ? $this->grav['locator']->findResource($extra_admin_twig_path) : null;

        $paths = $event['paths'];
        if ($extra_path) {
            $paths[] = $extra_path;
        }

        $paths[] = __DIR__ . '/admin/templates';
        $event['paths'] = $paths;
    }

    /**
     * Set needed variables to display direcotry.
     */
    public function onTwigAdminVariables(): void
    {
        if ($this->controller->isActive()) {
            // Twig shortcuts
            $this->grav['twig']->twig_vars['controller'] = $this->controller;
            $this->grav['twig']->twig_vars['action'] = $this->controller->getAction();
            $this->grav['twig']->twig_vars['task'] = $this->controller->getTask();
            $this->grav['twig']->twig_vars['target'] = $this->controller->getTarget();
            $this->grav['twig']->twig_vars['key'] = $this->controller->getId();

            $this->grav['twig']->twig_vars['flex'] = $this->grav['flex_objects'];
            $this->grav['twig']->twig_vars['directory'] = $this->controller->getDirectory();
            $this->grav['twig']->twig_vars['collection'] = $this->controller->getCollection();
            $this->grav['twig']->twig_vars['object'] = $this->controller->getObject();

            // CSS / JS Assets
            $this->grav['assets']->addCss('plugin://flex-objects/css/admin.css');
            $this->grav['assets']->addCss('plugin://admin/themes/grav/css/codemirror/codemirror.css');
        }
    }
}
