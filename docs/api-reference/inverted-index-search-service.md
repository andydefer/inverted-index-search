# InvertedIndexSearchService - Référence Technique

## Description

Service de recherche principal pour l'index inversé, fournissant des méthodes pratiques pour les opérations booléennes courantes (AND, OR, NOT, XOR), l'évaluation d'expressions complexes et l'accès au QueryBuilder fluent.

## Hiérarchie / Implémentations

```
InvertedIndexSearchServiceInterface
    └── InvertedIndexSearchService (final)
```

## Rôle principal

Fournit une API unifiée et intuitive pour effectuer des recherches dans un index inversé. Le service encapsule les opérations ensemblistes et délègue l'évaluation des expressions complexes à `InvertedIndexExpressionEvaluator`. Il agit comme point d'entrée principal pour les fonctionnalités de recherche.

## Installation

Ce service est inclus dans le package `andydefer/inverted-index-search`. Aucune installation supplémentaire n'est requise.

```bash
composer require andydefer/inverted-index-search
```

## API / Méthodes publiques

### `and(array $tokens): StringTypedCollection`

Recherche les documents contenant TOUS les tokens (opérateur AND).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$tokens` | `array<string>` | Liste des tokens à rechercher |

**Retourne :** `StringTypedCollection` - IDs des documents contenant tous les tokens

**Exemple :**
```php
$results = $search->and(['php', 'laravel']);
// Retourne les documents contenant 'php' ET 'laravel'
```

---

### `or(array $tokens): StringTypedCollection`

Recherche les documents contenant AU MOINS UN des tokens (opérateur OR).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$tokens` | `array<string>` | Liste des tokens à rechercher |

**Retourne :** `StringTypedCollection` - IDs des documents contenant au moins un token

**Exemple :**
```php
$results = $search->or(['php', 'python']);
// Retourne les documents contenant 'php' OU 'python'
```

---

### `not(string $include, string $exclude): StringTypedCollection`

Recherche les documents contenant le premier token mais PAS le second.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$include` | `string` | Token à inclure |
| `$exclude` | `string` | Token à exclure |

**Retourne :** `StringTypedCollection` - IDs des documents contenant `$include` mais pas `$exclude`

**Exceptions :** `InvalidArgumentException` - Si les tokens sont vides

**Exemple :**
```php
$results = $search->not('php', 'python');
// Retourne les documents contenant 'php' mais PAS 'python'
```

---

### `xor(string $term1, string $term2): StringTypedCollection`

Recherche les documents contenant EXACTEMENT UN des deux tokens (XOR).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$term1` | `string` | Premier token |
| `$term2` | `string` | Deuxième token |

**Retourne :** `StringTypedCollection` - IDs des documents contenant exactement un des tokens

**Exceptions :** `InvalidArgumentException` - Si les tokens sont vides

**Exemple :**
```php
$results = $search->xor('php', 'python');
// Retourne les documents contenant 'php' OU 'python' mais PAS les deux
```

---

### `expression(string $expression): StringTypedCollection`

Évalue une expression booléenne complexe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$expression` | `string` | Expression booléenne à évaluer |

**Retourne :** `StringTypedCollection` - IDs des documents correspondants

**Exceptions :** 
- `InvalidArgumentException` - Si l'expression est syntaxiquement invalide
- `RuntimeException` - Si l'évaluation échoue

**Exemple :**
```php
$results = $search->expression('(php OR python) AND laravel');
// Retourne les documents contenant ('php' OU 'python') ET 'laravel'
```

---

### `andWithLimit(array $tokens, int $limit = 10): StringTypedCollection`

Recherche AND avec limitation du nombre de résultats.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$tokens` | `array<string>` | Liste des tokens à rechercher |
| `$limit` | `int` | Nombre maximum de résultats (défaut: 10) |

**Retourne :** `StringTypedCollection` - IDs des documents (limités)

**Exemple :**
```php
$results = $search->andWithLimit(['php'], 5);
// Retourne au maximum 5 documents contenant 'php'
```

---

### `orWithLimit(array $tokens, int $limit = 10): StringTypedCollection`

Recherche OR avec limitation du nombre de résultats.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$tokens` | `array<string>` | Liste des tokens à rechercher |
| `$limit` | `int` | Nombre maximum de résultats (défaut: 10) |

**Retourne :** `StringTypedCollection` - IDs des documents (limités)

**Exemple :**
```php
$results = $search->orWithLimit(['php', 'python'], 5);
// Retourne au maximum 5 documents contenant 'php' OU 'python'
```

---

### `query(): InvertedIndexQueryBuilderInterface`

Retourne un QueryBuilder fluent pour construire des requêtes complexes.

**Retourne :** `InvertedIndexQueryBuilderInterface` - Instance du QueryBuilder

**Exemple :**
```php
$results = $search->query()
    ->where('php')
    ->whereGroup(function($q) {
        $q->where('laravel')->orWhere('vuejs');
    })
    ->whereNot('python')
    ->get();
