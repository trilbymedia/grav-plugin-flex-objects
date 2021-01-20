<?php

namespace Grav\Plugin\FlexObjects\Admin;

use Exception;
use Grav\Common\Cache;
use Grav\Common\Config\Config;
use Grav\Common\Debugger;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Flex\Types\Pages\PageCollection;
use Grav\Common\Flex\Types\Pages\PageIndex;
use Grav\Common\Flex\Types\Pages\PageObject;
use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Uri;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Common\Utils;
use Grav\Framework\Controller\Traits\ControllerResponseTrait;
use Grav\Framework\File\Formatter\CsvFormatter;
use Grav\Framework\File\Formatter\YamlFormatter;
use Grav\Framework\File\Interfaces\FileFormatterInterface;
use Grav\Framework\Flex\FlexForm;
use Grav\Framework\Flex\FlexFormFlash;
use Grav\Framework\Flex\Interfaces\FlexAuthorizeInterface;
use Grav\Framework\Flex\Interfaces\FlexCollectionInterface;
use Grav\Framework\Flex\Interfaces\FlexDirectoryInterface;
use Grav\Framework\Flex\Interfaces\FlexFormInterface;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Flex\Interfaces\FlexTranslateInterface;
use Grav\Framework\Object\Interfaces\ObjectInterface;
use Grav\Framework\Psr7\Response;
use Grav\Framework\RequestHandler\Exception\RequestException;
use Grav\Framework\Route\Route;
use Grav\Framework\Route\RouteFactory;
use Grav\Plugin\Admin\Admin;
use Grav\Plugin\FlexObjects\Controllers\MediaController;
use Grav\Plugin\FlexObjects\Flex;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RocketTheme\Toolbox\Session\Message;
use RuntimeException;
use function dirname;
use function in_array;
use function is_array;
use function is_callable;

/**
 * Class AdminController
 * @package Grav\Plugin\FlexObjects
 */
class AdminController
{
    use ControllerResponseTrait;

    /** @var AdminController|null */
    private static $instance;

    /** @var Grav */
    public $grav;
    /** @var string */
    public $view;
    /** @var string */
    public $task;
    /** @var Route|null */
    public $route;
    /** @var array */
    public $post;
    /** @var array|null */
    public $data;

    /** @var array */
    protected $adminRoutes;
    /** @var Uri */
    protected $uri;
    /** @var Admin */
    protected $admin;
    /** @var UserInterface */
    protected $user;
    /** @var string */
    protected $redirect;
    /** @var int */
    protected $redirectCode;
    /** @var Route */
    protected $currentRoute;
    /** @var Route */
    protected $referrerRoute;

    /** @var string|null */
    protected $action;
    /** @var string|null */
    protected $location;
    /** @var string|null */
    protected $target;
    /** @var string|null */
    protected $id;
    /** @var bool */
    protected $active;
    /** @var FlexObjectInterface */
    protected $object;
    /** @var FlexCollectionInterface */
    protected $collection;
    /** @var FlexDirectoryInterface */
    protected $directory;

    /** @var string */
    protected $nonce_name = 'admin-nonce';
    /** @var string */
    protected $nonce_action = 'admin-form';
    /** @var string */
    protected $task_prefix = 'task';
    /** @var string */
    protected $action_prefix = 'action';

    /**
     * Unknown task, call onFlexTask[NAME] event.
     *
     * @return bool
     */
    public function taskDefault(): bool
    {
        $object = $this->getObject();
        $type = $this->target;
        $key = $this->id;

        $directory = $this->getDirectory($type);

        if ($object && $object->exists()) {
            $event = new Event(
                [
                    'type' => $type,
                    'key' => $key,
                    'admin' => $this->admin,
                    'flex' => $this->getFlex(),
                    'directory' => $directory,
                    'object' => $object,
                    'data' => $this->data,
                    'redirect' => $this->redirect
                ]
            );

            try {
                $grav = Grav::instance();
                $grav->fireEvent('onFlexTask' . ucfirst($this->task), $event);
            } catch (Exception $e) {
                /** @var Debugger $debugger */
                $debugger = $this->grav['debugger'];
                $debugger->addException($e);
                $this->admin->setMessage($e->getMessage(), 'error');
            }

            $redirect = $event['redirect'];
            if ($redirect) {
                $this->setRedirect($redirect);
            }

            return $event->isPropagationStopped();
        }

        return false;
    }

    /**
     * Default action, onFlexAction[NAME] event.
     *
     * @return bool
     */
    public function actionDefault(): bool
    {
        $object = $this->getObject();
        $type = $this->target;
        $key = $this->id;

        $directory = $this->getDirectory($type);

        if ($object && $object->exists()) {
            $event = new Event(
                [
                    'type' => $type,
                    'key' => $key,
                    'admin' => $this->admin,
                    'flex' => $this->getFlex(),
                    'directory' => $directory,
                    'object' => $object,
                    'redirect' => $this->redirect
                ]
            );

            try {
                $grav = Grav::instance();
                $grav->fireEvent('onFlexAction' . ucfirst($this->action), $event);
            } catch (Exception $e) {
                /** @var Debugger $debugger */
                $debugger = $this->grav['debugger'];
                $debugger->addException($e);
                $this->admin->setMessage($e->getMessage(), 'error');
            }

            $redirect = $event['redirect'];
            if ($redirect) {
                $this->setRedirect($redirect);
            }

            return $event->isPropagationStopped();
        }

        return false;
    }

    /**
     * Get datatable for list view.
     *
     * @return void
     */
    public function actionList(): void
    {
        /** @var Uri $uri */
        $uri = $this->grav['uri'];
        if ($uri->extension() === 'json') {
            $directory = $this->getDirectory();

            $options = [
                'collection' => $this->getCollection(),
                'url' => $uri->path(),
                'page' => $uri->query('page'),
                'limit' => $uri->query('per_page'),
                'sort' => $uri->query('sort'),
                'search' => $uri->query('filter'),
                'filters' => $uri->query('filters'),
            ];

            $table = $this->getFlex()->getDataTable($directory, $options);

            $response = $this->createJsonResponse($table->jsonSerialize());

            $this->close($response);
        }
    }

