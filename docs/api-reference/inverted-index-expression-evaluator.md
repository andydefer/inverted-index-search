# InvertedIndexExpressionEvaluator - Référence Technique

## Description

Évalue des expressions booléennes complexes pour effectuer des recherches dans un index inversé. Supporte les opérateurs AND, OR, NOT, XOR ainsi que les parenthèses pour le regroupement.

## Hiérarchie / Implémentations

```
InvertedIndexExpressionEvaluatorInterface
    └── InvertedIndexExpressionEvaluator (final)
```

## Rôle principal

Convertit une expression booléenne textuelle (ex: `'php AND (laravel OR python)'`) en une collection de documents correspondants. L'évaluateur utilise l'algorithme **Shunting Yard** pour transformer l'expression en notation polonaise inversée (RPN), puis l'évalue en utilisant les opérations ensemblistes sur les résultats de recherche.

## Installation

Ce service est inclus dans le package `andydefer/inverted-index-search`. Aucune installation supplémentaire n'est requise.

```bash
composer require andydefer/inverted-index-search
```

## API / Méthodes publiques

### `evaluate(string $expression): StringTypedCollection`

Évalue une expression booléenne et retourne les IDs des documents correspondants.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$expression` | `string` | Expression booléenne à évaluer |

**Retourne :** `StringTypedCollection` - Collection des IDs de documents correspondants

**Exceptions :** 
- `InvalidArgumentException` - Si l'expression est syntaxiquement invalide
- `RuntimeException` - Si l'évaluation échoue (erreur interne)

**Exemple :**
```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\InvertedIndex;
use AndyDefer\InvertedIndexSearch\Services\InvertedIndexExpressionEvaluator;

$index = new InvertedIndex($storage, 'docs');
$evaluator = new InvertedIndexExpressionEvaluator($index);

$results = $evaluator->evaluate('php AND (laravel OR vuejs)');
// Retourne les documents contenant 'php' ET ('laravel' OU 'vuejs')
```

## Cas d'utilisation

### Cas 1 : Recherche avec exclusion

**Problème :** Trouver tous les documents PHP qui ne sont pas liés à Python.

```php
<?php

$results = $evaluator->evaluate('php AND (NOT python)');
// Retourne les documents avec 'php' mais sans 'python'
```

### Cas 2 : Recherche avec conditions alternatives

**Problème :** Trouver des documents contenant soit "laravel" soit "vuejs", mais uniquement s'ils sont en PHP.

```php
<?php

$results = $evaluator->evaluate('php AND (laravel OR vuejs)');
// Retourne les documents PHP contenant laravel OU vuejs
```

### Cas 3 : Expression complexe imbriquée

**Problème :** Rechercher des documents qui sont soit en PHP et Laravel, soit en Python et Django.

```php
<?php

$results = $evaluator->evaluate('(php AND laravel) OR (python AND django)');
// Retourne les documents correspondant à l'une des deux conditions
```

## Flux d'exécution

```
Expression textuelle
    ↓
Tokenisation (découpage en tokens)
    ↓
Normalisation des tokens
    ↓
Gestion du NOT unaire
    ↓
Transformation Shunting Yard → RPN
    ↓
Évaluation RPN (opérations ensemblistes)
    ↓
StringTypedCollection (résultats)
```

### Détail des étapes :

1. **Tokenisation** : L'expression est parcourue caractère par caractère pour extraire les tokens (mots-clés, opérateurs, parenthèses).

2. **Normalisation** : Les tokens sont normalisés pour garantir la cohérence.

3. **Gestion du NOT** : L'opérateur NOT est préparé comme opérateur unaire.

4. **Shunting Yard** : L'expression infixe est convertie en notation polonaise inversée (RPN) en respectant les priorités des opérateurs.

5. **Évaluation RPN** : La RPN est évaluée en utilisant une pile :
   - Les tokens sont des opérandes (recherche dans l'index)
   - Les opérateurs effectuent des opérations ensemblistes (AND, OR, XOR)

6. **Résultat** : Une collection contenant les IDs des documents correspondants.

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Token invalide | `InvalidArgumentException` | `Invalid token: "{token}"` |
| Parenthèses déséquilibrées | `InvalidArgumentException` | `Mismatched parentheses` |
| Opérateur NOT sans opérande | `InvalidArgumentException` | `NOT operator requires at least 1 operand` |
| Opérateur binaire sans opérandes | `InvalidArgumentException` | `{operator} operator requires 2 operands` |
| Évaluation RPN invalide | `RuntimeException` | `Invalid expression: {count} results on stack` |

## Intégration

### Avec InvertedIndex

L'évaluateur dépend directement de `InvertedIndexInterface` pour effectuer les recherches.

```php
<?php

use AndyDefer\AlgoKIT\Algorithms\InvertedIndex;
use AndyDefer\InvertedIndexSearch\Services\InvertedIndexExpressionEvaluator;

