<?php

declare(strict_types=1);

namespace Baraja\Search;


use Nette\Utils\Strings;

final class Helpers
{

	/** @throws \Error */
	public function __construct()
	{
		throw new \Error('Class ' . get_class($this) . ' is static and cannot be instantiated.');
	}


	/**
	 * Create best feature snippet which should contains maximum of query words.
	 *
	 * Snippet can be generated from set of matches which will be combined by "...".
	 */
	public static function smartTruncate(string $query, string $haystack, int $len = 60): string
	{
		$queryWords = array_filter(explode(' ', $query), fn (string $word): bool => Strings::length($word) > 1);
		$queryWithPatterns = str_replace(
			['a', 'c', 'd', 'e', 'i', 'l', 'n', 'o', 'r', 's', 't', 'u', 'y', 'z'],
			['[aáä]', '[cč]', '[dď]', '[eèêéě]', '[ií]', '[lĺľ]', '[nň]', '[oô]', '[rŕř]', '[sśš]', '[tť]', '[uúů]', '[yý]', '[zžź]'],
			trim((string) mb_strtolower((string) preg_quote((string) preg_replace('/\s+/', ' ', implode(' ', array_unique($queryWords))), '/')))
		);
		$words = implode('|', explode(' ', $queryWithPatterns));

		$s = '\s\x00\-\/\:\-\@\[-`{-~';
		$snippetGenerator = static function (int $len) use ($words, $haystack, $s): array {
			preg_match_all('/(?<=[' . $s . ']).{1,' . $len . '}((' . $words . ').{1,' . $len . '})+(?=[' . $s . '])/uis', $haystack, $matches, PREG_SET_ORDER);

			$snippets = [];
			foreach ($matches as $match) {
				$snippets[] = htmlspecialchars($match[0], 0, 'UTF-8');
			}

			return $snippets;
		};

		$return = '';
		for ($i = 0; $i <= $len / 30; $i++) {
			if (Strings::length($attempt = implode(' ... ', $snippetGenerator(30 + $i * 10))) >= $len) {
				$return = $attempt;
				break;
			}
		}

		return (string) preg_replace('/^(.*?)\s*(?:\.{2,}\s+)+\.{2,}\s*$/', '$1 ...', Strings::truncate($return, $len, ' ...'));
	}


	public static function highlightFoundWords(string $haystack, string $words, ?string $replacePattern = null, bool $caseSensitive = false): string
	{
		if (($words = trim($words)) === '') {
			return $haystack;
		}

		$words = (string) preg_replace('/\s+/', ' ', $words);
		$replacePattern = $replacePattern ?? '<i class="highlight">\\0</i>';
		[$replaceLeft, $replaceRight] = explode('\\0', $replacePattern);
		$wordList = array_unique(explode(' ', $caseSensitive === true ? $words : mb_strtolower($words)));
		// first match longest words
		usort($wordList, fn (string $a, string $b): int => Strings::length($a) < Strings::length($b) ? 1 : -1);


		foreach ($wordList as $word) {
			$haystack = self::replaceAndIgnoreAccent($word, $replacePattern, $haystack);
		}

		return (string) preg_replace('/(?:' . preg_quote($replaceRight, '/') . ')(\s+)(?:' . preg_quote($replaceLeft, '/') . ')/', '$1', $haystack);
	}


	/**
	 * Replace $from => $to, in $string. Helper for national characters.
	 * The function first constructs a pattern that it uses to replace with a regular expression.
	 */
	public static function replaceAndIgnoreAccent(string $from, string $to, string $string, bool $caseSensitive = false): string
	{
		$conjunction = Strings::length($from = preg_quote(Strings::toAscii($from), '/')) === 1;

		$fromPattern = str_replace(
			['a', 'c', 'd', 'e', 'i', 'l', 'n', 'o', 'r', 's', 't', 'u', 'y', 'z'],
			['[aáä]', '[cč]', '[dď]', '[eèêéě]', '[ií]', '[lĺľ]', '[nň]', '[oô]', '[rŕř]', '[sśš]', '[tť]', '[uúů]', '[yý]', '[zžź]'],
			$caseSensitive === false ? (string) mb_strtolower($from) : $from
		);

		if ($conjunction === true) { // the conjunction must be a whole word, partial match is not supported
			$fromPattern = '(?:^|\s)' . $fromPattern . '(?:\s|$)';
		}

		return ((string) preg_replace(
			'/(' . $fromPattern . ')(?=[^>]*(<|$))/smu' . ($caseSensitive === false ? 'i' : ''),
			$to,
			$string
		)) ?: $string;
	}


	/**
	 * @param mixed[][] $snippets
	 * @return string
	 */
	public static function implodeSnippets(array $snippets): string
	{
		$return = '';
		foreach ($snippets as $snippet) {
			$return .= ($return !== '' && ($snippet['haystack'] ?? '') !== '' ? '; ' : '') . ($snippet['haystack'] ?? '');
		}

		return strip_tags(trim(trim($return, '; ')));
	}


	/**
	 * @param string[] $possibilities
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
			if ($item !== $value && (
					($len = levenshtein($item, $value, 10, 11, 10)) < $min
					|| ($len = levenshtein((string) preg_replace($re, '', $item), $norm, 10, 11, 10) + 20) < $min
				)) {
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

		for ($i = ($length = Strings::length($query)) - 1; $i > 0; $i--) {
			$part = Strings::substring($query, 0, $i);
			foreach ($queryScore as $_query => $score) {
				if (strncmp($q = (string) $_query, $part, \strlen($part)) === 0) {
					$similarCandidates[$q] = [
						'query' => $q,
						'score' => $score,
						'levenshtein' => levenshtein($q, $query),
					];
				}
			}
			if ($i <= (int) ($length / 2) || \count($similarCandidates) > 1000) {
				break;
			}
		}

		$candidatesByScore = $similarCandidates;
		$candidatesByLevenshtein = $similarCandidates;

		usort($candidatesByScore, fn (array $a, array $b): int => $a['score'] < $b['score'] ? 1 : -1);
		usort($candidatesByLevenshtein, fn (array $a, array $b): int => $a['levenshtein'] > $b['levenshtein'] ? 1 : -1);

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

		return ((string) $top) ?: null;
	}
}
