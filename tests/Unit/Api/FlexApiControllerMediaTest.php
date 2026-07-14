<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Tests\Unit\Api;

use Grav\Common\Config\Config;
use Grav\Common\Flex\FlexObject;
use Grav\Common\Grav;
use Grav\Plugin\Api\Exceptions\ValidationException;
use Grav\Plugin\FlexObjects\Api\FlexApiController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use ReflectionMethod;

/**
 * Integration coverage for the flex-object media endpoints. The behavior that
 * matters: uploads attached to an object land in that object's own storage
 * folder — alongside the object's data file — for folder-based directories, and
 * directories without per-object folders (SimpleStorage) are refused with a
 * clear validation error rather than writing somewhere unexpected.
 */
#[CoversClass(FlexApiController::class)]
class FlexApiControllerMediaTest extends TestCase
{
    private string $tempDir;
    private Config $config;
    private FlexApiController $controller;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/grav_flex_media_' . uniqid();
        mkdir($this->tempDir, 0775, true);

        // A mocked container (ArrayAccess) so the controller can reach the
        // locator without booting a real Grav (which would install global error
        // handlers and trip PHPUnit's risky-test detection).
        $locator = new FlexMediaTestLocator($this->tempDir);
        $grav = $this->createMock(Grav::class);
        $grav->method('offsetExists')->willReturn(true);
        $grav->method('offsetGet')->willReturnCallback(
            static fn($key) => $key === 'locator' ? $locator : null,
        );

        $this->config = new Config([
            'security' => ['uploads_dangerous_extensions' => ['php', 'phtml', 'phar', 'js', 'html']],
        ]);

        $this->controller = new FlexApiController($grav, $this->config);
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->tempDir);
    }

    #[Test]
    public function upload_lands_in_the_objects_own_folder_next_to_its_data_file(): void
    {
        // A folder-stored object: its media folder IS its storage folder.
        $objectFolder = $this->tempDir . '/data/flex-objects/contacts/123';
        mkdir($objectFolder, 0775, true);
        file_put_contents($objectFolder . '/item.md', "---\nname: Ada\n---\n");

        $object = $this->fakeObject('user-data://flex-objects/contacts/123', '123');

        $resolved = $this->invoke('resolveMediaFolder', $object);
        self::assertSame($objectFolder, $resolved, 'Media folder must resolve to the object folder.');

        $this->invoke('processUploadedFile', new FlexMediaTestFile('avatar.png', 'png-bytes'), $resolved);

        // The uploaded file sits in the same folder as the object's data file.
        self::assertFileExists($objectFolder . '/avatar.png');
        self::assertFileExists($objectFolder . '/item.md');
    }

    #[Test]
    public function simple_storage_directory_is_refused(): void
    {
        // SimpleStorage objects have no per-object folder — getMediaFolder() is null.
        $object = $this->fakeObject(null, 'abc');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/folder-based storage/i');

        $this->invoke('resolveMediaFolder', $object);
    }

    #[Test]
    public function grav_root_relative_media_folder_is_made_absolute(): void
    {
        // FolderStorage typically returns a GRAV_ROOT-relative, non-stream path.
        $object = $this->fakeObject('user/data/flex-objects/contacts/9', '9');

        $resolved = $this->invoke('resolveMediaFolder', $object);

        self::assertSame(
            rtrim(GRAV_ROOT, '/') . '/user/data/flex-objects/contacts/9',
            $resolved,
        );
    }

    #[Test]
    public function already_absolute_media_folder_is_left_untouched(): void
    {
        $absolute = $this->tempDir . '/somewhere/abs';
        $object = $this->fakeObject($absolute, '7');

        self::assertSame($absolute, $this->invoke('resolveMediaFolder', $object));
    }

    #[Test]
    public function removed_object_local_file_is_queued_for_physical_deletion(): void
    {
        // Stored: two images. Incoming update keeps only one. The dropped file
        // must be queued as a `[$field => [$filename => null]]` delete marker so
        // core unlinks it on save (regression: admin-next left it orphaned).
        $object = $this->fakeMediaObject(
            ['images' => ['type' => 'file', 'multiple' => true]],
            ['images' => [
                'keep.png' => ['name' => 'keep.png', 'path' => 'keep.png'],
                'drop.png' => ['name' => 'drop.png', 'path' => 'drop.png'],
            ]],
        );

        $body = ['images' => ['keep.png' => ['name' => 'keep.png', 'path' => 'keep.png']]];

        self::assertSame(
            ['images' => ['drop.png' => null]],
            $this->invoke('collectRemovedMediaFiles', $object, $body),
        );
    }

    #[Test]
    public function shared_destination_file_is_dereferenced_but_not_deleted(): void
    {
        // A `media://` (or any non-self) destination points at shared storage —
        // dropping the reference must NOT unlink the file, which may be in use
        // elsewhere. So no delete marker is produced.
        $object = $this->fakeMediaObject(
            ['images' => ['type' => 'file', 'destination' => 'media://']],
            ['images' => ['shared.png' => ['name' => 'shared.png', 'path' => 'shared.png']]],
        );

        $body = ['images' => []];

        self::assertSame([], $this->invoke('collectRemovedMediaFiles', $object, $body));
    }

    #[Test]
    public function field_absent_from_body_is_left_untouched(): void
    {
        // A partial update that doesn't send the media field must not read the
        // absence as "all files removed".
        $object = $this->fakeMediaObject(
            ['images' => ['type' => 'file']],
            ['images' => ['a.png' => ['name' => 'a.png', 'path' => 'a.png']]],
        );

        self::assertSame([], $this->invoke('collectRemovedMediaFiles', $object, ['title' => 'x']));
    }

    #[Test]
    public function non_media_fields_are_ignored(): void
    {
        $object = $this->fakeMediaObject(
            ['title' => ['type' => 'text']],
            ['title' => 'old'],
        );

        self::assertSame([], $this->invoke('collectRemovedMediaFiles', $object, ['title' => 'new']));
    }

    #[Test]
    public function file_basenames_normalize_full_paths(): void
    {
        // The value may be keyed by full path or bare filename; both normalize
        // to the same basename set so the diff stays stable.
        $names = $this->invoke('mediaFileBasenames', [
            'user/data/flex-objects/x/photo.png' => ['name' => 'photo.png', 'path' => 'user/data/flex-objects/x/photo.png'],
            'logo.svg' => ['name' => 'logo.svg', 'path' => 'logo.svg'],
        ]);

        self::assertSame(['photo.png' => true, 'logo.svg' => true], $names);
    }

    /**
     * A folder-stored Flex object stub that only needs to answer getMediaFolder()
     * and getKey() for the media-folder resolution path.
     */
    private function fakeObject(?string $mediaFolder, string $key): FlexObject
    {
        $object = $this->createMock(FlexObject::class);
        $object->method('getMediaFolder')->willReturn($mediaFolder);
        $object->method('getKey')->willReturn($key);

        return $object;
    }

    /**
     * A Flex object stub for the media-deletion diff: it answers getBlueprint()
     * (→ schema()->getState()['items']) and getNestedProperty() for stored
     * field values, which is all collectRemovedMediaFiles() touches.
     *
     * @param array<string,array<string,mixed>> $items  blueprint schema items
     * @param array<string,mixed>               $stored current field values
     */
    private function fakeMediaObject(array $items, array $stored): FlexObject
    {
        $object = $this->createMock(FlexObject::class);
        $object->method('getBlueprint')->willReturn(new FlexMediaTestBlueprint($items));
        $object->method('getNestedProperty')->willReturnCallback(
            static fn($field) => $stored[$field] ?? null,
        );

        return $object;
    }

    private function invoke(string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionMethod($this->controller, $method);
        return $ref->invoke($this->controller, ...$args);
    }

    private function rmrf(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->rmrf($path . '/' . $item);
        }
        rmdir($path);
    }
}

