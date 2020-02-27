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
		throw new self('Haystack "' . $entityName . '" is not valid class, ' . \gettype($entityName) . ' given.');
	}

	/**
	 * @param string $haystack
	 * @throws SearchException
	 */
	public static function columnIsNotValidArray(string $haystack): void
	{
		throw new self('Haystack "' . $haystack . '" is not valid column array.');
	}

	/**
	 * @param string $column
	 * @param string $entityName
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
	 * @param string $entityName
	 * @param string $docComment
	 * @throws SearchException
	 */
	public static function classIsNotValidDatabaseEntity(string $entityName, string $docComment): void
	{
		throw new self('Class "' . $entityName . '" is not valid database entity. Please check comment annotation.' . "\n" . $docComment);
	}

	/**
	 * @throws SearchException
	 */
	public static function contextEntityDoesNotExist(): void
	{
		throw new self('Context entity does not exist. Did you call addEntity() first?');
	}

	/**
	 * @throws SearchException
	 */
	public static function selectorBuilderIsClosed(): void
	{
		throw new self('Selector builder is closed. You can not modify select query after search.');
	}

}