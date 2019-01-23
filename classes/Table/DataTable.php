<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Table;

use Grav\Framework\Collection\CollectionInterface;

/**
 * Class DataTable
 * @package Grav\Plugin\Gitea
 *
 * https://github.com/ratiw/vuetable-2/wiki/Data-Format-(JSON)
 * https://github.com/ratiw/vuetable-2/wiki/Sorting
 */
class DataTable implements \JsonSerializable
{
    /** @var string */
    private $url;
    /** @var int */
    private $limit;
    /** @var int */
    private $page;
    /** @var array */
    private $sort;
    /** @var CollectionInterface */
    private $collection;

    public function __construct(array $params)
    {
        $this->setUrl($params['url'] ?? '');
        $this->setLimit((int)($params['limit'] ?? 10));
        $this->setPage((int)($params['page'] ?? 1));
        $this->setSort($params['sort'] ?? ['id' => 'asc']);
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function setLimit(int $limit): void
    {
        $this->limit = max(1, $limit);
    }

    public function setPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function setSort($sort)
    {
        if (is_string($sort)) {
            $sort = $this->decodeSort($sort);
        } elseif (!\is_array($sort)) {
            $sort = [];
        }

        $this->sort = $sort;
    }

    public function setCollection(CollectionInterface $collection): void
    {
        $this->collection = $collection;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getLastPage(): int
    {
        return 1 + (int)floor(max(0, $this->getTotal()-1) / $this->getLimit());
    }

    public function getTotal(): int
    {
        $collection = $this->getCollection();

        return $collection ? $collection->count() : 0;
    }

    public function getSort(): array
    {
        return $this->sort;
    }

    public function getCollection(): ?CollectionInterface
    {
        return $this->collection;
    }

    public function getUrl(int $page): ?string
    {
        if ($page < 1 || $page > $this->getLastPage()) {
            return null;
        }

        return "{$this->url}.json?page={$page}&per_page={$this->getLimit()}&sort={$this->encodeSort()}";
    }

    public function getData()
    {
        $limit = $this->getLimit();
        $page = $this->getPage();
        $to = $page * $limit;
        $from = $to - $limit + 1;

        $collection = $this->getCollection();

        $array = $collection ? $collection->slice($from, $limit) : [];

        $list = [];
        foreach ($array as $object) {
            $list[] = [
                'id' => $object->getKey()
            ];
        }

        return $list;
    }

    public function jsonSerialize()
    {
        $total = $this->getTotal();
        $limit = $this->getLimit();
        $page = $this->getPage();
        $to = $page * $limit;
        $from = $to - $limit + 1;

        return [
            'links' => [
                'pagination' => [
                    'total' => $total,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'last_page' => $this->getLastPage(),
                    'next_page_url' => $this->getUrl($page+1),
                    'prev_page_url' => $this->getUrl($page-1),
                    'from' => min($from, $total) ?: null,
                    'to' => min($to, $total) ?: null,
                ]
            ],
            'data' => $this->getData()
        ];
    }

    protected function decodeSort(string $sort, $fieldSeparator = ',', $orderSeparator = '|')
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

    protected function encodeSort($fieldSeparator = ',', $orderSeparator = '|')
    {
        $list = [];
        foreach ($this->getSort() as $key => $order) {
            $list[] = $key . $orderSeparator . ($order ?: 'asc');
        }

        return implode($fieldSeparator, $list);
    }
}
