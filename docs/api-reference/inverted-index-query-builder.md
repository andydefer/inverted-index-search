# InvertedIndexQueryBuilder - Référence Technique

## Description

Constructeur de requêtes fluent pour l'index inversé, permettant de construire des expressions booléennes complexes sans manipulation manuelle de chaînes de caractères.

## Hiérarchie / Implémentations

```
InvertedIndexQueryBuilderInterface
    └── InvertedIndexQueryBuilder (final)
```

## Rôle principal

Fournit une interface fluide pour construire progressivement des requêtes booléennes. Le builder accumule les conditions (AND, OR, NOT) et les groupes imbriqués, puis génère automatiquement une expression évaluable par `InvertedIndexExpressionEvaluator`.

## Installation

Ce service est inclus dans le package `andydefer/inverted-index-search`. Aucune installation supplémentaire n'est requise.

```bash
composer require andydefer/inverted-index-search
```

## API / Méthodes publiques

### `where(string $token): self`

Ajoute une condition AND. Les documents doivent contenir ce token.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Token à inclure dans la recherche |

**Retourne :** `self` - Instance du builder pour le chaînage

**Exemple :**
```php
$builder->where('php')->where('laravel');
// Équivalent à : 'php AND laravel'
```

---

### `orWhere(string $token): self`

Ajoute une condition OR. Les documents doivent contenir au moins un des tokens.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Token à inclure dans la recherche |

**Retourne :** `self` - Instance du builder pour le chaînage

**Exemple :**
```php
$builder->where('php')->orWhere('python');
// Équivalent à : 'php OR python'
```

---

### `whereNot(string $token): self`

Ajoute une condition d'exclusion AND NOT. Les documents ne doivent pas contenir ce token.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Token à exclure de la recherche |

**Retourne :** `self` - Instance du builder pour le chaînage

**Exemple :**
```php
$builder->where('php')->whereNot('python');
// Équivalent à : 'php AND (NOT python)'
```

---

### `whereGroup(callable $callback): self`

Ajoute un groupe de conditions imbriqué avec l'opérateur AND.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$callback` | `callable(InvertedIndexQueryBuilderInterface): void` | Fonction recevant un builder pour construire le groupe |

**Retourne :** `self` - Instance du builder pour le chaînage

**Exemple :**
```php
$builder->where('php')->whereGroup(function($q) {
    $q->where('laravel')->orWhere('vuejs');
});
// Équivalent à : 'php AND (laravel OR vuejs)'
```

---

### `orWhereGroup(callable $callback): self`

Ajoute un groupe de conditions imbriqué avec l'opérateur OR.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$callback` | `callable(InvertedIndexQueryBuilderInterface): void` | Fonction recevant un builder pour construire le groupe |

**Retourne :** `self` - Instance du builder pour le chaînage

**Exemple :**
```php
$builder->orWhereGroup(function($q) {
    $q->where('php')->where('laravel');
});
// Équivalent à : 'OR (php AND laravel)' 
// (utilisé avec d'autres conditions pour créer une union)
```

---

### `get(): StringTypedCollection`

Exécute la requête et retourne les résultats.

**Retourne :** `StringTypedCollection` - Collection des IDs des documents correspondants

**Exceptions :** 
- `InvalidArgumentException` - Si l'expression générée est invalide
- `RuntimeException` - Si l'évaluation échoue

**Exemple :**
```php
$results = $builder->where('php')->get();
// Retourne les documents contenant 'php'
```

---

### `reset(): self`

Réinitialise le builder à son état initial (conditions vidées).

**Retourne :** `self` - Instance du builder pour le chaînage

**Exemple :**
```php
$builder->where('php')->reset()->where('python')->get();
// Recherche uniquement 'python', ignore 'php'
```

---

### `toExpression(): string`

Convertit les conditions actuelles en une chaîne d'expression booléenne.

**Retourne :** `string` - Expression booléenne générée

**Exemple :**
```php
$expression = $builder
    ->where('php')
    ->whereGroup(function($q) {
        $q->where('laravel')->orWhere('vuejs');
    })
    ->toExpression();
// Retourne : 'php AND (laravel OR vuejs)'
```

## Cas d'utilisation

### Cas 1 : Recherche avec exclusion

**Problème :** Trouver les documents PHP qui ne parlent pas de Python.

```php
<?php

$results = $queryBuilder
    ->where('php')
    ->whereNot('python')
    ->get();
// Équivalent à : 'php AND (NOT python)'
```

### Cas 2 : Recherche avec conditions alternatives

