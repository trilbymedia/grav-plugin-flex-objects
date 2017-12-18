<?php
namespace Grav\Plugin\FlexDirectory;

use Grav\Framework\Object\ObjectCollection;
use Grav\Plugin\FlexDirectory\Interfaces\FlexCollectionInterface;

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
}
