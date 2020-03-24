<?php

declare(strict_types=1);

namespace Baraja\Search\QueryNormalizer;


use Baraja\Search\Helpers;

final class QueryNormalizer implements IQueryNormalizer
{

	/** @var int[]|string[] */
	private static $filterSearchKeys = [
		'in' => 1, 'it' => 1, 'a' => 1, 'the' => 1, 'of' => 1, 'or' => 1, 'I' => 1, 'you' => 1,
		'he' => 1, 'me' => 1, 'us' => 1, 'they' => 1, 'she' => 1, 'to' => 1, 'but' => 1,
		'that=>1', 'this' => 1, 'those' => 1, 'then' => 1,
	];


	/**
	 * Converts $query to canonical form.
	 *
	 * @param string $query
	 * @return string
	 */
	public function normalize(string $query): string
	{
		$query = (string) str_replace("\n", ' ', Helpers::normalize($query));
		$query = (string) preg_replace('/\s+/', ' ', trim($query));
		$query = Helpers::substring($query, 0, 255);
		$query = $this->filterSearchKeys($query);
		$query = (string) preg_replace('/\#(\d+)/', '$1', $query);
		$query = (string) preg_replace('/\s*\.\s*/', '.', $query);
		$query = str_replace(['%', '_'], '', $query);

		if (strpos($query, ' ') !== false) {
			$query = $this->fixDuplicateWords($query);
		}

		return trim($query);
	}


	/**
	 * @param string $query
	 * @param int $ttl
	 * @return string
	 */
	private function filterSearchKeys(string $query, int $ttl = 15): string
	{
		$return = [];

		foreach (explode(' ', $query) as $word) {
			if (($ttl--) <= 0) {
				break;
			}
			if (isset(self::$filterSearchKeys[$word]) === true) {
				continue;
			}
			$return[] = $word;
		}

		return implode(' ', $return);
	}


	/**
	 * Finds duplicate words and keep only the first occurrence.
	 *
	 * @param string $query
	 * @return string
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