<?php
namespace Grav\Plugin\FlexObjects\Controllers;

use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Common\Utils;
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

    protected $task_prefix = 'task';
    protected $action_prefix = 'action';

    /**
     * @param Plugin   $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->grav = Grav::instance();
        $this->active = false;

        $uri = $this->grav['uri'];

        $post = !empty($_POST) ? $_POST : [];
        if (isset($post['data'])) {
            $this->data = $this->getPost($post['data']);
            unset($post['data']);
        }
        $this->post  = $this->getPost($post);

        // Ensure the controller should be running
        if (Utils::isAdminPlugin()) {
            list($base, $location, $target) = $this->grav['admin']->getRouteDetails();

            // return null if this is not running
            if ($location !== $plugin->name)  {
                return;
            }
            $this->location = $location;
            $this->action = !empty($this->post['action']) ? $this->post['action'] : $uri->param('action');
            $this->id = !empty($this->post['id']) ? $this->post['id'] : $uri->param('id');
            $this->target = $target;
            $this->active = true;
            $this->admin = Grav::instance()['admin'];
        }

        $task = !empty($post['task']) ? $post['task'] : $uri->param('task');
        if ($task && ($this->location === $plugin->name)) {
            $this->task = $task;
            $this->active = true;
        }
    }

    /**
     * Performs a task or action on a post or target.
     *
     * @return bool|mixed
     */
    public function execute()
    {
        $success = false;
        $params = [];

        // Handle Task & Action
        if ($this->post && $this->task) {
            // validate nonce
            if (!$this->validateNonce()) {
                return false;
            }
            $method = $this->task_prefix . ucfirst($this->task);

            $this->handlePostProcesses();

        } elseif ($this->target) {
            if (!$this->action) {
                if ($this->id) {
                    $this->action = 'edit';
                    $params[] = $this->id;
                } else {
                    $this->action = 'list';
                }
            }
            $method = 'task' . ucfirst(strtolower($this->action));
        } else {
            return null;
        }

        if (!method_exists($this, $method)) {
            return null;
        }

        try {
            $success = call_user_func_array([$this, $method], $params);
        } catch (\RuntimeException $e) {
            $this->setMessage($e->getMessage(), 'error');
        }

        // Grab redirect parameter.
        $redirect = isset($this->post['_redirect']) ? $this->post['_redirect'] : null;
        unset($this->post['_redirect']);

        // Redirect if requested.
        if ($redirect) {
            $this->setRedirect($redirect);
        }

        return $success;
    }

    protected function prepareData(array $data)
    {
        $type = trim("{$this->location}/{$this->target}", '/');
        $data = $this->data($type, $data);

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

    protected function handlePostProcesses()
    {
        if (is_array($this->data)) {
            foreach ($this->data as $key => $value) {
                if (Utils::startsWith($key, '_')) {
                    $method = 'process' . $this->grav['inflector']->camelize($key);

                    if (method_exists($this, $method)) {
                        try {
                            $this->{$method}($value);
                        } catch (\RuntimeException $e) {
                            $this->setMessage($e->getMessage(), 'error');
                        }
                    }
                    unset ($this->data[$key]);
                }
            }
        }
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
}
