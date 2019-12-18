<?php

declare(strict_types=1);

namespace Baraja\Search\QueryNormalizer;


interface IQueryNormalizer
{

	/**
	 * @param string $query
	 * @return string
	 */
	public function normalize(string $query): string;

}