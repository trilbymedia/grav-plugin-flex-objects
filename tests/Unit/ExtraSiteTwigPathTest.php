<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Tests\Unit;

use Grav\Common\Config\Config;
use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Twig\Twig;
use Grav\Plugin\FlexObjectsPlugin;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Issue #242: updating a plugin deletes and replaces its whole directory, so Flex site
 * templates hand-copied into `user/plugins/flex-objects/templates/` are lost on the next
 * update. `extra_site_twig_path` is the update-safe way out -- it registers a templates
 * root outside the plugin, searched before the plugin's own templates.
 *
 * This covers the contract the documentation now promises: a single value, a list, all
 * roots a stream maps to, the position relative to the plugin's own templates, and that
 * an absent or empty setting changes nothing.
 */
class ExtraSiteTwigPathTest extends TestCase
{
    /** @var string */
    private $tmpDir;

    /** @var string */
    private $pluginTemplates;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/flex-objects-242-' . bin2hex(random_bytes(6));

        // Two roots behind one scheme, the way `config://` spans environment/user/system
        // and an inherited theme spans child and parent.
        mkdir($this->tmpDir . '/user/templates', 0777, true);
        mkdir($this->tmpDir . '/system/templates', 0777, true);

        $this->pluginTemplates = dirname(__DIR__, 2) . '/templates';
    }

    protected function tearDown(): void
    {
        foreach (['/user/templates', '/system/templates', '/user', '/system', ''] as $dir) {
            if (is_dir($this->tmpDir . $dir)) {
                rmdir($this->tmpDir . $dir);
            }
        }

        $this->setSingleton(null);
    }

    #[Test]
    public function a_single_path_is_registered_before_the_plugins_own_templates(): void
    {
        $twig = $this->registerPaths('user://templates');

        self::assertSame(
            [$this->tmpDir . '/user/templates', $this->pluginTemplates],
            $twig->twig_paths,
            'A custom root must shadow the shipped templates, not the other way round'
        );
    }

    #[Test]
    public function a_list_of_paths_is_registered_in_order(): void
    {
        $twig = $this->registerPaths(['user://templates', 'system://templates']);

        self::assertSame(
            [
                $this->tmpDir . '/user/templates',
                $this->tmpDir . '/system/templates',
                $this->pluginTemplates,
            ],
            $twig->twig_paths,
            'Listed roots are searched in the order they are written'
        );
    }

    #[Test]
    public function every_root_a_stream_maps_to_is_registered(): void
    {
        // `templates://` stands in for `config://`: one scheme, several roots. Using only
        // the first match would silently drop the site-level override in an inherited
        // theme, or the user-level one under `config://`.
        $twig = $this->registerPaths('templates://');

        self::assertSame(
            [
                $this->tmpDir . '/user/templates',
                $this->tmpDir . '/system/templates',
                $this->pluginTemplates,
            ],
            $twig->twig_paths
        );
    }

    #[Test]
    public function an_unset_or_empty_setting_adds_nothing(): void
    {
        foreach ([null, '', [], ['']] as $value) {
            self::assertSame(
                [$this->pluginTemplates],
                $this->registerPaths($value)->twig_paths,
                'An unconfigured or blank path must be a no-op'
            );
        }
    }

    #[Test]
    public function a_path_that_does_not_exist_is_skipped(): void
    {
        self::assertSame(
            [$this->pluginTemplates],
            $this->registerPaths('user://nope')->twig_paths,
            'Pointing at a folder that was never created must not break rendering'
        );
    }

    /**
     * @param mixed $extraSiteTwigPath
     */
    private function registerPaths($extraSiteTwigPath): Twig
    {
        $locator = new UniformResourceLocator($this->tmpDir);
        $locator->addPath('user', '', 'user');
        $locator->addPath('system', '', 'system');
        $locator->addPath('templates', '', ['user/templates', 'system/templates']);

        $twig = new Twig($this->createMock(Grav::class));
        $config = new Config(['plugins' => ['flex-objects' => ['extra_site_twig_path' => $extraSiteTwigPath]]]);

        $grav = $this->gravReturning(['locator' => $locator, 'twig' => $twig, 'config' => $config]);
        (new FlexObjectsPlugin('flex-objects', $grav, $config))->onTwigTemplatePaths();

        return $twig;
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

        $this->setSingleton($grav);

        return $grav;
    }

    private function setSingleton(?Grav $grav): void
    {
        $property = new ReflectionProperty(Grav::class, 'instance');
        $property->setAccessible(true);
        $property->setValue(null, $grav);
    }
}
