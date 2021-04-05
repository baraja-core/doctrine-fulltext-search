<?php

declare(strict_types=1);

namespace Baraja\Search\Entity;


use Tracy\Debugger;

class SearchResult implements \Iterator
{
	private ?string $didYouMean = null;

	/** @var SearchItem[] */
	private array $items = [];

	private bool $ordered = false;

	/** Time in milliseconds. */
	private float $searchTime = 0;


	public function __construct(
		private string $query
	) {
	}


	public function getQuery(): string
	{
		return $this->query;
	}


	/**
	 * @return SearchItem[]
	 */
	public function getItems(int $limit = 10, int $offset = 0): array
	{
		return $this->filterItemsByPaginator($this->fetchOrderedItems(), $limit, $offset);
	}


	/**
	 * @return SearchItem[]
	 */
	public function getItemsOfType(string $type, int $limit = 10, int $offset = 0): array
	{
		if (\class_exists($type) === false && \interface_exists($type) === false) {
			throw new \InvalidArgumentException('Class or interface "' . $type . '" does not exist.');
		}

		$candidateItems = [];
		foreach ($this->fetchOrderedItems() as $candidateItem) {
			if ($candidateItem->getEntity() instanceof $type) {
				$candidateItems[] = $candidateItem;
			}
		}

		return $this->filterItemsByPaginator($candidateItems, $limit, $offset);
	}


	/**
	 * @return string[]|int[]
	 */
	public function getIds(int $limit = 10, int $offset = 0): array
	{
		$return = [];
		foreach ($this->getItems($limit, $offset) as $item) {
			$entity = $item->getEntity();
			if (method_exists($entity, 'getId')) {
				$return[] = $entity->getId();
			} else {
				throw new \LogicException('Entity "' . get_debug_type($entity) . '" do not implement method getId().');
			}
		}

		return $return;
	}


	/**
	 * Base render of search results.
	 */
	public function __toString(): string
	{
		$results = '';
		$isDebugMode = class_exists(Debugger::class) ? Debugger::isEnabled() : false;

		foreach ($this->getItems() as $item) {
			$results .= ($results !== '' ? '<hr>' : '')
				. '<div class="search__result">'
				. '<h2>'
				. ($isDebugMode ? '<span style="color:#aaa;font-size:8pt">' . $item->getScore() . '</span>&nbsp;' : '')
				. $item->getTitleHighlighted() . '</h2>'
				. "\n" . $item->getSnippetHighlighted()
				. '</div>';
		}

		// header
		$countResults = $this->getCountResults();
		$searchTime = (float) number_format($this->getSearchTime() / 1_000, 2);
		$return = '<div class="search__info">'
			. 'About ' . number_format($countResults)
			. ' ' . ($countResults === 1 ? 'result' : 'results')
			. ' (' . number_format($searchTime, 2) . '&nbsp;' .
			(abs($searchTime - 1) < 0.001 ? 'second' : 'seconds')
			. ($searchTime < 0.5 ? ' &#8776;&nbsp;' . number_format($searchTime * 1_000) . '&nbsp;milliseconds' : '')
			. ')'
			. '</div>';

		// did you mean?
		if ($this->getDidYouMean() !== null) {
			$return .= '<div class="search__did_you_mean">'
				. 'Did you mean <strong>' . $this->getDidYouMean() . '</strong>?'
				. '</div>';
		}

		// results
		$return .= $results;

		// styles
		$return .= '<style>'
			. '.highlight{background:rgba(68, 134, 255, 0.35)}'
			. '.search__info{padding:.5em 0;margin-bottom:.5em;border-bottom:1px solid #eee}'
			. '.search__did_you_mean{color: #ff421e}'
			. '</style>';

		return '<div class="search__container">' . $return . '</div>';
	}


	public function getCountResults(): int
	{
		return \count($this->items);
	}


	public function getDidYouMean(): ?string
	{
		return $this->didYouMean;
	}


	public function setDidYouMean(?string $didYouMean): void
	{
		$this->didYouMean = $didYouMean;
	}


	public function getSearchTime(): float
	{
		return $this->searchTime;
	}


	public function addSearchTime(float $searchTime): void
	{
		$this->searchTime += $searchTime < 0 ? 0 : $searchTime;
	}


	public function addItem(SearchItem $item): void
	{
		$this->items[] = $item;
		$this->ordered = false;
	}


	public function rewind(): void
	{
		reset($this->items);
	}


	public function current(): SearchItem
	{
		if (($current = current($this->items)) === false) {
			throw new \LogicException('Current item is not "' . SearchItem::class . '", because does not exist.');
		}

		return $current;
	}


	public function key(): int
	{
		return (int) key($this->items);
	}


	public function next(): SearchItem|false
	{
		return next($this->items);
	}


	public function valid(): bool
	{
		return ($key = key($this->items)) !== null && $key !== false;
	}


	/**
	 * @return SearchItem[]
	 */
	private function fetchOrderedItems(): array
	{
		if ($this->ordered === false) {
			usort($this->items, static fn(SearchItem $a, SearchItem $b): int => $a->getScore() < $b->getScore() ? 1 : -1);
			$this->ordered = true;
		}

		return $this->items;
	}


	/**
	 * @param SearchItem[] $items
	 * @return SearchItem[]
	 */
	private function filterItemsByPaginator(array $items, int $limit, int $offset): array
	{
		if ($limit < 0) {
			throw new \InvalidArgumentException('Limit can not be smaller than zero, but "' . $limit . '" given.');
		}
		if ($offset < 0) {
			throw new \InvalidArgumentException('Offset can not be smaller than zero, but "' . $offset . '" given.');
		}

		$return = [];
		for ($i = $offset; $i < $offset + $limit; $i++) {
			if (isset($items[$i]) === true) {
				$return[] = $items[$i];
			} else {
				break;
			}
		}

		return $return;
	}
}
