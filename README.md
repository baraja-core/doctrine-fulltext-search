Doctrine fulltext search engine
===============================

![Integrity check](https://github.com/baraja-core/doctrine-fulltext-search/workflows/Integrity%20check/badge.svg)

Implementation of an easy-to-use search engine in Doctrine entities.

For basic use, all you have to do is define a map of the searched entities and their properties, the search engine will arrange for them to be loaded correctly and will automatically sort the search results based on the candidates found.

📦 Installation & Basic Usage
-----------------------------

To manually install the package call Composer and execute the following command:

```shell
$ composer require baraja-core/doctrine-fulltext-search
```

And then register `DoctrineFulltextSearchExtension` in configuration NEON:

```yaml
extensions:
    doctrineFulltextSearch: Baraja\Search\DoctrineFulltextSearchExtension
```

```php
$results = $this->search->search($query, [
    Article::class => [':title'],
    User::class => ':username', // it can also be an ordinary string for a single column
    UserLogin::class => [':ip', 'hostname', 'userAgent'],
]);

echo $results; // Uses the default HTML renderer
```

Or you can use `SelectorBuilder` with full strict type validations and hinting methods to build the query:

```php
$results = $this->search->selectorBuilder($query)
    ->addEntity(Article::class)
        ->addColumnTitle('title')
    ->addEntity(User::class)
        ->addColumnTitle('username')
    ->addEntity(UserLogin::class)
        ->addColumnTitle('ip')
        ->addColumn('hostname')
        ->addColumnSearchOnly('userAgent')
    ->search();

echo $results;
```

There is no need to escap the output, all logic is solved by the engine automatically.

🛠️ Switches and special characters
----------------------------------

`:username` - The column will be used as a caption

`!slug` - The column will be used for searching, but ignored in perex output.

`_durationTime` - The column will be loaded into the entity, but will not be taken into account when calculating relevance and will not be included in the perex.

`content.versions.haystack` - A relationship between entities automatically creates a join and loads the last property.

`versions(content)` - Custom getter (automatically join the `versions` column, but call `getContent()` to get the data).

⚙️ Caption settings
-------------------

If we use a colon at the beginning of the column name (for example `':username'`), it will be automatically used as a title.

The title will be displayed even if it does not contain the search words.

The caption may be empty and may not exist (may be `null`).

If the title does not exist, then the engine can automatically calculate it according to the best occurrence in the found text.

Query normalization
------------------

The search query is automatically normalized and **stopwords** are removed, for which it does not make sense to search.

The algorithm can be overridden in a specific project by implementing the `IQueryNormalizer` interface and overwriting it in the DIC container.

🗺️ Browse search results
------------------------

The output of the `search()` method is an entity of the `SearchResult` type, which implements the `\Iterator` interface for the ability to easily cycle through the results.

> 🚩 **TIP:** If you just need to print search results quickly and the appearance requirements are not very high, the `SearchResult` entity directly implements the `__toString()` method for easy rendering of results directly as HTML.

The search result summarizes all the results of all searches in all entities. All results are obtained by the `getItems()` method - the output will be an array of entities of the `SearchItem[]` type.

However, we often need to compile a query in bulk and then list, for example, categories and products separately. To do this, use the helper method `getItemsOfType(string $type)`, which returns a truncated array of results of type `SearchItem[]` only for entities according to the passed parameter.

Render a specific search result
-------------------------------

We used the `getItems()` or `getItemsOfType()` method to get the search results that we go through. But how to work with a specific result?

It is important to note that at this point we no longer have the `__toString()` method available and must render the result (ideally in a template) ourselves.

For most cases, ready-made helpers will suffice:

- `getTitle()` returns the title of the found entity as string or null.
- `getTitleHighlighted()` calls `getTitle()` internally, and if the result is a valid string, it stains the occurrences of each word with `<i class="highlight">` and `</i>`.
- `getSnippet()` returns a snippet of the found entity, which summarizes the best found area in the original entity (for example, an article snippet where the search words occur). More snippets can be returned (individual occurrences are divided by a colon). Always returns a string (can be empty).
- `getTitleHighlighted()` internally calls `getSnippet()` and colors the occurrences of each word with `<i class="highlight">` and `</i>`.
- `getScore()` returns the relative (different contextually according to the search query and available data in each project) point evaluation of the result (according to this parameter the results are automatically sorted).
- `getEntity()` returns the original found entity that Doctrine produced internally. The search is performed using PARTIAL selection, so not all properties may always be available.
- `entityToArray()` returns itself as an array. Strings are automatically normalized.

Pagination
----------

Both methods for getting results (`getItems()` and `getItemsOfType()`) accept the parameters `$limit` (default `10`) and `$offset` (default `0`).

Paging itself is best implemented using [Nette Pagination](https://doc.nette.org/en/3.0/pagination).

The total number of results is obtained by the `getCountResults()` method above the `SearchResult` entity.

Read the found entity
---------------------

The search engine uses `PARTIAL` to load database entities and wraps the resulting entities into search results, so you can load them at any time by calling `->getEntity()` above a specific search result.

✅ Did you mean?
----------------

If no result can be found, or their number is "small" (the definition is determined by the algorithm itself according to the analysis of a specific project), a tip for the best correction of the search query may (and may not) be available.

For help, call the `getDidYouMean()` method over `SearchResult`. The output is either string (better search query) or null.

The best search query correction is obtained by the search engine itself based on advanced search analysis within each project separately using **machine learning** methods. With each search, statistics about the search query, the number of results and other signals are automatically saved and analyzed retrospectively if necessary.

Getting help is natural and can't be easily influenced. The search engine strives for maximum objectivity and offers users words that search for others and returns as many relevant results as possible according to the current context. Internally, complex mathematical functions are used, which we are constantly improving based on the experience from all projects.

🔒 Scoring system of search results
-----------------------------------

When searching, a list of candidates for the search results is first compiled. These results are individually passed through an evaluation algorithm that performs automatic "relative" evaluation in the range `0` - `512` (based on various signals such as the search query, recent user history, language, physical location, entity content and type) (the result is always `int`).

According to the point evaluation, the results are automatically sorted.

The scoring algorithm can be overridden by implementing the `IScoreCalculator` interface and overwriting it in the DIC container.

📄 License
-----------

`baraja-core/doctrine-fulltext-search` is licensed under the MIT license. See the [LICENSE](https://github.com/baraja-core/doctrine/blob/master/LICENSE) file for more details.
