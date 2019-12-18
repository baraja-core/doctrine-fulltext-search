<?php

declare(strict_types=1);

namespace Baraja\Search;


class SearchException extends \Exception
{

	/**
	 * @param string $entityName
	 * @throws SearchException
	 */
	public static function entityIsNotValidClass(string $entityName): void
	{
		throw new self('"' . $entityName . '" is not valid class, ' . \gettype($entityName) . ' given.');
	}

	/**
	 * @param string $haystack
	 * @throws SearchException
	 */
	public static function columnIsNotValidArray(string $haystack): void
	{
		throw new self('"' . $haystack . '" is not valid column array.');
	}

	/**
	 * @param string $column
	 * @param string $entityName
	 * @throws SearchException
	 */
	public static function columnIsNotValidProperty(string $column, string $entityName): void
	{
		throw new self('Column "' . $column . '" is not valid property of "' . $entityName . '".');
	}

}