    /**
     * Alias for Export action.
     *
     * @return void
     */
    public function actionCsv(): void
    {
        $this->actionExport();
    }

    /**
     * Export action. Defaults to CVS export.
     *
     * @return void
     */
    public function actionExport(): void
    {
        $collection = $this->getCollection();
        if (!$collection) {
            throw new RuntimeException('Internal Error', 500);
        }
        if (is_callable([$collection, 'isAuthorized']) && !$collection->isAuthorized('list', 'admin', $this->user)) {
            throw new RuntimeException($this->admin::translate('PLUGIN_ADMIN.INSUFFICIENT_PERMISSIONS_FOR_TASK') . ' list.', 403);
        }

        $config = $collection->getFlexDirectory()->getConfig('admin.views.export') ?? $collection->getFlexDirectory()->getConfig('admin.export') ?? false;
        if (!$config || empty($config['enabled'])) {
            throw new RuntimeException($this->admin::translate('Not Found'), 404);
        }

        $defaultFormatter = CsvFormatter::class;
        $class = trim($config['formatter']['class'] ?? $defaultFormatter, '\\');
        $method = $config['method'] ?? ($class === $defaultFormatter ? 'csvSerialize' : 'jsonSerialize');
        if (!class_exists($class)) {
            throw new RuntimeException($this->admin::translate('Formatter Not Found'), 404);
        }
        /** @var FileFormatterInterface $formatter */
        $formatter = new $class($config['formatter']['options'] ?? []);
        $filename = ($config['filename'] ?? 'export') . $formatter->getDefaultFileExtension();

        if (method_exists($collection, $method)) {
            $list = $collection->{$method}();
        } else {
            $list = [];

            /** @var ObjectInterface $object */
            foreach ($collection as $object) {
                if (method_exists($object, $method)) {
                    $data = $object->{$method}();
                    if ($data) {
                        $list[] = $data;
                    }
                } else {
                    $list[] = $object->jsonSerialize();
                }
            }
        }

        $response = new Response(
            200,
            [
                'Content-Type' => $formatter->getMimeType(),
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
                'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
                'Last-Modified' => gmdate('D, d M Y H:i:s') . ' GMT',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ],
            $formatter->encode($list)
        );

        $this->close($response);
    }

    /**
     * Delete object from directory.
     *
     * @return ObjectInterface|bool
     */
    public function taskDelete()
    {
        $object = null;
        try {
            $object = $this->getObject();

            if ($object && $object->exists()) {
                if ($object instanceof FlexAuthorizeInterface && !$object->isAuthorized('delete', 'admin', $this->user)) {
                    throw new RuntimeException($this->admin::translate('PLUGIN_ADMIN.INSUFFICIENT_PERMISSIONS_FOR_TASK') . ' delete.', 403);
                }

                $object->delete();

                $this->admin->setMessage($this->admin::translate(['PLUGIN_ADMIN.REMOVED_SUCCESSFULLY', 'Directory Entry']), 'info');
                if ($this->currentRoute->withoutGravParams()->getRoute() === $this->referrerRoute->getRoute()) {
                    $redirect = dirname($this->currentRoute->withoutGravParams()->toString(true));
                } else {
                    $redirect = $this->referrerRoute->toString(true);
                }

                $this->setRedirect($redirect);

                $grav = Grav::instance();
                $grav->fireEvent('onFlexAfterDelete', new Event(['type' => 'flex', 'object' => $object]));
                $grav->fireEvent('gitsync');
            }
        } catch (RuntimeException $e) {
            $this->admin->setMessage('Delete Failed: ' . $e->getMessage(), 'error');

            $this->setRedirect($this->referrerRoute->toString(true), 302);
        }

        return $object ? true : false;
    }

    /**
     * Create a new empty folder (from modal).
     *
     * TODO: Move pages specific logic
     *
     * @return void
     */
    public function taskSaveNewFolder(): void
    {
        $directory = $this->getDirectory();
        if (!$directory) {
            throw new RuntimeException('Not Found', 404);
        }

        if ($directory instanceof FlexAuthorizeInterface && !$directory->isAuthorized('create', 'admin', $this->user)) {
            throw new RuntimeException($this->admin::translate('PLUGIN_ADMIN.INSUFFICIENT_PERMISSIONS_FOR_TASK') . ' save.', 403);
        }

        $collection = $directory->getCollection();
        if (!($collection instanceof PageCollection || $collection instanceof PageIndex)) {
            throw new RuntimeException('Task saveNewFolder works only for pages', 400);
        }

        $data = $this->data;
        $route = trim($data['route'] ?? '', '/');

        // TODO: Folder name needs to be validated!
        $folder = mb_strtolower($data['folder'] ?? '');
        if ($folder === '' || mb_strpos($folder, '/') !== false) {
            throw new RuntimeException('Creating folder failed, bad folder name', 400);
        }

        $parent = $route ? $directory->getObject($route) : $collection->getRoot();
        if (!$parent instanceof PageObject) {
            throw new RuntimeException('Creating folder failed, bad parent route', 400);
        }

        $path = $parent->getFlexDirectory()->getStorageFolder($parent->getStorageKey());
        if (!$path) {
            throw new RuntimeException('Creating folder failed, bad parent storage path', 400);
        }

        // Ordering
        $orders = $parent->children()->visible()->getProperty('order');
        $maxOrder = 0;
        foreach ($orders as $order) {
            $maxOrder = max($maxOrder, (int)$order);
        }

        $orderOfNewFolder = $maxOrder ? sprintf('%02d.', $maxOrder+1) : '';
        $new_path         = $path . '/' . $orderOfNewFolder . $folder;

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        Folder::create($locator->findResource($new_path, true, true));
        Cache::clearCache('invalidate');

        $this->grav->fireEvent('onAdminAfterSaveAs', new Event(['path' => $new_path]));

        $this->admin->setMessage($this->admin::translate('PLUGIN_ADMIN.SUCCESSFULLY_SAVED'), 'info');

        $this->setRedirect($this->referrerRoute->toString(true));
    }

