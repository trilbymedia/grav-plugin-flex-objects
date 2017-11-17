<?php
namespace Grav\Plugin\FlexDirectory\Entities;

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
            $this->types[$type] = new Type($type, $config);
        }
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
