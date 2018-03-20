<?php
namespace Grav\Plugin\FlexObjects;

use Grav\Common\Filesystem\Folder;

/**
 * Class FlexObjects
 * @package Grav\Plugin\FlexObjects\Entities
 */
class FlexObjects implements \Countable
{
    /**
     * @var array|FlexType[]
     */
    protected $types = [];

    public function __construct(array $types = [])
    {
        foreach ($types as $type => $config) {
            $this->types[$type] = new FlexType($type, $config, true);
        }
    }

    public function getAll()
    {
        $params = [
            'pattern' => '|\.yaml|',
            'value' => 'Url',
            'recursive' => false
        ];

        $directories = $this->getDirectories();
        $all = Folder::all('blueprints://flex-objects', $params);

        foreach ($all as $url) {
            $type = basename($url, '.yaml');
            if (!isset($directories[$type])) {
                $directories[$type] = new FlexType($type, $url);
            }
        }

        ksort($directories);

        return $directories;
    }

    public function getDirectories()
    {
        return $this->types;
    }

    /**
     * @param string|null $type
     * @return FlexType|null
     */
    public function getDirectory($type = null)
    {
        if (!$type) {
            return reset($this->types) ?: null;
        }

        return isset($this->types[$type]) ? $this->types[$type] : null;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->types);
    }
}
