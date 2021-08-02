<?php

declare(strict_types=1);

namespace Baraja\Search\QueryNormalizer;


use Nette\Utils\Strings;

final class QueryNormalizer implements IQueryNormalizer
{

	/** @var array<string, int> (string => int) */
	private static array $filterSearchKeys = [
		'in' => 1, 'it' => 1, 'a' => 1, 'the' => 1, 'of' => 1, 'or' => 1, 'I' => 1, 'you' => 1,
		'he' => 1, 'me' => 1, 'us' => 1, 'they' => 1, 'she' => 1, 'to' => 1, 'but' => 1,
		'that' => 1, 'this' => 1, 'those' => 1, 'then' => 1,
	];


	/**
	 * Converts $query to canonical form.
	 */
	public function normalize(string $query): string
	{
		$query = str_replace("\n", ' ', Strings::normalize($query));
		$query = (string) preg_replace('/\s+/', ' ', trim($query));
		$query = mb_substr($query, 0, 255, 'UTF-8');
		$query = $this->filterSearchKeys($query);
		$query = (string) preg_replace('/#(\d+)/', '$1', $query);
		$query = (string) preg_replace('/\s*\.\s*/', '.', $query);
		$query = str_replace(['%', '_', '{', '}'], ['', '', '(', ')'], $query);

		if (str_contains($query, ' ') === true) {
			$query = $this->fixDuplicateWords($query);
		}

		return trim($query);
	}


	private function filterSearchKeys(string $query, int $ttl = 15): string
	{
		if (str_contains($query, ' ') === false) {
			return $query;
		}
		$return = [];
		foreach (explode(' ', $query) as $word) {
			if (($ttl--) <= 0) {
				break;
			}
			if (isset(self::$filterSearchKeys[$word]) === false) {
				$return[] = $word;
			}
		}
		if ($return === []) {
			return $query;
		}

		return implode(' ', $return);
	}


	/**
	 * Finds duplicate words and keep only the first occurrence.
	 */
	private function fixDuplicateWords(string $query): string
	{
		$return = '';
		$usedWords = [];
		foreach (explode(' ', $query) as $word) {
			if (isset($usedWords[$word]) === false) {
				$return .= ($return !== '' ? ' ' : '') . $word;
				$usedWords[$word] = true;
			}
		}

		return $return;
	}
}
