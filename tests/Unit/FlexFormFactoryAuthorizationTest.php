<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Tests\Unit;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\FlexObject;
use Grav\Framework\Form\Interfaces\FormInterface;
use Grav\Framework\Route\Route;
use Grav\Plugin\FlexObjects\Flex;
use Grav\Plugin\FlexObjects\FlexFormFactory;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * A flex form is authorized where it is created, not only on the page that is requested: a form can be rendered
 * by a page other than the one it belongs to, such as a module shown in a collection.
 */
class FlexFormFactoryAuthorizationTest extends TestCase
{
    private const FORM = [
        'type' => 'flex',
        'flex' => ['type' => 'contacts'],
        'actions' => ['create' => true],
    ];

    protected function tearDown(): void
    {
        $property = new ReflectionProperty(Grav::class, 'instance');
        $property->setValue(null, null);
    }

    #[Test]
    public function a_form_the_user_may_not_use_is_not_created(): void
    {
        $builtForms = 0;

        $form = $this->create(self::FORM, ['contacts' => false], $builtForms);

        self::assertNull($form);
        self::assertSame(0, $builtForms, 'a form was built for a user who may not use it');
    }

    #[Test]
    public function a_form_naming_an_unknown_directory_is_not_created(): void
    {
        $builtForms = 0;

        $form = $this->create(['type' => 'flex', 'flex' => ['type' => 'nowhere']], ['contacts' => true], $builtForms);

        self::assertNull($form);
    }

    // The form interface still extends Serializable, which PHP 8.1+ deprecates for the mock of it.
    #[IgnoreDeprecations]
    #[Test]
    public function a_form_the_user_may_use_is_created(): void
    {
        $builtForms = 0;

        $form = $this->create(self::FORM, ['contacts' => true], $builtForms);

        self::assertInstanceOf(FormInterface::class, $form);
        self::assertSame(1, $builtForms);
    }

    /**
     * @param array $form
     * @param array<string, bool> $directories Known flex types and whether the user may create in them.
     */
    private function create(array $form, array $directories, int &$builtForms): ?FormInterface
    {
        $flex = $this->createMock(Flex::class);
        $flex->method('getDirectory')->willReturnCallback(function (string $type) use ($directories, &$builtForms) {
            if (!array_key_exists($type, $directories)) {
                return null;
            }

            $allowed = $directories[$type];
            $directory = $this->createMock(FlexDirectory::class);
            $directory->method('isAuthorized')->willReturn($allowed);
            $directory->method('createObject')->willReturnCallback(function () use ($allowed, &$builtForms) {
                $object = $this->createMock(FlexObject::class);
                $object->method('isAuthorized')->willReturn($allowed);
                $object->method('getForm')->willReturnCallback(function () use (&$builtForms) {
                    $builtForms++;

                    return $this->createMock(FormInterface::class);
                });

                return $object;
            });

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

        $page = new class extends \Grav\Common\Page\Page {
            public function __construct()
            {
            }

            public function header($var = null)
            {
                return (object)[];
            }

            public function addForms(array $new, $override = true)
            {
                return $this;
            }
        };

        return (new FlexFormFactory())->createFormForPage($page, 'form', $form);
    }
}
