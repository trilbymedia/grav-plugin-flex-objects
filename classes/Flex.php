<?php
namespace Grav\Plugin\FlexObjects;

use Grav\Common\Filesystem\Folder;

/**
 * Class Flex
 * @package Grav\Plugin\FlexObjects
 */
class Flex implements \Countable
{
    /** @var array|FlexDirectory[] */
    protected $types = [];

    public function __construct(array $types = [], array $config)
    {
        $defaults = ['enabled' => true] + $config['object'];

        foreach ($types as $type => $blueprint) {
            $this->types[$type] = new FlexDirectory($type, $blueprint, $defaults);
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
                $directories[$type] = new FlexDirectory($type, $url);
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
     * @return FlexDirectory|null
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
        return \count($this->types);
    }
}
