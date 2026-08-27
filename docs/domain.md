# Domain

## What is modelled

**A signal** is one interaction, as the caller stated it: a tenant, an optional subject reference, a
product reference, an optional occurrence reference, a kind, the cause it came from, and when it
happened. It is a derived, purpose-limited input to a ranking — not an analytics event. See
`docs/adr/0001-observation-and-inference.md`.

**A generation run** is one strategy over one window for one tenant, with the counts in and out, the
anonymity floor it applied, when it started and finished, and how it ended.

**An affinity** is a derived, directional, scored claim between two catalogue references, owned by a
strategy, asserted against a run. Popularity's claims have no anchor: `from_ref` is the empty string,
because popularity is about the store and not about a product to sit beside.

**A placement** is what a surface asked for and what it was given, with an entry per candidate: a
position and no reason, or a reason and no position.

## Decisions, including the ones rejected

### Two kinds, named apart

The host had one `product_recommendations` table carrying both a `unique(product_id,
recommended_product_id)` — which assumes one claim per pair — and a `rule_id` — which assumes several.
Those two facts cannot both be true, and the code resolved the contradiction by silently overwriting:
`updateOrCreate` on the pair meant the second rule to run erased the first rule's score and reason.

Affinity and placement separate the claim from the serving of it. **Rejected:** one table with a
`served_at` nullable column, which would have made every read of the claim table filter on a
serving concept it has no business knowing about.

### The strategy is part of the key

`unique(tenant_id, strategy, from_ref, to_ref)`. Two strategies may both claim a pair, and the serve
path ranks them against each other. This is what makes decision "manual outranks derived" expressible
at all.

### A score is a ratio, and its scale is a fact about the strategy

The host wrote `$maxFrequency = 100; // Assume max frequency` and then `min(1, $frequency / 100)`, so
a pair bought together 100 times and one bought together 5,000 times both scored 1.0 — into a
`decimal(5,4)` column whose ceiling is 9.9999.

Each strategy here produces a ratio it can defend on its own terms:

| Strategy | Score | Evidence count | Subject count |
|---|---|---|---|
| `collaborative` | confidence: occurrences containing both ÷ occurrences containing the anchor | distinct occurrences containing both | distinct subjects behind them |
| `popularity` | distinct subjects who touched the product ÷ distinct subjects in the window | signals in the window | distinct subjects |
| `content_similarity` | Jaccard overlap of category and tag references | size of the intersection | 0 — no person is in it |
| `manual` | whatever the merchandiser said, in [0, 1] | 1 | 0 |

Comparing them across strategies is only fair against the candidate set actually read, so
normalisation happens at serve time in `Support\Normalisation` and never at write time. A strategy
whose whole candidate set scores zero normalises to zero rather than to one: there is no scale, and
zero is the honest answer.

`Affinity::setScoreAttribute` refuses anything outside [0, 1], so the schema and the code agree. The
host's own suite wrote `85.5` into that column and passed, because it ran on sqlite `:memory:`, which
does not enforce decimal precision, and asserted against the in-memory model rather than a re-read
row. On MySQL those inserts raise "Out of range value".

**Rejected:** an integer basis-points column. It reads well for money and badly for a similarity —
`decimal(8,6)` says "a ratio to six places" without a reader having to divide by 10,000 in their head.

### Retraction is the run's job

The host's generator upserted every qualifying pair and removed nothing, so a pair that fell below
the threshold, or whose orders were refunded, or whose product was withdrawn, kept its last score
forever. Here, an affinity that the newest successful run for its strategy did not reassert is
superseded — a state transition that writes its own audit row, in the shape of `Order::transitionTo()`.
A superseded claim can come back: `AffinityState::Superseded` transitions to `Active` when the
evidence returns, and the history shows both moves.

**Rejected:** deleting unasserted affinities. Deletion loses the fact that the claim was once true,
which is exactly the question an operator asks when a recommendation disappears.

### Idempotency is a natural key, and it is scoped to the subject

A signal's cause exists before this module does: the host already has an event, an order line, a
click identifier. So `unique(tenant_id, subject_ref, source_ref)` is the arbiter and there is nothing
for a client to mint or hold. **A client-supplied idempotency key is not worth having here**, because
the natural key always exists — a caller with no reference to offer is a caller who does not yet know
what happened.

