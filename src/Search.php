<?php

declare(strict_types=1);

namespace Baraja\Search;


use Baraja\Search\Entity\SearchResult;
use Baraja\Search\QueryNormalizer\IQueryNormalizer;
use Baraja\Search\QueryNormalizer\QueryNormalizer;
use Baraja\Search\ScoreCalculator\IScoreCalculator;
use Baraja\Search\ScoreCalculator\ScoreCalculator;
use Doctrine\ORM\EntityManagerInterface;

final class Search
{

	private const SEARCH_TIMEOUT = 2500;

	/**
	 * @var IQueryNormalizer
	 */
	private $IQueryNormalizer;

	/**
	 * @var Core
	 */
	private $core;

	/**
	 * @var Analytics
	 */
	private $analytics;

	/**
	 * @param EntityManagerInterface $entityManager
	 * @param IQueryNormalizer $queryNormalizer
	 * @param IScoreCalculator $scoreCalculator
	 */
	public function __construct(
		EntityManagerInterface $entityManager,
		?IQueryNormalizer $queryNormalizer = null,
		?IScoreCalculator $scoreCalculator = null
	)
	{
		$this->IQueryNormalizer = $queryNormalizer ?? new QueryNormalizer;
		$this->core = new Core(new QueryBuilder($entityManager), $scoreCalculator ?? new ScoreCalculator);
		$this->analytics = new Analytics($entityManager);
	}

	/**
	 * Search string in entity map.
	 *
	 * Entity map example:
	 *    'Article::class' => ['title', 'description'],
	 *    'User::class' => 'username',
	 *
	 * @param string|null $query
	 * @param string[][] $entityMap
	 * @param bool $searchExactly
	 * @return SearchResult
	 * @throws SearchException
	 */
	public function search(?string $query, array $entityMap, bool $searchExactly = false): SearchResult
	{
		if (($query = $this->IQueryNormalizer->normalize($query ?? '')) === '') {
			throw new SearchException('Empty search string.');
		}

		$result = new SearchResult($query);

		foreach (EntityMapNormalizer::normalize($entityMap) as $entity => $columns) {
			$startTime = microtime(true);
			foreach ($this->core->processCandidateSearch($query, $entity, $columns) as $searchItem) {
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
					throw new SearchException($e->getMessage(), $e->getCode(), $e);
				}
			} elseif ($searchExactly === false) {
				$result->setDidYouMean($this->getSimilarSearch()->findSimilarQuery($query));
			}
			$result->addSearchTime((microtime(true) - $didYouMeanTime) * 1000);
		}

		return $result;
	}

	/**
	 * @return SimilarSearch
	 */
	private function getSimilarSearch(): SimilarSearch
	{
		static $cache;

		if ($cache === null) {
			$cache = new SimilarSearch($this->analytics);
		}

		return $cache;
	}

}