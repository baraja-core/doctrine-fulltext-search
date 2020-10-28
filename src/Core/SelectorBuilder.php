<?php

declare(strict_types=1);

namespace Baraja\Search;


use Baraja\Search\Entity\SearchItem;
use Baraja\Search\Entity\SearchResult;

final class SelectorBuilder
{
	private string $query;

	private bool $searchExactly;

	private ?string $contextEntity;

	private bool $closed = false;

	private Search $search;

	private SearchResult $searchResult;

	/**
	 * Internal map in format:
	 * Entity => [
	 *    'content' => '!',
	 * ]
	 *
	 * @var string[][]
	 */
	private array $map = [];

	/** @var string[] */
	private array $userWheres = [];


	public function __construct(string $query, bool $searchExactly, Search $search)
	{
		$this->query = $query;
		$this->searchExactly = $searchExactly;
		$this->search = $search;
	}


	/**
	 * @throws SearchException
	 */
	public function search(): SearchResult
	{
		if ($this->closed === true) {
			throw new \RuntimeException('Selector builder is closed. You can not modify select query after search.');
		}
		if ($this->closed === false) {
			$this->searchResult = $this->search->search($this->query, $this->getMap(), $this->searchExactly, $this->userWheres);
			$this->closed = true;
		}

		return $this->searchResult;
	}


	/**
	 * Process search engine and get items.
	 *
	 * @return SearchItem[]
	 * @throws SearchException
	 */
	public function getItems(int $limit = 10, int $offset = 0): array
	{
		return $this->search()->getItems($limit, $offset);
	}


	/**
	 * Compute current entity search map by selector preferences.
	 *
	 * @return string[][]
	 * @internal
	 */
	public function getMap(): array
	{
		$return = [];
		foreach ($this->map as $entity => $columns) {
			$columnsReturn = [];
			foreach ($columns as $column => $format) {
				$columnsReturn[] = $format . $column;
			}
			$return[$entity] = $columnsReturn;
		}

		return $return;
	}


	/**
	 * @param string[] $columns
	 * @return SelectorBuilder
	 */
	public function addEntity(string $entity, array $columns = []): self
	{
		if (\class_exists($entity) === false) {
			throw new \InvalidArgumentException('Haystack "' . $entity . '" is not valid class, ' . \gettype($entity) . ' given.');
		}

		$returnColumns = [];
		foreach ($columns as $column) {
			$returnColumns[$column] = '';
		}

		$this->map[$entity] = $returnColumns;
		$this->contextEntity = $entity;

		return $this;
	}


	public function addColumn(string $column, ?string $entity = null, ?string $format = null): self
	{
		if ($this->contextEntity === null && $entity === null) {
			throw new \InvalidArgumentException('Context entity does not exist. Did you call addEntity() first?');
		}
		if ($this->contextEntity === null) {
			$this->contextEntity = $entity;
		}
		if (isset($this->map[$entity = $entity ?? $this->contextEntity]) === false) {
			$this->addEntity($entity);
		}
		if (isset($this->map[$entity]) === false) {
			$this->map[$entity] = [];
		}
		if (isset($this->map[$entity][$column]) === false) {
			$this->map[$entity][$column] = $format;
		}

		return $this;
	}


	public function addColumnTitle(string $column, ?string $entity = null): self
	{
		$this->addColumn($column, $entity);
		$this->map[$entity ?? $this->contextEntity][$column] = ':';

		return $this;
	}


	public function addColumnSearchOnly(string $column, ?string $entity = null): self
	{
		$this->addColumn($column, $entity);
		$this->map[$entity ?? $this->contextEntity][$column] = '!';

		return $this;
	}


	public function addColumnSelectOnly(string $column, ?string $entity = null): self
	{
		$this->addColumn($column, $entity);
		$this->map[$entity ?? $this->contextEntity][$column] = '_';

		return $this;
	}


	public function addWhere(string $statement): self
	{
		$this->userWheres[] = $statement;

		return $this;
	}


	/**
	 * @internal
	 */
	public function isClosed(): bool
	{
		return $this->closed;
	}
}
