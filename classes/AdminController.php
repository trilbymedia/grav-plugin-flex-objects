<?php

namespace Grav\Plugin\FlexDirectory;

use Grav\Common\Grav;
use Grav\Common\Helpers\Base32;
use Grav\Common\Utils;
use Grav\Plugin\FlexDirectory\Entities\Collection;

/**
 * Class Controller
 * @package Grav\Plugin\Directory
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

        $status = $this->getCollection($type)->deleteDataItem($id);
        if ($status) {
            $this->admin->setMessage($this->admin->translate(['PLUGIN_ADMIN.REMOVED_SUCCESSFULLY', 'Directory Entry']), 'info');
            $list_page = $this->location . '/' . $type;
            $this->setRedirect($list_page);

            Grav::instance()->fireEvent('gitsync');
        }
    }

    public function taskSave()
    {
        $type = $this->target;
        $id = Grav::instance()['uri']->param('id');

        // if no id param, assume new, generate an ID
        $new = false;
        if (!$id) {
            $new = true;
            do {
                $id = strtolower(Base32::encode(Utils::generateRandomString(10)));
            } while (($obj = $this->get($type, $id)) && $obj->toArray());
        } else {
            $obj = $this->get($type, $id);
        }

        $obj->merge($this->data);

        $status = $this->saveObjectItem($id, $obj, $this->getCollection($type));

        if ($status) {
            $this->admin->setMessage($this->admin->translate('PLUGIN_ADMIN.SUCCESSFULLY_SAVED'), 'info');

            if (!$this->redirect && $new) {
                $edit_page = $this->location . '/' . $this->target . '/id:' . $id;
                $this->setRedirect($edit_page);
            }

            Grav::instance()->fireEvent('gitsync');
        }

        return $status;
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
        $collection = $this->getCollection($type);

        if ($id) {
            return $collection->filterData($id);
        }

        return $collection->getData();
    }

    /**
     * @param $type
     * @return Collection
     */
    protected function getCollection($type)
    {
        return Grav::instance()['flex_directory']->getDirectory($type)->getCollection();
    }
}