**Problème :** Trouver des documents PHP contenant soit Laravel, soit VueJS.

```php
<?php

$results = $queryBuilder
    ->where('php')
    ->whereGroup(function($q) {
        $q->where('laravel')
          ->orWhere('vuejs');
    })
    ->get();
// Équivalent à : 'php AND (laravel OR vuejs)'
```

### Cas 3 : Requête dynamique avec groupes imbriqués

**Problème :** Construire une requête complexe où l'utilisateur peut combiner plusieurs critères.

```php
<?php

class DocumentSearch
{
    public function search(array $filters): StringTypedCollection
    {
        $builder = $this->queryBuilder;
        
        if (!empty($filters['must_have'])) {
            foreach ($filters['must_have'] as $token) {
                $builder->where($token);
            }
        }
        
        if (!empty($filters['should_have'])) {
            $builder->whereGroup(function($q) use ($filters) {
                foreach ($filters['should_have'] as $token) {
                    $q->orWhere($token);
                }
            });
        }
        
        if (!empty($filters['must_not_have'])) {
            foreach ($filters['must_not_have'] as $token) {
                $builder->whereNot($token);
            }
        }
        
        return $builder->get();
    }
}

// Utilisation
$search = new DocumentSearch($queryBuilder);
$results = $search->search([
    'must_have' => ['php'],
    'should_have' => ['laravel', 'vuejs'],
    'must_not_have' => ['python'],
]);
// Génère : 'php AND (laravel OR vuejs) AND (NOT python)'
```

### Cas 4 : Requêtes imbriquées complexes

**Problème :** Rechercher des documents ayant soit (PHP ET Laravel) OU soit (Python ET Django).

```php
<?php

$results = $queryBuilder
    ->whereGroup(function($q) {
        $q->where('php')
          ->where('laravel');
    })
    ->orWhereGroup(function($q) {
        $q->where('python')
          ->where('django');
    })
    ->get();
// Équivalent à : '(php AND laravel) OR (python AND django)'
```

## Flux d'exécution

```
Conditions ajoutées via méthodes fluent
    ↓
Accumulation dans $conditions
    ↓
Appel de get()
    ↓
toExpression() → Génération de l'expression
    ↓
evaluator->evaluate(expression)
    ↓
StringTypedCollection (résultats)
```

### Détail du flux :

1. **Construction** : Les conditions sont ajoutées via les méthodes `where()`, `orWhere()`, `whereNot()`, etc.

2. **Stockage** : Chaque condition est stockée dans `$conditions` avec son type et son opérateur.

3. **Génération** : `toExpression()` parcourt récursivement les conditions :
   - Les tokens simples deviennent des termes
   - Les `whereNot` deviennent `(NOT token)`
   - Les groupes créent des sous-builders pour générer des sous-expressions entre parenthèses

4. **Évaluation** : L'expression est passée à l'évaluateur qui retourne les résultats.

5. **Réinitialisation** : `reset()` vide le tableau de conditions.

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Expression invalide générée | `InvalidArgumentException` | Transmise par l'évaluateur |
| Évaluation RPN échoue | `RuntimeException` | Transmise par l'évaluateur |
| Erreur de syntaxe dans l'expression | `InvalidArgumentException` | `Invalid token: "{token}"` |

## Intégration

### Avec InvertedIndexExpressionEvaluator

Le builder délègue l'évaluation à l'`InvertedIndexExpressionEvaluator`.

```php
<?php

use AndyDefer\InvertedIndexSearch\Services\InvertedIndexQueryBuilder;
use AndyDefer\InvertedIndexSearch\Services\InvertedIndexExpressionEvaluator;

$evaluator = new InvertedIndexExpressionEvaluator($index);
$builder = new InvertedIndexQueryBuilder($index, $evaluator);
```

### Avec InvertedIndexSearchService

Le service de recherche expose directement le builder via la méthode `query()`.

```php
<?php

use AndyDefer\InvertedIndexSearch\Services\InvertedIndexSearchService;

$search = new InvertedIndexSearchService($index, $evaluator);
$results = $search->query()
    ->where('php')
    ->where('laravel')
    ->get();
```

### Création de builders imbriqués

Pour les groupes, le builder crée automatiquement de nouvelles instances du builder.

```php
<?php

// Dans whereGroup() ou orWhereGroup()
$subBuilder = new self($this->index, $this->evaluator);
$callback($subBuilder);
$subExpression = $subBuilder->toExpression();
```

## Performance

### Complexité algorithmique

