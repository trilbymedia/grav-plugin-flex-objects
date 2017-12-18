<?php
namespace Grav\Plugin\FlexDirectory;

use Grav\Framework\Object\ArrayObject;
use Grav\Plugin\FlexDirectory\Interfaces\FlexObjectInterface;

/**
 * Class FlexObject
 * @package Grav\Plugin\FlexDirectory\Entities
 */
class FlexObject extends ArrayObject implements FlexObjectInterface
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

        parent::__construct($elements, $key);
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
}
