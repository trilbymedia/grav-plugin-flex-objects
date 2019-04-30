<?php

namespace Grav\Plugin\FlexObjects\Admin;

use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Common\Uri;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Common\Utils;
use Grav\Framework\File\Formatter\CsvFormatter;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\FlexForm;
use Grav\Framework\Flex\Interfaces\FlexCollectionInterface;
use Grav\Framework\Flex\Interfaces\FlexFormInterface;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Object\Interfaces\ObjectInterface;
use Grav\Plugin\Admin\Admin;
use Grav\Plugin\FlexObjects\Controllers\MediaController;
use Grav\Plugin\FlexObjects\Flex;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\Session\Message;

/**
 * Class AdminController
 * @package Grav\Plugin\FlexObjects
 */
class AdminController
{
    /** @var Grav */
    public $grav;

    /** @var string */
    public $view;

    /** @var string */
    public $task;

    /** @var string */
    public $route;

    /** @var array */
    public $post;

    /** @var array|null */
    public $data;

    /** @var \Grav\Common\Uri */
    protected $uri;

    /** @var Admin */
    protected $admin;

    /** @var string */
    protected $redirect;

    /** @var int */
    protected $redirectCode;

    protected $currentUri;
    protected $referrerUri;
    protected $action;
    protected $location;
    protected $target;
    protected $id;
    protected $active;
    protected $object;
    protected $collection;
    protected $directory;

    protected $nonce_name = 'admin-nonce';
    protected $nonce_action = 'admin-form';

    protected $task_prefix = 'task';
    protected $action_prefix = 'action';