    /**
     * Create a new object (from modal).
     *
     * TODO: Move pages specific logic
     *
     * @return void
     */
    public function taskContinue(): void
    {
        $directory = $this->getDirectory();
        if (!$directory) {
            throw new RuntimeException('Not Found', 404);
        }

        $this->data['route'] = '/' . trim($this->data['route'] ?? '', '/');
        $route = trim($this->data['route'], '/');

        $object = $this->getObject($route);
        $authorized = $object && $object->isAuthorized('create', 'admin', $this->user);

        if (!$authorized && !$directory->isAuthorized('create', 'admin', $this->user)) {
            $this->setRedirect($this->referrerRoute->toString(true));

            throw new RuntimeException($this->admin::translate('PLUGIN_ADMIN.INSUFFICIENT_PERMISSIONS_FOR_TASK') . ' add.', 403);
        }

        $folder = $this->data['folder'] ?? null;
        $title = $this->data['title'] ?? null;
        if ($title) {
            $this->data['header']['title'] = $this->data['title'];
            unset($this->data['title']);
        }
        if (null !== $folder && 0 === strpos($folder, '@slugify-')) {
            $folder = \Grav\Plugin\Admin\Utils::slug($this->data[substr($folder, 9)] ?? '');
        }
        if (!$folder) {
            $folder = \Grav\Plugin\Admin\Utils::slug($title) ?: '';
        }
        $folder = ltrim($folder, '_');
        if ($folder === '' || mb_strpos($folder, '/') !== false) {
            throw new RuntimeException('Creating page failed: bad folder name', 400);
        }

        if (isset($this->data['name']) && strpos($this->data['name'], 'modular/') === 0) {
            $this->data['header']['body_classes'] = 'modular';
            $folder = '_' . $folder;
        }
        $this->data['folder'] = $folder;

        unset($this->data['blueprint']);
        $key = trim("{$route}/{$folder}", '/');
        if ($directory->getObject($key)) {
            throw new RuntimeException("Page '/{$key}' already exists!", 403);
        }

        $max = 0;
        if (isset($this->data['visible'])) {
            $auto = $this->data['visible'] === '';
            $visible = (bool)($this->data['visible'] ?? false);
            unset($this->data['visible']);

            // Empty string on visible means auto.
            if ($auto || $visible) {
                /** @var PageObject $parent */
                $parent = $route ? $directory->getObject($route) : $directory->getIndex()->getRoot();
                $children = $parent ? $parent->children()->visible() : [];
                $max = $auto ? 0 : 1;
                foreach ($children as $child) {
                    $max = max($max, (int)$child->order());
                }
            }

            $this->data['order'] = $max ? $max + 1 : false;
        }

        $formatter = new YamlFormatter();
        $this->data['frontmatter'] = $formatter->encode($this->data['header'] ?? []);
        $this->data['lang'] = $this->getLanguage();

        $this->object = $directory->createObject($this->data, $key);

        /** @var FlexForm $form */
        $form = $this->object->getForm();

        // Reset form, we are starting from scratch.
        $form->reset();

        /** @var FlexFormFlash $flash */
        $flash = $form->getFlash();
        $flash->setUrl($this->getFlex()->adminRoute($this->object));
        $flash->save(true);

        // Store the name and route of a page, to be used pre-filled defaults of the form in the future
        $this->admin->session()->lastPageName  = $this->data['name'] ?? '';
        $this->admin->session()->lastPageRoute = $this->data['route'] ?? '';

        $this->setRedirect($flash->getUrl());
    }

    /**
     * Save page as a new copy.
     *
     * Route: /pages
     *
     * @return bool True if the action was performed.
     * @throws RuntimeException
     */
    protected function taskCopy(): bool
    {
        try {
            $object = $this->getObject();
            if (!$object || !$object->exists()) {
                throw new RuntimeException('Not Found', 404);
            }

            // Pages are a special case.
            $parent = $object instanceof PageInterface ? $object->parent() : $object;
            if (null === $parent || ($parent instanceof FlexAuthorizeInterface && !$parent->isAuthorized('create', 'admin', $this->user))) {
                throw new RuntimeException($this->admin::translate('PLUGIN_ADMIN.INSUFFICIENT_PERMISSIONS_FOR_TASK') . ' copy.',
                    403);
            }

            $object = $object->createCopy();

            $this->setRedirect($this->getFlex()->adminRoute($object));

        } catch (RuntimeException $e) {
            $this->admin->setMessage('Copy Failed: ' . $e->getMessage(), 'error');
            $this->setRedirect($this->referrerRoute->toString(true), 302);
        }

        return true;
    }

