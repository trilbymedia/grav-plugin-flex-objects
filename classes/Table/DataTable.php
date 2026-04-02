<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Table;

use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Collection\CollectionInterface;
use Grav\Framework\Flex\Interfaces\FlexAuthorizeInterface;
use Grav\Framework\Flex\Interfaces\FlexCollectionInterface;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use JsonSerializable;
use Throwable;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use function html_entity_decode;
use function is_array;
use function is_string;
use function preg_replace;
use function strip_tags;

/**
 * Class DataTable
 * @package Grav\Plugin\Gitea
 *
 * https://github.com/ratiw/vuetable-2/wiki/Data-Format-(JSON)
 * https://github.com/ratiw/vuetable-2/wiki/Sorting
 */
class DataTable implements JsonSerializable
{
    /** @var string */
    private $url;
    /** @var int */
    private $limit;
    /** @var int */
    private $page;
    /** @var array */
    private $sort;
    /** @var string */
    private $search;
    /** @var array */
    private $filters = [];
    /** @var FlexCollectionInterface */
    private $collection;
    /** @var FlexCollectionInterface */
    private $filteredCollection;
    /** @var array */
    private $columns;
    /** @var Environment */
    private $twig;
    /** @var array */
    private $twig_context;
    /** @var array|null */
    private $detailConfig;
    /** @var bool */
    private $detailConfigLoaded = false;

    /**
     * DataTable constructor.
     * @param array $params
     */
    public function __construct(array $params)
    {
        $this->setUrl($params['url'] ?? '');
        $this->setLimit((int)($params['limit'] ?? 10));
        $this->setPage((int)($params['page'] ?? 1));
        $this->setSort($params['sort'] ?? ['id' => 'asc']);
        $this->setSearch($params['search'] ?? '');
        $this->setFilters($params['filters'] ?? []);
    }

    /**
     * @param string $url
     * @return void
     */
    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    /**
     * @param int $limit
     * @return void
     */
    public function setLimit(int $limit): void
    {
        $this->limit = max(1, $limit);
    }

    /**
     * @param int $page
     * @return void
     */
    public function setPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    /**
     * @param string|string[] $sort
     * @return void
     */
    public function setSort($sort): void
    {
        if (is_string($sort)) {
            $sort = $this->decodeSort($sort);
        } elseif (!is_array($sort)) {
            $sort = [];
        }

        $this->sort = $sort;
    }

    /**
     * @param string $search
     * @return void
     */
    public function setSearch(string $search): void
    {
        $this->search = $search;
    }

    /**
     * @param mixed $filters
     * @return void
     */
    public function setFilters($filters): void
    {
        if (is_string($filters) && $filters !== '') {
            $decoded = json_decode($filters, true);
            if (is_array($decoded)) {
                $filters = $decoded;
            }
        }

        $this->filters = is_array($filters) ? $filters : [];
    }

