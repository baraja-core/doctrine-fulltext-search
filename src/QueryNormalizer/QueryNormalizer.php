<?php

declare(strict_types=1);

namespace Baraja\Search\QueryNormalizer;


use Baraja\Search\Helpers;

class QueryNormalizer implements IQueryNormalizer
{

	/**
	 * @param string $query
	 * @return string
	 */
	public function normalize(string $query): string
	{
		$query = str_replace("\n", ' ', Helpers::normalize($query));
		$query = (string) preg_replace('/\s+/', ' ', trim($query));
		$query = Helpers::substring($query, 0, 255);
		$query = $this->filterSearchKeys($query);
		$query = (string) preg_replace('/\#(\d+)/', '$1', $query);
		$query = (string) preg_replace('/\s*\.\s*/', '.', $query);
		$query = str_replace(['%', '_'], '', $query);

		if (strpos($query, ' ') !== false) {
			$query = $this->filterDuplicateWords($query);
		}

		return trim($query);
	}

	/**
	 * @param string $query
	 * @return string
	 */
	private function filterSearchKeys(string $query): string
	{
		$return = [];
		$list = [
			'in', 'it', 'a', 'the', 'of', 'or', 'I', 'you',
			'he', 'me', 'us', 'they', 'she', 'to', 'but',
			'that', 'this', 'those', 'then',
		];

		$c = 0;
		foreach (explode(' ', $query) as $word) {
			if (\in_array($word, $list, true)) {
				continue;
			}
			$return[] = $word;
			if ($c >= 15) {
				break;
			}
			$c++;
		}

		return implode(' ', $return);
	}

	/**
	 * @param string $query
	 * @return string
	 */
	private function filterDuplicateWords(string $query): string
	{
		$newQuery = '';
		$usedWords = [];
		foreach (explode(' ', $query) as $word) {
			if (!isset($usedWords[$word])) {
				$newQuery .= ($newQuery !== '' ? ' ' : '') . $word;
				$usedWords[$word] = true;
			}
		}

		return $newQuery;
	}

}