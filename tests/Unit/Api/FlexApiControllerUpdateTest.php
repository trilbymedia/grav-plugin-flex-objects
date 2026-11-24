<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Tests\Unit\Api;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Plugin\FlexObjects\Api\FlexApiController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * PATCH /flex-objects/{type}/{key} updates an existing object from the request body. The body is user data:
 * it must not carry storage metadata or a storage key, the same as when an object is created.
 */
#[CoversClass(FlexApiController::class)]
class FlexApiControllerUpdateTest extends TestCase
{
    protected function tearDown(): void
    {
        Grav::resetInstance();
    }

    #[Test]
    public function storage_metadata_and_keys_are_dropped_in_any_case(): void
    {
        $controller = new FlexApiController($this->createMock(Grav::class), new Config());
        $method = new ReflectionMethod($controller, 'withoutStorageFields');

        $result = $method->invoke($controller, [
            'title' => 'Hello',
            '__META' => ['storage_key' => 'admin'],
            '__meta' => ['storage_key' => 'admin'],
            'storage_key' => 'admin',
            'storage_timestamp' => 1,
            'nested' => ['storage_key' => 'kept'],
        ]);

        self::assertSame(['title' => 'Hello', 'nested' => ['storage_key' => 'kept']], $result);
    }

    #[Test]
    public function the_update_route_cleans_its_body_that_way(): void
    {
        $source = (string) file_get_contents((new \ReflectionClass(FlexApiController::class))->getFileName());

        $this->assertMatchesRegularExpression(
            '/public function update\(ServerRequestInterface \$request\): ResponseInterface\s*\{(?:(?!public function).)*?\$this->withoutStorageFields\(\$this->getRequestBody\(\$request\)\)(?:(?!public function).)*?->update\(/s',
            $source
        );
    }
}
