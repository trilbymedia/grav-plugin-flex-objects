<?php
namespace Grav\Plugin\FlexDirectory\Entities;

use Grav\Common\Data\Blueprint;
use Grav\Common\Grav;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class Type
{
    protected $type;
    protected $blueprint_file;
    protected $blueprint;
    protected $blueprint_base;
    protected $collection;

    public function __construct($type, $blueprint_file)
    {
        $this->type = $type;
        $this->blueprint_file = $blueprint_file;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getTitle()
    {
        return $this->getBlueprint()->get('title', ucfirst($this->getType()));
    }

    public function getDescription()
    {
        return $this->getBlueprint()->get('description', '');
    }

    public function getStorage($resolve = false)
    {
        $file = $this->getConfig('data/storage/file', 'user://data/flex-directory/' . $this->getType() . '.json');

        if ($resolve) {
            $grav = Grav::instance();
            /** @var UniformResourceLocator $locator */
            $locator = $grav['locator'];

            $file = $locator->findResource($file, false, true);
        }

        return $file;
    }

    public function getConfig($name = null, $default = null)
    {
        $path = 'config' . ($name ? '/' . $name : '');

        return $this->getBlueprint()->get($path, $default);
    }

    public function getBlueprint()
    {
        if (null === $this->blueprint_base) {
            $this->blueprint_base = (new Blueprint('plugin://flex-directory/blueprints/flex-directory.yaml'))->load();
        }
        if (null === $this->blueprint) {
            $this->blueprint = (new Blueprint($this->blueprint_file))->load();
            if ($this->blueprint->get('type') === 'flex-directory') {
                $this->blueprint->extend($this->blueprint_base, true);
            }
            $this->blueprint->init();
            if (empty($this->blueprint->fields())) {
                throw new \RuntimeException(sprintf('Blueprint for %s is missing', $this->type));
            }
        }

        return $this->blueprint;
    }

    public function getCollection()
    {
        if (null === $this->collection) {
            $this->collection = new Collection($this->getStorage(), $this->blueprint);
        }

        return $this->collection;
    }
}