$index = new InvertedIndex($storage, 'my_index');
$evaluator = new InvertedIndexExpressionEvaluator($index);
```

### Avec InvertedIndexSearchService

L'évaluateur est utilisé en interne par le service de recherche principal.

```php
<?php

use AndyDefer\InvertedIndexSearch\Services\InvertedIndexSearchService;

$search = new InvertedIndexSearchService($index, $evaluator);
$results = $search->expression('php AND laravel');
```

### Avec InvertedIndexQueryBuilder

Le QueryBuilder utilise l'évaluateur pour exécuter les requêtes construites.

```php
<?php

use AndyDefer\InvertedIndexSearch\Services\InvertedIndexQueryBuilder;

$builder = new InvertedIndexQueryBuilder($index, $evaluator);
$results = $builder->where('php')->where('laravel')->get();
```

## Performance

### Complexité algorithmique

- **Tokenisation** : O(n) où n est la longueur de l'expression
- **Shunting Yard** : O(n) avec une pile d'opérateurs
- **Évaluation RPN** : O(n × m) où m est le nombre de tokens uniques (recherche dans l'index)
- **Opérations ensemblistes** : O(a + b) pour AND/OR, où a et b sont les tailles des ensembles

### Optimisations

- Les recherches dans l'index sont effectuées une seule fois par token
- Les opérations ensemblistes utilisent les fonctions natives PHP (`array_intersect`, `array_merge`, `array_diff`)
- Les résultats sont mis en cache dans la pile RPN pendant l'évaluation

### Recommandations

- **Limiter les expressions** : Éviter les expressions avec plus de 20 opérateurs pour les gros index
- **Utiliser les parenthèses** : Clarifier la priorité des opérateurs pour éviter les ambiguïtés
- **Préférer le QueryBuilder** : Pour les requêtes dynamiques complexes

## Compatibilité

| Version PHP | Support | Notes |
|-------------|---------|-------|
| PHP 8.4 | ✅ Complet | Match expression, typed properties |
| PHP 8.3 | ✅ Complet | Support total |
| PHP 8.2 | ✅ Complet | Support total |
| PHP 8.1 | ✅ Complet | Support total (version minimale requise) |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\InvertedIndex;
use AndyDefer\AlgoKIT\Records\InvertedIndexRecord;
use AndyDefer\InvertedIndexSearch\Services\InvertedIndexExpressionEvaluator;
use AndyDefer\StorageKit\Storage\MemoryStorage;

// Initialisation de l'index
$storage = new MemoryStorage();
$index = new InvertedIndex($storage, 'blog_posts');

// Indexation de documents
$documents = [
    ['id' => 'post_1', 'tokens' => ['php', 'laravel', 'eloquent']],
    ['id' => 'post_2', 'tokens' => ['php', 'python', 'django']],
    ['id' => 'post_3', 'tokens' => ['php', 'laravel', 'vuejs']],
    ['id' => 'post_4', 'tokens' => ['python', 'django', 'drf']],
    ['id' => 'post_5', 'tokens' => ['php', 'laravel', 'api', 'rest']],
];

foreach ($documents as $doc) {
    $index->add(InvertedIndexRecord::from([
        'document_id' => $doc['id'],
        'tokens' => $doc['tokens'],
    ]));
}

// Création de l'évaluateur
$evaluator = new InvertedIndexExpressionEvaluator($index);

// Recherche 1 : Articles PHP avec Laravel ou VueJS
$results1 = $evaluator->evaluate('php AND (laravel OR vuejs)');
echo "Articles PHP avec Laravel ou VueJS : " . implode(', ', $results1->toArray()) . "\n";
// Sortie : post_1, post_3, post_5

// Recherche 2 : Articles PHP ET Django (aucun résultat)
$results2 = $evaluator->evaluate('php AND django');
echo "Articles PHP et Django : " . implode(', ', $results2->toArray()) . "\n";
// Sortie : (vide)

// Recherche 3 : Articles Python ET Django
$results3 = $evaluator->evaluate('python AND django');
echo "Articles Python et Django : " . implode(', ', $results3->toArray()) . "\n";
// Sortie : post_2, post_4

// Recherche 4 : Articles PHP NOT Django
$results4 = $evaluator->evaluate('php AND (NOT django)');
echo "Articles PHP sans Django : " . implode(', ', $results4->toArray()) . "\n";
// Sortie : post_1, post_3, post_5

// Recherche 5 : Expression complexe
$results5 = $evaluator->evaluate('(php AND laravel) OR (python AND django)');
echo "Articles (PHP ET Laravel) OU (Python ET Django) : " . implode(', ', $results5->toArray()) . "\n";
// Sortie : post_1, post_2, post_3, post_4, post_5
```

## Voir aussi

- `InvertedIndex` - Structure de données sous-jacente pour l'indexation
- `InvertedIndexSearchService` - Service de recherche avec méthodes pratiques
- `InvertedIndexQueryBuilder` - Constructeur de requêtes fluent
- `InvertedIndexOperator` - Énumération des opérateurs booléens
- `StringTypedCollection` - Collection typée pour les résultats