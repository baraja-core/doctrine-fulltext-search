Vyhledávání
===========

Implementace jednoduše použitelného vyhledávače v Doctrine entitách.

Pro základní použití stačí jen definovat mapu prohledávaných entit a jejich properties, vyhledávač sám zařídí jejich korektní načtení a na základě nalezených kandidátů automaticky setřídí výsledky vyhledávání.

Základní použití
----------------

```php
$results = $this->search->search($query, [
	Article::class => [':title'],
	User::class => ':username', // může být i obyčejný string, pokud jde o jeden sloupec
	UserLogin::class => [':ip', 'hostname', 'userAgent'],
]);

echo $results; // Použije výchozí renderer do HTML
```

Výstup není potřeba nijak escapovat, veškerou logiku řeší engine automaticky.

Přepínače a speciální znaky
---------------------------

`:username` - Sloupec bude použit jako titulek

`!slug` - Sloupec bude použit pro vyhledávání, ale ignorován ve výstupu do perexu.

`_durationTime` - Sloupec bude načten do entity, ale nebude zohledněn při počítání relevance a neuvede se do perexu.

`content.versions.haystack` - Relace mezi entitami, automaticky vytvoří join a načte poslední property.

`versions(content)` - Vlastní getter (automaticky se vytvoří join na sloupec `versions`, ale pro získání dat se zavolá `getContent()`).

Nastavení titulku
-----------------

Pokud v názvu sloupce použijeme na začátku dvojtečku (například `':username'`), bude automaticky použit jako titulek.

Titulek bude zobrazen, i když neobsahuje hledaná slova.

Titulek může být prázdný a nemusí existovat (může být `null`).

Pokud titulek neexistuje, tak jej engine umí automaticky dopočítat podle nejlepšího výskytu v nalezeném textu.

Normalizace dotazu
------------------

Vyhledávaný dotaz je automaticky normalizován a jsou odebrána **stopslova**, u kterých nedává smysl vyhledávání.

Algoritmus lze v konkrétním projektu přepsat implementací rozhraní `IQueryNormalizer` a přepsáním v DIC kontejneru.

Procházení výsledků vyhledávání
-------------------------------

Výstupem metody `search()` je entita typu `SearchResult`, která implementuje rozhraní `\Iterator` pro možnost snadného procházení výsledků cyklem.

> **TIP:** Pokud potřebujete jen rychle vypsat výsledky vyhledávání a nejsou moc vysoké nároky na vzhled, přímo entita `SearchResult` implementuje metodu `__toString()` pro snadné vyrenderování výsledků přímo jako HTML.

Výsledek vyhledávání obsahuje souhrně všechny výsledky všech hledání ve všech entitách. Všechny výsledky získáme metodou `getItems()` - výstupem bude pole entit typu `SearchItem[]`.

Často však potřebujeme hromadně sestavit dotaz a pak vypsat například kategorie a produkty zvlášť. K tomu slouží helper metoda `getItemsOfType(string $type)`, která vrátí ořezané pole výsledků typu `SearchItem[]` jen pro entity podle předaného parametru.

Vypsání konkrétního výsledku vyhledávání
----------------------------------------

Metodou `getItems()` nebo `getItemsOfType()` jsme získali výsledky vyhledávání, které procházíme cyklem. Jak ale s konkrétním výsledkem pracovat?

Je důležité si uvědomit, že v tomto okamžiku už nemáme k dispozici metodu `__toString()` a musíme si výsledek vyrenderovat (ideálně v šabloně) sami.

Pro většinu případů nám budou stačit připravené helpery:

- `getTitle()` vrátí titulek nalezené entity jako string nebo null.
- `getTitleHighlighted()` interně zavolá `getTitle()` a pokud je výsledkem validní string, obarví nalezené výskyty jednotlivých slov pomocí `<i class="highlight">` a `</i>`.
- `getSnippet()` vrátí snippet nalezené entity, který shrnuje nejlepší nalezenou oblast v původní entitě (například úryvek článku, kde se vyskytují hledaná slova). Snippetů může být vráceno více (jednotlivé výskyty se rozdělí trojtečkou). Vždy vrátí string (může být i prázdný).
- `getTitleHighlighted()` interně zavolá `getSnippet()` a obarví nalezené výskyty jednotlivých slov pomocí `<i class="highlight">` a `</i>`.
- `getScore()` vrátí relativní (liší se kontextově podle hledaného dotazu a dostupných dat v každém projektu) bodové hodnocení výsledku (podle tohoto parametru se výsledky automaticky řadí).
- `getEntity()` vrátí původní nalezenou entitu, kterou Doctrine interně vyrobila. Vyhledávání probíhá pomocí PARTIAL selection, proto nemusí být všechny properties vždy k dispozici.
- `entityToArray()` vrátí sám sebe jako array. Stringy jsou automaticky normalizovány.

Stránkování výsledků
--------------------

Obě metody pro získání výsledků (`getItems()` a `getItemsOfType()`) přijímají parametry `$limit` (default `10`) a `$offset` (default `0`).

Samotné stránkování je nejlepší implementovat pomocí Nette Pagination (dokumentace: https://doc.nette.org/cs/2.4/pagination).

Celkový počet výsledků získáme metodou `getCountResults()` nad entitou `SearchResult`.

Čtení nalezené entity
---------------------

Engine při vyhledávání používá `PARTIAL` načítání databázových entit a výsledné entity zabaluje do výsledků vyhledávání, proto je lze kdykoli načíst zavoláním `->getEntity()` nad konkrétním výsledkem vyhledávání.

Did you mean?
-------------

Pokud se nepodaří najít žádný výsledek, nebo je jejich počet "malý" (definici si určuje sám algoritmus podle analýzy konkrétního projektu), může být (a nemusí) k dispozici tip na nejlepší opravu hledaného dotazu.

Nápovědu získáme voláním metody `getDidYouMean()` nad `SearchResult`. Výstupem je buď string (lepší dotaz pro hledání), nebo null.

Nejlepší opravu hledaného dotazu získává samo vyhledávací jádro na základě pokročilé analýzy vyhledávání v rámci každého projektu zvlášť pomocí metod **strojového učení**. S každým vyhledáváním se automaticky ukládají statistiky o hledaném dotazu, počtu výsledků a další signály, které se v případě potřeby zpětně analyzují.

Získávání nápovědy je přirozené a nelze jednoduše ovlivnit. Vyhledávací jádro se snaží o maximální objektivitu a uživatelům nabízet slova, která hledají ostatní a vrácí co nejvyšší počet relevantních výsledků podle aktuálního kontextu. Interně se používají složité matematické funkce, které na základě zkušeností ze všech projektů neustále vylepšujeme.

Bodování výsledků
-----------------

Při vyhledávání se nejprve sestaví seznam kandidátů na výsledky vyhledávání. Tyto výsledky se jednotlivě projdou hodnotícím algoritmem, který na základě různých signálů, jako je právě vyhledávaný dotaz, poslední historie uživatele, jazyk, fyzické umístění, obsah entit a jejich typ provede automatické "relativní" ohodnocení v intervalu `0` - `512` (výsledkem je vždy `int`).

Podle bodového hodnocení jsou výsledky automaticky řazeny.

Bodovací algoritmus lze přepsat implementací rozhraní `IScoreCalculator` a jeho přepsání v DIC kontejneru.