    /**
     * $data['route'] = $this->grav['uri']->param('route');
     * $data['sortby'] = $this->grav['uri']->param('sortby', null);
     * $data['filters'] = $this->grav['uri']->param('filters', null);
     * $data['page'] $this->grav['uri']->param('page', true);
     * $data['base'] = $this->grav['uri']->param('base');
     * $initial = (bool) $this->grav['uri']->param('initial');
     *
     * @return ResponseInterface
     * @throws RequestException
     * @TODO: Move pages specific logic
     */
    protected function actionGetLevelListing(): ResponseInterface
    {
        /** @var PageInterface|FlexObjectInterface $object */
        $object = $this->getObject($this->id ?? '');

        if (!$object || !method_exists($object, 'getLevelListing')) {
            throw new RuntimeException('Not Found', 404);
        }

        $request = $this->getRequest();
        $data = $request->getParsedBody();

        if (!isset($data['field'])) {
            throw new RequestException($request, 'Bad Request', 400);
        }

        // Base64 decode the route
        $data['route'] = isset($data['route']) ? base64_decode($data['route']) : null;
        $data['filters'] = json_decode($options['filters'] ?? '{}', true) + ['type' => ['root', 'dir']];

        $initial = $data['initial'] ?? null;
        if ($initial) {
            $data['leaf_route'] = $data['route'];
            $data['route'] = null;
            $data['level'] = 1;
        }

        [$status, $message, $response,$route] = $object->getLevelListing($data);

        $json = [
            'status'  => $status,
            'message' => $this->admin::translate($message ?? 'PLUGIN_ADMIN.NO_ROUTE_PROVIDED'),
            'route' => $route,
            'initial' => (bool)$initial,
            'data' => array_values($response)
        ];

        return $this->createJsonResponse($json, 200);
    }

    /**
     * $data['route'] = $this->grav['uri']->param('route');
     * $data['sortby'] = $this->grav['uri']->param('sortby', null);
     * $data['filters'] = $this->grav['uri']->param('filters', null);
     * $data['page'] $this->grav['uri']->param('page', true);
     * $data['base'] = $this->grav['uri']->param('base');
     * $initial = (bool) $this->grav['uri']->param('initial');
     *
     * @return ResponseInterface
     * @throws RequestException
     * @TODO: Move pages specific logic
     */
    protected function actionListLevel(): ResponseInterface
    {
        try {
            /** @var PageInterface|FlexObjectInterface $object */
            $object = $this->getObject('');

            if (!$object || !method_exists($object, 'getLevelListing')) {
                throw new RuntimeException('Not Found', 404);
            }

            $directory = $object->getFlexDirectory();
            if (!$directory->isAuthorized('list', 'admin', $this->user)) {
                throw new RuntimeException($this->admin::translate('PLUGIN_ADMIN.INSUFFICIENT_PERMISSIONS_FOR_TASK') . ' getLevelListing.',
                    403);
            }

            $request = $this->getRequest();
            $data = $request->getParsedBody();

            // Base64 decode the route
            $data['route'] = isset($data['route']) ? base64_decode($data['route']) : null;
            $data['filters'] = ($data['filters'] ?? []) + ['type' => ['dir']];
            $data['lang'] = $this->getLanguage();

            // Display root if permitted.
            $action = $directory->getConfig('admin.views.configure.authorize') ?? $directory->getConfig('admin.configure.authorize') ?? 'admin.super';
            if ($this->user->authorize($action)) {
                $data['filters']['type'][] = 'root';
            }

            $initial = $data['initial'] ?? null;
            if ($initial) {
                $data['leaf_route'] = $data['route'];
                $data['route'] = null;
                $data['level'] = 1;
            }

            [$status, $message, $response, $route] = $object->getLevelListing($data);

            $json = [
                'status'  => $status,
                'message' => $this->admin::translate($message ?? 'PLUGIN_ADMIN.NO_ROUTE_PROVIDED'),
                'route' => $route,
                'initial' => (bool)$initial,
                'data' => array_values($response)
            ];
        } catch (Exception $e) {
            return $this->createErrorResponse($e);
        }

        return $this->createJsonResponse($json, 200);
    }

    /**
     * @return ResponseInterface
     */
    public function taskReset(): ResponseInterface
    {
        $key = $this->id;

        $object = $this->getObject($key);
        if (!$object) {
            throw new RuntimeException('Not Found', 404);
        }

        /** @var FlexForm $form */
        $form = $this->getForm($object);
        $flash = $form->getFlash();
        $flash->delete();

        return $this->createRedirectResponse($this->referrerRoute->toString(true));
    }

    /**
     * @return bool
     */
    public function taskSaveas(): bool
    {
        return $this->taskSave();
    }

