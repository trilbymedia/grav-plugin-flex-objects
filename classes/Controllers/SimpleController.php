<?php

namespace Grav\Plugin\FlexObjects\Controllers;

use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Common\Utils;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Interfaces\FlexFormInterface;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Plugin\Admin\AdminBaseController;
use RocketTheme\Toolbox\Session\Message;

/**
 * Class SimpleController
 * @package Grav\Plugin\FlexObjects
 */
abstract class SimpleController extends AdminBaseController
{
    protected $action;
    protected $location;
    protected $target;
    protected $id;
    protected $active;
    protected $blueprints;
    protected $object;

    protected $nonce_name = 'admin-nonce';
    protected $nonce_action = 'admin-form';

    protected $task_prefix = 'task';
    protected $action_prefix = 'action';

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
            $type = $this->target;
            $key = $this->id;
            $object = false;

            $directory = $this->getDirectory($type);
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
     * @return FlexDirectory
     */
    protected function getDirectory($type)
    {
        return Grav::instance()['flex_objects']->getDirectory($type);
    }

    public function prepareData(array $data = null)
    {
        $type = trim("{$this->location}/{$this->target}", '/');
        $data = $this->data($type, $data ?? $_POST);

        return $data;
    }

    public function saveObjectItem($id, $obj, $data_type)
    {
        try {
            $obj->validate();
        } catch (\Exception $e) {
            $this->admin->setMessage($e->getMessage(), 'error');
            return false;
        }

        $obj->filter();

        if ($obj) {
            if (Utils::isAdminPlugin()) {
                $obj = $this->storeFiles($obj);
            }
            $data_type->saveDataItem($id, $obj);
            return true;
        }

        return false;
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

    protected function validateNonce()
    {
        $nonce_action = $this->nonce_action;
        $nonce = $this->post[$this->nonce_name] ??  $this->grav['uri']->param($this->nonce_name) ?? $this->grav['uri']->query($this->nonce_name);

        if (!$nonce) {
            $nonce = $this->post['admin-nonce'] ??  $this->grav['uri']->param('admin-nonce') ?? $this->grav['uri']->query('admin-nonce');
            $nonce_action = 'admin-form';
        }

        if (!$nonce || !Utils::verifyNonce($nonce, $nonce_action)) {
            return false;
        }

        return true;
    }
}
