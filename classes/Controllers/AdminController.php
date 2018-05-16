<?php
namespace Grav\Plugin\FlexObjects\Controllers;

use Grav\Common\Grav;
use Grav\Plugin\FlexObjects\FlexDirectory;

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
        $id = Grav::instance()['uri']->param('id');

        $directory = $this->getDirectory($type);
        $object = $directory->remove($id);

        if ($object) {
            $this->admin->setMessage($this->admin->translate(['PLUGIN_ADMIN.REMOVED_SUCCESSFULLY', 'Directory Entry']), 'info');
            $list_page = $this->location . '/' . $type;
            $this->setRedirect($list_page);

            Grav::instance()->fireEvent('gitsync');
        }
    }

    public function taskSave()
    {
        $type = $this->target;
        $id = Grav::instance()['uri']->param('id') ?: null;

        $directory = $this->getDirectory($type);

        // if no id param, assume new, generate an ID
        $object = $directory->update($this->data, $id);

        if ($object) {
            $this->admin->setMessage($this->admin->translate('PLUGIN_ADMIN.SUCCESSFULLY_SAVED'), 'info');

            if (!$this->redirect && !$id) {
                $edit_page = $this->location . '/' . $this->target . '/id:' . $object->getKey();
                $this->setRedirect($edit_page);
            }

            Grav::instance()->fireEvent('gitsync');
        }

        return $object ? true : false;
    }

    protected function processPostEntriesSave($var)
    {
        switch ($var) {
            case 'create-new':
                $this->setRedirect($this->location . '/' . $this->target . '/action:add');
                $saved_option = $var;
                break;
            case 'list':
                $this->setRedirect($this->location . '/' . $this->target);
                $saved_option = $var;
                break;
            case 'edit':
            default:
                $this->setRedirect($this->location . '/' . $this->target . '/id:' . Grav::instance()['uri']->param('id'));
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
        $collection = $this->getDirectory($type)->getCollection();

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
}
