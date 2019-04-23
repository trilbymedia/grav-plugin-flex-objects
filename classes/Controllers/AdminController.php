<?php

namespace Grav\Plugin\FlexObjects\Controllers;

use Grav\Common\Grav;
use Grav\Common\Uri;
use Grav\Framework\Flex\FlexForm;
use Grav\Framework\Flex\FlexObject;
use Grav\Plugin\FlexObjects\Flex;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class AdminController
 * @package Grav\Plugin\FlexObjects
 */
class AdminController extends SimpleController
{
    /**
     * Delete Directory
     */
    public function taskDefault()
    {
        $type = $this->target;
        $key = $this->id;

        $directory = $this->getDirectory($type);
        $object = $directory && null !== $key ? $directory->getIndex()->get($key) : null;

        if ($object) {
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
        $type = $this->target;
        $key = $this->id;

        $directory = $this->getDirectory($type);
        $object = $directory && null !== $key ? $directory->getIndex()->get($key) : null;

        if ($object) {
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
            $type = $this->target;

            $options = [
                'url' => $uri->path(),
                'page' => $uri->query('page'),
                'limit' => $uri->query('per_page'),
                'sort' => $uri->query('sort'),
                'search' => $uri->query('filter'),
                'filters' => $uri->query('filters'),
            ];

            $table = $this->getFlex()->getDataTable($type, $options);

            header('Content-Type: application/json');
            echo json_encode($table);
            die();
        }
    }

    /**
     * Delete Directory
     */
    public function taskDelete()
    {
        $type = $this->target;
        $key = $this->id;
        $object = null;

        try {
            $directory = $this->getDirectory($type);
            $object = $directory && null !== $key ? $directory->getIndex()->get($key) : null;

            if ($object) {
                if (!$object->isAuthorized('delete')) {
                    throw new \RuntimeException($this->admin->translate('PLUGIN_ADMIN.INSUFFICIENT_PERMISSIONS_FOR_TASK') . ' delete.', 403);
                }

                $object->delete();

                $this->admin->setMessage($this->admin->translate(['PLUGIN_ADMIN.REMOVED_SUCCESSFULLY', 'Directory Entry']), 'info');

                $this->setRedirect($this->getFlex()->adminRoute($directory));

                $grav = Grav::instance();
                $grav->fireEvent('onFlexAfterDelete', new Event(['type' => 'flex', 'object' => $object]));
                $grav->fireEvent('gitsync');
            }
        } catch (\RuntimeException $e) {
            $this->admin->setMessage('Delete Failed: ' . $e->getMessage(), 'error');
            $this->setRedirect($this->getFlex()->adminRoute($object ?? $directory ?? null), 302);
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
                    throw new \RuntimeException($this->admin->translate('PLUGIN_ADMIN.INSUFFICIENT_PERMISSIONS_FOR_TASK') . ' save.',
                        403);
                }
            } else {
                if (!$object->isAuthorized('create')) {
                    throw new \RuntimeException($this->admin->translate('PLUGIN_ADMIN.INSUFFICIENT_PERMISSIONS_FOR_TASK') . ' save.',
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

            $this->admin->setMessage($this->admin->translate('PLUGIN_ADMIN.SUCCESSFULLY_SAVED'), 'info');

            if (!$this->redirect) {
                $this->setRedirect($this->getFlex()->adminRoute($object));
            }

            $grav = Grav::instance();
            $grav->fireEvent('onFlexAfterSave', new Event(['type' => 'flex', 'object' => $object]));
            $grav->fireEvent('gitsync');
        } catch (\RuntimeException $e) {
            $this->admin->setMessage('Save Failed: ' . $e->getMessage(), 'error');
            $this->setRedirect($this->getFlex()->adminRoute($object ?? $directory ?? null), 302);
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

    protected function processPostEntriesSave($var)
    {
        switch ($var) {
            case 'create-new':
                $this->setRedirect($this->getFlex()->adminRoute($this->target) . '/action:add');
                $saved_option = $var;
                break;
            case 'list':
                $this->setRedirect($this->getFlex()->adminRoute($this->target));
                $saved_option = $var;
                break;
            case 'edit':
            default:
                $id = $this->id;
                if ($id) {
                    $this->setRedirect($this->getFlex()->adminRoute($this->target) . '/' . $id);
                }
                $saved_option = 'edit';
                break;
        }

        $this->grav['session']->post_entries_save = $saved_option;
    }

    /**
     * Dynamic method to 'get' data types
     *
     * @param string $type
     * @param string|null $id
     * @return mixed
     */
    protected function get($type, $id = null)
    {
        $collection = $this->getDirectory($type)->getIndex();

        return null !== $id ? $collection[$id] : $collection;
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
     * @return FlexObject
     */
    public function data($type)
    {
        $type = explode('/', $type, 2)[1] ?? null;
        $id = $this->id;

        $directory = $this->getDirectory($type);

        return $id ? $directory->getObject($id) : $directory->createObject([], '__new__');
    }
}
