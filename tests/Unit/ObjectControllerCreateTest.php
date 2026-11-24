<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Tests\Unit;

use Grav\Framework\Flex\FlexObject;
use Grav\Plugin\FlexObjects\Controllers\ObjectController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionProperty;

/**
 * The create task makes a new object. It has to refuse an object which is stored already, so a user who may
 * only create cannot use it to change an object they may not update.
 */
class ObjectControllerCreateTest extends TestCase
{
    #[Test]
    public function an_existing_object_is_refused_with_a_409(): void
    {
        $controller = $this->controllerFor($this->object(true));

        try {
            $controller->taskCreate($this->createMock(ServerRequestInterface::class));
            self::fail('taskCreate() accepted an object which exists');
        } catch (\RuntimeException $e) {
            self::assertSame(409, $e->getCode());
        }
    }

    #[Test]
    public function the_authorization_check_still_comes_first(): void
    {
        $object = $this->createMock(FlexObject::class);
        $object->method('isAuthorized')->willReturn(false);
        $object->method('exists')->willReturn(true);

        try {
            $this->controllerFor($object)->taskCreate($this->createMock(ServerRequestInterface::class));
            self::fail('taskCreate() ran for a user who may not create');
        } catch (\RuntimeException $e) {
            self::assertSame(403, $e->getCode());
        }
    }

    #[Test]
    public function a_new_object_is_not_refused(): void
    {
        $controller = $this->controllerFor($this->object(false));

        try {
            $controller->taskCreate($this->createMock(ServerRequestInterface::class));
        } catch (\Throwable $e) {
            // The test has no form to submit, so it stops later on; it must not be the refusal.
            self::assertNotSame(409, $e->getCode());
        }

        $this->addToAssertionCount(1);
    }

    private function object(bool $exists): FlexObject
    {
        $object = $this->createMock(FlexObject::class);
        $object->method('isAuthorized')->willReturn(true);
        $object->method('exists')->willReturn($exists);

        return $object;
    }

    private function controllerFor(FlexObject $object): ObjectController
    {
        $controller = new ObjectController();
        (new ReflectionProperty($controller, 'object'))->setValue($controller, $object);

        return $controller;
    }
}
