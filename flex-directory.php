<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Plugin\FlexDirectory\AdminController;
use Grav\Plugin\FlexDirectory\SiteController;
use Grav\Plugin\FlexDirectory\Entries;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class FlexDirectoryPlugin
 * @package Grav\Plugin
 */
class FlexDirectoryPlugin extends Plugin
{

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
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
            'onTwigInitialized' => ['onTwigInitialized'],
            'onTwigSiteVariables'  => ['onTwigSiteVariables', 0],
            'onPageInitialized'    => ['onPageInitialized', 0],
        ];
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized()
    {
        require_once __DIR__ . '/vendor/autoload.php';

        if ($this->isAdmin()) {
            $this->enable([
                'onTwigTemplatePaths'                        => ['onTwigAdminTemplatePaths', 0],
                'onAdminMenu'                                => ['onAdminMenu', 0],
                'onDataTypeExcludeFromDataManagerPluginHook' => ['onDataTypeExcludeFromDataManagerPluginHook', 0],
                'onAdminControllerInit'                      => ['onAdminControllerInit', 0],
            ]);
            /** @var AdminController controller */
            $this->controller = new AdminController($this);

        } else {
            $this->enable([
                'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            ]);
            /** @var SiteController controller */
            $this->controller = new SiteController($this);
        }

        $config = $this->config->get('plugins.flex-directory');

        // Add to DI container
        $this->grav['flex-entries'] = new Entries($config['json_file'], $config['blueprint_file']);

    }

    public function onPageInitialized()
    {
        if ($this->controller->isActive()) {
            $this->controller->execute();
            $this->controller->redirect();
        }
    }

    public function onAdminControllerInit(Event $event)
    {
        $eventController = $event['controller'];
        $eventController->blacklist_views[] = $this->name;
    }

    /**
     * Add Flex Directory to admin menu
     */
    public function onAdminMenu()
    {
        $this->grav['twig']->plugins_hooked_nav['PLUGIN_FLEX_DIRECTORY.TITLE'] = ['route' => $this->name .'/entries', 'icon' => 'fa-list'];
    }

    /**
     * Exclude Flex Directory data from the Data Manager plugin
     */
    public function onDataTypeExcludeFromDataManagerPluginHook()
    {
        $this->grav['admin']->dataTypesExcludedFromDataManagerPlugin[] = 'directory';
    }

    /**
     * Add current directory to twig lookup paths.
     */
    public function onTwigTemplatePaths()
    {
        $extra_site_twig_path = $this->config->get('plugins.flex-directory.extra_site_twig_path');
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
        $extra_admin_twig_path = $this->config->get('plugins.flex-directory.extra_admin_twig_path');
        $extra_path = $extra_admin_twig_path ? $this->grav['locator']->findResource($extra_admin_twig_path) : null;
        if ($extra_path) {
            $this->grav['twig']->twig_paths[] = $extra_path;
        }

        $this->grav['twig']->twig_paths[] = __DIR__ . '/admin/templates';

    }

    /**
     * Set the entries on the Twig vars
     */
    public function onTwigInitialized()
    {
        // Twig shortcuts
        if (isset($this->grav['flex-entries'])) {
            $this->grav['twig']->twig_vars['flex_entries'] = $this->grav['flex-entries'];
        }

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

            // CSS / JS Assets
            $this->grav['assets']->addCss('plugin://flex-directory/css/admin.css');
            $this->grav['assets']->addCss('plugin://admin/themes/grav/css/codemirror/codemirror.css');

            if ($this->controller->getTarget() == 'entries' && $this->controller->getAction() == 'list') {
                $this->grav['assets']->addCss('plugin://flex-directory/css/filter.formatter.css');
                $this->grav['assets']->addCss('plugin://flex-directory/css/theme.default.css');
                $this->grav['assets']->addJs('plugin://flex-directory/js/jquery.tablesorter.min.js');
                $this->grav['assets']->addJs('plugin://flex-directory/js/widgets/widget-storage.min.js');
                $this->grav['assets']->addJs('plugin://flex-directory/js/widgets/widget-filter.min.js');
                $this->grav['assets']->addJs('plugin://flex-directory/js/widgets/widget-pager.min.js');
            }
        } else {
            if ($this->config->get('plugins.flex-directory.built_in_css')) {
                $this->grav['assets']->addCss('plugin://flex-directory/css/site.css');
            }
            $this->grav['assets']->addJs('plugin://flex-directory/js/list.min.js');
        }
    }
}
