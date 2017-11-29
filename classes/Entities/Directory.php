<?php
namespace Grav\Plugin\FlexDirectory\Entities;

use Grav\Common\Filesystem\Folder;

/**
 * Class Directory
 * @package Grav\Plugin\FlexDirectory\Entities
 */
class Directory implements \Countable
{
    protected $types = [];

    public function __construct(array $types = [])
    {
        foreach ($types as $type => $config) {
            $this->types[$type] = new Type($type, $config, true);
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
        $all = Folder::all('blueprints://flex-directory', $params);

        foreach ($all as $url) {
            $type = basename($url, '.yaml');
            if (!isset($directories[$type])) {
                $directories[$type] = new Type($type, $url);
            }
        }

        ksort($directories);

        return $directories;
    }

    public function getDirectories()
    {
        return $this->types;
    }

    public function getDirectory($type = null)
    {
        if (!$type) {
            return reset($this->types) ?: null;
        }

        return isset($this->types[$type]) ? $this->types[$type] : null;
    }

    public function count()
    {
        return count($this->types);
    }
}