    /**
     * @return bool
     */
    public function taskSave(): bool
    {
        $key = $this->id;

        try {
            $object = $this->getObject($key);
            if (!$object) {
                throw new RuntimeException('Not Found', 404);
            }

            if ($object->exists()) {
                if (!$object->isAuthorized('update', 'admin', $this->user)) {
                    throw new RuntimeException($this->admin::translate('PLUGIN_ADMIN.INSUFFICIENT_PERMISSIONS_FOR_TASK') . ' save.',
                        403);
                }
            } else {
                if (!$object->isAuthorized('create', 'admin', $this->user)) {
                    throw new RuntimeException($this->admin::translate('PLUGIN_ADMIN.INSUFFICIENT_PERMISSIONS_FOR_TASK') . ' save.',
                        403);
                }
            }
            $grav = Grav::instance();

            /** @var ServerRequestInterface $request */
            $request = $grav['request'];

            /** @var FlexForm $form */
            $form = $this->getForm($object);
            $form->handleRequest($request);
            $error = $form->getError();
            $errors = $form->getErrors();
            if ($errors) {
                if ($error) {
                    $this->admin->setMessage($error, 'error');
                }

                foreach ($errors as $field => $list) {
                    foreach ((array)$list as $message) {
                        $this->admin->setMessage($message, 'error');
                    }
                }
                throw new RuntimeException('Form validation failed, please check your input');
            }
            if ($error) {
                throw new RuntimeException($error);
            }

            $object = $form->getObject();
            $objectKey = $object->getKey();

            $this->admin->setMessage($this->admin::translate('PLUGIN_ADMIN.SUCCESSFULLY_SAVED'), 'info');

            // Set route to point to the current page.
            if (!$this->redirect) {
                $postAction = $request->getParsedBody()['_post_entries_save'] ?? 'edit';
                if ($postAction === 'create-new') {
                    // Create another.
                    $route = $this->referrerRoute->withGravParam('action', null)->withGravParam('', 'add');
                } elseif ($postAction === 'list') {
                    // Back to listing.
                    $route = $this->currentRoute;

                    // Remove :add action.
                    $actionAdd = $key === '' || $route->getGravParam('action') === 'add' || $route->getGravParam('') === 'add';
                    if ($actionAdd) {
                        $route = $route->withGravParam('action', null)->withGravParam('', null);
                    }

                    $len = ($key === '' ? 0 : -1) - \substr_count($key, '/');
                    if ($len) {
                        $route = $route->withRoute($route->getRoute(0, $len));
                    }
                } else {
                    // Back to edit.
                    $route = $this->currentRoute;
                    $isRoot = $object instanceof PageInterface && $object->root();
                    $hasKeyChanged = !$isRoot && $key !== $objectKey;

                    // Remove :add action.
                    $actionAdd = $key === '' || $route->getGravParam('action') === 'add' || $route->getGravParam('') === 'add';
                    if ($actionAdd) {
                        $route = $route->withGravParam('action', null)->withGravParam('', null);
                    }

                    if ($hasKeyChanged) {
                        if ($key === '') {
                            // Append new key.
                            $path = $route->getRoute() . '/' . $objectKey;
                        } elseif ($objectKey === '') {
                            // Remove old key.
                            $path = preg_replace('|/' . preg_quote($key, '|') . '$|u', '/', $route->getRoute());
                        } else {
                            // Replace old key with new key.
                            $path = preg_replace('|/' . preg_quote($key, '|') . '$|u', '/' . $objectKey, $route->getRoute());
                        }
                        $route = $route->withRoute($path);
                    }

                    // Make sure we're using the correct language.
                    $lang = null;
                    if ($object instanceof FlexTranslateInterface) {
                        $lang = $object->getLanguage();
                        $route = $route->withLanguage($lang);
                    }
                }

                $this->setRedirect($route->toString(true));
            }

            $grav = Grav::instance();
            $grav->fireEvent('onFlexAfterSave', new Event(['type' => 'flex', 'object' => $object]));
            $grav->fireEvent('gitsync');
        } catch (RuntimeException $e) {
            $this->admin->setMessage('Save Failed: ' . $e->getMessage(), 'error');

            if (isset($object, $form)) {
                $data = $form->getData();
                if (null !== $data) {
                    $flash = $form->getFlash();
                    $flash->setObject($object);
                    $flash->setData($data->toArray());
                    $flash->save();
                }
            }

            // $this->setRedirect($this->referrerRoute->withQueryParam('uid', $flash->getUniqueId())->toString(true), 302);
            $this->setRedirect($this->referrerRoute->toString(true), 302);
        }

        return true;
    }

    /**
     * @return bool
     */
    public function taskConfigure(): bool
    {
        try {
            $directory = $this->getDirectory();
            $config = $directory->getConfig('admin.views.configure.authorize') ?? $directory->getConfig('admin.configure.authorize') ?? 'admin.super';
            if (!$this->user->authorize($config)) {
                throw new RuntimeException($this->admin::translate('PLUGIN_ADMIN.INSUFFICIENT_PERMISSIONS_FOR_TASK') . ' configure.', 403);
            }

            $grav = Grav::instance();

            /** @var ServerRequestInterface $request */
            $request = $grav['request'];

            /** @var FlexForm $form */
            $form = $this->getDirectoryForm();
            $form->handleRequest($request);
            $error = $form->getError();
            $errors = $form->getErrors();
            if ($errors) {
                if ($error) {
                    $this->admin->setMessage($error, 'error');
                }

                foreach ($errors as $field => $list) {
                    foreach ((array)$list as $message) {
                        $this->admin->setMessage($message, 'error');
                    }
                }
                throw new RuntimeException('Form validation failed, please check your input');
            }
            if ($error) {
                throw new RuntimeException($error);
            }

            $this->admin->setMessage($this->admin::translate('PLUGIN_ADMIN.SUCCESSFULLY_SAVED'), 'info');

            if (!$this->redirect) {
                $this->referrerRoute = $this->currentRoute;

                $this->setRedirect($this->referrerRoute->toString(true));
            }
        } catch (RuntimeException $e) {
            $this->admin->setMessage('Save Failed: ' . $e->getMessage(), 'error');
            $this->setRedirect($this->referrerRoute->toString(true), 302);
        }

        return true;
    }

    /**
     * @return bool
     */
    public function taskMediaList(): bool
    {
        try {
            $response = $this->forwardMediaTask('action', 'media.list');

            $this->admin->json_response = json_decode($response->getBody(), false);
        } catch (Exception $e) {
            die($e->getMessage());
        }

        return true;
    }

    /**
     * @return bool
     */
    public function taskMediaUpload(): bool
    {
        try {
            $response = $this->forwardMediaTask('task', 'media.upload');

            $this->admin->json_response = json_decode($response->getBody(), false);
        } catch (Exception $e) {
            die($e->getMessage());
        }

        return true;
    }

    /**
     * @return bool
     */
    public function taskMediaDelete(): bool
    {
        try {
            $response = $this->forwardMediaTask('task', 'media.delete');

            $this->admin->json_response = json_decode($response->getBody(), false);
        } catch (Exception $e) {
            die($e->getMessage());
        }

        return true;
    }

    /**
     * @return bool
     */
    public function taskListmedia(): bool
    {
        return $this->taskMediaList();
    }

    /**
     * @return bool
     */
    public function taskAddmedia(): bool
    {
        try {
            $response = $this->forwardMediaTask('task', 'media.copy');

            $this->admin->json_response = json_decode($response->getBody(), false);
        } catch (Exception $e) {
            die($e->getMessage());
        }

        return true;
    }

    /**
     * @return bool
     */
    public function taskDelmedia(): bool
    {
        try {
            $response = $this->forwardMediaTask('task', 'media.remove');

            $this->admin->json_response = json_decode($response->getBody(), false);
        } catch (Exception $e) {
            die($e->getMessage());
        }

        return true;
    }

