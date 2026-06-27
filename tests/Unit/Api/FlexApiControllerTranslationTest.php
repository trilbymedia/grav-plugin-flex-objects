<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Tests\Unit\Api;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Plugin\FlexObjects\Api\FlexApiController;
use Grav\Plugin\FlexObjects\Flex;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Covers the directory-metadata translation behavior added for
 * getgrav/grav-plugin-flex-objects#219: GET /flex-objects must localize the
 * admin config labels (directory title, list field labels, …) against the
 * signed-in user's admin language instead of returning raw PLUGIN_* keys, while
 * leaving non-label strings (Twig templates, field types) untouched.
 */
#[CoversClass(FlexApiController::class)]
class FlexApiControllerTranslationTest extends TestCase
{
    private FlexApiController $controller;

    protected function setUp(): void
    {
        // Language stub: ICU.<key> lookups for our two keys resolve to the
        // Russian strings; everything else echoes the key back, mirroring
        // Grav's "return the key unchanged when not found" contract.
        $translations = [
            'ICU.PLUGIN_MYPLUGIN.ADMIN.ITEMS.TITLE'     => 'Случаи терапии',
            'ICU.PLUGIN_MYPLUGIN.ADMIN.ITEMS.PUBLISHED' => 'Публ.',
        ];

        $language = $this->createMock(Language::class);
        $language->method('translate')->willReturnCallback(
            static fn($key, $languages = null, $array = false) => $translations[$key] ?? $key,
        );
        $language->method('getLanguage')->willReturn('ru');

        $grav = $this->createMock(Grav::class);
        $grav->method('offsetExists')->willReturn(true);
        $grav->method('offsetGet')->willReturnCallback(
            static fn($key) => $key === 'language' ? $language : null,
        );

        $this->controller = new FlexApiController($grav, new Config([]));

        // Prime the language chain directly (the live endpoint resolves this
        // from the user's adminLanguage preference; here we skip that lookup).
        $chain = new ReflectionProperty($this->controller, 'adminLabelLanguages');
        $chain->setValue($this->controller, ['ru', 'en']);
    }

    #[Test]
    public function translates_language_key_labels_and_leaves_other_strings_alone(): void
    {
        $list = [
            'fields' => [
                'published' => [
                    'field' => [
                        'type'  => 'toggle',
                        'label' => 'PLUGIN_MYPLUGIN.ADMIN.ITEMS.PUBLISHED',
                    ],
                ],
                'updated_at' => [
                    'field' => [
                        'type'  => 'datetime',
                        'label' => 'Updated at',
                        'value' => '{{ object.updated_timestamp }}',
                    ],
                ],
            ],
        ];

        $result = $this->invoke('translateConfigLabels', $list);

        // Language-key label is localized.
        self::assertSame('Публ.', $result['fields']['published']['field']['label']);
        // A plain English label has no key to resolve, so it passes through.
        self::assertSame('Updated at', $result['fields']['updated_at']['field']['label']);
        // Field types and Twig values are never touched.
        self::assertSame('toggle', $result['fields']['published']['field']['type']);
        self::assertSame('{{ object.updated_timestamp }}', $result['fields']['updated_at']['field']['value']);
    }

    #[Test]
    public function translate_label_resolves_a_directory_title_key(): void
    {
        self::assertSame(
            'Случаи терапии',
            $this->invoke('translateLabel', 'PLUGIN_MYPLUGIN.ADMIN.ITEMS.TITLE'),
        );
    }

    #[Test]
    public function blueprints_endpoint_translates_titles_with_admin_language(): void
    {
        $translations = [
            'ICU.PLUGIN_MYPLUGIN.ADMIN.ITEMS.TITLE' => 'Pagos',
            'ICU.PLUGIN_MYPLUGIN.ADMIN.ITEMS.DESC'  => 'Historial de pagos',
        ];

        $language = $this->createMock(Language::class);
        $language->method('translate')->willReturnCallback(
            static fn($key, $languages = null, $array = false) => $translations[$key] ?? $key,
        );
        $language->method('getLanguage')->willReturn('en');

        $directory = $this->createMock(FlexDirectory::class);
        $directory->method('getBlueprintFile')->willReturn('blueprints://flex-objects/items.yaml');
        $directory->method('getFlexType')->willReturn('items');
        $directory->method('getTitle')->willReturn('PLUGIN_MYPLUGIN.ADMIN.ITEMS.TITLE');
        $directory->method('getDescription')->willReturn('PLUGIN_MYPLUGIN.ADMIN.ITEMS.DESC');

        $flex = $this->createMock(Flex::class);
        $flex->method('getBlueprints')->willReturn([$directory]);

        $grav = $this->createMock(Grav::class);
        $grav->method('offsetExists')->willReturnCallback(
            static fn($key) => in_array($key, ['language', 'flex_objects', 'locator'], true),
        );
        $grav->method('offsetGet')->willReturnCallback(
            static fn($key) => match ($key) {
                'language' => $language,
                'flex_objects' => $flex,
                'locator' => new FlexTranslationTestLocator(),
                default => null,
            },
        );

        $user = $this->createMock(UserInterface::class);
        $user->method('get')->willReturnCallback(
            static fn($key, mixed $default = null) => match ($key) {
                'access.api.super' => true,
                'admin_next' => ['preferences' => ['adminLanguage' => 'es']],
                default => $default,
            },
        );

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnCallback(
            static fn($name, $default = null) => $name === 'api_user' ? $user : $default,
        );

        $controller = new FlexApiController($grav, new Config([]));
        $response = $controller->blueprints($request);
        $payload = json_decode((string) $response->getBody(), true);

        self::assertSame('Pagos', $payload['data'][0]['title']);
        self::assertSame('Historial de pagos', $payload['data'][0]['description']);
    }

    private function invoke(string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionMethod($this->controller, $method);
        return $ref->invoke($this->controller, ...$args);
    }
}

final class FlexTranslationTestLocator
{
    public function findResource(string $uri, bool $absolute = true, bool $create = false): ?string
    {
        return null;
    }
}
