<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Api;

use Grav\Common\Page\Media;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Plugin\Api\Controllers\AbstractApiController;
use Grav\Plugin\Api\Controllers\HandlesMediaUploads;
use Grav\Plugin\Api\Controllers\TranslatesAdminLabels;
use Grav\Plugin\Api\Exceptions\NotFoundException;
use Grav\Plugin\Api\Exceptions\ValidationException;
use Grav\Plugin\Api\Response\ApiResponse;
use Grav\Plugin\FlexObjects\Flex;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class FlexApiController extends AbstractApiController
{
    use HandlesMediaUploads;
    use TranslatesAdminLabels;

    /**
     * Admin-config keys whose string values are user-facing labels and should
     * be translated against the signed-in user's admin language. Everything
     * else (Twig templates in `value`, formatters, field `type`, etc.) is left
     * untouched, matching how blueprint serialization only translates labels.
     */
    private const TRANSLATABLE_LABEL_KEYS = ['label', 'title', 'text', 'help', 'placeholder', 'description'];

    /**
     * Recursively translate language-key-looking label values within an admin
     * config subtree. {@see translateLabel()} is a no-op for anything that
     * isn't a translation key, so non-label strings pass through unchanged.
     *
     * @param array<mixed> $node
     * @return array<mixed>
     */
    private function translateConfigLabels(array $node): array
    {
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = $this->translateConfigLabels($value);
            } elseif (is_string($value) && in_array($key, self::TRANSLATABLE_LABEL_KEYS, true)) {
                $node[$key] = $this->translateLabel($value);
            }
        }

        return $node;
    }

    /**
     * Flatten a blueprint field's `options` into a translated value→label map
     * the frontend can use to render select/checkbox/radio list cells as their
     * configured labels instead of raw stored keys.
     *
     * Only static option arrays are handled. Dynamic options (`data-options@`
     * callables) and non-scalar shapes return null, so the frontend falls back
     * to showing the raw value rather than a wrong label.
     *
     * @param mixed $options
     * @return array<string, string>|null
     */
    private function normalizeOptionLabels($options): ?array
    {
        if (!is_array($options) || $options === []) {
            return null;
        }

        $map = [];
        foreach ($options as $value => $label) {
            // Reject grouped/nested option arrays — only flat maps are supported.
            if (is_array($label)) {
                return null;
            }
            $map[(string) $value] = $this->translateLabel((string) $label);
        }

        return $map;
    }

    /**
     * GET /flex-objects/config
     *
     * Returns UI-relevant plugin configuration for admin-next. Never returns
     * secrets or backend-only data. The flex directories list is exposed
     * separately via GET /flex-objects so callers that just need config stay
     * lightweight.
     */
    public function config(ServerRequestInterface $request): ResponseInterface
    {
        $this->requirePermission($request, 'api.access');

        $cfg = $this->config->get('plugins.flex-objects', []);

        return ApiResponse::create([
            'enabled'      => (bool) ($cfg['enabled'] ?? true),
            'built_in_css' => (bool) ($cfg['built_in_css'] ?? true),
            'security'     => [
                'restrict_page_frontmatter' => (bool) ($cfg['security']['restrict_page_frontmatter'] ?? true),
            ],
            'admin_list'   => [
                'per_page' => (int) ($cfg['admin_list']['per_page'] ?? 15),
                'order'    => [
                    'by'  => (string) ($cfg['admin_list']['order']['by'] ?? 'updated_timestamp'),
                    'dir' => (string) ($cfg['admin_list']['order']['dir'] ?? 'desc'),
                ],
            ],
        ]);
    }

    /**
     * GET /flex-objects — List all enabled flex directories with their admin config.
     */
    public function directories(ServerRequestInterface $request): ResponseInterface
    {
        $this->requirePermission($request, 'api.access');

        $flex = $this->getFlex();
        $user = $this->getUser($request);
        $result = [];

        // Resolve the signed-in user's admin language once so the directory
        // labels below come back localized instead of as raw PLUGIN_* keys
        // (matching how the blueprint endpoints translate their labels).
        $this->primeAdminLanguages($request);

        // Skip built-in types that already have dedicated admin-next UI
        $builtIn = ['pages', 'user-accounts', 'user-groups'];

        foreach ($flex->getDirectories() as $directory) {
            if (!$directory->isEnabled()) {
                continue;
            }

            if (in_array($directory->getFlexType(), $builtIn, true)) {
                continue;
            }

            $config = $directory->getConfig('admin');
            if (empty($config) || !empty($config['disabled'])) {
                continue;
            }

            // Skip directories the user cannot list
            if (!$this->isSuperAdmin($user) && !$directory->isAuthorized('list', 'admin', $user)) {
                continue;
            }

            $menu = $config['menu']['list'] ?? [];

            // Resolve the display type (and, for choice fields, the value→label
            // option map) for each list column so the frontend can render typed
            // cells — datetimes as dates, selects as labels — instead of raw
            // stored values. A list column's own `field.type` wins over the
            // edit-form field type so a list-only `datetime` column isn't
            // mis-reported as `text`.
            $listFields = $config['list']['fields'] ?? [];
            $fieldTypes = [];
            $fieldOptions = [];
            try {
                $formFields = $directory->getBlueprint()->fields();
                foreach ($listFields as $fieldName => $listFieldCfg) {
                    $listDef = (array) ($listFieldCfg['field'] ?? []);
                    $formDef = (array) ($formFields[$fieldName] ?? []);

                    $fieldTypes[$fieldName] = $listDef['type'] ?? $formDef['type'] ?? 'text';

                    $options = $listDef['options'] ?? $formDef['options'] ?? null;
                    $normalized = $this->normalizeOptionLabels($options);
                    if ($normalized !== null) {
                        $fieldOptions[$fieldName] = $normalized;
                    }
                }
            } catch (\Exception $e) {
                // Non-critical
            }

            $result[] = [
                'type'          => $directory->getFlexType(),
                'title'         => $this->translateLabel($menu['title'] ?? $directory->getTitle()),
                'description'   => $this->translateLabel($directory->getDescription() ?? ''),
                'icon'          => $menu['icon'] ?? 'fa-file',
                'list'          => $this->translateConfigLabels($config['list'] ?? []),
                'edit'          => $this->translateConfigLabels($config['edit'] ?? []),
                'search'        => $directory->getConfig('data.search') ?? [],
                'field_types'   => $fieldTypes,
                'field_options' => $fieldOptions,
                'export'        => $this->translateConfigLabels($config['export'] ?? []),
            ];
        }

        return ApiResponse::create($result);
    }

    /**
     * GET /flex-objects/blueprints — List every available flex directory blueprint.
     *
     * Powers the `directories` field on the flex-objects plugin settings page:
     * one toggle per available blueprint, value is the array of enabled
     * blueprint URLs. Includes hidden + currently-disabled blueprints because
     * they're the things the admin is choosing to enable. The legacy URL
     * (pre-rc.4 alias) is included so the field can match saved values that
     * still reference the old form.
     */
    public function blueprints(ServerRequestInterface $request): ResponseInterface
    {
        $this->requirePermission($request, 'api.access');

        $flex = $this->getFlex();
        $newToOld = Flex::getLegacyBlueprintMap(false); // [newUrl => oldUrl]

        $items = [];
        foreach ($flex->getBlueprints() as $directory) {
            $url = $directory->getBlueprintFile();
            $items[] = [
                'url'         => $url,
                'legacy_url'  => $newToOld[$url] ?? null,
                'type'        => $directory->getFlexType(),
                'title'       => $directory->getTitle(),
                'description' => $directory->getDescription(),
            ];
        }

        return ApiResponse::create($items);
    }

    /**
     * GET /flex-objects/{type} — List objects with pagination, search, sort.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $type = $this->getRouteParam($request, 'type');
        $directory = $this->resolveDirectory($type);
        $this->requireFlexPermission($request, $directory, 'list');

        // Per-directory list defaults (admin.list.options). Explicit client
        // query params always win; these only fill the gaps so non-Admin2 API
        // consumers get the same initial page size and ordering Admin2 shows.
        $listOptions = $directory->getConfig('admin.list.options') ?? [];

        $query = $request->getQueryParams();
        $defaultPerPage = isset($listOptions['per_page']) ? (int) $listOptions['per_page'] : null;
        $pagination = $this->getPagination($request, $defaultPerPage);

        $search = $query['search'] ?? null;
        $sortField = $query['sort'] ?? null;
        $sortOrder = strtolower($query['order'] ?? '');
        if ($sortField === null && !empty($listOptions['order']['by'])) {
            $sortField = (string) $listOptions['order']['by'];
            $sortOrder = $sortOrder !== '' ? $sortOrder : strtolower((string) ($listOptions['order']['dir'] ?? 'asc'));
        }
        if (!in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'asc';
        }

        $collection = $directory->getCollection();

        // Apply search
        if ($search && $search !== '') {
            $collection = $collection->search($search);
        }

        // Apply sort
        if ($sortField) {
            $collection = $collection->sort([$sortField => $sortOrder]);
        }

        $total = $collection->count();

        // Slice for pagination
        $objects = $collection->slice($pagination['offset'], $pagination['limit']);

        // Get list field names from config
        $listFields = array_keys($directory->getConfig('admin.list.fields') ?? []);

        $data = [];
        foreach ($objects as $object) {
            $data[] = $this->serializeForList($object, $listFields);
        }

        return ApiResponse::paginated(
            data: $data,
            total: $total,
            page: $pagination['page'],
            perPage: $pagination['per_page'],
            baseUrl: $this->getApiBaseUrl() . '/flex-objects/' . $type,
        );
    }

    /**
     * GET /flex-objects/{type}/{key} — Get a single object.
     */
    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $type = $this->getRouteParam($request, 'type');
        $directory = $this->resolveDirectory($type);
        $this->requireFlexPermission($request, $directory, 'read');

        $key = $this->getRouteParam($request, 'key');
        $object = $directory->getObject($key);

        if (!$object) {
            throw new NotFoundException("Object '{$key}' not found in '{$type}'.");
        }

        return $this->respondWithEtag($this->serializeObject($object));
    }

    /**
     * POST /flex-objects/{type} — Create a new object.
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $type = $this->getRouteParam($request, 'type');
        $directory = $this->resolveDirectory($type);
        $this->requireFlexPermission($request, $directory, 'create');

        $body = $this->getRequestBody($request);
        unset($body['__meta']);

        try {
            $object = $directory->createObject($body, '');
            $object->save();
        } catch (\Exception $e) {
            throw new \Grav\Plugin\Api\Exceptions\ValidationException(
                'Failed to create object: ' . $e->getMessage(),
            );
        }

        $this->fireAdminEvent('onAdminAfterSave', ['object' => $object]);

        $key = $object->getKey();

        return ApiResponse::created(
            data: $this->serializeObject($object),
            location: $this->getApiBaseUrl() . '/flex-objects/' . $type . '/' . $key,
            headers: $this->invalidationHeaders([
                'flex-objects:' . $type . ':list',
            ]),
        );
    }

    /**
     * PATCH /flex-objects/{type}/{key} — Update an existing object.
     */
    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $type = $this->getRouteParam($request, 'type');
        $directory = $this->resolveDirectory($type);
        $this->requireFlexPermission($request, $directory, 'update');

        $key = $this->getRouteParam($request, 'key');
        $object = $directory->getObject($key);

        if (!$object) {
            throw new NotFoundException("Object '{$key}' not found in '{$type}'.");
        }

        // ETag validation
        $currentEtag = $this->generateEtag($this->serializeObject($object));
        $this->validateEtag($request, $currentEtag);

        $body = $this->getRequestBody($request);
        unset($body['__meta']);

        try {
            $object->update($body);
            $object->save();
        } catch (\Exception $e) {
            throw new \Grav\Plugin\Api\Exceptions\ValidationException(
                'Failed to update object: ' . $e->getMessage(),
            );
        }

        $this->fireAdminEvent('onAdminAfterSave', ['object' => $object]);

        return $this->respondWithEtag(
            $this->serializeObject($object),
            200,
            ['flex-objects:' . $type . ':list', 'flex-objects:' . $type . ':update:' . $key],
        );
    }

    /**
     * DELETE /flex-objects/{type}/{key} — Delete an object.
     */
    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $type = $this->getRouteParam($request, 'type');
        $directory = $this->resolveDirectory($type);
        $this->requireFlexPermission($request, $directory, 'delete');

        $key = $this->getRouteParam($request, 'key');
        $object = $directory->getObject($key);

        if (!$object) {
            throw new NotFoundException("Object '{$key}' not found in '{$type}'.");
        }

        $object->delete();

        $this->fireAdminEvent('onAdminAfterDelete', ['object' => $object]);

        return ApiResponse::noContent(
            $this->invalidationHeaders([
                'flex-objects:' . $type . ':list',
                'flex-objects:' . $type . ':delete:' . $key,
            ]),
        );
    }

    /**
     * GET /flex-objects/{type}/export — Export all objects as YAML.
     */
    public function export(ServerRequestInterface $request): ResponseInterface
    {
        $type = $this->getRouteParam($request, 'type');
        $directory = $this->resolveDirectory($type);
        $this->requireFlexPermission($request, $directory, 'list');

        $collection = $directory->getCollection();
        $data = [];

        foreach ($collection as $object) {
            $data[$object->getKey()] = $object->jsonSerialize();
        }

        $yaml = \Grav\Common\Yaml::dump($data, 10, 2);
        $filename = $type . '-' . date('Y-m-d') . '.yaml';

        return new \Grav\Framework\Psr7\Response(
            200,
            [
                'Content-Type' => 'application/x-yaml',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-store, max-age=0',
            ],
            $yaml,
        );
    }

    /**
     * GET /flex-objects/{type}/{key}/media — List media attached to an object.
     *
     * For folder-stored directories the media lives in the object's own
     * storage folder (e.g. user-data://flex-objects/contacts/{id}), alongside
     * the object's data file.
     */
    public function mediaList(ServerRequestInterface $request): ResponseInterface
    {
        $type = $this->getRouteParam($request, 'type');
        $directory = $this->resolveDirectory($type);
        $this->requireFlexPermission($request, $directory, 'read');

        $object = $this->resolveObject($directory, $request);
        $folder = $this->resolveMediaFolder($object);

        $media = new Media($folder);
        $serialized = $this->getSerializer()->serializeCollection($media->all());

        return ApiResponse::create($serialized);
    }

    /**
     * POST /flex-objects/{type}/{key}/media — Upload file(s) to an object.
     */
    public function mediaUpload(ServerRequestInterface $request): ResponseInterface
    {
        $type = $this->getRouteParam($request, 'type');
        $directory = $this->resolveDirectory($type);
        $this->requireFlexPermission($request, $directory, 'update');

        $object = $this->resolveObject($directory, $request);
        $key = $object->getKey();
        $folder = $this->resolveMediaFolder($object);

        if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
            throw new ValidationException('Unable to create media directory for this object.');
        }

        $uploadedFiles = $this->flattenUploadedFiles($request->getUploadedFiles());
        if ($uploadedFiles === []) {
            throw new ValidationException('No files were uploaded.');
        }

        // Honor per-field upload settings (random_name, accept, ...) forwarded
        // by the file field; absent, this is an inert no-op.
        $settings = $this->parseUploadFieldSettings($request);

        $uploadedNames = [];
        foreach ($uploadedFiles as $file) {
            // Fire before event — plugins can throw to reject specific files
            $this->fireEvent('onApiBeforeMediaUpload', [
                'object' => $object,
                'filename' => $file->getClientFilename(),
                'type' => $file->getClientMediaType(),
                'size' => $file->getSize(),
            ]);

            $uploadedNames[] = $this->processUploadedFile($file, $folder, $settings);
        }

        // Fresh Media object to pick up the newly uploaded files
        $media = new Media($folder);
        $serialized = $this->getSerializer()->serializeCollection($media->all());

        $this->fireAdminEvent('onAdminAfterAddMedia', ['object' => $object]);
        $this->fireEvent('onApiMediaUploaded', [
            'object' => $object,
            'filenames' => $uploadedNames,
        ]);

        return ApiResponse::created(
            data: $serialized,
            location: $this->getApiBaseUrl() . '/flex-objects/' . $type . '/' . $key . '/media',
            headers: $this->invalidationHeaders([
                'flex-objects:' . $type . ':media:' . $key,
                'flex-objects:' . $type . ':update:' . $key,
            ]),
        );
    }

    /**
     * DELETE /flex-objects/{type}/{key}/media/{filename} — Delete a media file.
     */
    public function mediaDelete(ServerRequestInterface $request): ResponseInterface
    {
        $type = $this->getRouteParam($request, 'type');
        $directory = $this->resolveDirectory($type);
        $this->requireFlexPermission($request, $directory, 'update');

        $object = $this->resolveObject($directory, $request);
        $key = $object->getKey();
        $folder = $this->resolveMediaFolder($object);
        $filename = $this->getSafeFilename($request);

        $filePath = $folder . '/' . $filename;
        if (!file_exists($filePath)) {
            throw new NotFoundException("Media file '{$filename}' not found on this object.");
        }

        $this->fireEvent('onApiBeforeMediaDelete', ['object' => $object, 'filename' => $filename]);

        unlink($filePath);

        // Also remove any metadata sidecar (.meta.yaml) if it exists
        $metaPath = $filePath . '.meta.yaml';
        if (file_exists($metaPath)) {
            unlink($metaPath);
        }

        $this->fireAdminEvent('onAdminAfterDelMedia', ['object' => $object, 'filename' => $filename]);
        $this->fireEvent('onApiMediaDeleted', ['object' => $object, 'filename' => $filename]);

        return ApiResponse::noContent(
            $this->invalidationHeaders([
                'flex-objects:' . $type . ':media:' . $key,
                'flex-objects:' . $type . ':update:' . $key,
            ]),
        );
    }

    // ─── Helpers ───────────────────────────────────────────────

    /**
     * Resolve the {key} route param to an existing object or throw a 404.
     */
    private function resolveObject(FlexDirectory $directory, ServerRequestInterface $request): FlexObjectInterface
    {
        $key = $this->getRouteParam($request, 'key');
        $object = $key !== null && $key !== '' ? $directory->getObject($key) : null;

        if (!$object) {
            $type = $directory->getFlexType();
            throw new NotFoundException("Object '{$key}' not found in '{$type}'.");
        }

        return $object;
    }

    /**
     * Resolve an object's media folder to an absolute, writable filesystem path.
     *
     * getMediaFolder() returns null for SimpleStorage directories (a single
     * shared file, no per-object folder) and a GRAV_ROOT-relative or stream
     * path for folder-stored ones. Normalize all cases to an absolute path.
     */
    private function resolveMediaFolder(FlexObjectInterface $object): string
    {
        $folder = method_exists($object, 'getMediaFolder') ? $object->getMediaFolder() : null;
        if (!$folder) {
            throw new ValidationException(
                'This directory does not support per-object media. '
                . 'Object media requires folder-based storage.',
            );
        }

        /** @var \RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        if ($locator->isStream($folder)) {
            // Resolve to the absolute writable path, even if it doesn't exist yet
            $resolved = $locator->findResource($folder, true, true);
            if ($resolved) {
                return $resolved;
            }
        }

        // Already absolute? Use as-is. Otherwise treat as GRAV_ROOT-relative.
        if (str_starts_with($folder, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $folder)) {
            return $folder;
        }

        return rtrim(GRAV_ROOT, '/') . '/' . $folder;
    }

    private function getFlex(): Flex
    {
        return $this->grav['flex_objects'];
    }

    private function resolveDirectory(?string $type): FlexDirectory
    {
        if (!$type) {
            throw new NotFoundException('Flex directory type is required.');
        }

        $flex = $this->getFlex();
        $directory = $flex->getDirectory($type);

        if (!$directory || !$directory->isEnabled()) {
            throw new NotFoundException("Flex directory '{$type}' not found or not enabled.");
        }

        return $directory;
    }

    /**
     * Check the directory-specific permission derived from the blueprint.
     *
     * Checks both api.* and admin.* prefixed permissions (OR logic) so users
     * with either grant can access the flex directory via the API.
     */
    private function requireFlexPermission(
        ServerRequestInterface $request,
        FlexDirectory $directory,
        string $action,
    ): void {
        $user = $this->getUser($request);

        if ($this->isSuperAdmin($user)) {
            return;
        }

        // Check API access
        if (!$this->hasPermission($user, 'api.access')) {
            throw new \Grav\Plugin\Api\Exceptions\ForbiddenException('API access is not enabled for this user.');
        }

        // Check directory-level permission from blueprint config.
        // Blueprints may define both admin.* and api.* permissions — check all
        // registered prefixes (OR: any matching permission grants access).
        $permissions = $directory->getConfig('admin.permissions');
        if ($permissions) {
            foreach ($permissions as $prefix => $config) {
                $permission = $prefix . '.' . $action;
                if ($this->hasPermission($user, $permission)) {
                    return;
                }
            }
            // None matched — report the first prefix for a clear error
            $prefix = array_key_first($permissions);
            throw new \Grav\Plugin\Api\Exceptions\ForbiddenException("Missing required permission: {$prefix}.{$action}");
        }
    }

    private function serializeObject(FlexObjectInterface $object): array
    {
        $data = $object->jsonSerialize();

        return array_merge(
            ['key' => $object->getKey(), '__meta' => $this->objectMeta($object)],
            is_array($data) ? $data : [],
        );
    }

    /**
     * Read-only metadata for the admin "object info" panel: the identifier used
     * in code snippets plus where the object lives on disk. Returned under the
     * reserved `__meta` key so the admin can show it without it ever becoming
     * part of the saved object data.
     *
     * @return array<string, string>
     */
    private function objectMeta(FlexObjectInterface $object): array
    {
        $meta = [
            'type' => $object->getFlexType(),
            'key' => $object->getKey(),
            'storageKey' => $object->getStorageKey(),
        ];

        // Storage folder comes from the media trait, not the object interface,
        // so guard it — some storages return null until the object is saved.
        if (method_exists($object, 'getStorageFolder')) {
            $folder = $object->getStorageFolder();
            if ($folder) {
                $meta['storagePath'] = $folder;
            }
        }

        return $meta;
    }

    private function serializeForList(FlexObjectInterface $object, array $listFields): array
    {
        $data = ['key' => $object->getKey()];

        if ($listFields) {
            foreach ($listFields as $field) {
                $data[$field] = $object->getProperty($field);
            }
        } else {
            // No list config — return all data
            $all = $object->jsonSerialize();
            if (is_array($all)) {
                $data = array_merge($data, $all);
            }
        }

        return $data;
    }
}
