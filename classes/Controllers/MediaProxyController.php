<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Controllers;

use Grav\Common\Grav;
use Grav\Common\Utils;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Media\Interfaces\MediaInterface;
use Grav\Framework\Psr7\Response;
use Grav\Framework\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use function in_array;
use function is_string;

/**
 * Public, permission-aware proxy for Flex Object media.
 *
 * PROTOTYPE (see docs/specs/media-proxy.md).
 *
 * Flex Objects store their data file and their uploaded media in the same
 * folder under `user://data/<type>/<key>`. Historically the media was linked
 * with a direct `/user/data/...` URL, which means the webserver — not Grav —
 * decides who can read it, and a blanket deny on `user/data` breaks every
 * image (getgrav/grav#4129).
 *
 * This controller serves a single media file through PHP after resolving the
 * owning object and (optionally) checking its read ACL, so the data folder can
 * stay private while public media still loads. It is the retrieval half of the
 * "store in user://data, serve via a lightweight proxy" design.
 *
 * Route (registered in flex-objects.php, base configurable):
 *   GET /flex-media/<type>/<key>/<filename>[?field=<field>]
 */
final class MediaProxyController
{
    /** Media types we are willing to serve inline. Mirrors the .htaccess allow-list. */
    private const SERVEABLE = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'ico',
        'mp4', 'webm', 'ogg', 'ogv', 'mov', 'mp3', 'wav', 'm4a', 'flac', 'pdf',
    ];

    public function __construct(private readonly Grav $grav)
    {
    }

    /**
     * Build the public proxy URL for a media file on an object.
     *
     * Templates use this instead of `medium.url` while the proxy is opt-in;
     * the core follow-up rewrites `Medium::url()` to emit it automatically.
     */
    public static function url(FlexObjectInterface $object, string $filename, ?string $field = null): string
    {
        $grav = Grav::instance();
        $base = (string) $grav['config']->get('plugins.flex-objects.media_proxy.base', '/flex-media');

        $path = rtrim($base, '/')
            . '/' . rawurlencode($object->getFlexType())
            . '/' . rawurlencode($object->getKey())
            . '/' . str_replace('%2F', '/', rawurlencode($filename));

        if ($field !== null && $field !== '') {
            $path .= '?field=' . rawurlencode($field);
        }

        return $grav['base_url'] . $path;
    }

    /**
     * Resolve and stream the requested media file.
     *
     * @return ResponseInterface 200/206 with the file, or 304/403/404.
     */
    public function serve(
        string $type,
        string $key,
        string $filename,
        ?string $field,
        ServerRequestInterface $request
    ): ResponseInterface {
        $config = $this->grav['config'];

        // Reject path traversal and hidden files outright.
        if ($filename === '' || str_contains($filename, '..') || str_starts_with($filename, '.')) {
            return $this->error(404);
        }

        $extension = strtolower(Utils::pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, self::SERVEABLE, true)) {
            // Never let the proxy hand out data files, databases, keys, etc.
            return $this->error(404);
        }

        $flex = $this->grav['flex'] ?? null;
        $object = $flex ? $flex->getObject($key, $type) : null;
        if (!$object instanceof FlexObjectInterface || !$object->exists()) {
            return $this->error(404);
        }

        // Permission gate. Off by default — the proxy currently exists to keep a
        // single retrieval chokepoint, not to ACL-gate reads. When explicitly
        // enabled, only an explicit "no" blocks the file, so directories without
        // a read ACL keep behaving as public media.
        if ($config->get('plugins.flex-objects.media_proxy.authorize', false)) {
            $user = $this->grav['user'] ?? null;
            if ($object->isAuthorized('read', 'frontend', $user) === false) {
                return $this->error(403);
            }
        }

        // Resolve the media collection (field-scoped or the object's own media).
        $media = $field
            ? (method_exists($object, 'getMediaField') ? $object->getMediaField($field) : null)
            : ($object instanceof MediaInterface ? $object->getMedia() : null);

        $medium = $media[$filename] ?? null;
        if ($medium === null) {
            return $this->error(404);
        }

        $filepath = $medium->get('filepath');
        if (!is_string($filepath) || !is_file($filepath)) {
            return $this->error(404);
        }

        return $this->stream($filepath, $medium->get('mime') ?: null, $request, $config);
    }

    /**
     * Stream a file with caching, conditional-GET (304) and single-range (206) support.
     */
    private function stream(string $filepath, ?string $mime, ServerRequestInterface $request, $config): ResponseInterface
    {
        $size = (int) filesize($filepath);
        $mtime = (int) filemtime($filepath);
        $etag = '"' . dechex($mtime) . '-' . dechex($size) . '"';
        $mime = $mime ?: (Utils::getMimeByExtension(Utils::pathinfo($filepath, PATHINFO_EXTENSION), 'application/octet-stream'));

        $cacheControl = (string) $config->get('plugins.flex-objects.media_proxy.cache_control', 'public, max-age=604800');

        $baseHeaders = [
            'Content-Type' => $mime,
            'Cache-Control' => $cacheControl,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $mtime) . ' GMT',
            'ETag' => $etag,
            'Accept-Ranges' => 'bytes',
            // Defense in depth: never let a served file be interpreted as a document.
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="' . basename($filepath) . '"',
        ];

        // Conditional GET — return 304 when the client's copy is still fresh.
        $ifNoneMatch = $request->getHeaderLine('If-None-Match');
        $ifModifiedSince = $request->getHeaderLine('If-Modified-Since');
        if (($ifNoneMatch !== '' && trim($ifNoneMatch) === $etag)
            || ($ifModifiedSince !== '' && @strtotime($ifModifiedSince) >= $mtime)) {
            return new Response(304, $baseHeaders);
        }

        // Single-range support (enough for video/audio seeking).
        $range = $request->getHeaderLine('Range');
        if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $m)) {
            $start = $m[1] === '' ? null : (int) $m[1];
            $end = $m[2] === '' ? null : (int) $m[2];
            if ($start === null && $end !== null) {        // suffix range: last N bytes
                $start = max(0, $size - $end);
                $end = $size - 1;
            } else {
                $start ??= 0;
                $end = $end === null ? $size - 1 : min($end, $size - 1);
            }
            if ($start > $end || $start >= $size) {
                return new Response(416, $baseHeaders + ['Content-Range' => "bytes */$size"]);
            }

            $length = $end - $start + 1;
            $fh = fopen($filepath, 'rb');
            fseek($fh, $start);
            $body = stream_get_contents($fh, $length);
            fclose($fh);

            return new Response(206, $baseHeaders + [
                'Content-Range' => "bytes $start-$end/$size",
                'Content-Length' => (string) $length,
            ], $body);
        }

        // Full file — stream the resource so we don't buffer it all in memory.
        $body = Stream::create(fopen($filepath, 'rb'));

        return new Response(200, $baseHeaders + ['Content-Length' => (string) $size], $body);
    }

    private function error(int $code): ResponseInterface
    {
        $text = [403 => 'Forbidden', 404 => 'Not Found'][$code] ?? 'Error';

        return new Response($code, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
        ], $text);
    }
}
