<?php

declare(strict_types=1);

namespace Baraja\Search;


use Baraja\Search\Entity\SearchResult;
use Baraja\Search\QueryNormalizer\IQueryNormalizer;
use Baraja\Search\ScoreCalculator\IScoreCalculator;
use Doctrine\ORM\EntityManagerInterface;

final class Search
{
	private Container $container;


	public function __construct(
		EntityManagerInterface $em,
		?IQueryNormalizer $queryNormalizer = null,
		?IScoreCalculator $scoreCalculator = null,
		?Container $container = null,
	) {
		$this->container = $container ?? new Container(
			entityManager: $em,
			queryNormalizer: $queryNormalizer,
			scoreCalculator: $scoreCalculator,
		);
	}


	/**
	 * Search string in entity map.
	 *
	 * Entity map example:
	 *    Article::class => ['title', 'description'],
	 *    User::class => 'username',
	 *
	 * @param array<string, string|array<int, string>> $entityMap
	 * @param array<int, string> $userConditions
	 */
	public function search(
		?string $query,
		array $entityMap,
		bool $searchExactly = false,
		array $userConditions = [],
		bool $useAnalytics = true,
	): SearchResult {
		$query = $this->container->getQueryNormalizer()->normalize($query ?? '');
		if ($query === '') {
			trigger_error('Search query can not be empty.');

			return new SearchResult('');
		}

		$result = new SearchResult($query);
		$entityMap = EntityMapNormalizer::normalize($entityMap, $this->container->getEntityManager());
		foreach ($entityMap as $entityClass => $columns) {
			$startTime = microtime(true);
			$searchItems = $this->container->getCore()
				->processCandidateSearch(
					query: $query,
					entity: $entityClass,
					columns: $columns,
					userConditions: $userConditions,
				);
			foreach ($searchItems as $searchItem) {
				$result->addItem($searchItem);
			}
			$result->addSearchTime((microtime(true) - $startTime) * 1_000);
			if ($result->getSearchTime() > $this->container->getSearchTimeout()) {
				break;
			}
		}
		if ($useAnalytics === true && $result->getSearchTime() < 1_500) {
			$didYouMeanTime = microtime(true);
			if ($result->getCountResults() > 0) {
				$this->container->getAnalytics()->save($query, $result->getCountResults());
			} elseif ($searchExactly === false) {
				$result->setDidYouMean(Helpers::findSimilarQuery($this->container->getAnalytics(), $query));
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
