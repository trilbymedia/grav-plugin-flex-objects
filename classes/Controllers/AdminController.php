<?php

namespace Grav\Plugin\FlexObjects\Controllers;

use Grav\Common\Grav;
use Grav\Plugin\FlexObjects\Flex;
use Grav\Plugin\FlexObjects\FlexDirectory;
use Grav\Plugin\FlexObjects\FlexObject;
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
    public function taskDelete()
    {
        $type = $this->target;
        $id = $this->id;

        $directory = $this->getDirectory($type);
        $object = null !== $id ? $directory->getIndex()->get($id) : null;

        if ($object) {
            if (!$object->authorize('delete')) {
                throw new \RuntimeException($this->admin->translate('PLUGIN_ADMIN.INSUFFICIENT_PERMISSIONS_FOR_TASK') . ' delete.', 403);
            }

            $object = $directory->remove($id);

            $this->admin->setMessage($this->admin->translate(['PLUGIN_ADMIN.REMOVED_SUCCESSFULLY', 'Directory Entry']), 'info');

            $this->setRedirect($this->getFlex()->adminRoute($directory));

            $grav = Grav::instance();
            $grav->fireEvent('onAdminAfterDelete', new Event(['object' => $object]));
            $grav->fireEvent('gitsync');
        }
    }

    public function taskSave()
    {
        $type = $this->target;
        $id = $this->id;

        $directory = $this->getDirectory($type);

        $object = $id ? $directory->getIndex()->get($id) : null;
        if (null === $object) {
            $object = $directory->createObject($this->data, $id, true);
        }

        if ($object->exists()) {
            if (!$object->authorize('update')) {
                throw new \RuntimeException($this->admin->translate('PLUGIN_ADMIN.INSUFFICIENT_PERMISSIONS_FOR_TASK') . ' save.', 403);
            }
        } else {
            if (!$object->authorize('create')) {
                throw new \RuntimeException($this->admin->translate('PLUGIN_ADMIN.INSUFFICIENT_PERMISSIONS_FOR_TASK') . ' save.', 403);
            }
        }

        // if no id param, assume new, generate an ID
        $object = $directory->update($this->data, $id, true);

        if ($object) {
            $this->admin->setMessage($this->admin->translate('PLUGIN_ADMIN.SUCCESSFULLY_SAVED'), 'info');

            if (!$this->redirect && !$id) {
                $this->setRedirect($this->getFlex()->adminRoute($object));
            }

            $grav = Grav::instance();
            $grav->fireEvent('onAdminAfterSave', new Event(['object' => $object]));
            $grav->fireEvent('gitsync');
        }

        return $object ? true : false;
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
