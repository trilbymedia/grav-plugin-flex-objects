# Flex Object media proxy

**Status:** prototype (opt-in, disabled by default)
**Target:** flex-objects 1.4.x
**Related:** [getgrav/grav#4129](https://github.com/getgrav/grav/issues/4129)

## Problem

A Flex Object stores its data file and its uploaded media in the **same**
folder under `user://data/<type>/<key>/`:

```
user/data/contacts/0001/
  item.yaml          <- object data: must NEVER be web-served
  avatar.jpg         <- uploaded media: has always been web-served
  resume.pdf         <- uploaded file: may be public OR private
```

Historically the media was linked with a direct `/user/data/...` URL, so the
**webserver** — not Grav — decides who can read it. That has two consequences:

1. A blanket deny on `user/data` (added as a security hardening in Grav core)
   returns a `403` for every Flex Object image. See grav#4129.
2. There is no way to keep a Flex Object's media *private*: anyone who knows or
   guesses the path can fetch it, regardless of the object's read permissions.

The webserver-level mitigation (Grav core) keeps `user/data` denied **except**
for a fixed set of public media extensions, which unbreaks images without
re-exposing data files. That is the compatibility floor. This proxy is the
application-level half: **store in `user://data`, retrieve through Grav.**

## Goal

Serve a single Flex Object media file through PHP after resolving the owning
object and (optionally) checking its read ACL, so that:

- object media can live under a fully locked-down `user/data`;
- *private* object media is actually enforceable (per-object read permission);
- existing direct URLs keep working during the transition (the webserver
  carve-out stays until content is fully migrated to proxy URLs).

## Target architecture

The intended end-state is **proxy-on by default**: the proxy is the *sole* path
to Flex Object media, and `user/data` is **full-deny** at the webserver. Opting
out reverts to **webserver-config-only** (direct URLs + the media carve-out).

```
media_proxy.enabled: true  (target default)   media_proxy.enabled: false (opt-out)
  ── Medium::url() for user://data originals     ── Medium::url() emits direct
     emits proxy URLs (images/ derivatives          /user/data/... URLs
     stay direct)                                 ── webserver config must carry the
  ── webserver config = simple full-deny             media carve-out (grav#4129) or
     of user/data (no carve-out)                      images 403
  ── media delivery no longer depends on the     ── media delivery depends entirely
     webserver being configured at all              on correct per-server config
```

**Why proxy-on is the more reliable default.** The auto-applied hardening only
covers Apache (`.htaccess`); nginx, Caddy, IIS and lighttpd users must hand-edit
their config, and the *media carve-out* is the fragile, hard-to-replicate part
(a per-server regex — see the IIS/Caddy caveats in the core change). With the
proxy as the sole media path, the webserver rule collapses to the simplest,
most portable thing there is — "deny `user/data`" — and **media keeps working
even on a server the admin never configured**, because the proxy serves through
`index.php`, which is always reachable.

**Important caveat — the proxy cannot protect the data files themselves.** A
direct request for `user/data/<type>/<key>/item.yaml` never reaches PHP, so Grav
cannot intercept it; only a webserver deny (or storing `user/` outside the web
root via `GRAV_USER_PATH`) keeps data files private. So the proxy makes *media
delivery* webserver-independent, but **data-file security still requires the
full-deny rule** — the win is that that rule is now the trivial, portable
one-liner instead of the fragile carve-out. Pushing `user/` out of the web root
remains the only way to make data-file security fully config-independent, and is
the recommended complementary hardening.

## Route

```
GET <base>/<type>/<key>/<filename>[?field=<field>]
```

- `base` — configurable, default `/flex-media`.
- `type` — flex directory key (e.g. `contacts`).
- `key` — object key.
- `filename` — media filename (may include sub-path segments).
- `field` — optional; resolve media from a specific field's collection
  (file/avatar/pagemedia field with a custom `destination`) rather than the
  object's own media.

Registered on `onPagesInitialized` at high priority (before the default flex
router and the 404 handler), gated by `media_proxy.enabled`.

## Behaviour

| Condition | Response |
|---|---|
| Proxy disabled | handler returns, request continues normally |
| `..`, leading `.`, or non-serveable extension in filename | `404` |
| Object missing / does not exist | `404` |
| `authorize: true` and `object.isAuthorized('read','frontend',user) === false` | `403` |
| Media item not found on the object | `404` |
| Fresh client copy (`If-None-Match` / `If-Modified-Since`) | `304` |
| Valid `Range` header | `206` partial |
| Otherwise | `200` streamed file |

Served responses set `Content-Type`, `Content-Length`, `Last-Modified`, `ETag`,
`Accept-Ranges`, `Cache-Control`, `X-Content-Type-Options: nosniff`, and
`Content-Disposition: inline`. The body is streamed from a file resource (full
requests) so large files are not buffered in memory.

### Serveable extensions

The proxy refuses anything outside an allow-list
(`jpg jpeg png gif webp avif bmp ico mp4 webm ogg ogv mov mp3 wav m4a flac pdf`)
so it can never hand out data files, databases or keys even if a caller crafts a
filename. **SVG is intentionally excluded** (stored-XSS vector), matching the
core `.htaccess` allow-list.

### Permission model

Reads are **not ACL-gated for now** — `authorize` defaults to `false`, so the
proxy is a pure routing/integrity gate (serve any existing media, no permission
check). Its purpose at this stage is a single retrieval chokepoint, not
per-object access control.

The capability is kept behind the flag: setting `authorize: true` makes the
proxy deny only on an **explicit** `false` from the object's read check, so
directories without a read ACL keep behaving as public media (no regression)
while directories that *do* define a read restriction get it enforced. Turn this
on only once the caching story for private media (below) is settled.

## Configuration

```yaml
media_proxy:
  enabled: false              # opt-in while prototyping
  base: '/flex-media'         # public route prefix
  authorize: true             # honour the object's read ACL
  cache_control: 'public, max-age=604800'
```

## Generating URLs

When `media_proxy.enabled`, **`medium.url` already routes through the proxy** —
existing templates need no change:

```twig
<img src="{{ object.media['avatar.jpg'].url }}">      {# proxied original #}
<img src="{{ object.media['avatar.jpg'].cropResize(300,300).url }}">  {# derivative, served from images/ #}
```

This works via three pieces (all behind the flag):

1. **Core — `onFlexObjectMedia` event** (`FlexMediaTrait::getMedia()`). Fired once
   per object when its media collection is first built, passing the object and
   the collection so a listener can stamp a `url` override on each item.
2. **Plugin — listener** stamps `MediaProxyController::url($object, $filename)`
   onto every media item as its `url` override.
3. **Core — `ImageMedium::url()`** honors that override **only for the unmodified
   original** — gated on `empty($this->image)`, the same condition under which
   `saveImage()` returns the source file. The moment a modifier is applied
   (`$this->image` is instantiated) the override is skipped and the derivative
   serves from `images/`. Non-image files already honor the override via
   `MediaFileTrait::url()`.

`flex_media_url(object, filename, field)` / `MediaProxyController::url(...)` remain
available for explicit links (e.g. field-scoped media not covered by the
object's own collection).

### Known nuances (prototype)

- The override is returned verbatim, so the media-timestamp cache-buster
  (`?<mtime>`) is not appended to proxied originals. The proxy already sends
  `ETag`/`Last-Modified`, but add the timestamp if byte-identical replacement
  under the same filename must bust shared caches.
- `srcset()` retina alternatives of an unmodified image also resolve to the
  override; per-alternative handling can come later.
- The persistent media cache stores the **un-stamped** collection (the event
  fires after `MediaTrait::getMedia()` caches it), so toggling `enabled` only
  needs a normal cache clear — and admin requests (no listener) are unaffected.

## Open items / core follow-up

1. **Automatic URL rewriting (core) — DONE.** `medium.url` now emits the proxy
   URL when `media_proxy.enabled`, via the `onFlexObjectMedia` event +
   `ImageMedium::url()` override described under "Generating URLs". Implemented in
   `system/src/Grav/Framework/Flex/Traits/FlexMediaTrait.php` (event) and
   `system/src/Grav/Common/Page/Medium/ImageMedium.php` (override).

   **Scope — only `user://data` originals, never `images/`** (satisfied by the
   implementation). The override is honored *only* on the original branch; the
   moment any modifier is applied (`.cropResize(...)`, `.resize(...)`, etc.) the
   result lives under `images/` and is served direct, untouched. Only `images/`
   is exempt — no other path. Remaining: the stamping listener currently applies
   to every object's own media collection regardless of where it is stored;
   restricting it to `user://data`-backed directories (vs. custom destinations)
   is a follow-up knob.
2. **Re-tighten `user/data` to full-deny (target default).** Once content emits
   proxy URLs, the core `.htaccess` media carve-out (grav#4129) is dropped and
   `user/data` goes back to a simple full-deny, because the proxy — not the
   webserver — serves the `user://data` originals (derivatives already live under
   `images/`). Sequencing matters: don't re-tighten until rewriting is in and
   content is migrated.

   **Config coordination.** The webserver rule that's correct depends on
   `media_proxy.enabled`, but the rule lives in core's installer
   (`.htaccess`/`webserver-configs/`), which doesn't read the plugin setting. So
   the two modes can't both be auto-correct from one shipped file. Resolution:
   ship the **carve-out** form by default (safe whether or not the proxy is on —
   it never *blocks* media), and have the Admin security check detect
   `media_proxy.enabled = true` and recommend (or, for Apache, offer to apply)
   the simpler full-deny. Flipping the shipped default to full-deny only makes
   sense once proxy-on is itself the default and the `Medium::url()` rewrite has
   landed.
3. **Caching headers vs. private media.** `cache_control: public` is correct
   while reads are not ACL-gated. If `authorize` is later turned on, hits to
   restricted objects must instead send `private, no-store`; production should
   vary the header by whether a read restriction applied. This is the gate that
   must be built before `authorize: true` is recommended.
4. **Range/conditional hardening.** Multi-range requests are not supported
   (single range only); revisit if large-video streaming is a real use case.
5. **Signed URLs (optional).** For private media shared via expiring links,
   consider an HMAC-signed variant of the route as an alternative to session ACL.
