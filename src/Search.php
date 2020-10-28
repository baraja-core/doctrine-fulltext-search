<?php

declare(strict_types=1);

namespace Baraja\Search;


use Baraja\Doctrine\EntityManager;
use Baraja\Search\Entity\SearchResult;
use Baraja\Search\QueryNormalizer\IQueryNormalizer;
use Baraja\Search\QueryNormalizer\QueryNormalizer;
use Baraja\Search\ScoreCalculator\IScoreCalculator;
use Baraja\Search\ScoreCalculator\ScoreCalculator;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;

final class Search
{
	private const SEARCH_TIMEOUT = 2500;

	private IQueryNormalizer $queryNormalizer;

	private Core $core;

	private Analytics $analytics;


	public function __construct(EntityManager $entityManager, IStorage $storage, ?IQueryNormalizer $queryNormalizer = null, ?IScoreCalculator $scoreCalculator = null)
	{
		$this->queryNormalizer = $queryNormalizer ?? new QueryNormalizer;
		$this->core = new Core(new QueryBuilder($entityManager), $scoreCalculator ?? new ScoreCalculator);
		$this->analytics = new Analytics($entityManager, new Cache($storage, 'baraja-doctrine-fulltext-search'));
	}


	/**
	 * Search string in entity map.
	 *
	 * Entity map example:
	 *    'Article::class' => ['title', 'description'],
	 *    'User::class' => 'username',
	 *
	 * @param string[][] $entityMap
	 * @param string[] $userWheres
	 * @return SearchResult
	 * @throws SearchException
	 */
	public function search(?string $query, array $entityMap, bool $searchExactly = false, array $userWheres = []): SearchResult
	{
		if (($query = $this->queryNormalizer->normalize($query ?? '')) === '') {
			throw new SearchException('Empty search string.');
		}

		$result = new SearchResult($query);
		foreach (EntityMapNormalizer::normalize($entityMap) as $entity => $columns) {
			$startTime = microtime(true);
			foreach ($this->core->processCandidateSearch($query, $entity, $columns, $userWheres) as $searchItem) {
				$result->addItem($searchItem);
			}
			$result->addSearchTime((microtime(true) - $startTime) * 1000);

			if ($result->getSearchTime() > self::SEARCH_TIMEOUT) {
				break;
			}
		}
		if ($result->getSearchTime() < 1500) {
			$didYouMeanTime = microtime(true);
			if ($result->getCountResults() > 0) {
				try {
					$this->analytics->save($query, $result->getCountResults());
				} catch (\RuntimeException $e) {
					throw new SearchException('Saving analytical data failed: ' . $e->getMessage(), $e->getCode(), $e);
				}
			} elseif ($searchExactly === false) {
				$result->setDidYouMean(Helpers::findSimilarQuery($this->analytics, $query));
			}
			$result->addSearchTime((microtime(true) - $didYouMeanTime) * 1000);
		}

		return $result;
	}


	public function selectorBuilder(?string $query, bool $searchExactly = false): SelectorBuilder
	{
		return new SelectorBuilder($query ?? '', $searchExactly, $this);
	}
}
