<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Tests\Unit;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Framework\Route\Route;
use Grav\Plugin\FlexObjects\Controllers\MediaProxyController;
use Grav\Plugin\FlexObjectsPlugin;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RocketTheme\Toolbox\Event\Event;

/**
 * Regression tests for PHP 8.6 compatibility fixes in grav-plugin-flex-objects (PHP 8.3+ compatible).
 */
class Php86CompatibilityTest extends TestCase
{
    /**
     * Run a callback and fail if any E_DEPRECATED / E_USER_DEPRECATED is triggered.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function assertNoDeprecation(callable $callback): mixed
    {
        $deprecations = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$deprecations): bool {
            if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
                $deprecations[] = $errstr;
                return true;
            }
            return false;
        });

        try {
            $result = $callback();
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations, 'Unexpected deprecation(s): ' . implode('; ', $deprecations));

        return $result;
    }

    #[Test]
    public function edit_form_without_an_id_param_gets_an_empty_key_without_deprecation(): void
    {
        $form = $this->assertNoDeprecation(fn () => $this->initializeEditForm([]));

        // An empty key (not an unset one) keeps the form key ahead of a page header `flex.id`.
        self::assertSame('', $form['flex']['key']);
    }

    #[Test]
    public function edit_form_treats_an_id_param_with_a_nul_byte_as_no_id(): void
    {
        foreach (['foo%00bar', "foo\0bar"] as $id) {
            $form = $this->assertNoDeprecation(fn () => $this->initializeEditForm(['id' => $id]));

            self::assertSame('', $form['flex']['key'], 'NUL-byte id must not become the flex key');
        }
    }

    #[Test]
    public function edit_form_still_takes_a_url_encoded_id_param_as_its_key(): void
    {
        $form = $this->assertNoDeprecation(fn () => $this->initializeEditForm(['id' => 'my%20item']));

        self::assertSame('my item', $form['flex']['key']);
    }

    #[Test]
    public function media_proxy_returns_404_for_a_nul_byte_filename_before_looking_up_the_object(): void
    {
        $grav = $this->createMock(Grav::class);
        $grav->method('offsetGet')->willReturnCallback(function ($id) {
            if ($id === 'flex') {
                self::fail('The proxy must reject a NUL-byte filename before resolving the object');
            }
            return $id === 'config' ? new Config() : null;
        });
        $grav->method('offsetExists')->willReturnCallback(static fn ($id) => in_array($id, ['config', 'flex'], true));

        $controller = new MediaProxyController($grav);
        $request = $this->createMock(ServerRequestInterface::class);

        foreach (["photo\0.jpg", "photo.jpg\0.php"] as $filename) {
            $response = $controller->serve('contacts', 'abc', $filename, null, $request);
            self::assertSame(404, $response->getStatusCode());
        }
    }

    /**
     * Fire the plugin's onBeforeFlexFormInitialize handler for an edit form on a
     * route carrying the given Grav params, and return the resulting form.
     *
     * @param array<string, string> $params
     * @return array<string, mixed>
     */
    private function initializeEditForm(array $params): array
    {
        $route = new Route([
            'grav' => ['root' => '', 'language' => '', 'route' => 'contacts/edit', 'params' => $params],
        ]);

        $grav = $this->createMock(Grav::class);
        $grav->method('offsetGet')->willReturnCallback(static fn ($id) => $id === 'route' ? $route : null);

        $plugin = new FlexObjectsPlugin('flex-objects', $grav, new Config());

        $event = new Event(['form' => ['flex' => ['type' => 'contacts'], 'actions' => ['edit' => true]]]);
        $plugin->onBeforeFlexFormInitialize($event);

        return $event['form'];
    }
}
