<?php

declare(strict_types=1);

namespace Baraja\Search;


use voku\helper\ASCII;

final class Helpers
{
	/** @throws \Error */
	public function __construct()
	{
		throw new \Error('Class ' . static::class . ' is static and cannot be instantiated.');
	}


	/**
	 * Create best feature snippet which should contains maximum of query words.
	 *
	 * Snippet can be generated from set of matches which will be combined by "...".
	 */
	public static function smartTruncate(string $query, string $haystack, int $len = 60): string
	{
		$words = implode('|', explode(' ', self::convertQueryToRegexWords($query)));

		$snippetGenerator = static function (int $len) use ($words, $haystack): array {
			$s = '\s\x00\-\/\:\-\@\[-`{-~';
			preg_match_all(
				'/(?<=[' . $s . ']).{0,' . $len . '}((' . $words . ').{0,' . $len . '})+(?=[' . $s . '])?/uis',
				$haystack,
				$matches,
				PREG_SET_ORDER,
			);

			$snippets = [];
			foreach ($matches as $match) {
				$snippets[] = htmlspecialchars($match[0], 0, 'UTF-8');
			}

			return $snippets;
		};

		$return = '';
		for ($i = 0; $i <= $len / 30; $i++) {
			$attempt = implode(' ... ', $snippetGenerator(30 + $i * 10));
			if (
				$attempt !== ''
				&& ($return === '' || mb_strlen($attempt, 'UTF-8') >= $len) // first iteration or longer
			) {
				$return = $attempt;
				break;
			}
		}

		return (string) preg_replace('/^(.*?)\s*(?:\.{2,}\s+)+\.{2,}\s*$/', '$1 ...', self::truncate($return, $len, ' ...'));
	}


	public static function highlightFoundWords(
		string $haystack,
		string $words,
		?string $replacePattern = null,
	): string {
		static $wordListCache = [];
		$cacheKey = $words;
		if (isset($wordListCache[$cacheKey]) === false) {
			$words = trim($words);
			if ($words === '') {
				return $haystack;
			}
			$words = (string) preg_replace('/\s+/', ' ', $words);
			$wordList = array_unique(explode(' ', mb_strtolower($words)));
			// first match longest words
			usort($wordList, static fn(string $a, string $b): int => mb_strlen($a, 'UTF-8') < mb_strlen($b, 'UTF-8') ? 1 : -1);
			$wordListCache[$words] = $wordList;
		}

		$replacePattern ??= '<i class="highlight">\\0</i>';
		[$replaceLeft, $replaceRight] = explode('\\0', $replacePattern);

		foreach ($wordListCache[$cacheKey] as $word) {
			$haystack = self::replaceAndIgnoreAccent($word, $replacePattern, $haystack);
		}

		return (string) preg_replace('/(?:' . preg_quote($replaceRight, '/') . ')(\s+)(?:' . preg_quote($replaceLeft, '/') . ')/', '$1', $haystack);
	}


	/**
	 * Replace $from => $to, in $string. Helper for national characters.
	 * The function first constructs a pattern that it uses to replace with a regular expression.
	 */
	public static function replaceAndIgnoreAccent(
		string $from,
		string $to,
		string $string,
		bool $caseSensitive = false
	): string {
		$from = preg_quote(self::toAscii($from, useCache: true), '/');
		$fromPattern = str_replace(
			['a', 'c', 'd', 'e', 'i', 'l', 'n', 'o', 'r', 's', 't', 'u', 'y', 'z'],
			['[aáä]', '[cč]', '[dď]', '[eèêéě]', '[ií]', '[lĺľ]', '[nň]', '[oô]', '[rŕř]', '[sśš]', '[tť]', '[uúů]', '[yý]', '[zžź]'],
			$caseSensitive === false ? mb_strtolower($from) : $from,
		);

		if (mb_strlen($from, 'UTF-8') === 1) { // the conjunction must be a whole word, partial match is not supported
			$fromPattern = '(?:^|\s)' . $fromPattern . '(?:\s|$)';
		}

		/** @phpstan-ignore-next-line */
		return ((string) preg_replace(
			'/(' . $fromPattern . ')(?=[^>]*(<|$))/smu' . ($caseSensitive === false ? 'i' : ''),
			$to,
			$string,
		)) ?: $string;
	}


	/**
	 * @param array<int, array{haystack: string, score: int}> $snippets
	 */
	public static function implodeSnippets(array $snippets): string
	{
		$return = '';
		foreach ($snippets as $snippet) {
			$return .= ($return !== '' && $snippet['haystack'] !== '' ? '; ' : '') . $snippet['haystack'];
		}

		return strip_tags(trim(trim($return, '; ')));
	}


	/**
	 * @param array<int, string> $possibilities
	 * @internal
	 * Copied from nette/utils.
	 * Finds the best suggestion (for 8-bit encoding).
	 */
	public static function getSuggestion(array $possibilities, string $value): ?string
	{
		$norm = (string) preg_replace($re = '#^(get|set|has|is|add)(?=[A-Z])#', '', $value);
		$best = null;
		$min = (strlen($value) / 4 + 1) * 10 + .1;
		foreach (array_unique($possibilities, SORT_REGULAR) as $item) {
			if ($item === $value) {
				continue;
			}
			$len = null;
			$lenLeft = levenshtein($item, $value, 10, 11, 10);
			if ($lenLeft < $min) {
				$len = $lenLeft;
			}
			$lenRight = levenshtein((string) preg_replace($re, '', $item), $norm, 10, 11, 10) + 20;
			if ($lenRight < $min) {
				$len = $lenRight;
			}
			if ($len !== null) {
				$min = $len;
				$best = $item;
			}
		}

		return $best;
	}


