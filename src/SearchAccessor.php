<?php

declare(strict_types=1);

namespace Baraja\Search;


interface SearchAccessor
{
	/**
	 * @return Search
	 */
	public function get(): Search;
}