/**
 * Maps the `user-data://` stream onto a `data/` subtree of the test temp dir,
 * matching the third-argument "create/return even if missing" contract that
 * resolveMediaFolder() relies on.
 */
final class FlexMediaTestLocator
{
    public function __construct(private readonly string $base) {}

    public function isStream(string $path): bool
    {
        return preg_match('#^[A-Za-z][A-Za-z0-9+.-]*://#', $path) === 1;
    }

    public function findResource(string $uri, bool $absolute = true, bool $first = false): string|false
    {
        $map = [
            'user-data://' => $this->base . '/data',
            'user://' => $this->base,
        ];

        foreach ($map as $prefix => $root) {
            if (str_starts_with($uri, $prefix)) {
                return rtrim($root . '/' . ltrim(substr($uri, strlen($prefix)), '/'), '/');
            }
        }

        return false;
    }
}

final class FlexMediaTestFile implements UploadedFileInterface
{
    private readonly string $source;
    private bool $moved = false;

    public function __construct(
        private readonly string $filename,
        string $contents,
    ) {
        $this->source = tempnam(sys_get_temp_dir(), 'grav_flex_upload_') ?: '';
        file_put_contents($this->source, $contents);
    }

    public function getStream(): StreamInterface
    {
        throw new \RuntimeException('Not needed in tests.');
    }

    public function moveTo(string $targetPath): void
    {
        if ($this->moved) {
            throw new \RuntimeException('File already moved.');
        }
        $dir = dirname($targetPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        rename($this->source, $targetPath);
        $this->moved = true;
    }

    public function getSize(): ?int { return file_exists($this->source) ? filesize($this->source) : null; }
    public function getError(): int { return UPLOAD_ERR_OK; }
    public function getClientFilename(): ?string { return $this->filename; }
    public function getClientMediaType(): ?string { return 'image/png'; }
}

/**
 * Minimal blueprint stub exposing schema()->getState()['items'] — the only
 * blueprint surface collectRemovedMediaFiles() reads.
 */
final class FlexMediaTestBlueprint
{
    /** @param array<string,array<string,mixed>> $items */
    public function __construct(private readonly array $items) {}

    public function schema(): FlexMediaTestSchema
    {
        return new FlexMediaTestSchema($this->items);
    }
}

final class FlexMediaTestSchema
{
    /** @param array<string,array<string,mixed>> $items */
    public function __construct(private readonly array $items) {}

    /** @return array{items: array<string,array<string,mixed>>} */
    public function getState(): array
    {
        return ['items' => $this->items];
    }
}
