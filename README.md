# ecommerce-recommendations

The claim that one product is worth showing next to another, and the evidence behind that claim.

## What it owns

The signals a shopper generates, the strategies that turn signals into claims, the stored claim
itself, the exclusions applied when a surface asks, and the explanation of what it got back.

## What it does not own

Products, categories, orders, carts and customers. Those belong to modules that already shipped, and
this one reads them through seams and never joins their tables. It also does not own the raw event
stream: **analytics owns the observation, and this module owns the inference.** An analytics event is
a record that something happened, kept for measurement and governed by consent. A recommendation
signal is a derived, purpose-limited input to a ranking. They describe the same click and they are
not the same record — see [`docs/adr/0001-observation-and-inference.md`](docs/adr/0001-observation-and-inference.md).

## The fact that shaped it

In the application this was extracted from, nothing ever wrote an interaction, ran the generator or
displayed a recommendation. The feature was inert end to end and its tests were its only caller —
and nobody could tell, because the fallback path returned an empty collection whether the shopper was
new, nothing had ever been recorded, or the generator had never run. Three operational states, one
output, in a feature whose whole job is to be quietly absent when it has nothing to say.

So every answer here carries why it is what it is: which strategy produced each entry, how many
candidates were examined, what each exclusion removed, and — when the answer is empty — the
precondition that failed, by name.

## What it publishes

| | |
|---|---|
| `Actions\RecordSignal` | One interaction, as the caller states it; idempotent on the cause |
| `Actions\IngestSignals` | The same, pulled through the `SignalSource` seam |
| `Actions\RunGeneration` | One strategy, one window, one audited run with retraction |
| `Actions\RecordManualAffinity` | A merchandiser's own claim, ranked against the computed ones |
| `Actions\WithdrawAffinity` | Retract one claim by hand |
| `Actions\ServePlacement` | Plan, record, then return |
| `Actions\ForgetSubject` | Erase a person across every tenant |
| `Actions\PruneExpiredSignals` | Enforce the retention window, or refuse for want of one |
| `Queries\PlanPlacement` | The same answer, computed and written nowhere |
| `Queries\ExplainPlacement` | A stored placement, read back months later |
| `Queries\ListAffinities` | What a merchant currently claims |
| `Queries\ExportSubjectRecord` | Everything held about one person |
| `Contracts\SignalSource` | Where interactions come from. Unbound by default |
| `Contracts\CatalogueReader` | Where a product reference resolves. Unbound by default |
| `Contracts\ShopperContext` | Where a live cart is read. Unbound by default |
| `Events\*` | `AffinitiesGenerated`, `PlacementServed`, `SubjectForgotten` |

Six tables, all prefixed `recommendations_`: `signals`, `generation_runs`, `affinities`,
`affinity_events`, `placements`, `placement_entries`.

## Installing

```bash
composer require liberusoftware/ecommerce-recommendations
```

Installing boots nothing. The host's module manager registers the provider when the module is named
in `MODULES_ENABLED`. See [`docs/adoption.md`](docs/adoption.md) for what a host must bind and what
it deletes.
