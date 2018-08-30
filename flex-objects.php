<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Page\Page;
use Grav\Common\Plugin;
use Grav\Plugin\FlexObjects\Controllers\AdminController;
use Grav\Plugin\FlexObjects\Flex;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class FlexObjectsPlugin
 * @package Grav\Plugin
 */
class FlexObjectsPlugin extends Plugin
{
    /** @var AdminController */
    protected $controller;

    protected $version;

    protected $directory;

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
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => [
                ['autoload', 100000],
                ['onPluginsInitialized', 0]
            ],
            'onTwigSiteVariables'  => ['onTwigSiteVariables', 0],
            'onPageInitialized'    => ['onPageInitialized', 100],
        ];
    }

    /**
     * [onPluginsInitialized:100000] Composer autoload.
     *
     * @return ClassLoader
     */
    public function autoload() : ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized()
    {
        if ($this->isAdmin()) {
            $this->enable([
                'onTwigTemplatePaths'                        => ['onTwigAdminTemplatePaths', 0],
                'onAdminMenu'                                => ['onAdminMenu', 0],
                'onAdminPage'                                => ['onAdminPage', 0],
                'onDataTypeExcludeFromDataManagerPluginHook' => ['onDataTypeExcludeFromDataManagerPluginHook', 0],
                'onAdminControllerInit'                      => ['onAdminControllerInit', 0],
            ]);
            /** @var AdminController controller */
            $this->controller = new AdminController($this);

        } else {
            $this->enable([
                'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            ]);
        }

        $config = $this->config->get('plugins.flex-objects');

        // Add to DI container
        $this->grav['flex_objects'] = function () use ($config) {
            $blueprints = $config['directories'] ?: [];

            $list = [];
            foreach ($blueprints as $blueprint) {
                $list[basename($blueprint, '.yaml')] = $blueprint;
            }

            return new Flex($list, $config);
        };

        // TODO: move later into Grav 1.6 (PHP 7.1+)
        $this->grav['server_request'] = function () {
            $psr17Factory = new Psr17Factory();
            $creator = new ServerRequestCreator(
                $psr17Factory, // ServerRequestFactory
                $psr17Factory, // UriFactory
                $psr17Factory, // UploadedFileFactory
                $psr17Factory  // StreamFactory
            );
            return $creator->fromGlobals();
        };
    }

    public function onAdminPage(Event $event)
    {
        if ($this->controller->isActive()) {
            $page = $event['page'];
            $page->init(new \SplFileInfo(__DIR__ . '/admin/pages/flex-objects.md'));
            $page->slug($this->controller->getLocation());
        }
    }

    public function onPageInitialized()
    {
        if ($this->isAdmin() && $this->controller->isActive()) {
            $this->controller->execute();
            $this->controller->redirect();
        }
    }

    public function onAdminControllerInit(Event $event)
    {
        $eventController = $event['controller'];
        $eventController->blacklist_views[] = $this->name;
    }

    public function getAdminMenu()
    {
        $config = $this->config();

        return $config['admin']['menu'] ?? ['flex-objects' => []];
    }

    /**
     * Add Flex Directory to admin menu
     */
    public function onAdminMenu()
    {
        /** @var Flex $flex */
        $flex = $this->grav['flex_objects'];

        foreach ($this->getAdminMenu() as $route => $item) {
            $directory = $item['directory'] ? $flex->getDirectory($item['directory']) : null;

            $title = $item['title'] ?? 'PLUGIN_FLEX_OBJECTS.TITLE';
            $icon = $item['icon'] ?? 'fa-list';
            $authorize = $item['authorize'] ?? ($directory ? null : ['admin.flex-objects', 'admin.super']);
            if (null === $authorize && $directory->authorize('list', 'admin')) {
                continue;
            }
            $badge = $directory ? ['badge' => ['count' => $directory->getCollection()->authorize('list')->count()]] : [];

            $this->grav['twig']->plugins_hooked_nav[$title] = [
                'route' => $route,
                'icon' => $icon,
                'authorize' => $authorize
            ] + $badge;
        }
    }

    /**
     * Exclude Flex Directory data from the Data Manager plugin
     */
    public function onDataTypeExcludeFromDataManagerPluginHook()
    {
        $this->grav['admin']->dataTypesExcludedFromDataManagerPlugin[] = 'flex-objects';
    }

    /**
     * Add current directory to twig lookup paths.
     */
    public function onTwigTemplatePaths()
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
    public function onTwigAdminTemplatePaths()
    {
        $extra_admin_twig_path = $this->config->get('plugins.flex-objects.extra_admin_twig_path');
        $extra_path = $extra_admin_twig_path ? $this->grav['locator']->findResource($extra_admin_twig_path) : null;
        if ($extra_path) {
            $this->grav['twig']->twig_paths[] = $extra_path;
        }

        $this->grav['twig']->twig_paths[] = __DIR__ . '/admin/templates';

    }

    /**
     * Set needed variables to display direcotry.
     */
    public function onTwigSiteVariables()
    {
        if ($this->isAdmin()) {
            // Twig shortcuts
            $this->grav['twig']->twig_vars['location'] = $this->controller->getLocation();
            $this->grav['twig']->twig_vars['action'] = $this->controller->getAction();
            $this->grav['twig']->twig_vars['task'] = $this->controller->getTask();
            $this->grav['twig']->twig_vars['target'] = $this->controller->getTarget();
            $this->grav['twig']->twig_vars['key'] = $this->controller->getId();

            // CSS / JS Assets
            $this->grav['assets']->addCss('plugin://flex-objects/css/admin.css');
            $this->grav['assets']->addCss('plugin://admin/themes/grav/css/codemirror/codemirror.css');

            if ($this->controller->getLocation() === 'flex-objects' && $this->controller->getAction() === 'list') {
                $this->grav['assets']->addCss('plugin://flex-objects/css/filter.formatter.css');
                $this->grav['assets']->addCss('plugin://flex-objects/css/theme.default.css');
                $this->grav['assets']->addJs('plugin://flex-objects/js/jquery.tablesorter.min.js');
                $this->grav['assets']->addJs('plugin://flex-objects/js/widgets/widget-storage.min.js');
                $this->grav['assets']->addJs('plugin://flex-objects/js/widgets/widget-filter.min.js');
                $this->grav['assets']->addJs('plugin://flex-objects/js/widgets/widget-pager.min.js');
            }
        }

        /* else {
            if ($this->config->get('plugins.flex-objects.built_in_css')) {
                $this->grav['assets']->addCss('plugin://flex-objects/css/site.css');
            }
            $this->grav['assets']->addJs('plugin://flex-objects/js/list.min.js');
        }*/
    }
}