	public static function findSimilarQuery(Analytics $analytics, string $query): ?string
	{
		$similarCandidates = [];
		$queryScore = $analytics->getQueryScore($query);

		for ($i = ($length = mb_strlen($query, 'UTF-8')) - 1; $i > 0; $i--) {
			$part = mb_substr($query, 0, $i, 'UTF-8');
			foreach ($queryScore as $q => $score) {
				if (strncmp($q, $part, \strlen($part)) === 0) {
					$similarCandidates[$q] = [
						'query' => $q,
						'score' => $score,
						'levenshtein' => levenshtein($q, $query),
					];
				}
			}
			if ($i <= (int) ($length / 2) || \count($similarCandidates) > 1_000) {
				break;
			}
		}

		$candidatesByScore = $similarCandidates;
		$candidatesByLevenshtein = $similarCandidates;

		usort($candidatesByScore, static fn(array $a, array $b): int => $a['score'] < $b['score'] ? 1 : -1);
		usort($candidatesByLevenshtein, static fn(array $a, array $b): int => $a['levenshtein'] > $b['levenshtein'] ? 1 : -1);

		$scores = [];
		foreach ($candidatesByScore as $index => $value) {
			$scores[$value['query']] = $index;
		}

		$levenshteins = [];
		foreach ($candidatesByLevenshtein as $index => $value) {
			$levenshteins[$value['query']] = $index;
		}

		$candidates = [];
		foreach ($similarCandidates as $similarCandidate) {
			$candidates[$similarCandidate['query']] =
				((float) $scores[$similarCandidate['query']]) + ((float) $levenshteins[$similarCandidate['query']]);
		}

		$top = null;
		$minScore = null;
		foreach ($candidates as $candidateQuery => $candidateScore) {
			if ($candidateScore < $minScore || $minScore === null) {
				$minScore = $candidateScore;
				$top = $candidateQuery;
			}
		}

		/** @phpstan-ignore-next-line */
		return (string) $top ?: null;
	}


	public static function convertQueryToRegexWords(string $query): string
	{
		static $cache = [];
		if (isset($cache[$query]) === false) {
			$words = array_filter(explode(' ', $query), static fn(string $word): bool => mb_strlen($word, 'UTF-8') > 1);
			$cache[$query] = str_replace(
				['a', 'c', 'd', 'e', 'i', 'l', 'n', 'o', 'r', 's', 't', 'u', 'y', 'z'],
				['[aáä]', '[cč]', '[dď]', '[eèêéě]', '[ií]', '[lĺľ]', '[nň]', '[oô]', '[rŕř]', '[sśš]', '[tť]', '[uúů]', '[yý]', '[zžź]'],
				mb_strtolower(trim(preg_quote((string) preg_replace('/\s+/', ' ', implode(' ', array_unique($words))), '/'))),
			);
		}

		return $cache[$query];
	}


	/**
	 * Removes control characters, normalizes line breaks to `\n`, removes leading and trailing blank lines,
	 * trims end spaces on lines, normalizes UTF-8 to the normal form of NFC.
	 */
	public static function normalize(string $s): string
	{
		// convert to compressed normal form (NFC)
		if (class_exists('Normalizer', false)) {
			$n = (string) \Normalizer::normalize($s, \Normalizer::FORM_C);
			if ($n !== '') {
				$s = $n;
			}
		}

		$s = str_replace(["\r\n", "\r"], "\n", $s);

		// remove control characters; leave \t + \n
		$s = (string) preg_replace('#[\x00-\x08\x0B-\x1F\x7F-\x9F]+#u', '', $s);

		// right trim
		$s = (string) preg_replace('#[\t ]+$#m', '', $s);

		// leading and trailing blank lines
		$s = trim($s, "\n");

		return $s;
	}


	public static function firstLower(string $s): string
	{
		return mb_strtolower(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($s, 1, null, 'UTF-8');
	}


	public static function firstUpper(string $s): string
	{
		return mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($s, 1, null, 'UTF-8');
	}


	public static function toAscii(string $s, bool $useCache = false): string
	{
		static $cache = [];
		if (isset($cache[$s])) {
			$return = $cache[$s];
		} else {
			$return = ASCII::to_transliterate($s);
			if ($useCache) {
				$cache[$s] = $return;
			}
		}

		return (string) $return;
	}


	/**
	 * Truncates a UTF-8 string to given maximal length, while trying not to split whole words. Only if the string is truncated,
	 * an ellipsis (or something else set with third argument) is appended to the string.
	 */
	public static function truncate(string $s, int $maxLen, string $append = "\u{2026}"): string
	{
		if (mb_strlen($s, 'UTF-8') > $maxLen) {
			$maxLen -= mb_strlen($append, 'UTF-8');
			if ($maxLen < 1) {
				return $append;
			}
			if (preg_match('#^.{1,' . $maxLen . '}(?=[\s\x00-/:-@\[-`{-~])#us', $s, $matches) === 1) {
				return $matches[0] . $append;
			}

			return mb_substr($s, 0, $maxLen, 'UTF-8') . $append;
		}

		return $s;
	}
}
