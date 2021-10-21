<?php

declare(strict_types=1);

namespace Baraja\Search\QueryNormalizer;


final class QueryNormalizer implements IQueryNormalizer
{
	/** @var array<string, int> */
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
		$query = str_replace("\n", ' ', $this->basicStringNormalize($query));
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


	/**
	 * Removes control characters, normalizes line breaks to `\n`, removes leading and trailing blank lines,
	 * trims end spaces on lines, normalizes UTF-8 to the normal form of NFC.
	 */
	private function basicStringNormalize(string $s): string
	{
		// convert to compressed normal form (NFC)
		if (class_exists('Normalizer', false)) {
			$n = \Normalizer::normalize($s, \Normalizer::FORM_C);
			if (is_string($n)) {
				$s = $n;
			}
		}

		// standardize line endings to unix-like.
		$s = str_replace(["\r\n", "\r"], "\n", $s);

		// remove control characters; leave \t + \n
		$s = (string) preg_replace('#[\x00-\x08\x0B-\x1F\x7F-\x9F]+#u', '', $s);

		// right trim
		$s = (string) preg_replace('#[\t ]+$#m', '', $s);

		// leading and trailing blank lines
		$s = trim($s, "\n");

		return $s;
	}
}
