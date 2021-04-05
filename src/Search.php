<?php

declare(strict_types=1);

namespace Baraja\Search;


use Baraja\Search\Entity\SearchResult;
use Baraja\Search\QueryNormalizer\IQueryNormalizer;
use Baraja\Search\QueryNormalizer\QueryNormalizer;
use Baraja\Search\ScoreCalculator\IScoreCalculator;
use Baraja\Search\ScoreCalculator\ScoreCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;

final class Search
{
	private const SEARCH_TIMEOUT = 2_500;

	private IQueryNormalizer $queryNormalizer;

	private Core $core;

	private Analytics $analytics;


	public function __construct(
		private EntityManagerInterface $em,
		?IStorage $storage = null,
		?IQueryNormalizer $queryNormalizer = null,
		?IScoreCalculator $scoreCalculator = null
	) {
		$this->queryNormalizer = $queryNormalizer ?? new QueryNormalizer;
		$this->core = new Core(new QueryBuilder($em), $scoreCalculator ?? new ScoreCalculator);
		$this->analytics = new Analytics($em, $storage !== null ? new Cache($storage, 'baraja-doctrine-fulltext-search') : null);
	}


	/**
	 * Search string in entity map.
	 *
	 * Entity map example:
	 *    'Article::class' => ['title', 'description'],
	 *    'User::class' => 'username',
	 *
	 * @param array<string, string|array<int, string>> $entityMap
	 * @param string[] $userConditions
	 */
	public function search(
		?string $query,
		array $entityMap,
		bool $searchExactly = false,
		array $userConditions = [],
		bool $useAnalytics = true
	): SearchResult {
		if (($query = $this->queryNormalizer->normalize($query ?? '')) === '') {
			trigger_error('Search query can not be empty.');

			return new SearchResult('');
		}

		$result = new SearchResult($query);
		foreach (EntityMapNormalizer::normalize($entityMap, $this->em) as $entity => $columns) {
			$startTime = microtime(true);
			foreach ($this->core->processCandidateSearch($query, $entity, $columns, $userConditions) as $searchItem) {
				$result->addItem($searchItem);
			}
			$result->addSearchTime((microtime(true) - $startTime) * 1_000);

			if ($result->getSearchTime() > self::SEARCH_TIMEOUT) {
				break;
			}
		}
		if ($useAnalytics === true && $result->getSearchTime() < 1_500) {
			$didYouMeanTime = microtime(true);
			if ($result->getCountResults() > 0) {
				$this->analytics->save($query, $result->getCountResults());
			} elseif ($searchExactly === false) {
				$result->setDidYouMean(Helpers::findSimilarQuery($this->analytics, $query));
			}
			$result->addSearchTime((microtime(true) - $didYouMeanTime) * 1_000);
		}

		return $result;
	}


	public function selectorBuilder(?string $query, bool $searchExactly = false): SelectorBuilder
	{
		return new SelectorBuilder($query ?? '', $searchExactly, $this);
	}
}