    /**
     * Delete Directory
     */
    public function taskDefault()
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
            } catch (\Exception $e) {
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
     * Delete Directory
     */
    public function actionDefault()
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
            } catch (\Exception $e) {
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

    public function actionList()
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

            header('Content-Type: application/json');
            echo json_encode($table);
            die();
        }
    }

    public function actionCsv()
    {
        $collection = $this->getCollection();
        if (!$collection) {
            throw new \RuntimeException('Internal Error', 500);
        }

        $list = [];
        /** @var ObjectInterface $object */
        foreach ($collection as $object) {
            if (method_exists($object, 'csvSerialize')) {
                $data = $object->csvSerialize();
                if ($data) {
                    $list[] = $data;
                }
            } else {
                $list[] = $object->jsonSerialize();
            }
        }

        $csv = new CsvFormatter();

        header('Content-Type: text/x-csv');
        header('Content-Disposition: inline; filename="export.csv"');
        header('Cache-Control: max-age=0');
        // If you're serving to IE over SSL, then the following may be needed
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0
        ob_end_clean();

        echo $csv->encode($list);
        die();
    }

    /**
     * Delete Directory
     */
    public function taskDelete()
    {
        $type = $this->target;

        try {
            $object = $this->getObject();

            if ($object && $object->exists()) {
                if (!$object->isAuthorized('delete')) {
                    throw new \RuntimeException($this->admin::translate('PLUGIN_ADMIN.INSUFFICIENT_PERMISSIONS_FOR_TASK') . ' delete.', 403);
                }

                $object->delete();

                $this->admin->setMessage($this->admin::translate(['PLUGIN_ADMIN.REMOVED_SUCCESSFULLY', 'Directory Entry']), 'info');

                $redirect = $this->referrerUri;
                if ($this->currentUri === $this->referrerUri) {
                    $redirect = dirname($this->currentUri);
                }

                $this->setRedirect($redirect);

                $grav = Grav::instance();
                $grav->fireEvent('onFlexAfterDelete', new Event(['type' => 'flex', 'object' => $object]));
                $grav->fireEvent('gitsync');
            }
        } catch (\RuntimeException $e) {
            $this->admin->setMessage('Delete Failed: ' . $e->getMessage(), 'error');

            $this->setRedirect($this->referrerUri, 302);
        }

        return $object ? true : false;
    }

    public function taskSave()
    {
        $type = $this->target;
        $key = $this->id;

        try {
            $directory = $this->getDirectory($type);
            if (!$directory) {
                throw new \RuntimeException('Not Found', 404);
            }
            $object = $key ? $directory->getIndex()->get($key) : null;
            if (null === $object) {
                $object = $directory->createObject($this->data, $key ?? '', true);
            }

            if ($object->exists()) {
                if (!$object->isAuthorized('update')) {
                    throw new \RuntimeException($this->admin::translate('PLUGIN_ADMIN.INSUFFICIENT_PERMISSIONS_FOR_TASK') . ' save.',
                        403);
                }
            } else {
                if (!$object->isAuthorized('create')) {
                    throw new \RuntimeException($this->admin::translate('PLUGIN_ADMIN.INSUFFICIENT_PERMISSIONS_FOR_TASK') . ' save.',
                        403);
                }
            }
            $grav = Grav::instance();

            /** @var ServerRequestInterface $request */
            $request = $grav['request'];

            /** @var FlexForm $form */
            $form = $this->getForm($object);

            $form->handleRequest($request);
            $errors = $form->getErrors();
            if ($errors) {
                foreach ($errors as $error) {
                    $this->admin->setMessage($error, 'error');
                }

                throw new \RuntimeException('Form validation failed, please check your input');
            }
            $object = $form->getObject();

            $this->admin->setMessage($this->admin::translate('PLUGIN_ADMIN.SUCCESSFULLY_SAVED'), 'info');

            if (!$this->redirect) {
                if (strpos($this->referrerUri, 'action:add')) {
                    $this->referrerUri = $this->currentUri . '/' . $object->getKey();
                }
                $this->setRedirect($this->referrerUri);
            }

            $grav = Grav::instance();
            $grav->fireEvent('onFlexAfterSave', new Event(['type' => 'flex', 'object' => $object]));
            $grav->fireEvent('gitsync');
        } catch (\RuntimeException $e) {
            $this->admin->setMessage('Save Failed: ' . $e->getMessage(), 'error');
            $this->setRedirect($this->referrerUri, 302);
        }

        return true;
    }

    public function taskMediaList()
    {
        try {
            $response = $this->forwardMediaTask('action', 'media.list');

            $this->admin->json_response = json_decode($response->getBody());
        } catch (\Exception $e) {
            die($e->getMessage());
        }

        return true;
    }

    public function taskMediaUpload()
    {
        try {
            $response = $this->forwardMediaTask('task', 'media.upload');

            $this->admin->json_response = json_decode($response->getBody());
        } catch (\Exception $e) {
            die($e->getMessage());
        }

        return true;
    }

    public function taskMediaDelete()
    {
        try {
            $response = $this->forwardMediaTask('task', 'media.delete');

            $this->admin->json_response = json_decode($response->getBody());
        } catch (\Exception $e) {
            die($e->getMessage());
        }

        return true;
    }

    public function taskListmedia()
    {
        return $this->taskMediaList();
    }

    public function taskAddmedia()
    {
        return $this->taskMediaUpload();
    }

    public function taskDelmedia()
    {
        return $this->taskMediaDelete();
    }

    public function taskFilesUpload()
    {
        throw new \RuntimeException('Should not be called!');
    }

    public function taskRemoveMedia($filename = null)
    {
        throw new \RuntimeException('Should not be called!');
    }

    public function taskGetFilesInFolder()
    {
        try {
            $response = $this->forwardMediaTask('action', 'media.picker');

            $this->admin->json_response = json_decode($response->getBody());
        } catch (\Exception $e) {
            $this->admin->json_response = ['success' => false, 'error' => $e->getMessage()];
        }

        return true;
    }

    protected function forwardMediaTask(string $type, string $name)
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

        return $controller->handle($request);
    }

    /**
     * @return Flex
     */
    protected function getFlex()
    {
        return Grav::instance()['flex_objects'];
    }

    /**
     * @param string $type
     * @return FlexObjectInterface
     */
    public function data($type)
    {
        $type = explode('/', $type, 2)[1] ?? null;
        $id = $this->id;

        $directory = $this->getDirectory($type);

        return $id ? $directory->getObject($id) : $directory->createObject([], '__new__');
    }

    /**
     * @param Plugin   $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->grav = Grav::instance();
        $this->active = false;

        // Ensure the controller should be running
        if (Utils::isAdminPlugin()) {
            list(, $location, $target) = $this->grav['admin']->getRouteDetails();

            $menu = $plugin->getAdminMenu();

            // return null if this is not running
            if (!isset($menu[$location]))  {
                return;
            }

            $directory = $menu[$location]['directory'] ?? '';
            $location = 'flex-objects';
            if ($directory) {
                $id = $target;
                $target = $directory;
            } else {
                $array = explode('/', $target, 2);
                $target = array_shift($array) ?: null;
                $id = array_shift($array) ?: null;
            }

            /** @var Uri $uri */
            $uri = $this->grav['uri'];

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
            $this->location = $location;
            $this->target = $target;
            $this->id = $this->post['id'] ?? $id;
            $this->action = $this->post['action'] ?? $uri->param('action');
            $this->active = true;
            $this->admin = Grav::instance()['admin'];
            $this->currentUri = $uri->route();
            $this->referrerUri = $uri->referrer() ?: $this->currentUri;

        }
    }

    /**
     * Performs a task or action on a post or target.
     *
     * @return bool|mixed
     */
    public function execute()
    {
        /** @var UserInterface $user */
        $user = $this->grav['user'];
        if (!$user->authorize('admin.login')) {
            // TODO: improve
            return false;
        }
        $success = false;
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
                return false;
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
            $success = $this->{$method}(...$params);
        } catch (\RuntimeException $e) {
            $this->setMessage($e->getMessage(), 'error');
        }

        // Grab redirect parameter.
        $redirect = $this->post['_redirect'] ?? null;
        unset($this->post['_redirect']);

        // Redirect if requested.
        if ($redirect) {
            $this->setRedirect($redirect);
        }

        return $success;
    }

    public function isFormSubmit(): bool
    {
        return (bool)($this->post['__form-name__'] ?? null);
    }

    public function getForm(FlexObjectInterface $object = null): FlexFormInterface
    {
        $object = $object ?? $this->getObject();
        if (!$object) {
            throw new \RuntimeException('Not Found', 404);
        }

        $formName = $this->post['__form-name__'] ?? null;
        $uniqueId = $this->post['__unique_form_id__'] ?? null;

        $form = $object->getForm();
        if ($uniqueId) {
            $form->setUniqueId($uniqueId);
        }

        return $form;
    }

    /**
     * @return FlexObjectInterface|null
     */
    public function getObject(): ?FlexObjectInterface
    {
        if (null === $this->object) {
            $key = $this->id;
            $object = false;

            $directory = $this->getDirectory();
            if ($directory) {
                if (null === $key) {
                    if ($this->action === 'add') {
                        $object = $directory->createObject([]);
                    }
                } else {
                    $object = $directory->getObject($key);
                }
            }

            $this->object = $object;
        }

        return $this->object ?: null;
    }

    /**
     * @param string $type
     * @return FlexDirectory|null
     */
    public function getDirectory($type = null)
    {
        $type = $type ?? $this->target;

        if ($type && null === $this->directory) {
            $this->directory = Grav::instance()['flex_objects']->getDirectory($type);
        }

        return $this->directory;
    }

    public function getCollection(): ?FlexCollectionInterface
    {
        if (null === $this->collection) {
            $directory = $this->getDirectory();

            $this->collection = $directory ? $directory->getCollection() : null;
        }

        return $this->collection;
    }

    public function setMessage($msg, $type = 'info')
    {
        /** @var Message $messages */
        $messages = $this->grav['messages'];
        $messages->add($msg, $type);
    }

    public function isActive()
    {
        return (bool) $this->active;
    }

    public function setLocation($location)
    {
        $this->location = $location;
    }

    public function getLocation()
    {
        return $this->location;
    }

    public function setAction($action)
    {
        $this->action = $action;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function setTask($task)
    {
        $this->task = $task;
    }

    public function getTask()
    {
        return $this->task;
    }

    public function setTarget($target)
    {
        $this->target = $target;
    }

    public function getTarget()
    {
        return $this->target;
    }

    public function setId($target)
    {
        $this->id = $target;
    }

    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets the page redirect.
     *
     * @param string $path The path to redirect to
     * @param int    $code The HTTP redirect code
     */
    public function setRedirect($path, $code = 303)
    {
        $this->redirect     = $path;
        $this->redirectCode = $code;
    }

    /**
     * Redirect to the route stored in $this->redirect
     */
    public function redirect()
    {
        if (!$this->redirect) {
            return;
        }

        $base = $this->admin->base;
        $this->redirect = '/' . ltrim($this->redirect, '/');

        // Redirect contains full path, so just use it.
        if (Utils::startsWith($this->redirect, $base)) {
            $this->grav->redirect($this->redirect, $this->redirectCode);
        }

        $redirect = '';
        if ($this->isMultilang()) {
            // if base path does not already contain the lang code, add it
            $langPrefix = '/' . $this->grav['session']->admin_lang;
            if (!Utils::startsWith($base, $langPrefix . '/')) {
                $base = $langPrefix . $base;
            }

            // now the first 4 chars of base contain the lang code.
            // if redirect path already contains the lang code, and is != than the base lang code, then use redirect path as-is
            if (Utils::pathPrefixedByLangCode($base) && Utils::pathPrefixedByLangCode($this->redirect)
                && !Utils::startsWith($this->redirect, $base)
            ) {
                $redirect = $this->redirect;
            } else {
                if (!Utils::startsWith($this->redirect, $base)) {
                    $this->redirect = $base . $this->redirect;
                }
            }

        } else {
            if (!Utils::startsWith($this->redirect, $base)) {
                $this->redirect = $base . $this->redirect;
            }
        }

        if (!$redirect) {
            $redirect = $this->redirect;
        }

        $this->grav->redirect($redirect, $this->redirectCode);
    }

    /**
     * Return true if multilang is active
     *
     * @return bool True if multilang is active
     */
    protected function isMultilang()
    {
        return count($this->grav['config']->get('system.languages.supported', [])) > 1;
    }

    protected function validateNonce()
    {
        $nonce_action = $this->nonce_action;
        $nonce = $this->post[$this->nonce_name] ??  $this->grav['uri']->param($this->nonce_name) ?? $this->grav['uri']->query($this->nonce_name);

        if (!$nonce) {
            $nonce = $this->post['admin-nonce'] ??  $this->grav['uri']->param('admin-nonce') ?? $this->grav['uri']->query('admin-nonce');
            $nonce_action = 'admin-form';
        }

        return $nonce && Utils::verifyNonce($nonce, $nonce_action);
    }

    /**
     * Prepare and return POST data.
     *
     * @param array $post
     *
     * @return array
     */
    protected function getPost($post)
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
     * Recursively JSON decode data.
     *
     * @param  array $data
     *
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

    protected function cleanDataKeys($source = [])
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
}
