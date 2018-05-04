<?php
namespace Grav\Plugin\FlexObjects;

use Doctrine\Common\Collections\Criteria;
use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Twig\Twig;
use Grav\Framework\ContentBlock\HtmlBlock;
use Grav\Framework\Object\ObjectCollection;
use Grav\Plugin\FlexObjects\Interfaces\FlexCollectionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class FlexCollection
 * @package Grav\Plugin\FlexObjects
 */
class FlexCollection extends ObjectCollection implements FlexCollectionInterface
{
    /** @var FlexType */
    private $flexType;

    /**
     * @return array
     */
    public static function getCachedMethods()
    {
        return [
            'getTypePrefix' => true,
            'getType' => true,
            'getFlexType' => true,
            'getCacheKey' => true,
            'getCacheChecksum' => true,
            'getTimestamp' => true,
            'hasProperty' => true,
            'getProperty' => true,
            'hasNestedProperty' => true,
            'getNestedProperty' => true,
        ];
    }

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
     * Creates a new instance from the specified elements.
     *
     * This method is provided for derived classes to specify how a new
     * instance should be created when constructor semantics have changed.
     *
     * @param array $elements Elements.
     *
     * @return static
     * @throws \InvalidArgumentException
     */
    protected function createFrom(array $elements)
    {
        return new static($elements, $this->flexType);
    }

    /**
     * @return string
     */
    protected function getTypePrefix()
    {
        return 'c.';
    }

    /**
     * @param bool $prefix
     * @return string
     */
    public function getType($prefix = true)
    {
        $type = $prefix ? $this->getTypePrefix() : '';

        return $type . $this->flexType->getType();
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
        $debugger->startTimer('flex-collection-' . $this->getType(false), 'Render Collection ' . $this->getType(false));

        $cache = null;
        if (!$context) {
            $key = $this->getCacheKey() . '.' . $layout;
            $cache = $this->flexType->getCache();
        }

        try {
            $data = $cache ? $cache->get($key) : null;

            $block = $data ? HtmlBlock::fromArray($data) : null;
        } catch (InvalidArgumentException $e) {
            $block = null;
        } catch (\InvalidArgumentException $e) {
            $block = null;
        }

        if (!$block) {
            $block = HtmlBlock::create($key);

            $grav->fireEvent('onFlexCollectionRender', new Event([
                'collection' => $this,
                'layout' => &$layout,
                'context' => &$context
            ]));

            $output = $this->getTemplate($layout)->render(
                ['grav' => $grav, 'block' => $block, 'collection' => $this, 'layout' => $layout] + $context
            );

            $block->setContent($output);

            try {
                $cache && $cache->set($key, $block->toArray());
            } catch (InvalidArgumentException $e) {
            }
        }

        $debugger->stopTimer('flex-collection-' . $this->getType(false));

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
     * @return string
     */
    public function getCacheKey()
    {
        return $this->getType(true) . '.' . sha1(json_encode($this->call('getKey')));
    }

    /**
     * @return string
     */
    public function getCacheChecksum()
    {
        return sha1(json_encode($this->getTimestamps()));
    }

    /**
     * @return int[]
     */
    public function getTimestamps()
    {
        return $this->call('getTimestamp');
    }

    /**
     * @param string $value
     * @param string $field
     * @return object|null
     */
    public function find($value, $field = 'id')
    {
        if ($value) foreach ($this as $element) {
            if (strtolower($element->getProperty($field)) === strtolower($value)) {
                return $element;
            }
        }

        return null;
    }

    /**
     * @param array $ordering
     * @return FlexCollection
     */
    public function orderBy(array $ordering)
    {
        $criteria = Criteria::create()->orderBy($ordering);

        return $this->matching($criteria);
    }

    /**
     * @param int $start
     * @param int|null $limit
     * @return FlexCollection
     */
    public function limit($start, $limit = null)
    {
        return $this->createFrom($this->slice($start, $limit));
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
            return $twig->twig()->resolveTemplate(["flex-objects/layouts/{$this->getType(false)}/collection/{$layout}.html.twig"]);
        } catch (\Twig_Error_Loader $e) {
            return $twig->twig()->resolveTemplate(["flex-objects/layouts/404.html.twig"]);
        }
    }
}
