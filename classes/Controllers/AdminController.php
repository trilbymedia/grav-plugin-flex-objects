<?php

namespace Grav\Plugin\FlexObjects\Controllers;

use Grav\Common\Grav;
use Grav\Common\Uri;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\FlexObject;
use Grav\Plugin\FlexObjects\Flex;
use Nyholm\Psr7\ServerRequest;
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

    /**
     * Delete Directory
     */
    public function taskDelete()
    {
        $type = $this->target;
        $key = $this->id;

        try {
            $directory = $this->getDirectory($type);
            $object = $directory && null !== $key ? $directory->getIndex()->get($key) : null;

            if ($object) {
                if (!$object->authorize('delete')) {
                    throw new \RuntimeException($this->admin->translate('PLUGIN_ADMIN.INSUFFICIENT_PERMISSIONS_FOR_TASK') . ' delete.', 403);
                }

                $object = $directory->remove($key);

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
                $object = $directory->createObject($this->data, $key, true);
            }

            if ($object->exists()) {
                if (!$object->authorize('update')) {
                    throw new \RuntimeException($this->admin->translate('PLUGIN_ADMIN.INSUFFICIENT_PERMISSIONS_FOR_TASK') . ' save.',
                        403);
                }
            } else {
                if (!$object->authorize('create')) {
                    throw new \RuntimeException($this->admin->translate('PLUGIN_ADMIN.INSUFFICIENT_PERMISSIONS_FOR_TASK') . ' save.',
                        403);
                }
            }

            // if no id param, assume new, generate an ID
            $object = $directory->update($this->data, $key);

            if ($object) {
                $this->admin->setMessage($this->admin->translate('PLUGIN_ADMIN.SUCCESSFULLY_SAVED'), 'info');

                if (!$this->redirect) {
                    $this->setRedirect($this->getFlex()->adminRoute($object));
                }

                $grav = Grav::instance();
                $grav->fireEvent('onFlexAfterSave', new Event(['type' => 'flex', 'object' => $object]));
                $grav->fireEvent('gitsync');
            }
        } catch (\RuntimeException $e) {
            $this->admin->setMessage('Save Failed: ' . $e->getMessage(), 'error');
            $this->setRedirect($this->getFlex()->adminRoute($object ?? $directory ?? null), 302);
        }

        return $object ? true : false;
    }

    public function taskListmedia()
    {
        $response = $this->forwardMediaTask('action', 'media.list');

        $this->admin->json_response = json_decode($response->getBody());

        return true;
    }

    public function taskAddmedia()
    {
        $response = $this->forwardMediaTask('task', 'media.upload');

        $this->admin->json_response = json_decode($response->getBody());

        return true;
    }

    public function taskDelmedia()
    {
        $response = $this->forwardMediaTask('task', 'media.delete');

        $this->admin->json_response = json_decode($response->getBody());

        return true;
    }

    public function taskGetFilesInFolder()
    {
        $response = $this->forwardMediaTask('action', 'media.picker');

        $this->admin->json_response = json_decode($response->getBody());

        return true;
    }

    protected function forwardMediaTask(string $type, string $name)
    {
        $route = Uri::getCurrentRoute()->withGravParam('task', null)->withGravParam($type, $name);

        /** @var ServerRequest $request */
        $request = $this->grav['request'];
        $request = $request
            ->withAttribute('type', $this->target)
            ->withAttribute('key', $this->id)
            ->withAttribute('route', $route);

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
     * @param $type
     * @param $id
     * @return mixed
     */
    protected function get($type, $id = null)
    {
        $collection = $this->getDirectory($type)->getIndex();

        return null !== $id ? $collection[$id] : $collection;
    }

    /**
     * @param string $type
     * @return FlexDirectory
     */
    protected function getDirectory($type)
    {
        return Grav::instance()['flex_objects']->getDirectory($type);
    }

    /**
     * @return Flex
     */
    protected function getFlex()
    {
        return Grav::instance()['flex_objects'];
    }

    /**
     * @param $type
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
