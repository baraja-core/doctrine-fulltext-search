<?php

declare(strict_types=1);

namespace Baraja\Search;


class SearchException extends \Exception
{

	/**
	 * @throws SearchException
	 */
	public static function columnIsNotValidArray(string $haystack): void
	{
		throw new self('Haystack "' . $haystack . '" is not valid column array.');
	}


	/**
	 * @param string[] $entityProperties
	 * @throws SearchException
	 */
	public static function columnIsNotValidProperty(string $column, string $entityName, array $entityProperties): void
	{
		sort($entityProperties);
		$hint = Helpers::getSuggestion($entityProperties, $column);
		throw new self(
			'Column "' . preg_replace('/^[\:\!\_]/', '', $column) . '" is not valid property of "' . $entityName . '".'
			. "\n" . 'Did you mean ' . ($hint !== null ? '"' . $hint . '"' : '"' . implode('", "', $entityProperties) . '"') . '?'
		);
	}


	/**
	 * @throws SearchException
	 */
	public static function classIsNotValidDatabaseEntity(string $entityName, string $docComment): void
	{
		throw new self('Class "' . $entityName . '" is not valid database entity. Please check comment annotation.' . "\n" . $docComment);
	}
}
