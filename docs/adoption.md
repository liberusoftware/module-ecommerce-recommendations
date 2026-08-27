# Adoption

## Install

```bash
composer require liberusoftware/ecommerce-recommendations
```

Installing boots nothing: `extra.laravel.providers` is absent on purpose, and the host's module
manager registers `RecommendationsServiceProvider` when `ecommerce-recommendations` is named in
`MODULES_ENABLED`. Then:

```bash
php artisan migrate
php artisan vendor:publish --tag=recommendations-config
```

## What the host must bind

Nothing is bound by default, and with nothing bound the module is inert rather than wrong. Each seam
is a config key that takes an instance or a class name, resolved at the moment of use so a rebinding
takes effect on the next call.

| Key | Contract | Unbound |
|---|---|---|
| `recommendations.seams.signal_source` | `Contracts\SignalSource` | Nothing is ingested; `IngestSignals` refuses by name |
| `recommendations.seams.catalogue` | `Contracts\CatalogueReader` | Stock, suppression and resolvability go unchecked and the placement says so; content-similarity runs fail by name |
| `recommendations.seams.shopper` | `Contracts\ShopperContext` | Already-in-cart is not evaluated |

The host's tenancy maps onto `tenant_id`. Commerce in the host scopes on `store_id`, and that is the
value to pass: a recommendation is a claim about one shopfront's catalogue, and there is no
cross-store recommendation and no global trending. `tenant_id` is `NOT NULL` with no default on every
table, and every call takes it explicitly rather than reading an ambient context the package cannot
see.

## What the host must configure

```php
'k_anonymity' => ['minimum_subjects' => 5],
'retention'   => ['signal_days' => null],
'serve'       => ['candidate_overfetch' => 3],
```

`signal_days` ships as `null` on purpose: null is not a window of zero, it is a host that never said,
and `PruneExpiredSignals` refuses on that basis rather than deleting or keeping on a guess. Set it
before the first signal is recorded — the window is stamped at write time, and a signal written under
a null window has no `retain_until` and will never be pruned. `PruneExpiredSignals` reports how many
such signals are standing.

`minimum_subjects` is the anonymity floor. Lowering it produces more recommendations from thinner
evidence and weakens the argument in `docs/adr/0001-observation-and-inference.md` that an affinity is
anonymous. It is an operator's decision and it is visible in every run's report.

## What the host deletes

| Host artefact | Why it is not adopted |
|---|---|
| `app/Services/ProductRecommendationEngine.php` | Its scoring divides by an assumed constant, it never retracts, it counts order lines rather than orders, it fabricates a session id, and five of its eight public methods have no test |
| `app/Services/UserHistoryRecommender.php` | It sorts on an attribute two thirds of its candidates do not have, so two of its three declared signals cannot win; `getRelatedProducts()` has no limit and `getPurchasedProducts()` loads a whole order history on a request path |
| `app/Console/Commands/GenerateProductRecommendations.php` | Replaced by `RunGeneration`, which takes a tenant and a strategy and records the run. The command had neither, and swallowed its failure into a console nobody read |
| `app/Models/ProductRecommendation.php` | Replaced by `Models\Affinity`, whose unique key includes the strategy |
| `app/Models/RecommendationRule.php` | A free-string `type` column is replaced by `Enums\Strategy`; there is no rule row to create, so there is no `firstOrCreate` running unscoped in console |
| `app/Models/ProductInteraction.php` | Replaced by `Models\Signal`, which is tenant-scoped, carries an occurrence reference and never asks a session who the shopper was |
| `app/Models/BrowsingHistory.php` | A second, guest-incapable browsing table with no dedup and no tenancy. `Signal` is the one table, and a signal with no subject is legitimate |
| `database/migrations/2024_02_16_000002_create_product_recommendations_table.php` | All three of its tables are replaced |
| `database/migrations/2026_07_14_000601_create_browsing_histories_table.php` | Replaced |
| `recommendation_rules` in the `2026_08_09_000001` sweep migration | The column that migration adds belongs to a table this module replaces |
| `tests/Unit/ProductRecommendationEngineTest.php`, `ProductRecommendationTest.php`, `ProductInteractionModelTest.php`, `UserHistoryRecommenderTest.php` | The first two assert score values the production column cannot hold; the fourth never calls the only public method of the service it names |

`ProductController::show()` still injects `UserHistoryRecommender` and imports `BrowsingHistory` for
a block that is entirely commented out. Both the injection and the import go with the service.

## GDPR

The host's `GdprExportService` exports `browsing_histories` and `product_interactions`, and
`GdprErasureService` deletes both. Repoint them:

- Export: `Queries\ExportSubjectRecord` — signals and placements, across every tenant.
- Erasure: `Actions\ForgetSubject` — the same set, deleted, with the aggregate affinities retained.

The two walk the same set by construction. The retention asymmetry — a person goes, the arithmetic
stays — is argued in the ADR and depends on the anonymity floor being enforced, so a host that sets
`minimum_subjects` to 1 has taken that argument away.
