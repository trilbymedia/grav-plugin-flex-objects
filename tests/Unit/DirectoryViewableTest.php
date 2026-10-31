<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Tests\Unit;

use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Interfaces\FlexInterface;
use Grav\Plugin\FlexObjects\Flex;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The object and directory views take the directory type from page content, so
 * the type has to name a directory that exists, and one marked `site.hidden`
 * (accounts, groups, pages) shows only to a viewer who may list it.
 */
class DirectoryViewableTest extends TestCase
{
    #[Test]
    public function an_ordinary_directory_is_viewable(): void
    {
        self::assertTrue($this->flex(['contacts' => $this->directory(hidden: false)])->isDirectoryViewable('contacts'));
    }

    #[Test]
    public function an_unknown_or_disabled_directory_is_not(): void
    {
        $flex = $this->flex(['off' => $this->directory(hidden: false, enabled: false)]);

        self::assertFalse($flex->isDirectoryViewable('nowhere'));
        self::assertFalse($flex->isDirectoryViewable('off'));
    }

    #[Test]
    public function a_value_that_is_not_a_type_name_is_not(): void
    {
        $flex = $this->flex(['contacts' => $this->directory(hidden: false)]);

        self::assertFalse($flex->isDirectoryViewable(null));
        self::assertFalse($flex->isDirectoryViewable(''));
        self::assertFalse($flex->isDirectoryViewable(['contacts']));
    }

    #[Test]
    public function a_hidden_directory_shows_only_to_a_viewer_who_may_list_it(): void
    {
        $flex = $this->flex([
            'user-accounts' => $this->directory(hidden: true, mayList: false),
            'user-groups' => $this->directory(hidden: true, mayList: true),
        ]);

        self::assertFalse($flex->isDirectoryViewable('user-accounts'));
        self::assertTrue($flex->isDirectoryViewable('user-groups'));
    }

    #[Test]
    public function a_directory_that_cannot_be_checked_is_not_viewable(): void
    {
        $directory = $this->createMock(FlexDirectory::class);
        $directory->method('isEnabled')->willReturn(true);
        $directory->method('getConfig')->willReturn(true);
        $directory->method('isAuthorized')->willThrowException(new \RuntimeException('broken blueprint'));

        self::assertFalse($this->flex(['broken' => $directory])->isDirectoryViewable('broken'));
    }

    /**
     * @param array<string, FlexDirectory> $directories
     */
    private function flex(array $directories): Flex
    {
        $inner = $this->createMock(FlexInterface::class);
        $inner->method('getDirectory')->willReturnCallback(static fn(string $type) => $directories[$type] ?? null);

        return new Flex($inner, []);
    }

    private function directory(bool $hidden, bool $enabled = true, bool $mayList = false): FlexDirectory
    {
        $directory = $this->createMock(FlexDirectory::class);
        $directory->method('isEnabled')->willReturn($enabled);
        $directory->method('getConfig')->willReturnCallback(
            static fn(string $name, $default = null) => $name === 'site.hidden' ? $hidden : $default
        );
        $directory->method('isAuthorized')->willReturn($mayList);

        return $directory;
    }
}
