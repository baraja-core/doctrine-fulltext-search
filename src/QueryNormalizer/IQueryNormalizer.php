<?php

declare(strict_types=1);

namespace Baraja\Search\QueryNormalizer;


interface IQueryNormalizer
{
	public function normalize(string $query): string;
}
