# Changelog

## 0.1.0

First release. Extracted from the host application's `ProductRecommendationEngine`,
`UserHistoryRecommender`, `BrowsingHistory`, `ProductInteraction`, `ProductRecommendation` and
`RecommendationRule`, none of which had a caller outside their own tests.

### The boundary

- Analytics owns the observation, this module owns the inference. No raw event stream is stored here
  and none of `attribution-and-analytics`'s tables are read.
- Per-shopper ranking of a candidate set is this module's; per-shopper page composition is not.
- Popularity as a recommendation input is this module's; popularity as an operator report is not.
- Manual product relationships are this module's, because a curated up-sell and a computed one have
  to rank against each other in one list.

### Decisions

- **Two stored kinds.** An affinity is a derived directional scored claim between two catalogue
  references owned by a strategy; a placement is what a surface asked for and what it was given. The
  host conflated them into one row carrying both a unique key that assumed one claim per pair and a
  `rule_id` that assumed several.
- **The strategy is part of the affinity's unique key**, so two strategies may claim the same pair.
- **Four strategies, as an enum**: `collaborative`, `content_similarity`, `popularity`, `manual`.
- **A score is a ratio its strategy can defend**, stored `decimal(8,6)`, range-guarded on the model,
  and normalised per strategy against the candidate set at serve time. The host stored a frequency
  divided by an assumed maximum of 100 into a `decimal(5,4)` column whose ceiling is 9.9999.
- **Generation is a run**, and retraction is the run's job: an affinity the newest successful run for
  its strategy did not reassert is superseded, with an audit row.
- **A derived claim about people is withheld** unless a configurable floor of distinct subjects
  stands behind it. Content similarity is about the catalogue rather than about people, so no floor
  applies to it.
- **Co-occurrence is counted by distinct occurrence**, not by order line.
- **Three seams, none bound**: `SignalSource`, `CatalogueReader`, `ShopperContext`. An unbound seam
  removes exactly the claim it controls and the placement records that it went unchecked.
- **No session is invented.** The module never calls `session()`, and a signal with no subject is a
  popularity input rather than an error.
- **Exclusions are one list evaluated once** — is-anchor, already-purchased, already-in-cart,
  out-of-stock, suppressed, unresolvable reference — and every removal is counted.
- **A placement is written before it is returned.**
- **Serve paths are bounded in SQL**, ordered and limited before hydration.
- **Determinism is a requirement**; variety takes an explicit seed the placement stores.
- **Manual outranks derived** at any score, in the SQL order rather than in a second pass.
- **Erasure removes a person's signals and placements and keeps the aggregate affinities.**

### Deliberately not shipped

- **No fallback from an anchored slot to popularity.** The host's fall-through to trending is what
  made an empty answer unfalsifiable. A surface that wants popularity asks for it by omitting the
  anchor, and an anchored slot with nothing to say says so by name.
- **No `interaction_type` enum column.** The host's was a database enum, so adding a signal kind was
  a schema change. Here the closed set is a PHP enum over a string column.
- **No client-supplied idempotency key.** The cause of a signal exists before this module does, so
  the natural key `(tenant, subject, source reference)` is the arbiter and there is nothing to mint.
- **No stored aggregate on a product.** Nothing derived is stored outside the affinity a run
  asserted, which supersession can retract.
