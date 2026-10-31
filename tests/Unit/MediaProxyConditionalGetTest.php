<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Tests\Unit;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Plugin\FlexObjects\Controllers\MediaProxyController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionMethod;

/**
 * The media proxy answers If-Modified-Since with a 304 when its file is not newer.
 * The header comes from the client, so a value too long to be an HTTP date is
 * not parsed at all.
 */
class MediaProxyConditionalGetTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'flex-media-');
        file_put_contents($this->file, 'x');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    #[Test]
    public function a_date_in_the_future_gets_a_304(): void
    {
        self::assertSame(304, $this->respond('Fri, 01 Jan 2100 00:00:00 GMT')->getStatusCode());
    }

    #[Test]
    public function a_date_in_the_past_gets_the_file(): void
    {
        self::assertSame(200, $this->respond('Mon, 01 Jan 1990 00:00:00 GMT')->getStatusCode());
    }

    #[Test]
    public function a_value_too_long_to_be_a_date_is_ignored(): void
    {
        $long = 'Fri, 01 Jan 2100 00:00:00 GMT' . str_repeat(' ', 4096);

        self::assertSame(200, $this->respond($long)->getStatusCode());
    }

    private function respond(string $ifModifiedSince)
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturnCallback(
            static fn(string $name) => $name === 'If-Modified-Since' ? $ifModifiedSince : ''
        );

        $controller = new MediaProxyController($this->createMock(Grav::class));
        $stream = new ReflectionMethod($controller, 'stream');

        return $stream->invoke($controller, $this->file, 'image/png', $request, new Config());
    }
}