```

## Cas d'utilisation

### Cas 1 : Recherche multi-critères simple

**Problème :** Trouver des articles de blog sur PHP et Laravel.

```php
<?php

$results = $search->and(['php', 'laravel']);
// Retourne les articles contenant les deux tags
```

### Cas 2 : Recherche avec exclusions

**Problème :** Trouver des projets PHP qui n'utilisent pas Python.

```php
<?php

$results = $search->not('php', 'python');
// Retourne les projets PHP sans Python
```

### Cas 3 : Recherche alternative

**Problème :** Trouver des documents sur PHP ou Python.

```php
<?php

$results = $search->or(['php', 'python']);
// Retourne les documents sur PHP OU Python
```

### Cas 4 : Expression complexe pour moteur de recherche

**Problème :** Implémenter une recherche avancée avec combinaisons logiques.

```php
<?php

class BlogSearch
{
    public function search(string $query): StringTypedCollection
    {
        // Construire une expression à partir de la requête utilisateur
        $expression = $this->buildExpression($query);
        return $this->search->expression($expression);
    }
    
    private function buildExpression(string $query): string
    {
        // Exemple : "php AND (laravel OR vuejs) NOT python"
        return $query;
    }
}

// Utilisation
$search = new BlogSearch($searchService);
$results = $search->search('php AND (laravel OR vuejs) NOT python');
```

### Cas 5 : Pagination des résultats

**Problème :** Afficher les résultats de recherche paginés.

```php
<?php

class PaginatedSearch
{
    public function search(array $tokens, int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        $limit = $perPage;
        
        // Récupérer tous les résultats
        $allResults = $this->search->and($tokens);
        $allIds = $allResults->toArray();
        
        // Paginer
        $paginatedIds = array_slice($allIds, $offset, $limit);
        
        return [
            'results' => StringTypedCollection::from($paginatedIds),
            'total' => count($allIds),
            'page' => $page,
            'perPage' => $perPage,
        ];
    }
}

// Utilisation
$paginated = new PaginatedSearch($search);
$page1 = $paginated->search(['php'], 1, 10);
$page2 = $paginated->search(['php'], 2, 10);
```

## Flux d'exécution

```
Méthode appelée (and, or, not, xor, expression)
    ↓
Validation des tokens (empty, count)
    ↓
Si expression → délégation à l'évaluateur
    ↓
Si and/or → applyOperator()
    ↓
Recherche de chaque token dans l'index (index->search())
    ↓
Opérations ensemblistes :
    - AND → array_intersect()
    - OR → array_merge() + array_unique()
    - NOT → array_diff()
    - XOR → array_diff() des deux côtés
    ↓
StringTypedCollection (résultats)
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Tokens vides pour AND/OR | (aucune) | Retourne une collection vide |
| NOT/XOR sans exactement 2 tokens | `InvalidArgumentException` | `{operator} operator requires exactly 2 tokens, {count} given` |
| Expression invalide | `InvalidArgumentException` | Transmise par l'évaluateur |
| Évaluation RPN échoue | `RuntimeException` | Transmise par l'évaluateur |
| Token invalide dans expression | `InvalidArgumentException` | `Invalid token: "{token}"` |

## Intégration

### Avec InvertedIndex

Le service dépend de `InvertedIndexInterface` pour effectuer les recherches.

```php
<?php

use AndyDefer\AlgoKIT\Algorithms\InvertedIndex;
use AndyDefer\InvertedIndexSearch\Services\InvertedIndexSearchService;

$index = new InvertedIndex($storage, 'my_index');
$evaluator = new InvertedIndexExpressionEvaluator($index);
$search = new InvertedIndexSearchService($index, $evaluator);
```

### Avec InvertedIndexExpressionEvaluator

L'évaluateur est injecté dans le service pour gérer les expressions complexes.

```php
<?php

$evaluator = new InvertedIndexExpressionEvaluator($index);
$search = new InvertedIndexSearchService($index, $evaluator);
```

### Avec InvertedIndexQueryBuilder

Le service crée une instance du QueryBuilder sur demande (lazy loading).

```php
<?php

$builder = $search->query();
// Retourne une instance de InvertedIndexQueryBuilder
```

### Avec StringTypedCollection

Toutes les méthodes retournent une `StringTypedCollection` pour un typage fort.

```php
<?php

$results = $search->and(['php', 'laravel']);
foreach ($results as $docId) {
    echo $docId; // string
}
```

## Performance

### Complexité algorithmique

