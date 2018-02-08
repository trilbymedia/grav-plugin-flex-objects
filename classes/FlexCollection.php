<?php
namespace Grav\Plugin\FlexDirectory;

use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Twig\Twig;
use Grav\Framework\ContentBlock\HtmlBlock;
use Grav\Framework\Object\ObjectCollection;
use Grav\Plugin\FlexDirectory\Interfaces\FlexCollectionInterface;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class FlexCollection
 * @package Grav\Plugin\FlexDirectory\Entities
 */
class FlexCollection extends ObjectCollection implements FlexCollectionInterface
{
    /** @var FlexType */
    private $flexType;

    /**
     * @param array $elements
     * @param FlexType $type
     * @throws \InvalidArgumentException
     */
    public function __construct(array $elements = [], FlexType $type = null)
    {
        parent::__construct($elements);

        if ($type) {
            $this->setFlexType($type)->setKey($type->getType());
        }
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
        $debugger = $grav['debugger'];
        $debugger->startTimer('flex-collection-' . $this->getType(), 'Collection ' . $this->getType());

        $block = new HtmlBlock();

        $grav->fireEvent('onFlexCollectionRender', new Event([
            'collection' => $this,
            'layout' => &$layout,
            'context' => &$context
        ]));

        $output = $this->getTemplate($layout)->render(
            ['grav' => $grav, 'block' => $block, 'collection' => $this, 'layout' => $layout] + $context
        );

        $block->setContent($output);

        $debugger->stopTimer('flex-collection-' . $this->getType());

        return $block;
    }

    /**
     * @param FlexType $type
     * @return $this
     */
    public function setFlexType(FlexType $type)
    {
        $this->flexType = $type;

        return $this;
    }

    /**
     * @return FlexType
     */
    public function getFlexType()
    {
        return $this->flexType;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        $elements = [];

        /**
         * @var string $key
         * @var FlexObject $object
         */
        foreach ($this->getElements() as $key => $object) {
            $elements[$key] = $object->jsonSerialize();
        }

        return $elements;
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
            return $twig->twig()->resolveTemplate(["flex-directory/layouts/{$this->getType()}/collection/{$layout}.html.twig"]);
        } catch (\Twig_Error_Loader $e) {
            return $twig->twig()->resolveTemplate(["flex-directory/layouts/404.html.twig"]);
        }
    }
}
