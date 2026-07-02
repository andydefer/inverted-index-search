# AlgoKIT - Documentation complète

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

## 📖 Table des matières

1. [Introduction](#introduction)
2. [Installation](#installation)
3. [Architecture](#architecture)
4. [Les structures de données](#les-structures-de-données)
   - [Trie - Autocomplétion](#trie---autocomplétion)
   - [BKTree - Correction orthographique](#bktree---correction-orthographique)
   - [BloomFilter - Test d'existence](#bloomfilter---test-dexistence)
   - [CountMinSketch - Comptage de fréquence](#countminsketch---comptage-de-fréquence)
   - [HyperLogLog - Comptage d'éléments uniques](#hyperloglog---comptage-déléments-uniques)
   - [TopK - Éléments les plus fréquents](#topk---éléments-les-plus-fréquents)
5. [Cas d'usage réels](#cas-dusage-réels)
6. [Persistance](#persistance)
7. [Performance](#performance)
8. [API Reference](#api-reference)

---

## Introduction

**AlgoKIT** est une bibliothèque PHP qui implémente des structures de données probabilistes et algorithmiques optimisées pour le traitement de données à grande échelle. Elle permet de résoudre des problèmes complexes (comptage de millions d'éléments, recherche en temps réel, analyse de flux) avec une consommation mémoire minimale.

### Philosophie

| Problème | Solution classique | Solution AlgoKIT |
|----------|-------------------|------------------|
| Compter 10M d'IP uniques | Array de 10M éléments (500MB) | HyperLogLog (64KB) |
| Suggestions en temps réel | Scanner tous les mots (lent) | Trie (O(1) par caractère) |
| Vérifier des URLs crawlées | Base de données (lente) | BloomFilter (O(k)) |
| Top 10 des recherches | Tri de millions de logs (lourd) | TopK (mémoire constante) |

### Les 6 structures clés

| Structure | Rôle | Complexité | Cas d'usage |
|-----------|------|------------|-------------|
| **Trie** | Autocomplétion | O(L) | Suggestions de recherche, dictionnaire |
| **BKTree** | Correction orthographique | O(n × log n) | "Vous avez voulu dire..." |
| **BloomFilter** | Test d'existence | O(k) | URLs crawlées, cache bloqué |
| **CountMinSketch** | Comptage de fréquence | O(d) | Analyse de logs, trending |
| **HyperLogLog** | Comptage d'éléments uniques | O(1) | Visiteurs uniques, distincts |
| **TopK** | Éléments les plus fréquents | O(k) | Classements, tendances |

---

## Installation

```bash
composer require andydefer/algokit
```

### Prérequis

- PHP 8.1 ou supérieur
- Extension `json` (optionnelle pour persistance)
- Extension `mbstring` (recommandée)

---

## Architecture

### StorageInterface

Toutes les structures utilisent une interface de stockage commune pour la persistance découplée.

```php
interface StorageInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value): void;
    public function delete(string $key): bool;
    public function exists(string $key): bool;
}
```

**Avantages :**
- Changez de moteur de stockage sans modifier le code métier
- Testez facilement avec un storage en mémoire
- Partagez les données entre plusieurs instances
- Persistez automatiquement les données

**Storages disponibles :**
- `MemoryStorage` : Stockage en mémoire (tests, développement)
- `CacheStorage` : Stockage avec cache (Redis, Memcached)
- `JsonlStorage` : Stockage dans des fichiers JSONL

### Le concept de contexte

Toutes les structures supportent le **contexte** pour isoler les données par catégorie :

```php
// Sans contexte → données globales
$trie->insert('laravel');

// Avec contexte → données isolées
$trie->insert('bonjour', 'french');
$trie->insert('hello', 'english');

// Recherche dans un contexte spécifique
$frenchWords = $trie->search('bon', 'french');  // ['bonjour']
$englishWords = $trie->search('hel', 'english'); // ['hello']
```

---

## Les structures de données

### Trie - Autocomplétion

**Description :** Structure arborescente où chaque nœud représente un caractère. Les mots partageant un préfixe commun partagent le même chemin, permettant des recherches de préfixes en temps O(L) où L est la longueur du préfixe.

**Théorie :** Le Trie est idéal pour l'autocomplétion car il évite de scanner tous les mots à chaque recherche. La complexité ne dépend que de la longueur du préfixe, pas du nombre total de mots.

**Utilisation typique :** Autocomplétion de recherche, suggestions en temps réel, dictionnaire.

```php
use AndyDefer\AlgoKIT\Algorithms\Trie;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;

$storage = new MemoryStorage();
$trie = new Trie($storage, 'search_autocomplete');

// Indexation du dictionnaire
$words = ['laravel', 'laragon', 'large', 'laptop', 'php', 'python', 'pandas'];
foreach ($words as $word) {
    $trie->insert($word);
}

// Recherche en temps réel
$query = 'la';
$suggestions = $trie->search($query, null, 5);

echo "Suggestions pour '$query' :\n";
foreach ($suggestions as $result) {
    echo "  • {$result->word}\n";
}
// Sortie :
// Suggestions pour 'la' :
//   • laravel
//   • laragon
//   • large
//   • laptop

// Avec contexte
$trie->insert('bonjour', 'french');
$trie->insert('bonsoir', 'french');
$trie->insert('hello', 'english');

$french = $trie->search('bon', 'french', 5);
foreach ($french as $result) {
    echo "🇫🇷 {$result->word}\n";
}
// Sortie :
// 🇫🇷 bonjour
// 🇫🇷 bonsoir
```

### BKTree - Correction orthographique

**Description :** Arbre de Burkhard-Keller qui organise les mots par distance de Levenshtein. Permet de trouver les mots les plus proches d'une requête avec une tolérance configurable.

**Théorie :** La distance de Levenshtein mesure le nombre minimal de caractères à insérer, supprimer ou remplacer pour transformer un mot en un autre. Le BKTree explore seulement les branches de l'arbre susceptibles de contenir des mots dans la tolérance donnée.

**Utilisation typique :** Correction des fautes de frappe, suggestions "Vous avez voulu dire...".

```php
use AndyDefer\AlgoKIT\Algorithms\BKTree;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;

$storage = new MemoryStorage();
$bkTree = new BKTree($storage, 'dictionary');

// Indexation du dictionnaire
$words = ['php', 'python', 'laravel', 'javascript', 'symfony', 'golang'];
foreach ($words as $word) {
    $bkTree->insert($word);
}

// Correction d'une faute de frappe
$typo = 'larvel';
$suggestions = $bkTree->search($typo, 2, 3);

echo "Suggestions pour '$typo' :\n";
foreach ($suggestions as $result) {
    echo "  • {$result->word} (distance: {$result->distance})\n";
}
// Sortie :
// Suggestions pour 'larvel' :
//   • laravel (distance: 1)
//   • javascript (distance: 6)
//   • symfony (distance: 6)

// Tolérance plus élevée
$typo = 'javscrit';
$suggestions = $bkTree->search($typo, 3, 2);
foreach ($suggestions as $result) {
    echo "  • {$result->word} (distance: {$result->distance})\n";
}
// Sortie :
//   • javascript (distance: 2)
//   • php (distance: 7)
```

### BloomFilter - Test d'existence

**Description :** Filtre probabiliste qui utilise un tableau de bits et plusieurs fonctions de hachage. L'insertion définit plusieurs bits à 1. Le test vérifie si tous les bits correspondants sont à 1.

**Théorie :** 
- ✅ **Pas de faux négatifs** : Si le test retourne `false`, l'élément n'est **certainement pas** dans l'ensemble
- ⚠️ **Faux positifs possibles** : Si le test retourne `true`, l'élément **probablement** dans l'ensemble
- La probabilité de faux positifs est contrôlée par la taille du filtre et le nombre de fonctions de hachage

**Utilisation typique :** Vérification d'URLs déjà crawlées, filtrage de spam, cache bloqué.

```php
use AndyDefer\AlgoKIT\Algorithms\BloomFilter;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;

$storage = new MemoryStorage();
$bloom = new BloomFilter($storage, 100000, 3, 'url_index');

// Indexation des URLs déjà crawlées
$urls = [
    'https://example.com/page1',
    'https://example.com/page2',
    'https://example.com/page3'
];

foreach ($urls as $url) {
    $bloom->insert($url);
}

// Web crawler - éviter de recrawler
function shouldCrawl(BloomFilter $bloom, string $url): bool {
    if ($bloom->exists($url)) {
        echo "⏭️  $url déjà crawlée\n";
        return false;
    }
    echo "🕷️  Crawl de $url\n";
    $bloom->insert($url);
    return true;
}

shouldCrawl($bloom, 'https://example.com/page1'); // ⏭️  déjà crawlée
shouldCrawl($bloom, 'https://example.com/page4'); // 🕷️  Crawl
shouldCrawl($bloom, 'https://example.com/page2'); // ⏭️  déjà crawlée

// Vérification par lot
use AndyDefer\AlgoKIT\Collections\BloomFilterCollection;
use AndyDefer\AlgoKIT\Records\BloomFilterRecord;

$collection = new BloomFilterCollection();
$collection->add(new BloomFilterRecord('https://example.com/page1'));
$collection->add(new BloomFilterRecord('https://example.com/page5'));

$results = $bloom->existsBatch($collection);
foreach ($results as $result) {
    echo $result->value . " : " . ($result->exists ? '✅ existe' : '❌ inexistant') . "\n";
}
```

### CountMinSketch - Comptage de fréquence

**Description :** Structure probabiliste utilisant une matrice de compteurs et plusieurs fonctions de hachage. Chaque insertion incrémente plusieurs compteurs. La fréquence estimée est le minimum des compteurs correspondants.

**Théorie :**
- ✅ **Jamais de sous-estimation** : La valeur estimée est toujours ≥ à la valeur réelle
- ⚠️ **Surestimation possible** : Les collisions de hachage peuvent gonfler les compteurs
- L'erreur est bornée par `(width / 2) × depth` avec une probabilité de `1 - e^(-depth)`

**Utilisation typique :** Analyse de logs, top des recherches, trending topics.

```php
use AndyDefer\AlgoKIT\Algorithms\CountMinSketch;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;

$storage = new MemoryStorage();
$cms = new CountMinSketch($storage, 10000, 5, 'search_frequencies');

// Tracker les recherches utilisateurs
$searches = [
    'php', 'laravel', 'php', 'python', 'php', 
    'laravel', 'php', 'javascript', 'php', 'golang'
];

foreach ($searches as $term) {
    $cms->add($term);
}

// Fréquences approximatives
echo "Fréquences approximatives :\n";
echo "  php: " . $cms->count('php') . "\n";          // ~5
echo "  laravel: " . $cms->count('laravel') . "\n";  // ~2
echo "  python: " . $cms->count('python') . "\n";    // ~1
echo "  ruby: " . $cms->count('ruby') . "\n";        // ~0

// Avec contexte (par site)
$cms->add('php', 'site_a');
$cms->add('php', 'site_a');
$cms->add('php', 'site_b');

echo "php sur site_a: " . $cms->count('php', 'site_a') . "\n"; // ~2
echo "php sur site_b: " . $cms->count('php', 'site_b') . "\n"; // ~1

// Opérations batch
use AndyDefer\AlgoKIT\Collections\CountMinSketchCollection;
use AndyDefer\AlgoKIT\Records\CountMinSketchRecord;

$batch = new CountMinSketchCollection();
$batch->add(new CountMinSketchRecord('php'));
$batch->add(new CountMinSketchRecord('php'));
$batch->add(new CountMinSketchRecord('laravel'));

$cms->addBatch($batch);
echo "php après batch: " . $cms->count('php') . "\n"; // ~7
```

### HyperLogLog - Comptage d'éléments uniques

**Description :** Algorithme qui estime le nombre d'éléments distincts en utilisant un tableau de registres. Chaque valeur est hachée, et le registre correspondant est mis à jour avec le nombre de zéros initiaux du hash.

**Théorie :** 
- L'algorithme observe la distribution des bits dans les hashs
- Plus il y a d'éléments uniques, plus il est probable d'observer des hashs avec de nombreux zéros initiaux
- La précision est contrôlée par le nombre de registres (2^precision)

**Erreur standard :** `1.04 / sqrt(2^precision)`
- `precision = 10` (1024 registres) → erreur ~3.2%
- `precision = 14` (16384 registres) → erreur ~0.8%
- `precision = 16` (65536 registres) → erreur ~0.4%

**Utilisation typique :** Visiteurs uniques, événements distincts, analyse de données massives.

```php
use AndyDefer\AlgoKIT\Algorithms\HyperLogLog;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;

$storage = new MemoryStorage();
$hll = new HyperLogLog($storage, 14, 'unique_visitors');

// Simuler un flux de visiteurs
$visitors = [
    'user_123', 'user_456', 'user_123', 'user_789', 
    'user_456', 'user_321', 'user_123', 'user_654'
];

foreach ($visitors as $userId) {
    $hll->add($userId);
}

$unique = $hll->count();
echo "Visiteurs uniques: $unique\n"; // ~5

// Par date (contexte)
$dates = ['2024-01-01', '2024-01-02'];

foreach ($visitors as $index => $userId) {
    $date = $dates[$index % 2];
    $hll->add($userId, $date);
}

echo "2024-01-01: " . $hll->count('2024-01-01') . " utilisateurs\n";
echo "2024-01-02: " . $hll->count('2024-01-02') . " utilisateurs\n";
echo "Total: " . $hll->count() . " utilisateurs\n";

// Opérations batch
use AndyDefer\AlgoKIT\Collections\HyperLogLogCollection;
use AndyDefer\AlgoKIT\Records\HyperLogLogRecord;

$batch = new HyperLogLogCollection();
$batch->add(new HyperLogLogRecord('user_999'));
$batch->add(new HyperLogLogRecord('user_888', '2024-01-03'));

$hll->addBatch($batch);

echo "Nouveau total: " . $hll->count() . "\n"; // ~6
```

### TopK - Éléments les plus fréquents

**Description :** Structure qui maintient les K éléments les plus fréquents dans un flux. Utilise un espace constant et un algorithme de remplacement du moins fréquent.

**Théorie :** 
- La structure conserve exactement K éléments
- À chaque nouvel élément, si la liste est pleine, l'élément le moins fréquent est remplacé si le nouvel élément est plus fréquent
- Idéal pour les flux où on ne peut pas stocker tous les éléments

**Utilisation typique :** Tendances, produits populaires, top des recherches.

```php
use AndyDefer\AlgoKIT\Algorithms\TopK;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;

$storage = new MemoryStorage();
$topK = new TopK($storage, 3, 'top_searches');

// Flux de recherches en temps réel
$searches = ['php', 'laravel', 'php', 'python', 'php', 'laravel', 'php', 'golang'];

foreach ($searches as $term) {
    $topK->add($term);
}

// Top 3 des recherches
echo "Top recherches :\n";
foreach ($topK->getTop() as $rank => $item) {
    echo "  #" . ($rank + 1) . " {$item->value}: {$item->count}\n";
}
// Sortie :
// Top recherches :
//   #1 php: 4
//   #2 laravel: 2
//   #3 python: 1

// Avec incréments plus importants
$topK->add('laravel', 5);
$topK->add('golang', 3);

echo "Après incréments :\n";
foreach ($topK->getTop() as $rank => $item) {
    echo "  #" . ($rank + 1) . " {$item->value}: {$item->count}\n";
}
// Sortie :
// Après incréments :
//   #1 laravel: 7
//   #2 php: 4
//   #3 golang: 3

// Opérations batch
use AndyDefer\AlgoKIT\Collections\TopKCollection;
use AndyDefer\AlgoKIT\Records\TopKRecord;

$batch = new TopKCollection();
$batch->add(new TopKRecord('php', 10));
$batch->add(new TopKRecord('javascript', 2));

$topK->addBatch($batch);

echo "Final :\n";
foreach ($topK->getTop() as $rank => $item) {
    echo "  #" . ($rank + 1) . " {$item->value}: {$item->count}\n";
}
// Sortie :
// Final :
//   #1 php: 14
//   #2 laravel: 7
//   #3 golang: 3
```

---

## Cas d'usage réels

### 1. Compteur d'IP uniques par jour

**Problème :** Compter le nombre d'IP uniques qui visitent un site web chaque jour, avec des millions de requêtes.

**Solution :** HyperLogLog avec contexte temporel. 64KB de mémoire suffisent pour compter des milliards d'IP uniques.

```php
use AndyDefer\AlgoKIT\Algorithms\HyperLogLog;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;
use AndyDefer\AlgoKIT\Collections\HyperLogLogCollection;
use AndyDefer\AlgoKIT\Records\HyperLogLogRecord;

class UniqueIPCounter
{
    private HyperLogLog $hll;
    
    public function __construct(HyperLogLog $hll)
    {
        $this->hll = $hll;
    }
    
    public function trackVisit(string $ip): void
    {
        $date = date('Y-m-d');
        $this->hll->add($ip, $date);
        $this->hll->add($ip); // Global
    }
    
    public function getUniqueIPs(string $date): int
    {
        return $this->hll->count($date);
    }
    
    public function getTotalUniqueIPs(): int
    {
        return $this->hll->count();
    }
    
    public function getDailyStats(array $dates): array
    {
        $collection = new HyperLogLogCollection();
        foreach ($dates as $date) {
            $collection->add(new HyperLogLogRecord('dummy', $date));
        }
        
        $results = $this->hll->countBatch($collection);
        $stats = [];
        foreach ($results as $result) {
            $stats[$result->context] = $result->count;
        }
        return $stats;
    }
}

// ============================================
// UTILISATION
// ============================================

$storage = new MemoryStorage();
$hll = new HyperLogLog($storage, 14, 'ip_counter');
$counter = new UniqueIPCounter($hll);

// Simuler des visites sur 3 jours
$visits = [
    '2024-01-01' => ['192.168.1.1', '192.168.1.2', '192.168.1.1', '192.168.1.3'],
    '2024-01-02' => ['192.168.1.1', '192.168.1.4', '192.168.1.5', '192.168.1.1'],
    '2024-01-03' => ['192.168.1.2', '192.168.1.3', '192.168.1.6']
];

foreach ($visits as $date => $ips) {
    foreach ($ips as $ip) {
        $counter->trackVisit($ip);
    }
}

echo "=== Statistiques IP uniques ===\n";
echo "2024-01-01: " . $counter->getUniqueIPs('2024-01-01') . " IP uniques\n";
echo "2024-01-02: " . $counter->getUniqueIPs('2024-01-02') . " IP uniques\n";
echo "2024-01-03: " . $counter->getUniqueIPs('2024-01-03') . " IP uniques\n";
echo "Total: " . $counter->getTotalUniqueIPs() . " IP uniques\n";

$stats = $counter->getDailyStats(['2024-01-01', '2024-01-02']);
echo "Batch: " . print_r($stats, true);
```

### 2. Système de recherche avec autocomplétion

**Problème :** Implémenter un système de recherche avec suggestions en temps réel et correction des fautes.

**Solution :** Combinaison de Trie (autocomplétion), BKTree (correction) et CountMinSketch (suivi des tendances).

```php
use AndyDefer\AlgoKIT\Algorithms\Trie;
use AndyDefer\AlgoKIT\Algorithms\BKTree;
use AndyDefer\AlgoKIT\Algorithms\CountMinSketch;
use AndyDefer\AlgoKIT\Algorithms\TopK;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;

class SearchEngine
{
    private Trie $trie;
    private BKTree $bkTree;
    private CountMinSketch $cms;
    private TopK $topK;
    
    public function __construct(
        Trie $trie,
        BKTree $bkTree,
        CountMinSketch $cms,
        TopK $topK
    ) {
        $this->trie = $trie;
        $this->bkTree = $bkTree;
        $this->cms = $cms;
        $this->topK = $topK;
    }
    
    public function indexTerm(string $term): void
    {
        $this->trie->insert(strtolower($term));
        $this->bkTree->insert(strtolower($term));
    }
    
    public function search(string $query, int $limit = 10): array
    {
        $query = strtolower(trim($query));
        $results = [];
        
        // 1. Autocomplétion (Trie)
        if (strlen($query) > 0) {
            $suggestions = $this->trie->search($query, null, $limit);
            foreach ($suggestions as $suggestion) {
                $results[] = [
                    'term' => $suggestion->word,
                    'type' => 'autocomplete',
                    'score' => $this->cms->count($suggestion->word) + 1
                ];
            }
            
            // 2. Correction orthographique (BKTree)
            if (count($results) < $limit) {
                $corrections = $this->bkTree->search($query, 2, $limit - count($results));
                foreach ($corrections as $correction) {
                    $results[] = [
                        'term' => $correction->word,
                        'type' => 'correction',
                        'distance' => $correction->distance,
                        'score' => $this->cms->count($correction->word) + 1
                    ];
                }
            }
        }
        
        // Tri par score décroissant
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($results, 0, $limit);
    }
    
    public function trackSearch(string $term): void
    {
        $term = strtolower(trim($term));
        $this->cms->add($term);
        $this->topK->add($term);
    }
    
    public function getPopularTerms(int $limit = 10): array
    {
        $top = $this->topK->getTop();
        $result = [];
        foreach ($top as $item) {
            $result[] = [
                'term' => $item->value,
                'count' => $item->count
            ];
        }
        return array_slice($result, 0, $limit);
    }
}

// ============================================
// UTILISATION
// ============================================

$storage = new MemoryStorage();

$searchEngine = new SearchEngine(
    new Trie($storage, 'search_trie'),
    new BKTree($storage, 'search_bktree'),
    new CountMinSketch($storage, 10000, 5, 'search_cms'),
    new TopK($storage, 10, 'search_topk')
);

// Indexation du dictionnaire
$terms = ['laravel', 'php', 'python', 'javascript', 'laragon', 'large', 'laptop'];
foreach ($terms as $term) {
    $searchEngine->indexTerm($term);
}

// Simuler des recherches
$searches = ['php', 'laravel', 'php', 'python', 'php', 'laravel', 'php', 'laptop'];
foreach ($searches as $search) {
    $searchEngine->trackSearch($search);
}

// Recherche avec autocomplétion
$query = 'la';
$results = $searchEngine->search($query, 5);
echo "Résultats pour '$query':\n";
foreach ($results as $result) {
    echo "- {$result['term']} (type: {$result['type']}, score: {$result['score']})\n";
}

// Recherche avec correction
$typo = 'larvel';
$results = $searchEngine->search($typo, 5);
echo "\nRésultats pour '$typo':\n";
foreach ($results as $result) {
    echo "- {$result['term']} (type: {$result['type']}, distance: " . ($result['distance'] ?? 'N/A') . ")\n";
}

// Top des recherches
$popular = $searchEngine->getPopularTerms(5);
echo "\nTop des recherches:\n";
foreach ($popular as $item) {
    echo "- {$item['term']}: {$item['count']}\n";
}
```

### 3. Analyse de logs en temps réel

**Problème :** Analyser les logs d'accès pour identifier les IPs les plus actives, les endpoints les plus sollicités, et les erreurs fréquentes.

**Solution :** Combinaison de CountMinSketch (fréquences), TopK (leaders) et HyperLogLog (IP uniques).

```php
use AndyDefer\AlgoKIT\Algorithms\CountMinSketch;
use AndyDefer\AlgoKIT\Algorithms\TopK;
use AndyDefer\AlgoKIT\Algorithms\HyperLogLog;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;

class LogAnalyzer
{
    private CountMinSketch $cms;
    private TopK $topK;
    private HyperLogLog $hll;
    
    public function __construct(
        CountMinSketch $cms,
        TopK $topK,
        HyperLogLog $hll
    ) {
        $this->cms = $cms;
        $this->topK = $topK;
        $this->hll = $hll;
    }
    
    public function processLog(array $log): void
    {
        $ip = $log['ip'] ?? 'unknown';
        $endpoint = $log['endpoint'] ?? '/';
        $status = $log['status'] ?? 200;
        $date = date('Y-m-d');
        
        // Fréquences
        $this->cms->add("ip:{$ip}");
        $this->cms->add("endpoint:{$endpoint}");
        $this->cms->add("status:{$status}");
        
        // Top K
        $this->topK->add("ip:{$ip}");
        $this->topK->add("endpoint:{$endpoint}");
        $this->topK->add("status:{$status}");
        
        // IP uniques par jour
        $this->hll->add($ip, "daily_{$date}");
        $this->hll->add($ip);
    }
    
    public function getTopIPs(int $limit = 10): array
    {
        $top = $this->topK->getTop();
        $ips = [];
        foreach ($top as $item) {
            if (str_starts_with($item->value, 'ip:')) {
                $ip = substr($item->value, 3);
                $ips[] = [
                    'ip' => $ip,
                    'requests' => $item->count,
                    'estimated' => $this->cms->count("ip:{$ip}")
                ];
            }
        }
        return array_slice($ips, 0, $limit);
    }
    
    public function getTopEndpoints(int $limit = 10): array
    {
        $top = $this->topK->getTop();
        $endpoints = [];
        foreach ($top as $item) {
            if (str_starts_with($item->value, 'endpoint:')) {
                $endpoint = substr($item->value, 9);
                $endpoints[] = [
                    'endpoint' => $endpoint,
                    'requests' => $item->count,
                    'estimated' => $this->cms->count("endpoint:{$endpoint}")
                ];
            }
        }
        return array_slice($endpoints, 0, $limit);
    }
    
    public function getTopStatuses(int $limit = 5): array
    {
        $top = $this->topK->getTop();
        $statuses = [];
        foreach ($top as $item) {
            if (str_starts_with($item->value, 'status:')) {
                $status = (int) substr($item->value, 7);
                $statuses[] = [
                    'status' => $status,
                    'count' => $item->count
                ];
            }
        }
        return array_slice($statuses, 0, $limit);
    }
    
    public function getUniqueIPsForDate(string $date): int
    {
        return $this->hll->count("daily_{$date}");
    }
    
    public function getTotalUniqueIPs(): int
    {
        return $this->hll->count();
    }
}

// ============================================
// UTILISATION
// ============================================

$storage = new MemoryStorage();

$analyzer = new LogAnalyzer(
    new CountMinSketch($storage, 50000, 5, 'log_cms'),
    new TopK($storage, 20, 'log_topk'),
    new HyperLogLog($storage, 14, 'log_hll')
);

// Simuler un flux de logs
$logs = [
    ['ip' => '192.168.1.1', 'endpoint' => '/api/users', 'status' => 200],
    ['ip' => '192.168.1.2', 'endpoint' => '/api/products', 'status' => 200],
    ['ip' => '192.168.1.1', 'endpoint' => '/api/users', 'status' => 200],
    ['ip' => '192.168.1.3', 'endpoint' => '/api/orders', 'status' => 404],
    ['ip' => '192.168.1.1', 'endpoint' => '/api/users', 'status' => 200],
    ['ip' => '192.168.1.2', 'endpoint' => '/api/products', 'status' => 200],
    ['ip' => '192.168.1.4', 'endpoint' => '/api/users', 'status' => 500],
    ['ip' => '192.168.1.1', 'endpoint' => '/api/orders', 'status' => 200],
];

foreach ($logs as $log) {
    $analyzer->processLog($log);
}

echo "=== TOP IPS ===\n";
foreach ($analyzer->getTopIPs(5) as $ip) {
    echo "- {$ip['ip']}: {$ip['requests']} requêtes\n";
}

echo "\n=== TOP ENDPOINTS ===\n";
foreach ($analyzer->getTopEndpoints(5) as $endpoint) {
    echo "- {$endpoint['endpoint']}: {$endpoint['requests']} requêtes\n";
}

echo "\n=== TOP STATUS ===\n";
foreach ($analyzer->getTopStatuses() as $status) {
    echo "- HTTP {$status['status']}: {$status['count']} occurrences\n";
}

echo "\n=== IP UNIQUES ===\n";
echo "Total: " . $analyzer->getTotalUniqueIPs() . " IP uniques\n";
echo "Aujourd'hui: " . $analyzer->getUniqueIPsForDate(date('Y-m-d')) . " IP uniques\n";
```

### 4. Détection de spam

**Problème :** Détecter les messages de spam en vérifiant si le contenu a déjà été vu ou contient des mots-clés suspects.

**Solution :** BloomFilter pour la détection rapide avec contrôle des faux positifs.

```php
use AndyDefer\AlgoKIT\Algorithms\BloomFilter;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;
use AndyDefer\AlgoKIT\Collections\BloomFilterCollection;
use AndyDefer\AlgoKIT\Records\BloomFilterRecord;

class SpamDetector
{
    private BloomFilter $bloom;
    
    public function __construct(BloomFilter $bloom)
    {
        $this->bloom = $bloom;
    }
    
    public function markAsSpam(string $content): void
    {
        $hash = md5($content);
        $this->bloom->insert($hash, 'spam');
        
        $words = explode(' ', strtolower($content));
        foreach ($words as $word) {
            if (strlen($word) > 3) {
                $this->bloom->insert($word, 'spam_keywords');
            }
        }
    }
    
    public function isSpam(string $content): bool
    {
        $hash = md5($content);
        
        if ($this->bloom->exists($hash, 'spam')) {
            return true;
        }
        
        $words = explode(' ', strtolower($content));
        $spamScore = 0;
        $totalWords = 0;
        
        foreach ($words as $word) {
            if (strlen($word) > 3) {
                $totalWords++;
                if ($this->bloom->exists($word, 'spam_keywords')) {
                    $spamScore++;
                }
            }
        }
        
        return $totalWords > 0 && ($spamScore / $totalWords) > 0.3;
    }
    
    public function isSpamBatch(array $messages): array
    {
        $collection = new BloomFilterCollection();
        foreach ($messages as $id => $content) {
            $hash = md5($content);
            $collection->add(new BloomFilterRecord($hash, 'spam'));
        }
        
        $results = $this->bloom->existsBatch($collection);
        $spam = [];
        foreach ($results as $result) {
            $spam[$result->value] = $result->exists;
        }
        return $spam;
    }
}

// ============================================
// UTILISATION
// ============================================

$storage = new MemoryStorage();
$bloom = new BloomFilter($storage, 100000, 3, 'spam_filter');
$detector = new SpamDetector($bloom);

// Entraînement avec des spams connus
$spams = [
    'Buy cheap viagra now!',
    'Get rich quick!!!',
    'FREE money making opportunity!',
    'Click here for guaranteed results'
];

foreach ($spams as $spam) {
    $detector->markAsSpam($spam);
}

// Tester des messages
$messages = [
    'Buy cheap viagra now!',
    'Hello, how are you today?',
    'Get rich quick!!!',
    'Meeting at 3pm tomorrow',
    'FREE money making opportunity! Click here'
];

echo "=== DÉTECTION DE SPAM ===\n";
foreach ($messages as $message) {
    $isSpam = $detector->isSpam($message);
    echo ($isSpam ? "[SPAM] " : "[HAM]  ") . $message . "\n";
}
```

### 5. Système de recommandation

**Problème :** Recommander des produits à un utilisateur en fonction de ses vues antérieures et de la popularité globale.

**Solution :** CountMinSketch (suivi des vues) + TopK (produits populaires) + filtrage par tags.

```php
use AndyDefer\AlgoKIT\Algorithms\CountMinSketch;
use AndyDefer\AlgoKIT\Algorithms\TopK;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;
use AndyDefer\AlgoKIT\Collections\CountMinSketchCollection;
use AndyDefer\AlgoKIT\Records\CountMinSketchRecord;

class RecommendationEngine
{
    private CountMinSketch $cms;
    private TopK $topK;
    private array $productCatalog = [];
    
    public function __construct(CountMinSketch $cms, TopK $topK)
    {
        $this->cms = $cms;
        $this->topK = $topK;
    }
    
    public function addProduct(string $id, string $name, array $tags): void
    {
        $this->productCatalog[$id] = [
            'name' => $name,
            'tags' => $tags
        ];
    }
    
    public function trackView(string $userId, string $productId): void
    {
        $key = "{$userId}:{$productId}";
        $this->cms->add($key);
        $this->topK->add($key);
    }
    
    public function trackViewBatch(array $views): void
    {
        $collection = new CountMinSketchCollection();
        foreach ($views as $view) {
            $key = "{$view['user_id']}:{$view['product_id']}";
            $collection->add(new CountMinSketchRecord($key));
        }
        $this->cms->addBatch($collection);
    }
    
    public function getUserPreferences(string $userId, int $limit = 5): array
    {
        $top = $this->topK->getTop();
        $preferences = [];
        
        foreach ($top as $item) {
            if (str_starts_with($item->value, $userId . ':')) {
                $productId = explode(':', $item->value)[1];
                if (isset($this->productCatalog[$productId])) {
                    $preferences[] = [
                        'product_id' => $productId,
                        'product_name' => $this->productCatalog[$productId]['name'],
                        'views' => $item->count
                    ];
                }
                if (count($preferences) >= $limit) {
                    break;
                }
            }
        }
        return $preferences;
    }
    
    public function getRecommendations(string $userId, int $limit = 5): array
    {
        $preferences = $this->getUserPreferences($userId, 3);
        $tags = [];
        
        foreach ($preferences as $pref) {
            $tags = array_merge($tags, $this->productCatalog[$pref['product_id']]['tags']);
        }
        
        $tagCounts = array_count_values($tags);
        arsort($tagCounts);
        $topTags = array_slice(array_keys($tagCounts), 0, 3);
        
        $recommendations = [];
        foreach ($this->productCatalog as $id => $product) {
            if (array_intersect($product['tags'], $topTags)) {
                $recommendations[] = [
                    'product_id' => $id,
                    'product_name' => $product['name'],
                    'matching_tags' => array_intersect($product['tags'], $topTags)
                ];
            }
            if (count($recommendations) >= $limit) {
                break;
            }
        }
        return $recommendations;
    }
    
    public function getPopularProducts(int $limit = 10): array
    {
        $top = $this->topK->getTop();
        $result = [];
        
        foreach ($top as $item) {
            $parts = explode(':', $item->value);
            if (count($parts) === 2) {
                $productId = $parts[1];
                if (isset($this->productCatalog[$productId])) {
                    $result[] = [
                        'product_id' => $productId,
                        'product_name' => $this->productCatalog[$productId]['name'],
                        'views' => $item->count
                    ];
                }
            }
            if (count($result) >= $limit) {
                break;
            }
        }
        return $result;
    }
}

// ============================================
// UTILISATION
// ============================================

$storage = new MemoryStorage();

$engine = new RecommendationEngine(
    new CountMinSketch($storage, 10000, 5, 'rec_cms'),
    new TopK($storage, 50, 'rec_topk')
);

// Catalogue produits
$products = [
    ['id' => 'p1', 'name' => 'Laptop', 'tags' => ['electronics', 'computer', 'gaming']],
    ['id' => 'p2', 'name' => 'Smartphone', 'tags' => ['electronics', 'mobile', 'communication']],
    ['id' => 'p3', 'name' => 'Headphones', 'tags' => ['electronics', 'audio', 'music']],
    ['id' => 'p4', 'name' => 'Book PHP', 'tags' => ['programming', 'php', 'web']],
    ['id' => 'p5', 'name' => 'Book Python', 'tags' => ['programming', 'python', 'ai']],
    ['id' => 'p6', 'name' => 'Tablet', 'tags' => ['electronics', 'mobile', 'reading']],
];

foreach ($products as $product) {
    $engine->addProduct($product['id'], $product['name'], $product['tags']);
}

// Tracking des vues
$views = [
    ['user_id' => 'user_123', 'product_id' => 'p1'],
    ['user_id' => 'user_123', 'product_id' => 'p1'],
    ['user_id' => 'user_123', 'product_id' => 'p2'],
    ['user_id' => 'user_123', 'product_id' => 'p4'],
    ['user_id' => 'user_456', 'product_id' => 'p1'],
    ['user_id' => 'user_456', 'product_id' => 'p3'],
];

foreach ($views as $view) {
    $engine->trackView($view['user_id'], $view['product_id']);
}

echo "=== PRÉFÉRENCES DE l'UTILISATEUR ===\n";
$preferences = $engine->getUserPreferences('user_123');
foreach ($preferences as $pref) {
    echo "- {$pref['product_name']} (vues: {$pref['views']})\n";
}

echo "\n=== RECOMMANDATIONS ===\n";
$recommendations = $engine->getRecommendations('user_123', 5);
foreach ($recommendations as $rec) {
    echo "- {$rec['product_name']} (tags: " . implode(', ', $rec['matching_tags']) . ")\n";
}

echo "\n=== PRODUITS POPULAIRES ===\n";
$popular = $engine->getPopularProducts(5);
foreach ($popular as $item) {
    echo "- {$item['product_name']} (vues: {$item['views']})\n";
}
```

---

## Persistance

### Avec MemoryStorage (par défaut)

```php
$storage = new MemoryStorage();
$trie = new Trie($storage, 'my_trie');
// Les données sont stockées en mémoire
// Perdues à la fin du script
```

### Avec RedisStorage (exemple)

```php
class RedisStorage implements StorageInterface
{
    private \Redis $redis;
    
    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }
    
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($key);
        return $value !== false ? unserialize($value) : $default;
    }
    
    public function set(string $key, mixed $value): void
    {
        $this->redis->set($key, serialize($value));
    }
    
    public function delete(string $key): bool
    {
        return (bool) $this->redis->del($key);
    }
    
    public function exists(string $key): bool
    {
        return (bool) $this->redis->exists($key);
    }
}

// Utilisation
$redis = new \Redis();
$redis->connect('localhost', 6379);
$storage = new RedisStorage($redis);
$trie = new Trie($storage, 'persistent_trie');
// Les données sont persistées dans Redis
```

---

## Performance

### Comparatif des structures

| Structure | Insertion | Recherche | Mémoire | Précision | Idéal pour |
|-----------|-----------|-----------|---------|-----------|------------|
| Trie | O(L) | O(L + M) | Élevée | 100% | Autocomplétion |
| BKTree | O(log n) | O(n) | Moyenne | 100% | Correction orthographique |
| BloomFilter | O(k) | O(k) | Très faible | ~99% | Test d'existence |
| CountMinSketch | O(d) | O(d) | Très faible | ~95% | Comptage de fréquence |
| HyperLogLog | O(1) | O(m) | Très faible | ~98% | Éléments uniques |
| TopK | O(k) | O(k) | Faible | 100% | Top fréquents |

### Recommandations

| Besoin | Structure recommandée |
|--------|----------------------|
| Précision absolue | Trie, BKTree, TopK |
| Mémoire limitée | BloomFilter, CountMinSketch, HyperLogLog |
| Flux massifs | CountMinSketch, HyperLogLog |
| Recherche temps réel | Trie, BloomFilter |
| Correction orthographique | BKTree |
| Comptage d'occurrences | CountMinSketch |
| Éléments uniques | HyperLogLog |
| Classement | TopK |

---

## API Reference

### Structures

- [Trie](docs/api-reference/algorithms/trie.md) - Autocomplétion et recherche par préfixe
- [BKTree](docs/api-reference/algorithms/bk-tree.md) - Correction orthographique et recherche floue
- [BloomFilter](docs/api-reference/algorithms/bloom-filter.md) - Test probabiliste d'appartenance
- [CountMinSketch](docs/api-reference/algorithms/count-min-sketch.md) - Comptage probabiliste de fréquences
- [HyperLogLog](docs/api-reference/algorithms/hyper-log-log.md) - Estimation de cardinalité
- [TopK](docs/api-reference/algorithms/top-k.md) - Suivi des éléments les plus fréquents

### Interfaces

- `StorageInterface` - Interface de persistance
- `TrieInterface` - Interface du Trie
- `BloomFilterInterface` - Interface du BloomFilter
- `CountMinSketchInterface` - Interface du CountMinSketch
- `HyperLogLogInterface` - Interface du HyperLogLog
- `TopKInterface` - Interface du TopK
- `TreeInterface` - Interface du BKTree

### Collections

- `TrieCollection` / `TrieResultCollection`
- `BloomFilterCollection` / `BloomFilterResultCollection`
- `CountMinSketchCollection` / `CountMinSketchResultCollection`
- `HyperLogLogCollection` / `HyperLogLogResultCollection`
- `TopKCollection` / `TopKResultCollection`
- `BKTreeNodeCollection` / `BKTreeResultCollection`

### Records

- `TrieRecord` / `TrieResultRecord`
- `BloomFilterRecord` / `BloomFilterResultRecord`
- `CountMinSketchRecord` / `CountMinSketchResultRecord`
- `HyperLogLogRecord` / `HyperLogLogResultRecord`
- `TopKRecord` / `TopKResultRecord`
- `BKTreeNodeRecord` / `BKTreeResultRecord`

---

## License

MIT © [Andy Defer](https://github.com/andydefer)