- **AND** : O(n × m) où n est le nombre de tokens et m la taille moyenne des résultats
- **OR** : O(n × m) avec fusion et déduplication
- **NOT/XOR** : O(a + b) où a et b sont les tailles des deux ensembles
- **Expression** : O(n × m) selon la complexité de l'expression

### Optimisations

- **Recherche en cache** : Chaque token est recherché une seule fois dans l'index
- **Opérations natives** : Utilisation de `array_intersect`, `array_merge`, `array_diff` optimisées en C
- **Lazy loading** : Le QueryBuilder n'est instancié que si nécessaire
- **Collections typées** : `StringTypedCollection` pour des opérations efficaces

### Recommandations

- **Utiliser `andWithLimit`/`orWithLimit`** : Pour les requêtes paginées
- **Préférer le QueryBuilder** : Pour les requêtes complexes avec groupes
- **Valider les tokens** : Éviter les tokens vides ou invalides
- **Limiter les expressions** : Éviter les expressions avec plus de 20 opérateurs

## Compatibilité

| Version PHP | Support | Notes |
|-------------|---------|-------|
| PHP 8.4 | ✅ Complet | Typed properties, match expression |
| PHP 8.3 | ✅ Complet | Support total |
| PHP 8.2 | ✅ Complet | Support total |
| PHP 8.1 | ✅ Complet | Version minimale requise |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\InvertedIndex;
use AndyDefer\AlgoKIT\Records\InvertedIndexRecord;
use AndyDefer\InvertedIndexSearch\Services\InvertedIndexExpressionEvaluator;
use AndyDefer\InvertedIndexSearch\Services\InvertedIndexSearchService;
use AndyDefer\StorageKit\Storage\MemoryStorage;

// Initialisation
$storage = new MemoryStorage();
$index = new InvertedIndex($storage, 'library');

// Indexation
$books = [
    ['id' => 'book_1', 'tags' => ['php', 'laravel', 'web']],
    ['id' => 'book_2', 'tags' => ['php', 'python', 'data']],
    ['id' => 'book_3', 'tags' => ['php', 'laravel', 'vuejs']],
    ['id' => 'book_4', 'tags' => ['python', 'django', 'web']],
    ['id' => 'book_5', 'tags' => ['php', 'laravel', 'api']],
    ['id' => 'book_6', 'tags' => ['javascript', 'vuejs', 'frontend']],
];

foreach ($books as $book) {
    $index->add(InvertedIndexRecord::from([
        'document_id' => $book['id'],
        'tokens' => $book['tags'],
    ]));
}

// Service de recherche
$evaluator = new InvertedIndexExpressionEvaluator($index);
$search = new InvertedIndexSearchService($index, $evaluator);

// 1. AND simple
$results1 = $search->and(['php', 'laravel']);
echo "Livres PHP ET Laravel : " . implode(', ', $results1->toArray()) . "\n";
// Sortie : book_1, book_3, book_5

// 2. OR simple
$results2 = $search->or(['php', 'python']);
echo "Livres PHP OU Python : " . implode(', ', $results2->toArray()) . "\n";
// Sortie : book_1, book_2, book_3, book_4, book_5

// 3. NOT (exclusion)
$results3 = $search->not('php', 'python');
echo "Livres PHP sans Python : " . implode(', ', $results3->toArray()) . "\n";
// Sortie : book_1, book_3, book_5

// 4. XOR (exclusif)
$results4 = $search->xor('php', 'python');
echo "Livres PHP OU Python (pas les deux) : " . implode(', ', $results4->toArray()) . "\n";
// Sortie : book_1, book_3, book_4, book_5

// 5. Expression complexe
$results5 = $search->expression('(php AND laravel) OR (python AND django)');
echo "Livres (PHP ET Laravel) OU (Python ET Django) : " . implode(', ', $results5->toArray()) . "\n";
// Sortie : book_1, book_3, book_4, book_5

// 6. Avec limite
$results6 = $search->andWithLimit(['php'], 3);
echo "Livres PHP (limité à 3) : " . implode(', ', $results6->toArray()) . "\n";
// Sortie : book_1, book_2, book_3 (ou book_5 selon l'ordre)

// 7. QueryBuilder fluent
$results7 = $search->query()
    ->where('php')
    ->whereGroup(function($q) {
        $q->where('laravel')
          ->orWhere('vuejs');
    })
    ->whereNot('python')
    ->get();
echo "QueryBuilder : " . implode(', ', $results7->toArray()) . "\n";
// Sortie : book_1, book_3, book_5
```

## Voir aussi

- `InvertedIndexExpressionEvaluator` - Évaluateur d'expressions booléennes
- `InvertedIndexQueryBuilder` - Constructeur de requêtes fluent
- `InvertedIndexOperator` - Énumération des opérateurs booléens
- `StringTypedCollection` - Collection typée pour les résultats
- `InvertedIndex` - Structure de données sous-jacente