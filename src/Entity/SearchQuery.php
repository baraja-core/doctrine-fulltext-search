<?php

declare(strict_types=1);

namespace Baraja\Search\Entity;


use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="search__search_query")
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
		$this->query = $query;
		$this->results = $results;
		$this->score = $score;
		$this->insertedDate = new \DateTime('now');
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
	 */
	public function addFrequency(int $frequency = 1): void
	{
		$this->frequency += $frequency;
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
	 */
	public function setResults(int $results): void
	{
		$this->results = $results;
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
	 */
	public function setScore(int $score): void
	{
		$this->score = $score;
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
	 */
	public function setUpdatedDate(\DateTime $updatedDate): void
	{
		$this->updatedDate = $updatedDate;
	}

}