    /**
     * @return bool
     * @deprecated Do not use
     */
    public function taskFilesUpload(): bool
    {
        throw new RuntimeException('Task filesUpload should not be called!');
    }

    /**
     * @param string|null $filename
     * @return bool
     * @deprecated Do not use
     */
    public function taskRemoveMedia($filename = null): bool
    {
        throw new RuntimeException('Task removeMedia should not be called!');
    }

    /**
     * @return bool
     */
    public function taskGetFilesInFolder(): bool
    {
        try {
            $response = $this->forwardMediaTask('action', 'media.picker');

            $this->admin->json_response = json_decode($response->getBody(), false);
        } catch (Exception $e) {
            $this->admin->json_response = ['success' => false, 'error' => $e->getMessage()];
        }

        return true;
    }

    /**
     * @param string $type
     * @param string $name
     * @return ResponseInterface
     */
    protected function forwardMediaTask(string $type, string $name): ResponseInterface
    {
        $route = Uri::getCurrentRoute()->withGravParam('task', null)->withGravParam($type, $name);
        $object = $this->getObject();

        /** @var ServerRequest $request */
        $request = $this->grav['request'];
        $request = $request
            ->withAttribute('type', $this->target)
            ->withAttribute('key', $this->id)
            ->withAttribute('storage_key', $object && $object->exists() ? $object->getStorageKey() : null)
            ->withAttribute('route', $route)
            ->withAttribute('forwarded', true)
            ->withAttribute('object', $object);

        $controller = new MediaController();
        $controller->setUser($this->user);

        return $controller->handle($request);
    }

    /**
     * @return Flex
     */
    protected function getFlex(): Flex
    {
        return Grav::instance()['flex_objects'];
    }

    public static function getInstance(): ?AdminController
    {
        return self::$instance;
    }

    /**
     * AdminController constructor.
     */
    public function __construct()
    {
        self::$instance = $this;

        $this->grav = Grav::instance();
        $this->admin = Grav::instance()['admin'];
        $this->user = $this->admin->user;

        $this->active = false;

        // Controller can only be run in admin.
        if (!Utils::isAdminPlugin()) {
            return;
        }

        [, $location, $target] = $this->grav['admin']->getRouteDetails();
        if (!$location) {
            return;
        }
        $target = \is_string($target) ? urldecode($target) : null;
        $id = null;

        /** @var Uri $uri */
        $uri = $this->grav['uri'];
        $routeObject = $uri::getCurrentRoute();
        $routeObject->withExtension('');

        $routes = $this->getAdminRoutes();

        // Match route to the flex directory.
        $path = '/' . ($target ? $location . '/' . $target : $location) . '/';
        $test = $routes[$path] ?? null;

        $directory = null;
        if ($test)  {
            $directory = $test['directory'];
            $location = trim($path, '/');
            $target = '';
        } else {
            krsort($routes);
            foreach ($routes as $route => $test) {
                if (strpos($path, $route) === 0) {
                    $directory = $test['directory'];
                    $location = trim($route, '/');
                    $target = trim(substr($path, strlen($route)), '/');
                    break;
                }
                $test = null;
            }
        }

        if ($directory) {
            // Redirect aliases.
            if (isset($test['redirect'])) {
                $route = $test['redirect'];
                // If directory route starts with alias and path continues, stop.
                if ($target && strpos($route, $location) === 0) {
                    // We are not in a directory.
                    return;
                }
                $redirect = '/' . $route . ($target ? '/' . $target : '');
                $this->setRedirect($redirect, 302);
                $this->redirect();
            } elseif (isset($test['action'])) {
                $routeObject = $routeObject->withGravParam('', $test['action']);
            }

            $id = $target;
            $target = $directory->getFlexType();
        } else {
            // We are not in a directory.
            if ($location !== 'flex-objects') {
                return;
            }
            $array = explode('/', $target, 2);
            $target = array_shift($array) ?: null;
            $id = array_shift($array) ?: null;
        }

        // Post
        $post = $_POST ?? [];
        if (isset($post['data'])) {
            $this->data = $this->getPost($post['data']);
            unset($post['data']);
        }

        // Task
        $task = $this->grav['task'];
        if ($task) {
            $this->task = $task;
        }

        $this->post = $this->getPost($post);
        $this->location = 'flex-objects';
        $this->target = $target;
        $this->id = $this->post['id'] ?? $id;
        $this->action = $this->post['action'] ?? $uri->param('action', null) ?? $uri->param('', null) ?? $routeObject->getGravParam('');
        $this->active = true;
        $this->currentRoute = $uri::getCurrentRoute();
        $this->route = $routeObject;

        $base = $this->grav['pages']->base();
        if ($base) {
            // Fix referrer for sub-folder multi-site setups.
            $referrer = preg_replace('`^' . $base . '`', '', $uri->referrer());
        } else {
            $referrer = $uri->referrer();
        }
        $this->referrerRoute = $referrer ? RouteFactory::createFromString($referrer) : $this->currentRoute;
    }

    public function getInfo(): array
    {
        if (!$this->isActive()) {
            return [];
        }

        $class = AdminController::class;
        return [
            'controller' => [
                'name' => $this->location,
                'instance' => [$class, 'getInstance']
            ],
            'location' => $this->location,
            'type' => $this->target,
            'key' => $this->id,
            'action' => $this->action,
            'task' => $this->task
        ];
    }

