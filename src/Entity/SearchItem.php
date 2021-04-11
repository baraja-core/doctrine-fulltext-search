<?php

declare(strict_types=1);

namespace Baraja\Search\Entity;


use Baraja\Search\Helpers;
use Nette\Utils\Strings;

class SearchItem
{
	private int $score = 0;

	private ?string $title;

	private string $snippet;


	public function __construct(
		private object $entity,
		private string $query,
		?string $title,
		string $snippet,
		?int $score = null
	) {
		$this->title = trim((string) $title) ?: null;
		$this->snippet = trim($snippet);

		if ($score !== null) {
			$this->setScore($score);
		}
	}


	public function getId(): string|int
	{
		if (method_exists($this->entity, 'getId')) {
			$id = $this->entity->getId();
			if (is_string($id) || is_int($id)) {
				return $id;
			}
			throw new \LogicException(
				'Entity identifier must be type of "string" or "int", '
				. 'but type "' . get_debug_type($id) . '" given.',
			);
		}
		throw new \LogicException('Entity does not contain identifier.');
	}


	public function getEntity(): object
	{
		return $this->entity;
	}


	/**
	 * This is an abbreviation for retrieving a value from a data entity.
	 * If there is no method for reading a specific value directly in the search result,
	 * we will try to call this method in the source method.
	 *
	 * @param mixed[] $args
	 */
	public function __call(string $method, array $args): mixed
	{
		/** @phpstan-ignore-next-line */
		return call_user_func([$this->entity, $method], $args);
	}


	public function getTitle(): ?string
	{
		return $this->title !== null
			? $this->normalize($this->title)
			: null;
	}


	public function getRawTitle(): ?string
	{
		return $this->title;
	}


	public function getSnippet(bool $normalize = true): string
	{
		return $normalize
			? $this->normalize($this->snippet ?: '')
			: $this->snippet;
	}


	public function getTitleHighlighted(): ?string
	{
		if ($this->getTitle() === null) {
			return null;
		}

		return Helpers::highlightFoundWords($this->getTitle(), $this->query);
	}


	public function getSnippetHighlighted(int $length = 160, bool $normalize = true): string
	{
		if ($this->getSnippet() === '') {
			return '';
		}

		return Helpers::highlightFoundWords(
			htmlspecialchars(
				htmlspecialchars_decode(htmlspecialchars(
					Helpers::smartTruncate(
						$this->query,
						$this->getSnippet($normalize),
						$length,
					),
					ENT_NOQUOTES | ENT_IGNORE,
				), ENT_NOQUOTES),
			),
			$this->query,
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
		$haystack = html_entity_decode($haystack);
		$haystack = strip_tags($haystack);
		$haystack = (string) str_replace("\n", ' ', $haystack);
		$haystack = (string) preg_replace('/(--+|==+|\*\*+)/', '', $haystack);
		$haystack = (string) preg_replace('/\s+\|\s+/', ' ', $haystack);
		$haystack = (string) preg_replace('/```(\w+\n)?/', '', $haystack);
		$haystack = (string) preg_replace('/`(\S+)`/', '$1', $haystack);
		$haystack = (string) preg_replace('/[*#\-:.`]{2,}/', '', $haystack);
		$haystack = (string) preg_replace('/(["\'])["\']+/', '$1', $haystack);
		$haystack = (string) preg_replace('/\s*(-\s+){2,}\s*/', ' - ', $haystack);

		return (string) preg_replace('/\s+/', ' ', $haystack);
	}
}
