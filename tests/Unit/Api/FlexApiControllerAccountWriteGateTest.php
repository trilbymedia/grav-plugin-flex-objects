<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Tests\Unit\Api;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Plugin\Api\Exceptions\ForbiddenException;
use Grav\Plugin\FlexObjects\Api\FlexApiController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * GHSA-pc8m-jxvh-vmrc: the generic Flex write route (create/update/delete)
 * authorizes only against the directory blueprint permission and skips the
 * super-admin-target and per-field guards the dedicated Users/Groups controllers
 * enforce. The controller now refuses generic writes to user-accounts/user-groups
 * so those changes must go through their gated endpoints. Other directories are
 * unaffected.
 */
#[CoversClass(FlexApiController::class)]
class FlexApiControllerAccountWriteGateTest extends TestCase
{
    private FlexApiController $controller;

    protected function setUp(): void
    {
        $grav = $this->createMock(Grav::class);
        $this->controller = new FlexApiController($grav, new Config());
    }

    protected function tearDown(): void
    {
        Grav::resetInstance();
    }

    #[Test]
    public function user_accounts_generic_write_is_refused(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->assertGenericWriteAllowed('user-accounts');
    }

    #[Test]
    public function user_groups_generic_write_is_refused(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->assertGenericWriteAllowed('user-groups');
    }

    #[Test]
    public function an_ordinary_directory_is_allowed(): void
    {
        $this->assertGenericWriteAllowed('contacts');
        $this->addToAssertionCount(1); // no exception == pass
    }

    #[Test]
    public function pages_are_not_blocked_by_this_gate(): void
    {
        // Pages carry no privilege-bearing fields; blocking them here would break
        // legitimate generic Flex page writes, so the gate is user/group only.
        $this->assertGenericWriteAllowed('pages');
        $this->addToAssertionCount(1);
    }

    private function assertGenericWriteAllowed(string $flexType): void
    {
        $directory = $this->createMock(FlexDirectory::class);
        $directory->method('getFlexType')->willReturn($flexType);

        $ref = new ReflectionMethod($this->controller, 'assertGenericWriteAllowed');
        $ref->invoke($this->controller, $directory);
    }
}