    /**
     * Performs a task or action on a post or target.
     *
     * @return ResponseInterface|bool|null
     */
    public function execute()
    {
        if (!$this->user->authorize('admin.login')) {
            // TODO: improve
            return false;
        }

        $params = [];

        $event = new Event(
            [
                'type' => &$this->target,
                'key' => &$this->id,
                'directory' => &$this->directory,
                'collection' => &$this->collection,
                'object' => &$this->object
            ]
        );
        $this->grav->fireEvent("flex.{$this->target}.admin.route", $event);

        if ($this->isFormSubmit()) {
            $form = $this->getForm();
            $this->nonce_name = $form->getNonceName();
            $this->nonce_action = $form->getNonceAction();
        }

        // Handle Task & Action
        if ($this->task) {
            // validate nonce
            if (!$this->validateNonce()) {
                $e = new RequestException($this->getRequest(), 'Page Expired', 400);

                $this->close($this->createErrorResponse($e));
            }
            $method = $this->task_prefix . ucfirst(str_replace('.', '', $this->task));

            if (!method_exists($this, $method)) {
                $method = $this->task_prefix . 'Default';
            }

        } elseif ($this->target) {
            if (!$this->action) {
                if ($this->id) {
                    $this->action = 'edit';
                    $params[] = $this->id;
                } else {
                    $this->action = 'list';
                }
            }
            $method = 'action' . ucfirst(strtolower(str_replace('.', '', $this->action)));

            if (!method_exists($this, $method)) {
                $method = $this->action_prefix . 'Default';
            }
        } else {
            return null;
        }

        if (!method_exists($this, $method)) {
            return null;
        }

        try {
            $response = $this->{$method}(...$params);
        } catch (RequestException $e) {
            $response = $this->createErrorResponse($e);
        } catch (RuntimeException $e) {
            // If task fails to run, redirect back to the previous page and display the error message.
            if ($this->task && !$this->redirect) {
                $this->setRedirect($this->referrerRoute->toString(true));
            }
            $response = null;
            $this->setMessage($e->getMessage(), 'error');
        }

        if ($response instanceof ResponseInterface) {
            $this->close($response);
        }

        // Grab redirect parameter.
        $redirect = $this->post['_redirect'] ?? null;
        unset($this->post['_redirect']);

        // Redirect if requested.
        if ($redirect) {
            $this->setRedirect($redirect);
        }

        return $response;
    }

    /**
     * @return bool
     */
    public function isFormSubmit(): bool
    {
        return (bool)($this->post['__form-name__'] ?? null);
    }

    /**
     * @param FlexObjectInterface|null $object
     * @return FlexFormInterface
     */
    public function getForm(FlexObjectInterface $object = null): FlexFormInterface
    {
        $object = $object ?? $this->getObject();
        if (!$object) {
            throw new RuntimeException('Not Found', 404);
        }

        $formName = $this->post['__form-name__'] ?? '';
        $name = '';
        $uniqueId = null;

        // Get the form name. This defines the blueprint which is being used.
        if (strpos($formName, '-')) {
            $parts = explode('-', $formName);
            $prefix = $parts[0] ?? '';
            $type = $parts[1] ?? '';
            if ($prefix === 'flex' && $type === $object->getFlexType()) {
                $name = $parts[2] ?? '';
                if ($name === 'object') {
                    $name = '';
                }
                $uniqueId = $this->post['__unique_form_id__'] ?? null;
            }
        }

        $options = [
            'unique_id' => $uniqueId,
        ];

        return $object->getForm($name, $options);
    }

    /**
     * @param FlexDirectoryInterface|null $directory
     * @return FlexFormInterface
     */
    public function getDirectoryForm(FlexDirectoryInterface $directory = null): FlexFormInterface
    {
        $directory = $directory ?? $this->getDirectory();
        if (!$directory) {
            throw new RuntimeException('Not Found', 404);
        }

        $formName = $this->post['__form-name__'] ?? '';
        $name = '';
        $uniqueId = null;

        // Get the form name. This defines the blueprint which is being used.
        if (strpos($formName, '-')) {
            $parts = explode('-', $formName);
            $prefix = $parts[0] ?? '';
            $type = $parts[1] ?? '';
            if ($prefix === 'flex_conf' && $type === $directory->getFlexType()) {
                $name = $parts[2] ?? '';
                $uniqueId = $this->post['__unique_form_id__'] ?? null;
            }
        }

        $options = [
            'unique_id' => $uniqueId,
        ];

        return $directory->getDirectoryForm($name, $options);
    }

    /**
     * @param string|null $key
     * @return FlexObjectInterface|null
     */
    public function getObject(string $key = null): ?FlexObjectInterface
    {
        if (null === $this->object) {
            $key = $key ?? $this->id;
            $object = false;

            $directory = $this->getDirectory();
            if ($directory) {
                // FIXME: hack for pages.
                if ($key === '_root') {
                    $index = $directory->getIndex();
                    if ($index instanceof PageIndex) {
                        $object = $index->getRoot();
                    }
                } elseif (null !== $key) {
                    $object = $directory->getObject($key) ?? $directory->createObject([], $key);
                } elseif ($this->action === 'add') {
                    $object = $directory->createObject([], '');
                }

                if ($object instanceof FlexTranslateInterface && $this->isMultilang()) {
                    $language = $this->getLanguage();
                    if ($object->hasTranslation($language)) {
                        $object = $object->getTranslation($language);
                    } elseif (!in_array('', $object->getLanguages(true), true)) {
                        $object->language($language);
                    }
                }
            }

            if (is_callable([$object, 'refresh'])) {
                $object->refresh();
            }

            $this->object = $object;
        }

        return $this->object ?: null;
    }

    /**
     * @param string|null $type
     * @return FlexDirectoryInterface|null
     */
    public function getDirectory(string $type = null): ?FlexDirectoryInterface
    {
        $type = $type ?? $this->target;

        if ($type && null === $this->directory) {
            $this->directory = Grav::instance()['flex_objects']->getDirectory($type);
        }

        return $this->directory;
    }

    /**
     * @return FlexCollectionInterface|null
     */
    public function getCollection(): ?FlexCollectionInterface
    {
        if (null === $this->collection) {
            $directory = $this->getDirectory();

            $this->collection = $directory ? $directory->getCollection() : null;
        }

        return $this->collection;
    }

