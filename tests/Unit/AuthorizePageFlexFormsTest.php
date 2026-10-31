<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Tests\Unit;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Route\Route;
use Grav\Plugin\FlexObjects\Flex;
use Grav\Plugin\FlexObjectsPlugin;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RocketTheme\Toolbox\Event\Event;

/**
 * authorizePage() decides who may see a page that holds flex forms. Every flex
 * form on the page has to name a directory that exists, and pass its check: a
 * form that names an unknown directory, or none, has nothing to be checked
 * against and is turned away rather than skipped.
 */
class AuthorizePageFlexFormsTest extends TestCase
{
    /** Header that makes the directory's own permission decide, so no object is needed. */
    private const ACCESS = ['access' => ['action' => 'create', 'scope' => 'admin']];

    protected function tearDown(): void
    {
        $property = new ReflectionProperty(Grav::class, 'instance');
        $property->setValue(null, null);
    }

    #[Test]
    public function a_form_naming_an_unknown_directory_is_denied(): void
    {
        $page = $this->page([['type' => 'flex', 'flex' => ['type' => 'nowhere']]]);

        $this->authorize($page);

        self::assertSame(['routable' => false, 'visible' => false], $page->calls);
    }

    #[Test]
    public function a_form_without_a_type_is_denied(): void
    {
        $page = $this->page([['type' => 'flex', 'flex' => ['key' => 'admin']]]);

        $this->authorize($page);

        self::assertSame(['routable' => false, 'visible' => false], $page->calls);
    }

    #[Test]
    public function a_second_form_naming_an_unknown_directory_is_denied(): void
    {
        $page = $this->page([
            ['type' => 'flex', 'name' => 'first', 'flex' => ['type' => 'contacts']],
            ['type' => 'flex', 'name' => 'second', 'flex' => ['type' => 'nowhere']],
        ], self::ACCESS);

        $this->authorize($page, ['contacts' => true]);

        self::assertSame(['routable' => false, 'visible' => false], $page->calls);
    }

    #[Test]
    public function every_form_naming_a_known_directory_the_user_may_use_is_allowed(): void
    {
        $page = $this->page([
            ['type' => 'flex', 'name' => 'first', 'flex' => ['type' => 'contacts']],
            ['type' => 'flex', 'name' => 'second', 'flex' => ['type' => 'contacts']],
        ], self::ACCESS);

        $this->authorize($page, ['contacts' => true]);

        self::assertSame([], $page->calls);
    }

    #[Test]
    public function a_directory_the_user_may_not_use_still_denies(): void
    {
        $page = $this->page([
            ['type' => 'flex', 'name' => 'first', 'flex' => ['type' => 'contacts']],
        ], self::ACCESS);

        $this->authorize($page, ['contacts' => false]);

        self::assertSame(['routable' => false, 'visible' => false], $page->calls);
    }

    #[Test]
    public function a_page_header_without_a_directory_is_left_alone(): void
    {
        $page = $this->page([], ['id' => 'something']);

        $this->authorize($page);

        self::assertSame([], $page->calls);
    }

    /**
     * @param array<int, array> $forms
     * @param array|null $flexHeader
     */
    private function page(array $forms, ?array $flexHeader = null): PageInterface
    {
        $indexed = [];
        foreach ($forms as $i => $form) {
            $indexed[$form['name'] ?? 'form' . $i] = $form;
        }

        return new class($indexed, $flexHeader) extends \Grav\Common\Page\Page {
            /** @var array<string, bool> */
            public array $calls = [];
            private array $formList;
            private object $fakeHeader;

            public function __construct(array $forms, ?array $flexHeader)
            {
                $this->formList = $forms;
                $this->fakeHeader = (object)($flexHeader !== null ? ['flex' => $flexHeader] : []);
            }

            public function header($var = null)
            {
                return $this->fakeHeader;
            }

            public function getForms(): array
            {
                return $this->formList;
            }

            public function addForms(array $new, $override = true)
            {
                return $this;
            }

            public function routable($var = null)
            {
                if ($var !== null) {
                    $this->calls['routable'] = $var;
                }

                return true;
            }

            public function visible($var = null)
            {
                if ($var !== null) {
                    $this->calls['visible'] = $var;
                }

                return true;
            }

            public function isModule(): bool
            {
                return true;
            }
        };
    }

    /**
     * @param array<string, bool> $directories Known flex types and whether the user may create in them.
     */
    private function authorize(PageInterface $page, array $directories = []): void
    {
        $flex = $this->createMock(Flex::class);
        $flex->method('getDirectory')->willReturnCallback(function (string $type) use ($directories) {
            if (!array_key_exists($type, $directories)) {
                return null;
            }
            $directory = $this->createMock(FlexDirectory::class);
            $directory->method('isAuthorized')->willReturn($directories[$type]);

            return $directory;
        });

        $route = $this->createMock(Route::class);
        $route->method('getGravParam')->willReturn(null);

        $services = ['route' => $route, 'flex_objects' => $flex, 'config' => new Config()];
        $grav = $this->createMock(Grav::class);
        $grav->method('offsetExists')->willReturnCallback(static fn($id) => array_key_exists($id, $services));
        $grav->method('offsetGet')->willReturnCallback(static fn($id) => $services[$id] ?? null);

        $property = new ReflectionProperty(Grav::class, 'instance');
        $property->setValue(null, $grav);

        $plugin = new FlexObjectsPlugin('flex-objects', $grav, new Config());
        $plugin->authorizePage(new Event(['page' => $page]));
    }
}
