<?php

declare(strict_types=1);

namespace Baraja\Search;


use Baraja\Search\Entity\SearchItem;
use Baraja\Search\Entity\SearchResult;

final class SelectorBuilder
{
	private ?string $baseEntity = null;

	private bool $closed = false;

	/**
	 * Internal map in format:
	 * Entity => [
	 *    'content' => '!',
	 * ]
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $map = [];

	/** @var array<int, string> */
	private array $userConditions = [];


	public function __construct(
		private string $query,
		private bool $searchExactly,
		private Search $search,
	) {
	}


	/**
	 * This method builds a query map and performs a search.
	 * The results are returned as a SearchResult entity.
	 * Each query can be used for a search only once and is then marked as closed.
	 * If you want to search the same query repeatedly, save the structure by the getMap() method.
	 */
	public function search(bool $useAnalytics = true): SearchResult
	{
		$this->checkIfClosed();
		$this->closed = true;

		return $this->search->search(
			query: $this->query,
			entityMap: $this->getMap(),
			searchExactly: $this->searchExactly,
			userConditions: $this->userConditions,
			useAnalytics: $useAnalytics,
		);
	}


	/**
	 * Process search engine and get items.
	 *
	 * @return array<int, SearchItem>
	 */
	public function getItems(int $limit = 10, int $offset = 0): array
	{
		return $this->search()->getItems($limit, $offset);
	}


	/**
	 * Compute current entity search map by selector preferences.
	 *
	 * @return array<string, array<int, string>>
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
	 * @param array<int, string> $columns
	 */
	public function addEntity(string $entity, array $columns = []): self
	{
		$this->checkIfClosed();
		if ($entity === '' || \class_exists($entity) === false) {
			throw new \InvalidArgumentException(sprintf(
				'Haystack "%s" is not valid class, %s given.',
				$entity,
				get_debug_type($entity),
			));
		}

		$returnColumns = [];
		foreach ($columns as $column) {
			$returnColumns[$column] = '';
		}

		$this->map[$entity] = $returnColumns;
		$this->baseEntity = $entity;

		return $this;
	}


	public function addColumn(string $column, ?string $entity = null, ?string $format = null): self
	{
		$this->checkIfClosed();
		if ($this->baseEntity === null && $entity === null) {
			throw new \InvalidArgumentException('Context entity does not exist. Did you call addEntity() first?');
		}
		if ($this->baseEntity === null) {
			$this->baseEntity = $entity;
		}
		$entity ??= $this->baseEntity;
		assert(is_string($entity));
		if (isset($this->map[$entity]) === false) {
			$this->addEntity($entity);
		}
		if (isset($this->map[$entity]) === false) {
			$this->map[$entity] = [];
		}
		if (isset($this->map[$entity][$column]) === false) {
			$this->map[$entity][$column] = $format ?? '';
		}

		return $this;
	}


	public function addColumnTitle(string $column, ?string $entity = null): self
	{
		$this->checkIfClosed();
		$this->addColumn($column, $entity);
		$this->map[$entity ?? $this->baseEntity][$column] = ':';

		return $this;
	}


	public function addColumnSearchOnly(string $column, ?string $entity = null): self
	{
		$this->checkIfClosed();
		$this->addColumn($column, $entity);
		$this->map[$entity ?? $this->baseEntity][$column] = '!';

		return $this;
	}


	public function addColumnSelectOnly(string $column, ?string $entity = null): self
	{
		$this->checkIfClosed();
		$this->addColumn($column, $entity);
		$this->map[$entity ?? $this->baseEntity][$column] = '_';

		return $this;
	}


	/**
	 * You can apply simple conditions to a base entity when searching.
	 * The condition must be entered in the format "column operator value",
	 * for example "active = TRUE" or "price > 25".
	 */
	public function addWhere(string $condition): self
	{
		$this->checkIfClosed();

		$parts = explode(' ', trim((string) preg_replace('/\s+/', ' ', $condition)), 3);
		if (isset($parts[0], $parts[1], $parts[2]) === false) {
			throw new \InvalidArgumentException(
				'Invalid condition format. Please use format "column operator value", '
				. 'for example "active = TRUE" or "price > 25". '
				. sprintf('But haystack "%s" given.', $condition),
			);
		}
		[$column, $operator, $value] = $parts;
		if (str_contains($column, '.') === false) {
			$column = 'e.' . $column;
		}

		$this->userConditions[] = $column . ' ' . $operator . ' ' . $value;

		return $this;
	}


	private function checkIfClosed(): void
	{
		if ($this->closed === false) {
			return;
		}
		throw new \RuntimeException(
			'SelectorBuilder is closed. You can not modify select query after search() method has been called.' . "\n"
			. 'How to solve this issue: First define the entire structure for the search, '
			. 'and then call the search() method to return a list of results. '
			. 'You can use each query to search only once, then it is marked as closed. '
			. 'If you need to use a query repeatedly in multiple places, save its structure by the getMap() method.',
		);
	}
}
