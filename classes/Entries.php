<?php
namespace Grav\Plugin\FlexDirectory;

use Grav\Common\Grav;

class Entries extends SimpleDataContainer
{
    public static function all()
    {
        $list = Grav::instance()['flex-entries']->getData()->toArray();
        $options = array_map(function($data) {
           return $data['first_name'] . ' ' . $data['last_name'];
        }, $list);

        return $options;
    }

    protected function prepareDataItem(&$data)
    {

    }

    public function saveDataItem($id, $item)
    {
        parent::saveDataItem($id, $item);
    }

}