- **Construction** : O(1) par condition ajoutée
- **Génération d'expression** : O(n) où n est le nombre de conditions
- **Évaluation** : Déléguée à l'évaluateur (voir sa documentation)

### Optimisations

- Les conditions sont stockées sous forme de tableau indexé
- La génération d'expression est récursive pour les groupes
- Les expressions vides retournent immédiatement une collection vide

### Recommandations

- **Réutiliser le builder** : Utiliser `reset()` plutôt que de recréer une instance
- **Limiter les groupes imbriqués** : Éviter plus de 5 niveaux d'imbrication pour la lisibilité
- **Utiliser `toExpression()`** : Pour déboguer ou logger les requêtes générées
- **Vérifier les conditions** : Toujours valider les tokens avant de les ajouter

## Compatibilité

| Version PHP | Support | Notes |
|-------------|---------|-------|
| PHP 8.4 | ✅ Complet | Typed properties, union types |
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
use AndyDefer\InvertedIndexSearch\Services\InvertedIndexQueryBuilder;
use AndyDefer\StorageKit\Storage\MemoryStorage;

// Initialisation
$storage = new MemoryStorage();
$index = new InvertedIndex($storage, 'blog_posts');

// Indexation des documents
$documents = [
    ['id' => 'post_1', 'tokens' => ['php', 'laravel', 'eloquent']],
    ['id' => 'post_2', 'tokens' => ['php', 'python', 'django']],
    ['id' => 'post_3', 'tokens' => ['php', 'laravel', 'vuejs']],
    ['id' => 'post_4', 'tokens' => ['python', 'django', 'drf']],
    ['id' => 'post_5', 'tokens' => ['php', 'laravel', 'api', 'rest']],
    ['id' => 'post_6', 'tokens' => ['javascript', 'vuejs', 'nuxt']],
];

foreach ($documents as $doc) {
    $index->add(InvertedIndexRecord::from([
        'document_id' => $doc['id'],
        'tokens' => $doc['tokens'],
    ]));
}

// Création du builder
$evaluator = new InvertedIndexExpressionEvaluator($index);
$builder = new InvertedIndexQueryBuilder($index, $evaluator);

// 1. Requête simple
$results1 = $builder
    ->where('php')
    ->where('laravel')
    ->get();
echo "PHP ET Laravel : " . implode(', ', $results1->toArray()) . "\n";
// Sortie : post_1, post_3, post_5

// 2. Requête avec exclusion
$builder->reset();
$results2 = $builder
    ->where('php')
    ->whereNot('python')
    ->get();
echo "PHP sans Python : " . implode(', ', $results2->toArray()) . "\n";
// Sortie : post_1, post_3, post_5

// 3. Requête avec groupe
$builder->reset();
$results3 = $builder
    ->where('php')
    ->whereGroup(function($q) {
        $q->where('laravel')
          ->orWhere('vuejs');
    })
    ->get();
echo "PHP ET (Laravel OU VueJS) : " . implode(', ', $results3->toArray()) . "\n";
// Sortie : post_1, post_3, post_5

// 4. Requête complexe avec groupes OR
$builder->reset();
$results4 = $builder
    ->whereGroup(function($q) {
        $q->where('php')
          ->where('laravel');
    })
    ->orWhereGroup(function($q) {
        $q->where('python')
          ->where('django');
    })
    ->get();
echo "(PHP ET Laravel) OU (Python ET Django) : " . implode(', ', $results4->toArray()) . "\n";
// Sortie : post_1, post_2, post_3, post_4, post_5

// 5. Génération d'expression
$builder->reset();
$expression = $builder
    ->where('php')
    ->whereGroup(function($q) {
        $q->where('laravel')
          ->orWhere('vuejs');
    })
    ->whereNot('python')
    ->toExpression();
echo "Expression générée : $expression\n";
// Sortie : php AND (laravel OR vuejs) AND (NOT python)

// 6. Réutilisation du builder
$builder->reset();
$results5 = $builder
    ->where('javascript')
    ->orWhere('vuejs')
    ->get();
echo "JavaScript OU VueJS : " . implode(', ', $results5->toArray()) . "\n";
// Sortie : post_3, post_6
```

## Voir aussi

- `InvertedIndexExpressionEvaluator` - Évaluateur d'expressions booléennes
- `InvertedIndexSearchService` - Service de recherche avec méthodes pratiques
- `InvertedIndexOperator` - Énumération des opérateurs booléens
- `StringTypedCollection` - Collection typée pour les résultats