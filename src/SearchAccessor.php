<?php

declare(strict_types=1);

namespace Baraja\Search;


interface SearchAccessor
{
	public function get(): Search;
}
