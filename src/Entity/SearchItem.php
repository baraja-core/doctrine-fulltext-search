<?php

declare(strict_types=1);

namespace Baraja\Search\Entity;


use Baraja\Search\Helpers;
use Nette\Utils\Strings;

class SearchItem
{
	private int $score = 0;

	private string $query;

	private ?string $title;

	private string $snippet;

	/** @var object */
	private $entity;


	/**
	 * @param object $entity
	 */
	public function __construct($entity, string $query, ?string $title, string $snippet, ?int $score = null)
	{
		$this->entity = $entity;
		$this->query = $query;
		$this->title = trim((string) $title) ?: null;
		$this->snippet = trim($snippet);

		if ($score !== null) {
			$this->setScore($score);
		}
	}


	/**
	 * @return object
	 */
	public function getEntity()
	{
		return $this->entity;
	}


	/**
	 * This is an abbreviation for retrieving a value from a data entity.
	 * If there is no method for reading a specific value directly in the search result,
	 * we will try to call this method in the source method.
	 *
	 * @param mixed[] $args
	 * @return mixed
	 */
	public function __call(string $method, array $args)
	{
		/** @phpstan-ignore-next-line */
		return call_user_func([$this->entity, $method], $args);
	}


	public function getTitle(): ?string
	{
		if ($this->title === null) {
			return null;
		}

		return (string) preg_replace('/`(\S+)`/', '$1', $this->title);
	}


	public function getSnippet(): string
	{
		return $this->snippet;
	}


	public function getTitleHighlighted(): ?string
	{
		if ($this->getTitle() === null) {
			return null;
		}

		return Helpers::highlightFoundWords($this->getTitle(), $this->query);
	}


	public function getSnippetHighlighted(int $length = 160, bool $normalize = false): string
	{
		if ($this->getSnippet() === '') {
			return '';
		}

		return Helpers::highlightFoundWords(
			htmlspecialchars(
				htmlspecialchars_decode(htmlspecialchars(
					Helpers::smartTruncate(
						$this->query,
						($normalize ? $this->normalize($this->snippet ?: '') : $this->snippet),
						$length
					), ENT_NOQUOTES | ENT_IGNORE, 'UTF-8'), ENT_NOQUOTES)
			),
			$this->query
		);
	}


	/**
	 * @return string[]
	 */
	public function entityToArray(): array
	{
		try {
			$properties = (new \ReflectionClass($this))->getProperties();
		} catch (\ReflectionException $e) {
			return [];
		}

		$return = [];
		foreach ($properties as $property) {
			$return[$property->name] = Strings::normalize((string) $this->{$property->name});
		}

		return $return;
	}


	public function getScore(): int
	{
		return $this->score;
	}


	public function setScore(int $score, int $min = 0, int $max = 512): void
	{
		if ($score > $max) {
			$score = $max;
		}
		if ($score < $min) {
			$score = $min;
		}

		$this->score = $score;
	}


	private function normalize(string $haystack): string
	{
		$haystack = strip_tags($haystack);
		$haystack = (string) str_replace("\n", ' ', $haystack);
		$haystack = (string) preg_replace('/(--+|==+|\*\*+)/', '', $haystack);
		$haystack = (string) preg_replace('/\s+\|\s+/', ' ', $haystack);
		$haystack = (string) preg_replace('/```(\w+\n)?/', '', $haystack);
		$haystack = (string) preg_replace('/`(\S+)`/', '$1', $haystack);
		$haystack = (string) preg_replace('/\s*(-\s+){2,}\s*/', ' - ', $haystack);
		$haystack = (string) preg_replace('/\s+/', ' ', $haystack);

		return $haystack;
	}
}
