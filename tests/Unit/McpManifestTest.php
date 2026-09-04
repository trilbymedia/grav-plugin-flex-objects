<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Tests\Unit;

use Grav\Plugin\Api\Mcp\McpToolCollector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * The MCP tool manifest (mcp.yaml) has to satisfy the API plugin's validator,
 * and every tool has to name a route this plugin actually registers. The
 * validator lives in the API plugin, so this runs the manifest through the
 * real McpToolCollector rather than re-implementing its rules.
 */
class McpManifestTest extends TestCase
{
    private const MANIFEST = __DIR__ . '/../../mcp.yaml';

    private const PLUGIN = __DIR__ . '/../../flex-objects.php';

    /** @var array<string, mixed> */
    private array $manifest;

    private McpToolCollector $collector;

    protected function setUp(): void
    {
        $this->manifest = Yaml::parseFile(self::MANIFEST);

        $this->collector = new McpToolCollector();
        $this->collector->registerPlugin('flex-objects', $this->manifest['prefix'] ?? null);
        foreach ($this->manifest['tools'] as $tool) {
            $this->collector->add('flex-objects', $tool, (int) $this->manifest['version']);
        }
    }

    #[Test]
    public function the_manifest_is_version_two(): void
    {
        // `body` only exists in version 2; an older API plugin skips the whole
        // file with a warning instead of serving create/update with a junk body.
        self::assertSame(2, $this->manifest['version']);
    }

    #[Test]
    public function every_tool_passes_the_api_plugins_validation(): void
    {
        self::assertSame([], $this->collector->warnings());
        self::assertCount(count($this->manifest['tools']), $this->collector->tools());
    }

    #[Test]
    public function the_tool_surface_is_the_expected_one(): void
    {
        self::assertSame([
            'flex_list_directories',
            'flex_get_directory',
            'flex_get_blueprint',
            'flex_list_objects',
            'flex_get_object',
            'flex_create_object',
            'flex_update_object',
            'flex_delete_object',
            'flex_list_media',
            'flex_delete_media',
        ], array_column($this->collector->tools(), 'name'));
    }

    #[Test]
    public function create_and_update_send_the_object_argument_as_the_body(): void
    {
        $tools = array_column($this->collector->tools(), null, 'name');

        foreach (['flex_create_object', 'flex_update_object'] as $name) {
            self::assertSame('object', $tools[$name]['body'], $name);
            self::assertTrue($tools[$name]['input_schema']['properties']['object']['additionalProperties'], $name);
            self::assertContains('object', $tools[$name]['input_schema']['required'], $name);
            self::assertArrayNotHasKey('additionalProperties', $tools[$name]['input_schema'], $name);
        }

        self::assertSame(['type'], $tools['flex_create_object']['path_params']);
        self::assertSame(['type', 'key'], $tools['flex_update_object']['path_params']);
    }

    #[Test]
    public function every_tool_names_a_registered_route(): void
    {
        // The routes are read from the plugin source rather than by booting
        // Grav; register a route any other way and this regex needs updating.
        preg_match_all(
            '/\$routes->(get|post|patch|put|delete)\(\s*\'([^\']+)\'/',
            (string) file_get_contents(self::PLUGIN),
            $matches,
            PREG_SET_ORDER,
        );
        $routes = array_map(static fn(array $m): string => strtoupper($m[1]) . ' ' . $m[2], $matches);
        self::assertNotSame([], $routes);

        foreach ($this->collector->tools() as $tool) {
            self::assertContains($tool['method'] . ' ' . $tool['path'], $routes, $tool['name']);
        }
    }

    #[Test]
    public function directory_wide_tools_carry_no_permission_and_catalog_tools_need_api_access(): void
    {
        $tools = array_column($this->collector->tools(), 'permission', 'name');

        // The route decides per directory (api.contacts.read, ...); a manifest
        // permission would hide these from keys scoped to one directory.
        foreach (['flex_list_objects', 'flex_get_object', 'flex_create_object', 'flex_update_object', 'flex_delete_object', 'flex_list_media', 'flex_delete_media'] as $name) {
            self::assertNull($tools[$name], $name);
        }
        foreach (['flex_list_directories', 'flex_get_directory', 'flex_get_blueprint'] as $name) {
            self::assertSame('api.access', $tools[$name], $name);
        }
    }
}
