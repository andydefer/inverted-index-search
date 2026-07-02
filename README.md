# InvertedIndexSearch - Documentation complète

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Package](https://img.shields.io/badge/package-andydefer%2Finverted--index--search-blue)](https://packagist.org/packages/andydefer/inverted-index-search)

## 📖 Table des matières

1. [Introduction](#introduction)
2. [Installation](#installation)
3. [Architecture](#architecture)
4. [Composants principaux](#composants-principaux)
   - [InvertedIndexSearchService](#invertedindexsearchservice)
   - [InvertedIndexQueryBuilder](#invertedindexquerybuilder)
   - [InvertedIndexExpressionEvaluator](#invertedindexexpressionevaluator)
5. [Opérateurs booléens](#opérateurs-booléens)
6. [Cas d'usage réels](#cas-dusage-réels)
7. [Performance](#performance)
8. [API Reference](#api-reference)

---

## Introduction

**InvertedIndexSearch** est une bibliothèque PHP qui étend les capacités de l'index inversé d'AlgoKIT en fournissant un moteur de recherche complet avec opérateurs booléens. Elle permet d'effectuer des recherches plein texte complexes avec une syntaxe intuitive et des performances optimales.

### Philosophie

| Problème | Solution classique | Solution InvertedIndexSearch |
|----------|-------------------|------------------------------|
| Recherche plein texte | Scanner tous les documents (lent) | Index inversé (O(1) par terme) |
| Requêtes booléennes | Implémentation manuelle | Opérateurs AND, OR, NOT, XOR |
| Requêtes complexes | Construction manuelle d'expressions | QueryBuilder fluent |
| Expressions imbriquées | Parsing complexe | Évaluateur avec support des parenthèses |

### Les 3 composants clés

| Composant | Rôle | Utilisation |
|-----------|------|-------------|
| **SearchService** | API principale de recherche | Méthodes pratiques (and, or, not, xor) |
| **QueryBuilder** | Construction fluent de requêtes | Conditions et groupes imbriqués |
| **ExpressionEvaluator** | Évaluation d'expressions | Parsing et évaluation booléenne |

---

## Installation

```bash
composer require andydefer/inverted-index-search
```

### Prérequis

- PHP 8.1 ou supérieur
- `andydefer/algo-kit` ^0.4.0 (inclus automatiquement)
- `andydefer/domain-structures` (inclus automatiquement)

---

## Architecture

### Dépendances

```
InvertedIndexSearch
    ├── AlgoKIT (InvertedIndex)
    ├── DomainStructures (StringTypedCollection)
    └── StorageKit (StorageInterface)
```

### Structure des composants

```
InvertedIndexSearchService (API principale)
    ├── and() / or() / not() / xor()
    ├── expression()
    ├── andWithLimit() / orWithLimit()
    └── query() → QueryBuilder
         ↓
InvertedIndexQueryBuilder (Fluent)
    ├── where() / orWhere() / whereNot()
    ├── whereGroup() / orWhereGroup()
    ├── get() / reset() / toExpression()
         ↓
InvertedIndexExpressionEvaluator (Évaluateur)
    ├── tokenize()
    ├── shuntingYard() → RPN
    └── evaluateRPN()
```

---

## Composants principaux

### InvertedIndexSearchService

Service principal offrant une API intuitive pour les recherches booléennes.

**Méthodes principales :**

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `and(array $tokens)` | Tous les tokens doivent être présents | `$search->and(['php', 'laravel'])` |
| `or(array $tokens)` | Au moins un token doit être présent | `$search->or(['php', 'python'])` |
| `not(string $include, string $exclude)` | Inclure un token, exclure un autre | `$search->not('php', 'python')` |
| `xor(string $term1, string $term2)` | Exactement un des deux tokens | `$search->xor('php', 'python')` |
| `expression(string $expr)` | Expression booléenne complexe | `$search->expression('(php OR python) AND laravel')` |
| `andWithLimit(array $tokens, int $limit)` | AND avec limite | `$search->andWithLimit(['php'], 10)` |
| `orWithLimit(array $tokens, int $limit)` | OR avec limite | `$search->orWithLimit(['php'], 10)` |
| `query()` | Retourne le QueryBuilder | `$search->query()->where('php')->get()` |

**Exemple d'utilisation :**

```php
use AndyDefer\AlgoKIT\Algorithms\InvertedIndex;
use AndyDefer\InvertedIndexSearch\Services\InvertedIndexSearchService;
use AndyDefer\InvertedIndexSearch\Services\InvertedIndexExpressionEvaluator;
use AndyDefer\StorageKit\Storage\MemoryStorage;

$storage = new MemoryStorage();
$index = new InvertedIndex($storage, 'documents');
$evaluator = new InvertedIndexExpressionEvaluator($index);
$search = new InvertedIndexSearchService($index, $evaluator);

// Indexation des documents
$documents = [
    ['id' => 'doc1', 'tokens' => ['php', 'laravel', 'web']],
    ['id' => 'doc2', 'tokens' => ['php', 'python', 'data']],
    ['id' => 'doc3', 'tokens' => ['php', 'laravel', 'vuejs']],
    ['id' => 'doc4', 'tokens' => ['python', 'django', 'web']],
];

foreach ($documents as $doc) {
    $index->add(InvertedIndexRecord::from([
        'document_id' => $doc['id'],
        'tokens' => $doc['tokens'],
    ]));
}

// Recherches
$results1 = $search->and(['php', 'laravel']);
// → doc1, doc3

$results2 = $search->or(['php', 'python']);
// → doc1, doc2, doc3, doc4

$results3 = $search->not('php', 'python');
// → doc1, doc3

$results4 = $search->expression('(php AND laravel) OR (python AND django)');
// → doc1, doc3, doc4
```

### InvertedIndexQueryBuilder

Constructeur fluent pour des requêtes complexes.

**Méthodes :**

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `where(string $token)` | Condition AND | `->where('php')` |
| `orWhere(string $token)` | Condition OR | `->orWhere('python')` |
| `whereNot(string $token)` | Exclusion | `->whereNot('java')` |
| `whereGroup(callable $callback)` | Groupe AND | `->whereGroup(fn($q) => $q->where('laravel')->orWhere('vuejs'))` |
| `orWhereGroup(callable $callback)` | Groupe OR | `->orWhereGroup(fn($q) => $q->where('php')->where('laravel'))` |
| `get()` | Exécute la requête | `->get()` |
| `reset()` | Réinitialise le builder | `->reset()` |
| `toExpression()` | Génère l'expression | `->toExpression()` |

**Exemple d'utilisation :**

```php
// Requête : php AND (laravel OR vuejs) AND NOT python
$results = $search->query()
    ->where('php')
    ->whereGroup(function($q) {
        $q->where('laravel')
          ->orWhere('vuejs');
    })
    ->whereNot('python')
    ->get();

// Construction dynamique
$builder = $search->query();

if ($mustHave) {
    foreach ($mustHave as $token) {
        $builder->where($token);
    }
}

if ($shouldHave) {
    $builder->whereGroup(function($q) use ($shouldHave) {
        foreach ($shouldHave as $token) {
            $q->orWhere($token);
        }
    });
}

$results = $builder->get();
```

### InvertedIndexExpressionEvaluator

Évaluateur d'expressions booléennes avec support des parenthèses.

**Syntaxe supportée :**

| Opérateur | Description | Priorité | Exemple |
|-----------|-------------|----------|---------|
| `NOT` | Négation (unaire) | 4 | `NOT python` |
| `XOR` | Ou exclusif | 3 | `php XOR python` |
| `AND` | Et logique | 2 | `php AND laravel` |
| `OR` | Ou logique | 1 | `php OR python` |
| `()` | Regroupement | - | `(php OR python) AND laravel` |

**Exemple d'utilisation :**

```php
// Expression simple
$results = $evaluator->evaluate('php AND laravel');

// Expression avec NOT
$results = $evaluator->evaluate('php AND (NOT python)');

// Expression complexe imbriquée
$results = $evaluator->evaluate('(php AND laravel) OR (python AND django)');

// Expression avec plusieurs niveaux
$results = $evaluator->evaluate('php AND (laravel OR vuejs) AND (NOT python)');
```

---

## Opérateurs booléens

### AND (Et logique)

Documents contenant tous les tokens.

```php
// Syntaxe directe
$results = $search->and(['php', 'laravel']);

// Via expression
$results = $search->expression('php AND laravel');

// Via QueryBuilder
$results = $search->query()->where('php')->where('laravel')->get();
```

### OR (Ou logique)

Documents contenant au moins un token.

```php
// Syntaxe directe
$results = $search->or(['php', 'python']);

// Via expression
$results = $search->expression('php OR python');

// Via QueryBuilder
$results = $search->query()->where('php')->orWhere('python')->get();
```

### NOT (Négation)

Documents contenant le premier token mais pas le second.

```php
// Syntaxe directe
$results = $search->not('php', 'python');

// Via expression
$results = $search->expression('php AND (NOT python)');

// Via QueryBuilder
$results = $search->query()->where('php')->whereNot('python')->get();
```

### XOR (Ou exclusif)

Documents contenant exactement un des deux tokens.

```php
// Syntaxe directe
$results = $search->xor('php', 'python');

// Via expression
$results = $search->expression('php XOR python');

// Via QueryBuilder (via expression)
$results = $search->query()->expression('php XOR python')->get();
```

---

## Cas d'usage réels

### 1. Moteur de recherche de blog

**Problème :** Implémenter un moteur de recherche d'articles avec filtres par tags, catégories et exclusions.

```php
class BlogSearch
{
    private InvertedIndexSearchService $search;
    
    public function searchArticles(array $criteria): array
    {
        $builder = $this->search->query();
        
        // Tags obligatoires
        if (!empty($criteria['tags'])) {
            foreach ($criteria['tags'] as $tag) {
                $builder->where($tag);
            }
        }
        
        // Catégories (au moins une)
        if (!empty($criteria['categories'])) {
            $builder->whereGroup(function($q) use ($criteria) {
                foreach ($criteria['categories'] as $category) {
                    $q->orWhere($category);
                }
            });
        }
        
        // Exclusions
        if (!empty($criteria['exclude'])) {
            foreach ($criteria['exclude'] as $exclude) {
                $builder->whereNot($exclude);
            }
        }
        
        return $builder->get()->toArray();
    }
}

// Utilisation
$articles = $blogSearch->searchArticles([
    'tags' => ['php', 'laravel'],
    'categories' => ['web', 'api'],
    'exclude' => ['deprecated'],
]);
```

### 2. Recherche de produits e-commerce

**Problème :** Rechercher des produits avec filtres multiples et alternatives.

```php
class ProductSearch
{
    private InvertedIndexSearchService $search;
    
    public function search(array $filters): array
    {
        $builder = $this->search->query();
        
        // Marques (AND)
        if (!empty($filters['brands'])) {
            foreach ($filters['brands'] as $brand) {
                $builder->where($brand);
            }
        }
        
        // Couleurs (OR)
        if (!empty($filters['colors'])) {
            $builder->whereGroup(function($q) use ($filters) {
                foreach ($filters['colors'] as $color) {
                    $q->orWhere($color);
                }
            });
        }
        
        // Prix (facultatif)
        if (isset($filters['price_range'])) {
            $builder->where($filters['price_range']);
        }
        
        return $builder->get()->toArray();
    }
}

// Utilisation
$products = $productSearch->search([
    'brands' => ['nike', 'adidas'],
    'colors' => ['red', 'blue', 'black'],
    'price_range' => 'premium',
]);
```

### 3. Analyse de logs avec filtres

**Problème :** Analyser des logs d'application avec des filtres complexes.

```php
class LogAnalyzer
{
    private InvertedIndexSearchService $search;
    
    public function analyzeLogs(array $logs): array
    {
        $expression = 'error OR (warning AND php) NOT (debug OR trace)';
        
        $results = $this->search->expression($expression);
        
        $stats = [
            'total' => count($logs),
            'matching' => $results->count(),
            'log_ids' => $results->toArray(),
        ];
        
        return $stats;
    }
}

// Utilisation
$analyzer = new LogAnalyzer($search);
$stats = $analyzer->analyzeLogs($logs);
```

### 4. API de recherche dynamique

**Problème :** Exposer une API de recherche où les utilisateurs peuvent utiliser des opérateurs booléens.

```php
class SearchAPI
{
    private InvertedIndexSearchService $search;
    
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q');
        $limit = (int) $request->get('limit', 10);
        
        try {
            // L'utilisateur peut utiliser des opérateurs avancés
            $results = $this->search->expression($query);
            $limited = $results->take($limit);
            
            return response()->json([
                'success' => true,
                'results' => $limited->toArray(),
                'total' => $results->count(),
                'limit' => $limit,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid search expression',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

// Requêtes API acceptées
// GET /search?q=php AND laravel
// GET /search?q=(php OR python) AND web
// GET /search?q=php AND (NOT java)
// GET /search?q=php AND (laravel OR vuejs) NOT python
```

### 5. Système de recommandation de tags

**Problème :** Recommander des tags pertinents basés sur une combinaison de tags existants.

```php
class TagRecommender
{
    private InvertedIndexSearchService $search;
    
    public function recommendTags(array $existingTags, int $limit = 5): array
    {
        $builder = $this->search->query();
        
        // Tags existants
        foreach ($existingTags as $tag) {
            $builder->where($tag);
        }
        
        $results = $builder->get();
        
        // Extraire les tags associés
        $relatedTags = [];
        foreach ($results as $docId) {
            $doc = $this->getDocument($docId);
            $relatedTags = array_merge($relatedTags, $doc['tags']);
        }
        
        // Compter et trier les tags
        $counts = array_count_values($relatedTags);
        arsort($counts);
        
        // Filtrer les tags existants
        $recommended = array_diff(array_keys($counts), $existingTags);
        
        return array_slice($recommended, 0, $limit);
    }
}

// Utilisation
$recommendations = $recommender->recommendTags(['php', 'laravel']);
// → ['web', 'api', 'eloquent', 'vuejs']
```

---

## Performance

### Complexité algorithmique

| Opération | Complexité | Détails |
|-----------|------------|---------|
| AND (2 tokens) | O(a + b) | Intersection de deux ensembles |
| AND (n tokens) | O(n × m) | Intersection séquentielle |
| OR (n tokens) | O(n × m) | Union et déduplication |
| NOT | O(a + b) | Différence d'ensembles |
| XOR | O(a + b) | Différences symétriques |
| Expression | O(n × m) | Dépend de la complexité |

### Optimisations

- **Recherche en cache** : Chaque token est recherché une seule fois
- **Opérations natives** : Utilisation des fonctions PHP optimisées en C
- **Lazy loading** : QueryBuilder instancié à la demande
- **Collections typées** : `StringTypedCollection` pour des opérations efficaces
- **Pas de duplication** : Les résultats sont dédupliqués automatiquement

### Comparatif

| Structure | Mémoire | Performance | Précision |
|-----------|---------|-------------|-----------|
| InvertedIndexSearch | Moyenne | Rapide (O(1) par terme) | 100% |
| Recherche naïve | Élevée | Lent (O(n)) | 100% |
| Base de données SQL | Élevée | Moyen (indexes) | 100% |

---

## API Reference

### Services

- [InvertedIndexSearchService](docs/api-reference/services/search-service.md) - Service principal de recherche
- [InvertedIndexQueryBuilder](docs/api-reference/services/query-builder.md) - Constructeur fluent
- [InvertedIndexExpressionEvaluator](docs/api-reference/services/expression-evaluator.md) - Évaluateur d'expressions

### Interfaces

- `InvertedIndexSearchServiceInterface` - Interface du service de recherche
- `InvertedIndexQueryBuilderInterface` - Interface du QueryBuilder
- `InvertedIndexExpressionEvaluatorInterface` - Interface de l'évaluateur

### Enums

- `InvertedIndexOperator` - Opérateurs booléens (AND, OR, NOT, XOR)

### Collections

- `StringTypedCollection` - Collection de chaînes de caractères

---

## License

MIT © [Andy Defer](https://github.com/andydefer)