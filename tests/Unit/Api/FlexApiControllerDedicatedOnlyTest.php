<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Tests\Unit\Api;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Plugin\Api\Exceptions\ForbiddenException;
use Grav\Plugin\FlexObjects\Api\FlexApiController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionMethod;

/**
 * The generic Flex object routes authorize against the directory blueprint
 * permission only. For pages that skipped the `api.pages.*` permissions and the
 * page's own `permissions` frontmatter, both of which the Pages API applies, so
 * a page that denied an editor could still be read, changed and deleted through
 * `/flex-objects/pages/{key}`. Pages are now refused on every generic object
 * route; the gate sits in requireFlexPermission(), which all of them call.
 */
#[CoversClass(FlexApiController::class)]
class FlexApiControllerDedicatedOnlyTest extends TestCase
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

    /** @return array<string, array{string}> */
    public static function actions(): array
    {
        return [
            'list' => ['list'],
            'read' => ['read'],
            'create' => ['create'],
            'update' => ['update'],
            'delete' => ['delete'],
        ];
    }

    #[Test]
    #[DataProvider('actions')]
    public function pages_are_refused_on_the_generic_route(string $action): void
    {
        $directory = $this->createMock(FlexDirectory::class);
        $directory->method('getFlexType')->willReturn('pages');

        // The refusal has to come before the caller is even looked at: nobody,
        // super included, reaches a page through this route.
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->never())->method('getAttribute');

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage("The 'pages' directory is only available through its dedicated API endpoint.");

        $ref = new ReflectionMethod($this->controller, 'requireFlexPermission');
        $ref->invoke($this->controller, $request, $directory, $action);
    }

    #[Test]
    public function every_object_route_goes_through_the_gate(): void
    {
        $source = (string) file_get_contents((new \ReflectionClass(FlexApiController::class))->getFileName());

        foreach (['index', 'show', 'create', 'update', 'delete', 'export', 'mediaList', 'mediaUpload', 'mediaDelete'] as $method) {
            $this->assertMatchesRegularExpression(
                '/public function ' . $method . '\(ServerRequestInterface \$request\): ResponseInterface\s*\{(?:(?!public function).)*?\$this->requireFlexPermission\(/s',
                $source,
                "{$method}() must call requireFlexPermission()",
            );
        }
    }
}
