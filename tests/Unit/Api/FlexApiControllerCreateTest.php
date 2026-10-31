<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Tests\Unit\Api;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Plugin\FlexObjects\Api\FlexApiController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * POST /flex-objects/{type} builds the new object from the request body. The body
 * is user data: it must not carry storage metadata or choose the storage key, and
 * the object is validated against the blueprint when it is built.
 */
#[CoversClass(FlexApiController::class)]
class FlexApiControllerCreateTest extends TestCase
{
    protected function tearDown(): void
    {
        Grav::resetInstance();
    }

    #[Test]
    public function storage_metadata_and_keys_are_dropped_and_validation_is_on(): void
    {
        $captured = $this->create([
            'title' => 'Hello',
            '__META' => ['storage_key' => 'admin', 'key' => 'admin'],
            '__meta' => ['storage_key' => 'admin'],
            'storage_key' => 'admin',
            'storage_timestamp' => 1,
            'nested' => ['storage_key' => 'kept'],
        ]);

        self::assertSame(
            [['title' => 'Hello', 'nested' => ['storage_key' => 'kept']], '', true],
            $captured
        );
    }

    #[Test]
    public function the_create_route_builds_its_object_that_way(): void
    {
        $source = (string) file_get_contents((new \ReflectionClass(FlexApiController::class))->getFileName());

        $this->assertMatchesRegularExpression(
            '/public function create\(ServerRequestInterface \$request\): ResponseInterface\s*\{(?:(?!public function).)*?\$this->createObjectFromRequest\(/s',
            $source
        );
        $this->assertDoesNotMatchRegularExpression(
            '/public function create\(ServerRequestInterface \$request\): ResponseInterface\s*\{(?:(?!public function|private function).)*?->createObject\(/s',
            $source
        );
    }

    /**
     * @param array<string, mixed> $body
     * @return array The arguments createObject() was called with.
     */
    private function create(array $body): array
    {
        $captured = null;
        $directory = $this->createMock(FlexDirectory::class);
        $directory->method('createObject')->willReturnCallback(function (...$args) use (&$captured) {
            $captured = $args;

            // Stops here: building a real object is the directory's job, not this test's.
            throw new \LogicException('createObject reached');
        });

        $controller = new FlexApiController($this->createMock(Grav::class), new Config());
        $method = new ReflectionMethod($controller, 'createObjectFromRequest');

        try {
            $method->invoke($controller, $directory, $body);
        } catch (\LogicException $e) {
            self::assertSame('createObject reached', $e->getMessage());
        }

        self::assertNotNull($captured, 'createObject() was not called');

        return $captured;
    }
}
