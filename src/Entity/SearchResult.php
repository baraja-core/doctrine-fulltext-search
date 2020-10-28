<?php

declare(strict_types=1);

namespace Baraja\Search\Entity;


use Tracy\Debugger;

class SearchResult implements \Iterator
{
	private string $query;

	private ?string $didYouMean = null;

	/** @var SearchItem[] */
	private array $items = [];

	private bool $ordered = false;

	/** Time in milliseconds. */
	private float $searchTime = 0;


	public function __construct(string $query)
	{
		$this->query = $query;
	}


	public function getQuery(): string
	{
		return $this->query;
	}


	/**
	 * @param int $limit
	 * @param int $offset
	 * @return SearchItem[]
	 */
	public function getItems(int $limit = 10, int $offset = 0): array
	{
		if ($this->ordered === false) {
			usort($this->items, fn (SearchItem $a, SearchItem $b): int => $a->getScore() < $b->getScore() ? 1 : -1);
			$this->ordered = true;
		}

		$return = [];

		for ($i = $offset; $i <= $offset + $limit; $i++) {
			if (isset($this->items[$i])) {
				$return[] = $this->items[$i];
			} else {
				break;
			}
		}

		return $return;
	}


	/**
	 * @param string $type
	 * @param int $limit
	 * @param int $offset
	 * @return SearchItem[]
	 */
	public function getItemsOfType(string $type, int $limit = 10, int $offset = 0): array
	{
		$candidateItems = [];
		foreach ($this->items as $candidateItem) {
			if ($candidateItem->getEntity() instanceof $type) {
				$candidateItems[] = $candidateItem;
			}
		}

		usort($candidateItems, fn (SearchItem $a, SearchItem $b): int => $a->getScore() < $b->getScore() ? 1 : -1);

		$return = [];
		for ($i = $offset; $i <= $offset + $limit; $i++) {
			if (isset($candidateItems[$i])) {
				$return[] = $candidateItems[$i];
			} else {
				break;
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
		$searchTime = (float) number_format($this->getSearchTime() / 1000, 2);
		$return = '<div class="search__info">'
			. 'About ' . number_format($countResults)
			. ' ' . ($countResults === 1 ? 'result' : 'results')
			. ' (' . number_format($searchTime, 2) . '&nbsp;' .
			(abs($searchTime - 1) < 0.001 ? 'second' : 'seconds')
			. ($searchTime < 0.5 ? ' &#8776;&nbsp;' . number_format($searchTime * 1000) . '&nbsp;milliseconds' : '')
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
		$this->searchTime += $searchTime;
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
		return current($this->items);
	}


	public function key(): int
	{
		return key($this->items);
	}


	public function next(): SearchItem
	{
		return next($this->items);
	}


	public function valid(): bool
	{
		return ($key = key($this->items)) !== null && $key !== false;
	}
}
