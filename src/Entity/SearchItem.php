<?php

declare(strict_types=1);

namespace Baraja\Search\Entity;


use Baraja\Search\Helpers;

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
		?int $score = null,
	) {
		$title = trim((string) $title);
		$this->title = $title === '' ? null : $title;
		$this->snippet = trim($snippet);

		if ($score !== null) {
			$this->setScore($score);
		}
	}


	public function getId(): string|int
	{
		if (method_exists($this->entity, 'getId')) {
			try {
				/** @var mixed $id */
				$id = $this->entity->getId();
			} catch (\Throwable $e) {
				throw new \LogicException(
					sprintf('Search entity must be persisted: %s', $e->getMessage()),
					(int) $e->getCode(),
					$e,
				);
			}
			if (is_string($id) || is_int($id)) {
				return $id;
			}
			throw new \LogicException(sprintf(
				'Entity identifier must be type of "string" or "int", but type "%s" given.',
				get_debug_type($id),
			));
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
	 * @param array<string, mixed> $args
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
			? $this->normalize($this->snippet)
			: $this->snippet;
	}


	public function getTitleHighlighted(): ?string
	{
		$title = $this->getTitle();
		if ($title === null) {
			return null;
		}

		return Helpers::highlightFoundWords($title, $this->query);
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
	 * @return array<string, string>
	 */
	public function entityToArray(): array
	{
		try {
			$properties = (new \ReflectionClass($this))->getProperties();
		} catch (\ReflectionException) {
			return [];
		}

		$return = [];
		foreach ($properties as $property) {
			/** @phpstan-ignore-next-line */
			$return[$property->name] = Helpers::normalize((string) $this->{$property->name});
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
		$haystack = str_replace("\n", ' ', $haystack);
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