The subject is in the key, not just the tenant. Two people quoting one reference — a shared session
identifier, a re-used order number — get two rows. Scoped only to the tenant, the second person's
signal would return the first person's row from a call that looked like it succeeded.

`subject_ref` is `NOT NULL` defaulting to the empty string rather than nullable, because SQL
uniqueness ignores nulls: a nullable column would stop the key arbitrating for exactly the anonymous
signals that need it most. Empty means *no subject*, which is a legitimate popularity input.

### Occurrence, not line

`group_ref` carries the occurrence a signal belongs to — an order, a session. Co-purchase counts
`COUNT(DISTINCT group_ref)`, so one order carrying the same product on two lines is one piece of
evidence. The host self-joined `order_items` without distinct-ing on `order_id`, which inflated a
pair count quadratically in the number of duplicate lines.

A purchase signal with no `group_ref` cannot contribute to co-occurrence, and is simply absent from
that strategy's evidence rather than counted as its own occurrence.

### No session is invented

The module never calls `session()`. The host's `ProductInteraction::track()` defaulted a missing
session id to `session()->getId()`, and in a queued or console context that is whatever id the array
session driver invented for the process — so unrelated interactions grouped under one identifier. Its
engine made it worse by discarding the caller's session id entirely and calling `session()->getId()`
directly in all three `track*` methods, even though `getPersonalizedRecommendations()` accepted one
as a parameter.

### Refusals, and why there are five of them

A recommender's failure mode is silence, and silence is indistinguishable from an empty result. Every
empty placement carries the precondition that failed:

| Refusal | What it means |
|---|---|
| `no_signal_source_bound` | Nothing was ever recorded and nothing is bound that could record it |
| `no_signals_recorded` | A source is bound and has offered nothing |
| `no_generation_run` | Signals exist and no run has succeeded |
| `no_affinities_for_anchor` | The generator ran and found nothing for this anchor |
| `all_candidates_excluded` | Candidates existed and the exclusion list removed them all |

**There is no fallback from an anchored slot to popularity.** The host fell through to trending when
a shopper had no interactions, and trending joined an always-empty table, so the fallback returned an
empty collection and the caller could not tell "this shopper is new" from "nothing has ever been
recorded" from "the generator has never run". A surface that wants popularity asks for it by leaving
the anchor out.

### One exclusion list

The host applied two different exclusion sets in two services that never saw each other — one
excluded purchases, the other excluded purchases and browsing. Here there is one list, evaluated once,
first reason wins: is-anchor, already-purchased, already-in-cart, out-of-stock, suppressed,
unresolvable reference. Every removal is an entry on the placement, so "you asked for ten and got
four" has an answer.

An unresolvable reference is reported rather than dropped. The host eager-loaded the recommended
product, let the soft-delete scope null it, filtered the nulls out and then took the limit — asking
for ten and getting four with nothing anywhere saying why.

### Blast radius of an unbound seam

- **`SignalSource` unbound**: nothing is ingested; the ingest refuses by name. Signals recorded
  directly are unaffected.
- **`CatalogueReader` unbound**: out-of-stock, suppressed and unresolvable are not evaluated and no
  candidate is dropped for having gone unchecked. The placement records `catalogue_checked = false`.
  A content-similarity run fails by name rather than generating from nothing.
- **`ShopperContext` unbound**: already-in-cart is not evaluated. `cart_checked = false`.

Nothing clamps, nothing substitutes, and no seam has a default binding.

### Determinism

Same store, same shopper, same catalogue state, same window gives the same list. The order is total:
manual first, then normalised score, then a tiebreak on the product reference. Where a surface wants
variety it passes a seed, and the tiebreak becomes a hash of the seed and the reference — different
per seed, identical per repeat — and the seed is stored on the placement. The host banded on the base
price ±30%, ignoring variants and currency, then called `inRandomOrder()`, so the same shopper
reloading the same page got a different answer with no seed and no explanation.

### Bounded reads

Candidates are ordered and limited in SQL, including the manual-first ordering, at `limit ×
recommendations.serve.candidate_overfetch`. The overfetch is what gives the exclusion list something
to remove without a second query; it is also why a placement can still return fewer than requested,
which `candidates_examined` on the row makes visible.
