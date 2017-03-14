<?php

namespace Grav\Plugin\FlexDirectory;

use Grav\Common\Grav;
use Grav\Common\Helpers\Base32;
use Grav\Common\Utils;

/**
 * Class Controller
 * @package Grav\Plugin\Directory
 */
class AdminController extends SimpleController
{

    /**
     * Dynamic method to 'get' data types
     *
     * @param $type
     * @param $id
     * @return mixed
     */
    public function get($type, $id)
    {
        $method = 'get' . ucfirst($type);
        return call_user_func([$this, $method], $id);
    }

    /**
     * Get Directory
     *
     * @param null $id
     * @return mixed
     */
    public function getEntries($id = null)
    {
        if ($id) {
            $obj = Grav::instance()['flex-entries']->filterData($id);
        } else {
            $obj = Grav::instance()['flex-entries']->getData();
        }

        return $obj;
    }

    /**
     * Delete Directory
     */
    public function deleteEntries()
    {
        $id = Grav::instance()['uri']->param('id');

        $status = Grav::instance()['flex-entries']->deleteDataItem($id);
        if ($status) {
            $this->admin->setMessage($this->admin->translate(['PLUGIN_ADMIN.REMOVED_SUCCESSFULLY', 'Directory Entry']), 'info');
            $list_page = $this->location . '/' . $this->target;
            $this->setRedirect($list_page);

            Grav::instance()->fireEvent('gitsync');
        }
    }

    public function taskSave()
    {
        $type = $this->target;

        // dynamically change this
        $data_type = Grav::instance()['flex-' . $type];

        // Get the instructor in question
        $id = Grav::instance()['uri']->param('id');
        $new = false;

        // if no id param, assume new, generate an ID
        if (!$id) {
            $new = true;
            $id = strtolower(Base32::encode(Utils::generateRandomString(10)));
        }

        $obj = $this->get($type, $id);
        $obj->merge($this->data);

        $status = $this->saveObjectItem($id, $obj, $data_type);

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
        $saved_option = 'edit';

        switch ($var) {
            case 'create-new':
                $this->setRedirect($this->location . '/' . $this->target . '/action:add');
                $saved_option = $var;
                break;
            case 'list':
                $this->setRedirect($this->location . '/' . $this->target);
                $saved_option = $var;
                break;
        }

        $this->grav['session']->post_entries_save = $saved_option;
    }

    protected function data($type)
    {
        static $data = [];

        $obj = null;

        if (isset($data[$type])) {
            return $data[$type];
        }

        switch ($type) {
            case 'flex-directory/entries':
                // load data as $obj
                $id = str_replace('.json', '', Grav::instance()['uri']->param('id'));
                $obj = Grav::instance()['flex-entries']->filterData($id);
                $data[$type] = $obj;
                break;

            default:
                return null;
        }

        return $obj;
    }

    public function taskRemoveFileFromBlueprint()
    {
        // admin method
        $this->taskRemoveMedia();

        $id = Grav::instance()['uri']->param('id');
        $field = $this->grav['uri']->param('field');
        $type      = $this->grav['uri']->param('type');
        $path = base64_decode($this->grav['uri']->param('path'));

        // dynamically change this
        $data_type = Grav::instance()['flex-' . $type];
        $method = 'get' . ucfirst($type);
        $obj = $this->$method($id);

        $files = $obj->{$field};

        if ($files) {
            foreach ($files as $key => $value) {
                if ($key == $path) {
                    unset($files[$key]);
                }
            }
        }

        $obj->set($field, $files);
        $this->saveObjectItem($id, $obj, $data_type);

        echo json_encode([
            'status'  => 'success',
            'message' => $this->admin->translate('PLUGIN_ADMIN.REMOVE_SUCCESSFUL')
        ]);

        exit;
    }
}
