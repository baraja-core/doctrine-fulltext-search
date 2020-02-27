<?php

declare(strict_types=1);

namespace Baraja\Search;


use Baraja\Search\Entity\SearchItem;
use Baraja\Search\Entity\SearchResult;

final class SelectorBuilder
{

	/**
	 * @var string
	 */
	private $query;

	/**
	 * @var bool
	 */
	private $searchExactly;

	/**
	 * Internal map in format:
	 * Entity => [
	 *    'content' => '!',
	 * ]
	 *
	 * @var string[][]
	 */
	private $map = [];

	/**
	 * @var string|null
	 */
	private $contextEntity;

	/**
	 * @var bool
	 */
	private $closed = false;

	/**
	 * @var Search
	 */
	private $search;

	/**
	 * @var SearchResult
	 */
	private $searchResult;

	/**
	 * @param string $query
	 * @param bool $searchExactly
	 * @param Search $search
	 */
	public function __construct(string $query, bool $searchExactly, Search $search)
	{
		$this->query = $query;
		$this->searchExactly = $searchExactly;
		$this->search = $search;
	}

	/**
	 * @return SearchResult
	 * @throws SearchException
	 */
	public function search(): SearchResult
	{
		if ($this->closed === true) {
			SearchException::selectorBuilderIsClosed();
		}

		if ($this->closed === false) {
			$this->searchResult = $this->search->search($this->query, $this->getMap(), $this->searchExactly);
			$this->closed = true;
		}

		return $this->searchResult;
	}

	/**
	 * Process search engine and get items.
	 *
	 * @param int $limit
	 * @param int $offset
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
	 * @internal
	 * @return string[][]
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
	 * @param string $entity
	 * @param string[] $columns
	 * @return SelectorBuilder
	 * @throws SearchException
	 */
	public function addEntity(string $entity, array $columns = []): self
	{
		if (\class_exists($entity) === false) {
			SearchException::entityIsNotValidClass($entity);
		}

		$returnColumns = [];
		foreach ($columns as $column) {
			$returnColumns[$column] = '';
		}

		$this->map[$entity] = $returnColumns;
		$this->contextEntity = $entity;

		return $this;
	}

	/**
	 * @param string $column
	 * @param string|null $entity
	 * @param string|null $format
	 * @return SelectorBuilder
	 * @throws SearchException
	 */
	public function addColumn(string $column, ?string $entity = null, ?string $format = null): self
	{
		if ($this->contextEntity === null && $entity === null) {
			SearchException::contextEntityDoesNotExist();
		} elseif ($this->contextEntity === null) {
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

	/**
	 * @param string $column
	 * @param string|null $entity
	 * @return SelectorBuilder
	 * @throws SearchException
	 */
	public function addColumnTitle(string $column, ?string $entity = null): self
	{
		$this->addColumn($column, $entity);
		$this->map[$entity ?? $this->contextEntity][$column] = ':';

		return $this;
	}

	/**
	 * @param string $column
	 * @param string|null $entity
	 * @return SelectorBuilder
	 * @throws SearchException
	 */
	public function addColumnSearchOnly(string $column, ?string $entity = null): self
	{
		$this->addColumn($column, $entity);
		$this->map[$entity ?? $this->contextEntity][$column] = '!';

		return $this;
	}

	/**
	 * @param string $column
	 * @param string|null $entity
	 * @return SelectorBuilder
	 * @throws SearchException
	 */
	public function addColumnSelectOnly(string $column, ?string $entity = null): self
	{
		$this->addColumn($column, $entity);
		$this->map[$entity ?? $this->contextEntity][$column] = '_';

		return $this;
	}

	/**
	 * @internal
	 * @return bool
	 */
	public function isClosed(): bool
	{
		return $this->closed;
	}

}