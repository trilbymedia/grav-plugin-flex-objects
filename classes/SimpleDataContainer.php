<?php
namespace Grav\Plugin\FlexDirectory;

use Grav\Common\File\CompiledJsonFile;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Data;
use Symfony\Component\Yaml\Yaml;

/**
 * Class SimpleDataContainer
 * @package Grav\Plugin\FlexDirectory
 */
class SimpleDataContainer implements \Countable
{

    protected $file;
    protected $extension;
    protected $data_file;
    protected $data_path;
    protected $blueprint_file;
    protected $blueprint;
    protected $cache_id;

    protected $data = [];
    protected $data_raw;

    /**
     * Initialize with location of data file
     *
     * @param $data_file
     * @param string|object $blueprint
     * @internal param $file
     */
    public function __construct($data_file, $blueprint)
    {
        $this->data_file = $data_file;
        $this->extension = pathinfo($data_file, PATHINFO_EXTENSION);
        if (is_string($blueprint)) {
            $this->blueprint_file = $blueprint;
        } else {
            $this->blueprint = $blueprint;
        }
        $this->data_path = Grav::instance()['locator']->findResource($data_file, true, true);
        $this->cache_id = substr(md5($data_file), 0, 10);
    }

    public function load()
    {
        if (!$this->data) {

            if (!$this->file) {
                switch ($this->extension) {
                    case 'json':
                        $this->file = CompiledJsonFile::instance($this->data_path);
                        break;
                    case 'yaml':
                        $this->file = CompiledYamlFile::instance($this->data_path);
                        break;
                }
            }

            if ($this->blueprint) {
                $blueprint = $this->blueprint;
            } else {
                $blueprint = (new Blueprint($this->blueprint_file))->load();
                if ($blueprint->get('type') === 'flex-directory') {
                    $blueprint->extend((new Blueprint('plugin://flex-directory/blueprints/flex-directory.yaml'))->load(), true);
                }
                $blueprint->init();
            }
            $data = $this->file->content(null, true);

            $obj = new Data($data, $blueprint);
            $obj->file($this->file);

            $this->data = $obj;


        }
        return $this->data;
    }

    /**
     * Filter the data array based on a filter value and optional $key field
     *
     * @param $filter
     * @param null $key
     * @return Data
     */
    public function filterData($filter, $key = null)
    {
        $this->load();

        $data = $this->data->toArray();
        $new_data = [];

        if ($key) {
            foreach ($data as $pkey => $value) {
                if (isset($value[$key])) {
                    if ($value[$key] == $filter) {
                        $new_data = $value;
                        break;
                    }
                }
            }
        } elseif (isset($data[$filter])) {
            $new_data = $data[$filter];
        }

        if ($new_data) {
            $this->prepareDataItem($new_data);
            return new Data($new_data, $this->data->blueprints());
        }
        // none found, so create an empty one
        return new Data([], $this->data->blueprints());
    }

    /**
     * Get the data
     */
    public function getData()
    {
        $this->load();
        return $this->data;
    }

    public function count()
    {
        $this->load();
        return count($this->data);
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function saveData($data)
    {
        if ($data) {
            self::setData($data);
        }
    }

    public function save()
    {
        $data_raw = false;

        switch ($this->extension) {
            case 'json':
                $data_raw = json_encode($this->data->toArray(), JSON_PRETTY_PRINT);
                break;
            case 'yaml':
                $data_raw = Yaml::dump($this->data->toArray());
                break;
        }

        if ($data_raw && $this->file) {
            $this->file->raw($data_raw);
            $this->file->save();
        }
    }

    public function deleteDataItem($id)
    {
        $this->load();
        if (isset($this->data[$id])) {
            unset($this->data[$id]);
            $this->save();
            return true;
        }
        return false;
    }

    public function saveDataItem($id, $item)
    {
        $this->load();
        $this->data[$id] = $item->toArray();
        $this->save();
        return true;
    }

    protected function prepareDataItem(&$data)
    {
    }
}
