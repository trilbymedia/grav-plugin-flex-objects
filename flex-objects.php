<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Types;
use Grav\Common\Plugin;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Events\FlexRegisterEvent;
use Grav\Events\PermissionsRegisterEvent;
use Grav\Events\PluginsLoadedEvent;
use Grav\Framework\Acl\PermissionsReader;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Interfaces\FlexInterface;
use Grav\Plugin\Admin\Admin;
use Grav\Plugin\FlexObjects\FlexFormFactory;
use Grav\Plugin\Form\Forms;
use Grav\Plugin\FlexObjects\Admin\AdminController;
use Grav\Plugin\FlexObjects\Flex;
use RocketTheme\Toolbox\Event\Event;
use function is_callable;

/**
 * Class FlexObjectsPlugin
 * @package Grav\Plugin
 */
class FlexObjectsPlugin extends Plugin
{
    /** @var string */
    protected const MIN_GRAV_VERSION = '1.7.0';

    /** @var int[] */
    public $features = [
        'blueprints' => 1000,
    ];

    /** @var AdminController */
    protected $controller;

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
            PluginsLoadedEvent::class => [
                ['initializeFlex', 10]
            ],
            PermissionsRegisterEvent::class => [
                ['onRegisterPermissions', 100]
            ],
            FlexRegisterEvent::class => [
                ['onRegisterFlex', 100]
            ],
            'onCliInitialize' => [
                ['autoload', 100000],
                ['initializeFlex', 10]
            ],
            'onPluginsInitialized' => [
                ['onPluginsInitialized', 0],
            ],
            'onFormRegisterTypes' => [
                ['onFormRegisterTypes', 0]
            ],
        ];
    }

    /**
     * [PluginsLoadedEvent:100000] Composer autoload.
     *
     * @return ClassLoader
     */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * [PluginsLoadedEvent:10]: Initialize Flex
     *
     * @return void
     */
    public function initializeFlex(): void
    {
        $config = $this->config->get('plugins.flex-objects.directories');

        // Add to DI container
        $this->grav['flex_objects'] = static function (Grav $grav) use ($config) {
            /** @var FlexInterface $flex */
            $flex = $grav['flex'];

            $flexObjects = new Flex($flex, $config);

            // This event is for backwards compatibility only, do not use it!
            $grav->fireEvent('onFlexInit', new Event(['flex' => $flexObjects]));

            return $flexObjects;
        };
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function onPluginsInitialized(): void
    {
        if ($this->isAdmin() && method_exists(Admin::class, 'getChangelog')) {
            /** @var UserInterface|null $user */
            $user = $this->grav['user'] ?? null;

            if (null === $user || !$user->authorize('login', 'admin')) {
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
                'onAdminCompilePresetSCSS' => [
                    ['onAdminCompilePresetSCSS', 0]
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
            $this->controller = new AdminController();

        } else {
            $this->enable([
                'onTwigTemplatePaths' => [
                    ['onTwigTemplatePaths', 0]
                ],
            ]);
        }
    }

    /**
     * @param FlexRegisterEvent $event
     * @return void
     */
    public function onRegisterFlex(FlexRegisterEvent $event): void
    {
        $flex = $event->flex;
        $map = Flex::getLegacyBlueprintMap(false);

        $types = (array)$this->config->get('plugins.flex-objects.directories', []);
        foreach ($types as $blueprint) {
            // Backwards compatibility to v1.0.0-rc.3
            $blueprint = $map[$blueprint] ?? $blueprint;
            $type = basename((string)$blueprint, '.yaml');

            if (!file_exists($blueprint)) {
                /** @var Debugger $debugger */
                $debugger = Grav::instance()['debugger'];
                $debugger->addMessage(sprintf('Flex: blueprint for flex type %s is missing', $type), 'error');

                continue;
            }

            $directory = $flex->getDirectory($type);
            if ($type && (!$directory || !$directory->isEnabled())) {
                $flex->addDirectoryType($type, $blueprint);
            }
        }
    }

    /**
     * Initial stab at registering permissions (WIP)
     *
     * @param PermissionsRegisterEvent $event
     * @return void
     */
    public function onRegisterPermissions(PermissionsRegisterEvent $event): void
    {
        /** @var Flex $flex */
        $flex = $this->grav['flex_objects'];
        $directories = $flex->getDirectories();

        $permissions = $event->permissions;

        $actions = [];
        foreach ($directories as $directory) {
            $data = $directory->getConfig('admin.permissions', []);
            $actions[] = PermissionsReader::fromArray($data, $permissions->getTypes());

        }
        $actions[] = PermissionsReader::fromYaml("plugin://{$this->name}/permissions.yaml");

        $permissions->addActions(array_replace(...$actions));
    }

    /**
     * @param Event $event
     * @return void
     */
    public function onFormRegisterTypes(Event $event): void
    {
        /** @var Forms $forms */
        $forms = $event['forms'];
        $forms->registerType('flex', new FlexFormFactory());
    }

    /**
     * @param Event $event
     * @return void
     */
    public function onAdminPage(Event $event): void
    {
        if ($this->controller->isActive()) {
            $event->stopPropagation();

            /** @var PageInterface $page */
            $page = $event['page'];
            $page->init(new \SplFileInfo(__DIR__ . '/admin/pages/flex-objects.md'));
            $page->slug($this->controller->getLocation());
            $header = $page->header();
            $header->access = ['admin.login'];
            $header->controller = $this->controller->getInfo();
        }
    }

    /**
     * [onPageInitialized:0]: Run controller
     *
     * @return void
     */
    public function onAdminPageInitialized(): void
    {
        if ($this->controller->isActive()) {
            $this->controller->execute();
            $this->controller->redirect();
        }
    }

    /**
     * @param Event $event
     * @return void
     */
    public function onAdminControllerInit(Event $event): void
    {
        $eventController = $event['controller'];

        // Blacklist all admin routes, including aliases and redirects.
        $eventController->blacklist_views[] = 'flex-objects';
        foreach ($this->controller->getAdminRoutes() as $route => $info) {
            $eventController->blacklist_views[] = trim($route, '/');
        }
    }

    /**
     * Add Flex-Object's preset.scss to the Admin Preset SCSS compile process
     *
     * @param Event $event
     * @return void
     */
    public function onAdminCompilePresetSCSS(Event $event): void
    {
        $event['scss']->add($this->grav['locator']->findResource('plugins://flex-objects/scss/_preset.scss'));
    }

    /**
     * @param Event $event
     * @return void
     */
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
     *
     * @return void
     */
    public function onAdminMenu(): void
    {
        /** @var Flex $flex */
        $flex = $this->grav['flex_objects'];
        /** @var Admin $admin */
        $admin = $this->grav['admin'];

        foreach ($this->getAdminMenu() as $route => $item) {
            $directory = null;
            if (isset($item['directory'])) {
                $directory = $flex->getDirectory($item['directory']);
                if (!$directory || !$directory->isEnabled()) {
                    continue;
                }
            }

            $title = $item['title'] ?? 'PLUGIN_FLEX_OBJECTS.TITLE';
            $index = $item['index'] ?? 0;
            if (($this->grav['twig']->plugins_hooked_nav[$title]['index'] ?? 1000) <= $index) {
                continue;
            }

            $location = $item['location'] ?? $route;
            $hidden = $item['hidden'] ?? false;
            $icon = $item['icon'] ?? 'fa-list';
            $authorize = $item['authorize'] ?? ($directory ? null : ['admin.flex-objects', 'admin.super']);
            if ($hidden || (null === $authorize && $directory->isAuthorized('list', 'admin', $admin->user))) {
                continue;
            }
            $cache = $directory ? $directory->getCache('index') : null;
            $count = $cache ? $cache->get('admin-count-' . md5($admin->user->username)) : false;
            if (null === $count) {
                try {
                    $collection = $directory->getCollection();
                    if (is_callable([$collection, 'isAuthorized'])) {
                        $count = $collection->isAuthorized('list', 'admin', $admin->user)->count();
                    } else {
                        $count = $collection->count();
                    }
                    $cache->set('admin-count-' . md5($admin->user->username), $count);
                } catch (\InvalidArgumentException $e) {
                    continue;
                }
            }
            $badge = $directory ? ['badge' => ['count' => $count]] : [];
            $priority = $item['priority'] ?? 0;

            $this->grav['twig']->plugins_hooked_nav[$title] = [
                'location' => $location,
                'route' => $route,
                'index' => $index,
                'icon' => $icon,
                'authorize' => $authorize,
                'priority' => $priority
            ] + $badge;
        }
    }

    /**
     * Exclude Flex Directory data from the Data Manager plugin
     *
     * @return void
     */
    public function onDataTypeExcludeFromDataManagerPluginHook(): void
    {
        $this->grav['admin']->dataTypesExcludedFromDataManagerPlugin[] = 'flex-objects';
    }

    /**
     * Add current directory to twig lookup paths.
     *
     * @return void
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
     *
     * @param Event $event
     * @return void
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
     * Set needed variables to display directory.
     *
     * @return void
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