    /**
     * @param CollectionInterface $collection
     * @return void
     */
    public function setCollection(CollectionInterface $collection): void
    {
        $this->collection = $collection;
        $this->filteredCollection = null;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @return int
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * @return int
     */
    public function getLastPage(): int
    {
        return 1 + (int)floor(max(0, $this->getTotal()-1) / $this->getLimit());
    }

    /**
     * @return int
     */
    public function getTotal(): int
    {
        $collection = $this->filteredCollection ?? $this->getCollection();

        return $collection ? $collection->count() : 0;
    }

    /**
     * @return array
     */
    public function getSort(): array
    {
        return $this->sort;
    }

    /**
     * @return FlexCollectionInterface|null
     */
    public function getCollection(): ?FlexCollectionInterface
    {
        return $this->collection;
    }

    /**
     * @param int $page
     * @return string|null
     */
    public function getUrl(int $page): ?string
    {
        if ($page < 1 || $page > $this->getLastPage()) {
            return null;
        }

        $params = [
            'page' => $page,
            'per_page' => $this->getLimit(),
            'sort' => $this->encodeSort()
        ];

        if ($this->search !== '') {
            $params['filter'] = $this->search;
        }

        if ($this->filters) {
            $params['filters'] = $this->filters;
        }

        return "{$this->url}.json?" . http_build_query($params);
    }

    /**
     * @return array
     */
    public function getColumns(): array
    {
        if (null === $this->columns) {
            $collection = $this->getCollection();
            if (!$collection) {
                return [];
            }

            $blueprint = $collection->getFlexDirectory()->getBlueprint();
            $columns = $blueprint->get('config/admin/views/list/fields') ?? $blueprint->get('config/admin/list/fields', []);
            $this->columns = $this->normalizeColumns($blueprint, $columns);
        }

        return $this->columns;
    }

    public function getDetailConfig(): ?array
    {
        if ($this->detailConfigLoaded) {
            return $this->detailConfig;
        }

        $collection = $this->getCollection();
        if (!$collection) {
            return null;
        }

        $blueprint = $collection->getFlexDirectory()->getBlueprint();
        $detail = $blueprint->get('config/admin/views/list/detail') ?? $blueprint->get('config/admin/list/detail');
        $this->detailConfigLoaded = true;
        if (!$detail || empty($detail['enabled'])) {
            return null;
        }

        $this->detailConfig = $detail;

        return $this->detailConfig;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        $grav = Grav::instance();

        /** @var Debugger $debugger */
        $debugger = $grav['debugger'];
        $debugger->startTimer('datatable', 'Data Table');

        $collection = $this->getCollection();
        if (!$collection) {
            return [];
        }
        if ($this->filters) {
            $collection = $this->applyFilters($collection, $this->filters);
        }

        if ($this->search !== '') {
            $collection = $collection->search($this->search);
        }

        $columns = $this->getColumns();

        $collection = $collection->sort($this->getSort());

        $this->filteredCollection = $collection;

        $limit = $this->getLimit();
        $page = $this->getPage();
        $to = $page * $limit;
        $from = $to - $limit + 1;

        if ($from < 1 || $from > $this->getTotal()) {
            $debugger->stopTimer('datatable');
            return [];
        }

        $array = $collection->slice($from-1, $limit);

        $twig = $grav['twig'];
        $grav->fireEvent('onTwigSiteVariables');

        $this->twig = $twig->twig;
        $this->twig_context = $twig->twig_vars;

        $list = [];
        /** @var FlexObjectInterface $object */
        foreach ($array as $object) {
            $item = [
                'id' => $object->getKey(),
                'timestamp' => $object->getTimestamp()
            ];
            foreach ($columns as $name => $column) {
                $item[str_replace('.', '_', $name)] = $this->renderColumn($name, $column, $object);
            }
            $item['_actions_'] = $this->renderActions($object);

            $detail = $this->renderDetail($object);
            if ($detail) {
                $item['detail_toggle'] = $detail['toggle'];
                $item['detail_title'] = $detail['title'];
                $item['detail_close_label'] = $detail['close_label'];
                $item['detail_store'] = $detail['store'];
            }

            $list[] = $item;
        }

        $debugger->stopTimer('datatable');

        return $list;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        $data = $this->getData();
        $total = $this->getTotal();
        $limit = $this->getLimit();
        $page = $this->getPage();
        $to = $page * $limit;
        $from = $to - $limit + 1;

        $empty = empty($data);

        return [
            'links' => [
                'pagination' => [
                    'total' => $total,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'last_page' => $this->getLastPage(),
                    'next_page_url' => $this->getUrl($page+1),
                    'prev_page_url' => $this->getUrl($page-1),
                    'from' => $empty ? null : $from,
                    'to' => $empty ? null : min($to, $total),
                ]
            ],
            'data' => $data
        ];
    }

    /**
     * @param string $name
     * @param array $column
     * @param FlexObjectInterface $object
     * @return false|string
     * @throws Throwable
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    protected function renderColumn(string $name, array $column, FlexObjectInterface $object)
    {
        $grav = Grav::instance();
        $flex = $grav['flex_objects'];

        $value = $object->getFormValue($name) ?? $object->getNestedProperty($name, $column['field']['default'] ?? null);
        $type = $column['field']['type'] ?? 'text';
        $hasLink = $column['link'] ?? null;
        $link = null;

        $authorized = $object instanceof FlexAuthorizeInterface
            ? ($object->isAuthorized('read') || $object->isAuthorized('update')) : true;

        if ($hasLink && $authorized) {
            $route = $grav['route']->withExtension('');
            $link = $route->withAddedPath($object->getKey())->withoutParams()->getUri();
        }

        $template = $this->twig->resolveTemplate(["forms/fields/{$type}/edit_list.html.twig", 'forms/fields/text/edit_list.html.twig']);

        return $this->twig->load($template)->render([
            'value' => $value,
            'link' => $link,
            'field' => $column['field'],
            'object' => $object,
            'flex' => $flex,
            'route' => $grav['route']->withExtension('')
        ] + $this->twig_context);
    }

    /**
     * @param FlexObjectInterface $object
     * @return false|string
     * @throws Throwable
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    protected function renderActions(FlexObjectInterface $object, $route = null)
    {
        $grav = Grav::instance();
        $type = $object->getFlexType();
        $template = $this->twig->resolveTemplate(["flex-objects/types/{$type}/list/list_actions.html.twig", 'flex-objects/types/default/list/list_actions.html.twig']);

        return $this->twig->load($template)->render([
            'object' => $object,
            'object_title' => $this->getActionTitle($object),
            'flex' => $grav['flex_objects'],
            'route' => ($route ?? $grav['route'])->withExtension('')
        ] + $this->twig_context);
    }

    protected function renderDetail(FlexObjectInterface $object): ?array
    {
        $detail = $this->getDetailConfig();
        if (!$detail) {
            return null;
        }

        $relation = $detail['relation'] ?? [];
        $relatedType = $relation['type'] ?? null;
        $localKey = $relation['local_key'] ?? 'id';
        $foreignKey = $relation['foreign_key'] ?? null;
        if (!$relatedType || !$foreignKey) {
            return null;
        }

        $localValue = $this->getObjectValue($object, $localKey);
        if ($localValue === null || $localValue === '') {
            return null;
        }

        $grav = Grav::instance();
        $flex = $grav['flex_objects'];
        /** @var FlexDirectory|null $relatedDirectory */
        $relatedDirectory = $flex->getDirectory($relatedType);
        if (!$relatedDirectory) {
            return null;
        }

        $relatedCollection = $this->applyFilters($relatedDirectory->getCollection(), [$foreignKey => $localValue]);

        $sort = $this->normalizeDetailSort($relation['sort'] ?? ($detail['sort'] ?? []));
        if ($sort) {
            $relatedCollection = $relatedCollection->sort($sort);
        }

        $relatedTotal = $relatedCollection->count();

        if (!$relatedTotal) {
            return null;
        }

        $columns = $this->getDetailColumns($relatedDirectory, $detail);
        if (!$columns) {
            return null;
        }

        $toggleLabel = $detail['label'] ?? $detail['title'] ?? 'Details';
        $toggleIcon = $detail['icon'] ?? 'fa-list-alt';
        $toggleText = sprintf('<i class="fa %s" aria-hidden="true"></i>', $toggleIcon);
        $perPage = (int)($detail['limit'] ?? 0);
        if ($perPage < 1) {
            $perPage = (int)($relatedDirectory->getBlueprint()->get('config/admin/list/per_page') ?? 10);
        }

        return [
            'toggle' => sprintf(
                '<button type="button" class="flex-detail-toggle" title="%s" aria-label="%s">%s</button>',
                htmlspecialchars($toggleLabel, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($toggleLabel, ENT_QUOTES, 'UTF-8'),
                $toggleText
            ),
            'title' => sprintf(
                '%s - %s',
                $this->getActionTitle($object),
                $this->translateString((string)($detail['title'] ?? $detail['label'] ?? $relatedDirectory->getBlueprint()->get('title') ?? $relatedType))
            ),
            'close_label' => $this->translateString('PLUGIN_FLEX_OBJECTS.ACTION.CLOSE'),
            'store' => [
                'api' => $this->getTypeApiUrl($relatedType),
                'perPage' => $perPage,
                'trackBy' => 'id',
                'fields' => $this->buildVuetableFields($relatedDirectory, $columns),
                'searchFields' => $this->buildSearchFields($columns),
                'sortOrder' => $this->buildSortOrder($sort),
                'paginationInfo' => $this->translateString('PLUGIN_FLEX_OBJECTS.LIST_INFO'),
                'emptyResult' => $this->translateString('PLUGIN_FLEX_OBJECTS.EMPTY_RESULT'),
                'filters' => [$foreignKey => $localValue]
            ]
        ];
    }

    protected function getObjectValue(FlexObjectInterface $object, string $property)
    {
        if ($property === 'id' || $property === 'key') {
            return $object->getKey();
        }

        return $object->getFormValue($property) ?? $object->getNestedProperty($property);
    }

    protected function applyFilters(FlexCollectionInterface $collection, array $filters): FlexCollectionInterface
    {
        $filters = array_filter($filters, static function ($value) {
            return $value !== null && $value !== '';
        });

        if (!$filters) {
            return $collection;
        }

        return $collection->filter(function (FlexObjectInterface $object) use ($filters) {
            foreach ($filters as $property => $expected) {
                $actual = $this->getObjectValue($object, (string)$property);

                if (is_array($expected)) {
                    $expectedValues = array_map('strval', $expected);
                    if (!in_array((string)$actual, $expectedValues, true)) {
                        return false;
                    }

                    continue;
                }

                if ((string)$actual !== (string)$expected) {
                    return false;
                }
            }

            return true;
        });
    }

    protected function normalizeDetailSort($sort): array
    {
        if (is_string($sort)) {
            return $this->decodeSort($sort);
        }

        if (isset($sort['by'])) {
            return [$sort['by'] => $sort['dir'] ?? 'asc'];
        }

        return is_array($sort) ? $sort : [];
    }

    protected function getDetailColumns(FlexDirectory $directory, array $detail): array
    {
        $blueprint = $directory->getBlueprint();
        $defaultColumns = $blueprint->get('config/admin/views/list/fields') ?? $blueprint->get('config/admin/list/fields', []);
        $columns = $detail['fields'] ?? [];

        if (!$columns) {
            $list = $this->normalizeColumns($blueprint, $defaultColumns);
        } else {
            $resolvedColumns = [];
            foreach ($columns as $key => $options) {
                if (is_int($key)) {
                    $key = is_string($options) ? $options : null;
                    $options = true;
                }

                if (!$key || $options === false || $options === null) {
                    continue;
                }

                $base = $defaultColumns[$key] ?? [];
                if ($options === true) {
                    $resolvedColumns[$key] = $base ?: [];
                    continue;
                }

                if (!is_array($options)) {
                    continue;
                }

                $resolvedColumns[$key] = $base ? array_replace_recursive($base, $options) : $options;
            }

            $list = $this->normalizeColumns($blueprint, $resolvedColumns);
        }

        if (!empty($detail['actions'])) {
            $list['_actions_'] = [
                'title' => 'PLUGIN_FLEX_OBJECTS.ACTION.ACTIONS',
                'width' => 1,
                'field' => [
                    'type' => 'text',
                    'label' => 'PLUGIN_FLEX_OBJECTS.ACTION.ACTIONS'
                ]
            ];
        }

        return $list;
    }

    protected function normalizeColumns($blueprint, array $columns): array
    {
        $schema = $blueprint->schema();
        $list = [];

        foreach ($columns as $key => $options) {
            if (is_int($key)) {
                $key = is_string($options) ? $options : null;
                $options = [];
            }

            if (!$key || $options === false || $options === null) {
                continue;
            }

            if ($options === true) {
                $options = [];
            }

            if (!is_array($options)) {
                continue;
            }

            if (!isset($options['field'])) {
                $options['field'] = $schema->get($options['alias'] ?? $key);
            }

            if (!$options['field'] || !empty($options['field']['ignore'])) {
                continue;
            }

            $list[$key] = $options;
        }

        return $list;
    }

    protected function buildVuetableFields(FlexDirectory $directory, array $columns): array
    {
        $blueprint = $directory->getBlueprint();
        $schema = $blueprint->schema();
        $fields = [];
        $grav = Grav::instance();
        $admin = $grav['admin'] ?? null;

        foreach ($columns as $key => $options) {
            if ($key === '_actions_') {
                $fields[] = [
                    'name' => '_actions_',
                    'title' => $this->translateString($options['title'] ?? 'PLUGIN_FLEX_OBJECTS.ACTION.ACTIONS', $admin),
                    'titleClass' => $options['titleClass'] ?? 'right',
                    'dataClass' => $options['dataClass'] ?? ''
                ];
                continue;
            }

            $width = $options['width'] ?? null;
            if (is_numeric($width)) {
                $width = $width . '%';
            }

            $fields[] = [
                'name' => str_replace('.', '_', $key),
                'sortField' => $options['sort']['field'] ?? $key,
                'title' => $this->translateString((string)($options['title'] ?? $options['field']['label'] ?? $schema->get($options['alias'] ?? $key)->label ?? $key), $admin),
                'width' => $width,
                'titleClass' => $options['title_class'] ?? ($options['titleClass'] ?? ''),
                'dataClass' => $options['data_class'] ?? ($options['dataClass'] ?? '')
            ];
        }

        return $fields;
    }

    protected function buildSearchFields(array $columns): array
    {
        $fields = [];

        foreach ($columns as $key => $_options) {
            if ($key === '_actions_') {
                continue;
            }

            $fields[] = str_replace('.', '_', $key);
        }

        return $fields;
    }

    protected function buildSortOrder(array $sort): array
    {
        $order = [];

        foreach ($sort as $field => $direction) {
            $order[] = [
                'field' => $field,
                'direction' => $direction
            ];
        }

        return $order;
    }

    protected function getTypeApiUrl(string $type): ?string
    {
        $grav = Grav::instance();
        $admin = $grav['admin'] ?? null;
        $flex = $grav['flex_objects'] ?? null;

        if (!$admin || !$flex) {
            return null;
        }

        $route = $admin->getAdminRoute($flex->adminRoute($type));

        return $route ? $route->withExtension('json')->toString(true) : null;
    }

    protected function translateString(string $text, $admin = null): string
    {
        if (!$admin) {
            $grav = Grav::instance();
            $admin = $grav['admin'] ?? null;
        }

        if ($admin && method_exists($admin, 'translate')) {
            $translated = $admin->translate($text);
            if (is_string($translated) && $translated !== '') {
                return $translated;
            }
        }

        return $text;
    }

    protected function getActionTitle(FlexObjectInterface $object): string
    {
        $blueprint = $object->getFlexDirectory()->getBlueprint();
        $titleTemplate = $blueprint->get('config/admin/views/edit/title/template') ?? $blueprint->get('config/admin/edit/title/template');

        if ($titleTemplate && $this->twig) {
            try {
                $form = new class($object) {
                    /** @var FlexObjectInterface */
                    private $object;

                    public function __construct(FlexObjectInterface $object)
                    {
                        $this->object = $object;
                    }

                    public function value(string $property)
                    {
                        return $this->object->getFormValue($property) ?? $this->object->getNestedProperty($property);
                    }
                };

                $rendered = $this->twig->createTemplate((string)$titleTemplate)->render([
                    'object' => $object,
                    'form' => $form
                ] + $this->twig_context);
                $rendered = trim((string)preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($rendered, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
                if ($rendered !== '') {
                    return $rendered;
                }
            } catch (Throwable $e) {
                // Fall back to list title field below.
            }
        }

        $titleField = $blueprint->get('config/admin/views/list/title') ?? $blueprint->get('config/admin/list/title');

        if ($titleField) {
            $value = $this->getObjectValue($object, (string)$titleField);
            if (is_array($value)) {
                $value = implode(' ', array_filter(array_map('strval', $value), static function ($item) {
                    return $item !== '';
                }));
            }

            if ($value !== null && $value !== '') {
                return (string)$value;
            }
        }

        return $object->getKey() ?: 'Item';
    }

    /**
     * @param string $sort
     * @param string $fieldSeparator
     * @param string $orderSeparator
     * @return array
     */
    protected function decodeSort(string $sort, string $fieldSeparator = ',', string $orderSeparator = '|'): array
    {
        $strings = explode($fieldSeparator, $sort);
        $list = [];
        foreach ($strings as $string) {
            $item = explode($orderSeparator, $string, 2);
            $key = array_shift($item);
            $order = array_shift($item) === 'desc' ? 'desc' : 'asc';
            $list[$key] = $order;
        }

        return $list;
    }

    /**
     * @param string $fieldSeparator
     * @param string $orderSeparator
     * @return string
     */
    protected function encodeSort(string $fieldSeparator = ',', string $orderSeparator = '|'): string
    {
        $list = [];
        foreach ($this->getSort() as $key => $order) {
            $list[] = $key . $orderSeparator . ($order ?: 'asc');
        }

        return implode($fieldSeparator, $list);
    }
}
