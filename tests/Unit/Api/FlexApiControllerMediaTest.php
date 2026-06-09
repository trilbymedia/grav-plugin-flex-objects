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
