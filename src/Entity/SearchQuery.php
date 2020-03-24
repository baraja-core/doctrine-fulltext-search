<?php

declare(strict_types=1);

namespace Baraja\Search\Entity;


use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;

/**
 * @ORM\Entity()
 * @ORM\Table(
 *    name="search__search_query",
 *    indexes={
 *       @Index(name="search__search_query__query_id", columns={"query", "id"}),
 *       @Index(name="search__search_query__results", columns={"results"}),
 *       @Index(name="search__search_query__frequency", columns={"frequency"})
 *    }
 * )
 */
class SearchQuery
{

	use UuidIdentifier;

	/**
	 * @var string
	 * @ORM\Column(type="string", unique=true)
	 */
	private $query;

	/**
	 * @var int
	 * @ORM\Column(type="integer")
	 */
	private $frequency = 1;

	/**
	 * @var int
	 * @ORM\Column(type="integer")
	 */
	private $results;

	/**
	 * @var int
	 * @ORM\Column(type="integer")
	 */
	private $score;

	/**
	 * @var \DateTime
	 * @ORM\Column(type="datetime")
	 */
	private $insertedDate;

	/**
	 * @var \DateTime|null
	 * @ORM\Column(type="datetime", nullable=true)
	 */
	private $updatedDate;


	/**
	 * @param string $query
	 * @param int $results
	 * @param int $score
	 */
	public function __construct(string $query, int $results, int $score = 0)
	{
		$this->query = trim($query);
		$this->results = $results < 0 ? 0 : $results;
		$this->setScore($score);
		try {
			$this->insertedDate = new \DateTime('now');
		} catch (\Throwable $e) {
			throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
		}
	}


	/**
	 * @return string
	 */
	public function getQuery(): string
	{
		return $this->query;
	}


	/**
	 * @return int
	 */
	public function getFrequency(): int
	{
		return $this->frequency;
	}


	/**
	 * @param int $frequency
	 * @return SearchQuery
	 */
	public function addFrequency(int $frequency = 1): self
	{
		$this->frequency += $frequency;

		return $this;
	}


	/**
	 * @return int
	 */
	public function getResults(): int
	{
		return $this->results;
	}


	/**
	 * @param int $results
	 * @return SearchQuery
	 */
	public function setResults(int $results): self
	{
		$this->results = $results;

		return $this;
	}


	/**
	 * @return int
	 */
	public function getScore(): int
	{
		return $this->score;
	}


	/**
	 * @param int $score
	 * @return SearchQuery
	 */
	public function setScore(int $score): self
	{
		if ($score < 0) {
			$this->score = 0;
		} elseif ($score > 100) {
			$this->score = 100;
		} else {
			$this->score = $score;
		}

		return $this;
	}


	/**
	 * @return \DateTime
	 */
	public function getInsertedDate(): \DateTime
	{
		return $this->insertedDate;
	}


	/**
	 * @return \DateTime|null
	 */
	public function getUpdatedDate(): ?\DateTime
	{
		return $this->updatedDate;
	}


	/**
	 * @param \DateTime $updatedDate
	 * @return SearchQuery
	 */
	public function setUpdatedDate(\DateTime $updatedDate): self
	{
		$this->updatedDate = $updatedDate;

		return $this;
	}


	public function setUpdatedNow(): self
	{
		try {
			$this->updatedDate = new \DateTime('now');
		} catch (\Throwable $e) {
			throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
		}

		return $this;
	}

}
