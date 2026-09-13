<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Tests\Unit;

use Grav\Common\Config\Config;
use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Events\FlexRegisterEvent;
use Grav\Framework\Flex\Flex;
use Grav\Plugin\FlexObjectsPlugin;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Issue #240: a Flex directory whose blueprint is shipped by the theme lives under
 * `blueprints://flex-objects/<type>.yaml`, and the theme only joins that stream when it
 * initializes. If Flex boots earlier in the request -- starting a session unserializes
 * the stored user and resolves the account blueprint's `groups` field through Flex, and
 * Flex user accounts boot Flex during initialization -- the `file_exists()` gate in
 * registerDirectories() misses the type and skips it silently.
 *
 * The plugin re-registers on `onThemeInitialized`, but that was only subscribed for
 * admin requests, so the frontend and Admin Next (which goes through the API plugin and
 * is therefore not "admin") never got the second chance.
 */
class ThemeDirectoryRegistrationTest extends TestCase
{
    /** @var string */
    private $tmpDir;

    /** @var string */
    private $pluginBlueprint;

    /** @var string */
    private $themeBlueprint;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/flex-objects-240-' . bin2hex(random_bytes(6));
        mkdir($this->tmpDir, 0777, true);

        // Stands in for a plugin-shipped blueprint: resolvable from the very first boot.
        $this->pluginBlueprint = $this->tmpDir . '/contacts.yaml';
        file_put_contents($this->pluginBlueprint, "title: Contacts\n");

        // Stands in for a theme-shipped blueprint: not resolvable until the theme has
        // added its path to the `blueprints://` stream.
        $this->themeBlueprint = $this->tmpDir . '/gallery.yaml';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }

        $this->setSingleton(null);
    }

    #[Test]
    public function non_admin_requests_subscribe_to_theme_initialized(): void
    {
        $dispatcher = new EventDispatcher();
        $plugin = $this->plugin($this->gravReturning(['events' => $dispatcher, 'config' => $this->config()]));

        $plugin->onPluginsInitialized();

        $listeners = $dispatcher->getListeners('onThemeInitialized');
        self::assertNotEmpty($listeners, 'Frontend and API requests must get the late re-registration too');
        self::assertSame([$plugin, 'onThemeInitialized'], $listeners[0]);
    }

    #[Test]
    public function theme_shipped_directory_is_registered_once_the_theme_stream_resolves(): void
    {
        $flex = new Flex([], []);
        $plugin = $this->plugin($this->gravReturning(['flex' => $flex]));

        // Flex boots before theme init: the theme blueprint does not resolve yet.
        $plugin->onRegisterFlex(new FlexRegisterEvent($flex));

        self::assertNotNull($flex->getDirectory('contacts'), 'Plugin-shipped directory registers normally');
        self::assertNull($flex->getDirectory('gallery'), 'Theme-shipped directory is skipped at this point');

        // The theme initializes and its blueprints become resolvable.
        file_put_contents($this->themeBlueprint, "title: Gallery\n");
        $plugin->onThemeInitialized();

        self::assertNotNull($flex->getDirectory('gallery'), 'Theme-shipped directory must be registered on theme init');
        self::assertNotNull($flex->getDirectory('contacts'), 'Already-registered directories are left alone');
    }

    #[Test]
    public function theme_initialized_does_not_boot_flex_when_flex_was_never_used(): void
    {
        // Nothing was missed if Flex has not booted yet, so the handler must not reach
        // into the container: booting Flex (and stat-ing every blueprint) on requests
        // that never touch it would be a needless cost on every page view.
        $grav = $this->createMock(Grav::class);
        $grav->expects(self::never())->method('offsetGet');
        $this->setSingleton($this->createMock(Grav::class));

        $this->plugin($grav)->onThemeInitialized();

        self::assertTrue(true);
    }

    /**
     * @param array<string, mixed> $services
     */
    private function gravReturning(array $services): Grav
    {
        $services += ['debugger' => $this->createMock(Debugger::class)];

        $grav = $this->createMock(Grav::class);
        $grav->method('offsetExists')->willReturnCallback(
            static fn($id) => array_key_exists($id, $services)
        );
        $grav->method('offsetGet')->willReturnCallback(
            static fn($id) => $services[$id] ?? null
        );

        // `isAdmin()` goes through `Utils::isAdminPlugin()`, which reads the Grav
        // singleton. Point it at the stub so no real Grav (and no real Debugger, which
        // installs a global error handler) has to be built for these tests. Without an
        // `admin` service the plugin sees a plain frontend/API request.
        $this->setSingleton($grav);

        return $grav;
    }

    private function setSingleton(?Grav $grav): void
    {
        $property = new ReflectionProperty(Grav::class, 'instance');
        $property->setAccessible(true);
        $property->setValue(null, $grav);
    }

    private function plugin(Grav $grav): FlexObjectsPlugin
    {
        return new FlexObjectsPlugin('flex-objects', $grav, $this->config());
    }

    private function config(): Config
    {
        return new Config([
            'plugins' => [
                'flex-objects' => [
                    'directories' => [
                        $this->pluginBlueprint,
                        $this->themeBlueprint,
                    ],
                ],
            ],
        ]);
    }
}