    /**
     * @param string $msg
     * @param string $type
     * @return void
     */
    public function setMessage(string $msg, string $type = 'info'): void
    {
        /** @var Message $messages */
        $messages = $this->grav['messages'];
        $messages->add($msg, $type);
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return (bool) $this->active;
    }

    /**
     * @param string $location
     * @return void
     */
    public function setLocation(string $location): void
    {
        $this->location = $location;
    }

    /**
     * @return string|null
     */
    public function getLocation(): ?string
    {
        return $this->location;
    }

    /**
     * @param string $action
     * @return void
     */
    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    /**
     * @return string|null
     */
    public function getAction(): ?string
    {
        return $this->action;
    }

    /**
     * @param string $task
     * @return void
     */
    public function setTask(string $task): void
    {
        $this->task = $task;
    }

    /**
     * @return string|null
     */
    public function getTask(): ?string
    {
        return $this->task;
    }

    /**
     * @param string $target
     * @return void
     */
    public function setTarget(string $target): void
    {
        $this->target = $target;
    }

    /**
     * @return string|null
     */
    public function getTarget(): ?string
    {
        return $this->target;
    }

    /**
     * @param string $id
     * @return void
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Sets the page redirect.
     *
     * @param string $path The path to redirect to
     * @param int    $code The HTTP redirect code
     * @return void
     */
    public function setRedirect(string $path, int $code = 303): void
    {
        $this->redirect     = $path;
        $this->redirectCode = (int)$code;
    }

    /**
     * Redirect to the route stored in $this->redirect
     *
     * @return void
     */
    public function redirect(): void
    {
        $this->admin->redirect($this->redirect, $this->redirectCode);
    }

    /**
     * @return array
     */
    public function getAdminRoutes(): array
    {
        if (null === $this->adminRoutes) {
            $routes = [];
            /** @var FlexDirectoryInterface $directory */
            foreach ($this->getFlex()->getDirectories() as $directory) {
                $config = $directory->getConfig('admin');
                if (!$directory->isEnabled() || !empty($config['disabled'])) {
                    continue;
                }

                // Alias under flex-objects (always exists, but can be redirected).
                $routes["/flex-objects/{$directory->getFlexType()}/"] = ['directory' => $directory];

                $route = $config['router']['path'] ?? $config['menu']['list']['route'] ?? null;
                if ($route) {
                    $routes[$route . '/'] = ['directory' => $directory];
                }

                $redirects = (array)($config['router']['redirects'] ?? null);
                foreach ($redirects as $redirectFrom => $redirectTo) {
                    $redirectFrom .= '/';
                    if (!isset($routes[$redirectFrom])) {
                        $routes[$redirectFrom] = ['directory' => $directory, 'redirect' => $redirectTo];
                    }
                }

                $actions = (array)($config['router']['actions'] ?? null);
                foreach ($actions as $name => $action) {
                    if (is_array($action)) {
                        $path = $action['path'] ?? null;
                    } else {
                        $path = $action;
                    }
                    if ($path !== null) {
                        $routes[$path . '/'] = ['directory' => $directory, 'action' => $name];
                    }
                }
            }

            $this->adminRoutes = $routes;
        }

        return $this->adminRoutes;
    }

    /**
     * Return true if multilang is active
     *
     * @return bool True if multilang is active
     */
    protected function isMultilang(): bool
    {
        /** @var Language $language */
        $language = $this->grav['language'];

        return $language->enabled();
    }

    protected function validateNonce(): bool
    {
        $nonce_action = $this->nonce_action;
        $nonce = $this->post[$this->nonce_name] ?? $this->grav['uri']->param($this->nonce_name) ?? $this->grav['uri']->query($this->nonce_name);

        if (!$nonce) {
            $nonce = $this->post['admin-nonce'] ?? $this->grav['uri']->param('admin-nonce') ?? $this->grav['uri']->query('admin-nonce');
            $nonce_action = 'admin-form';
        }

        return $nonce && Utils::verifyNonce($nonce, $nonce_action);
    }

    /**
     * Prepare and return POST data.
     *
     * @param array $post
     * @return array
     */
    protected function getPost(array $post): array
    {
        if (!is_array($post)) {
            return [];
        }

        unset($post['task']);

        // Decode JSON encoded fields and merge them to data.
        if (isset($post['_json'])) {
            $post = array_replace_recursive($post, $this->jsonDecode($post['_json']));
            unset($post['_json']);
        }

        $post = $this->cleanDataKeys($post);

        return $post;
    }

    /**
     * @param ResponseInterface $response
     */
    protected function close(ResponseInterface $response): void
    {
        $this->grav->close($response);
    }

    /**
     * Recursively JSON decode data.
     *
     * @param  array $data
     * @return array
     */
    protected function jsonDecode(array $data)
    {
        foreach ($data as &$value) {
            if (is_array($value)) {
                $value = $this->jsonDecode($value);
            } else {
                $value = json_decode($value, true);
            }
        }

        return $data;
    }

    /**
     * @param array $source
     * @return array
     */
    protected function cleanDataKeys($source = []): array
    {
        $out = [];

        if (is_array($source)) {
            foreach ($source as $key => $value) {
                $key = str_replace(['%5B', '%5D'], ['[', ']'], $key);
                if (is_array($value)) {
                    $out[$key] = $this->cleanDataKeys($value);
                } else {
                    $out[$key] = $value;
                }
            }
        }

        return $out;
    }

    /**
     * @return string
     */
    public function getLanguage(): string
    {
        return $this->admin->language ?? '';
    }

    /**
     * @return Config
     */
    protected function getConfig(): Config
    {
        return $this->grav['config'];
    }

    /**
     * @return ServerRequestInterface
     */
    protected function getRequest(): ServerRequestInterface
    {
        return $this->grav['request'];
    }
}
