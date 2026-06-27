<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Tests\Unit\Api;

use Grav\Common\Config\Config;
use Grav\Common\Data\Blueprint;
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

    #[Test]
    public function normalizes_detail_sort_to_admin_next_contract(): void
    {
        self::assertSame(
            ['by' => 'timeline_at', 'dir' => 'desc'],
            $this->invoke('normalizeSortConfig', ['timeline_at' => 'desc']),
        );

        self::assertSame(
            ['by' => 'paid_at', 'dir' => 'asc'],
            $this->invoke('normalizeSortConfig', ['by' => 'paid_at', 'dir' => 'sideways']),
        );

        self::assertSame([], $this->invoke('normalizeSortConfig', []));
    }

    #[Test]
    public function detail_actions_are_exposed_as_authorized_per_action_flags(): void
    {
        $related = $this->fakeDirectory('payments', [
            'list' => [
                'fields' => [],
                'options' => [],
            ],
        ], [
            'list' => true,
            'update' => true,
            'delete' => false,
        ]);

        $controller = $this->controllerWithRelatedDirectory('payments', $related);

        $detail = $this->invokeOn($controller, 'normalizeDetailConfig', $this->fakeDirectory('user-accounts'), [
            'enabled' => true,
            'actions' => true,
            'relation' => [
                'type' => 'payments',
                'local_key' => 'username',
                'foreign_key' => 'subject_key',
            ],
        ], $this->fakeUser(false));

        self::assertIsArray($detail);
        self::assertTrue($detail['actions']);
        self::assertTrue($detail['can_edit']);
        self::assertFalse($detail['can_delete']);
    }

    #[Test]
    public function detail_edit_action_is_not_exposed_for_dedicated_admin_next_types(): void
    {
        $related = $this->fakeDirectory('user-accounts', [
            'list' => [
                'fields' => [],
                'options' => [],
            ],
        ], [
            'list' => true,
            'update' => true,
            'delete' => true,
        ]);

        $controller = $this->controllerWithRelatedDirectory('user-accounts', $related);

        $detail = $this->invokeOn($controller, 'normalizeDetailConfig', $this->fakeDirectory('payments'), [
            'enabled' => true,
            'actions' => true,
            'relation' => [
                'type' => 'user-accounts',
                'local_key' => 'subject_key',
                'foreign_key' => 'username',
            ],
        ], $this->fakeUser(false));

        self::assertIsArray($detail);
        self::assertTrue($detail['actions']);
        self::assertFalse($detail['can_edit']);
        self::assertTrue($detail['can_delete']);
    }

    private function invoke(string $method, mixed ...$args): mixed
    {
        return $this->invokeOn($this->controller, $method, ...$args);
    }

    private function invokeOn(FlexApiController $controller, string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionMethod($controller, $method);
        return $ref->invoke($controller, ...$args);
    }

    /**
     * @param array<string, mixed> $adminConfig
     * @param array<string, bool> $authorization
     */
    private function fakeDirectory(string $type, array $adminConfig = [], array $authorization = []): FlexDirectory
    {
        $blueprint = $this->createMock(Blueprint::class);
        $blueprint->method('fields')->willReturn([]);

        $directory = $this->createMock(FlexDirectory::class);
        $directory->method('getFlexType')->willReturn($type);
        $directory->method('getTitle')->willReturn(ucfirst($type));
        $directory->method('isEnabled')->willReturn(true);
        $directory->method('getBlueprint')->willReturn($blueprint);
        $directory->method('getConfig')->willReturnCallback(
            static function (?string $name = null, mixed $default = null) use ($adminConfig) {
                return match ($name) {
                    'admin' => $adminConfig,
                    'admin.list.fields' => $adminConfig['list']['fields'] ?? [],
                    'admin.list.options' => $adminConfig['list']['options'] ?? [],
                    default => $default,
                };
            },
        );
        $directory->method('isAuthorized')->willReturnCallback(
            static fn(string $action) => $authorization[$action] ?? false,
        );

        return $directory;
    }

    private function fakeUser(bool $super): UserInterface
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('get')->willReturnCallback(
            static fn($key, mixed $default = null, $separator = null) => $key === 'access.api.super' ? $super : $default,
        );

        return $user;
    }

    private function controllerWithRelatedDirectory(string $type, FlexDirectory $directory): FlexApiController
    {
        $flex = $this->createMock(Flex::class);
        $flex->method('getDirectory')->willReturnCallback(
            static fn(string $requested) => $requested === $type ? $directory : null,
        );

        $grav = $this->createMock(Grav::class);
        $grav->method('offsetExists')->willReturn(true);
        $grav->method('offsetGet')->willReturnCallback(
            static fn($key) => $key === 'flex_objects' ? $flex : null,
        );

        return new FlexApiController($grav, new Config([]));
    }
}

final class FlexTranslationTestLocator
{
    public function findResource(string $uri, bool $absolute = true, bool $create = false): ?string
    {
        return null;
    }
}
