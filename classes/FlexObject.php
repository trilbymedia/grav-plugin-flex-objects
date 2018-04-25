<?php
namespace Grav\Plugin\FlexObjects;

use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Page\Medium\Medium;
use Grav\Common\Page\Medium\MediumFactory;
use Grav\Common\Twig\Twig;
use Grav\Framework\ContentBlock\HtmlBlock;
use Grav\Framework\Object\LazyObject;
use Grav\Plugin\FlexObjects\Interfaces\FlexObjectInterface;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class FlexObject
 * @package Grav\Plugin\FlexObjects\Entities
 */
class FlexObject extends LazyObject implements FlexObjectInterface
{
    /** @var FlexType */
    private $flexType;

    /**
     * @param array $elements
     * @param string $key
     * @param FlexType $type
     * @throws \InvalidArgumentException
     */
    public function __construct(array $elements = [], $key, FlexType $type)
    {
        $this->flexType = $type;

        $blueprint = $this->getFlexType()->getBlueprint();
        $elements = $blueprint->filter($elements);

        parent::__construct($elements, $key);
    }

    /**
     * @param bool $prefix
     * @return string
     */
    public function getType($prefix = true)
    {
        return ($prefix ? static::$prefix : '') . $this->flexType->getType();
    }

    /**
     * @param string $layout
     * @param array $context
     * @return HtmlBlock
     * @throws \Exception
     * @throws \Throwable
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Syntax
     */
    public function render($layout = 'default', array $context = [])
    {
        $grav = Grav::instance();

        /** @var Debugger $debugger */
        //$debugger = $grav['debugger'];
        //$debugger->startTimer('flex-object-' . $this->getType(), 'Render Object ' . $this->getType());

        $block = new HtmlBlock();

        $grav->fireEvent('onFlexObjectRender', new Event([
            'object' => $this,
            'layout' => &$layout,
            'context' => &$context
        ]));

        $output = $this->getTemplate($layout)->render(
            ['grav' => $grav, 'block' => $block, 'object' => $this, 'layout' => $layout] + $context
        );

        $block->setContent($output);

        //$debugger->stopTimer('flex-object-' . $this->getType());

        return $block;
    }

    /**
     * @return FlexType
     */
    public function getFlexType()
    {
        return $this->flexType;
    }

    /**
     * Form field compatibility.
     *
     * @param string $name
     * @return mixed
     */
    public function value($name)
    {
        return $this->getNestedProperty($name);
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->getElements();
    }

    /**
     * @param string $uri
     * @return Medium|null
     */
    protected function createMedium($uri)
    {
        $grav = Grav::instance();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];

        $file = $uri ? $locator->findResource($uri) : null;

        return $file ? MediumFactory::fromFile($file) : null;
    }

    /**
     * @param string $type
     * @param string $property
     * @return FlexCollection
     */
    protected function getCollectionByProperty($type, $property)
    {
        $type = $this->getDirectory($type);
        $collection = $type->getCollection();
        $list = $this->getNestedProperty($property) ?: [];

        $collection = $collection->filter(function ($object) use ($list) { return \in_array($object->id, $list, true); });

        // TODO: Remove workaround for Doctrine Collections 1.3.
        $collection->setFlexType($type);

        return $collection;
    }

    /**
     * @param $type
     * @return FlexType
     * @throws \RuntimeException
     */
    protected function getDirectory($type)
    {
        /** @var FlexObjects $flex */
        $flex = Grav::instance()['flex_objects'];
        $directory = $flex->getDirectory($type);
        if (!$directory) {
            throw new \RuntimeException(ucfirst($type). ' directory does not exist!');
        }

        return $directory;
    }

    /**
     * @param string $layout
     * @return \Twig_Template
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Syntax
     */
    protected function getTemplate($layout)
    {
        $grav = Grav::instance();

        /** @var Twig $twig */
        $twig = $grav['twig'];

        try {
            return $twig->twig()->resolveTemplate(["flex-objects/layouts/{$this->getType()}/object/{$layout}.html.twig"]);
        } catch (\Twig_Error_Loader $e) {
            return $twig->twig()->resolveTemplate(["flex-objects/layouts/404.html.twig"]);
        }
    }
}
