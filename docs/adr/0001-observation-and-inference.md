# 0001 — The observation/inference boundary, four contested placements, and why erasure keeps the arithmetic

Status: accepted, 0.1.0.

## Context

The epic's scope line reads "Manual/rule/model recommendations, related/up-sell/cross-sell, recently
viewed, popularity, context, exclusions, and explanation." Three of those — recently viewed,
popularity, context — are behavioural signals, and behavioural signals are what
`module-ecommerce-attribution-and-analytics` shipped in wave 14: `TrackingSession`, `AnalyticsEvent`,
`SegmentDefinition`, `SegmentMembership` and `Rollup`, with `OpenSession`, `RecordEvent`,
`ComputeEventRollup` and `RunSegmentCalculation`. Another three epics claim overlapping ground.
This module is extracted first among the rivals, so it states the boundary rather than inheriting it.

## Decision 1 — analytics owns the observation, recommendations owns the inference

An analytics event is a record that something happened. It is kept for measurement, it is governed by
the consent model the analytics module implements, and it is redacted on erasure under that module's
rules. A recommendation signal is a derived, purpose-limited input to a ranking.

They are not the same record even when they describe the same click, and the reason is a retention
argument rather than a modelling preference: collapsing them makes the recommender's retention policy
hostage to the analytics consent model. A shopper who withdraws analytics consent would silently
change what the recommender can rank, in a way neither module's operator could see.

So this module stores no raw event stream and reads none of that module's tables. It defines a
`Contracts\SignalSource` seam, unbound by default, through which a host may feed it interactions —
including from analytics, if the host chooses to wire the two together. It stores its own derived
affinity rows. With nothing bound it is inert rather than wrong, and it says which seam is missing.

## Decision 2 — the four contested placements

**Against `attribution-and-analytics` (#821, shipped).** The split above. This module stores no raw
event stream and reads none of that module's tables.

**Against #885 Personalization.** Per-shopper *ranking of a candidate set* is this module's, because
ranking is what an affinity score is for. Per-shopper *content selection and layout of a page* is
theirs. A slot on a page is their decision; what goes in it, ordered, is ours.

**Against #871 Merchandising Intelligence.** Popularity as a recommendation input is this module's,
and it is stored as an anchorless affinity so it ranks in the same list as everything else.
Popularity as an operator-facing report is theirs. The same underlying count, two consumers, and the
report is not built on our table.

**Against #872 Merchandising.** Manual product relationships are this module's, because a curated
up-sell and a computed up-sell must rank against each other in one list — two lists never do, and the
host proved it by shipping two recommender services with two different exclusion sets that never saw
each other. Collection curation and page merchandising remain theirs.

**Against `catalog` (shipped).** `module-ecommerce-catalog` owns `Product`, `ProductCollection`,
`Category`, `Tag`, `ProductOption` and `ProductVariant`. Content-similarity strategies read category
and tag references through the `CatalogueReader` seam, as opaque strings, and assume nothing about
the host's `category_id` column shape.

## Decision 3 — erasure removes the person and keeps the arithmetic

Erasing a person deletes their signals and their placements, across every tenant, in the same set the
export walks. It does **not** retract the aggregate affinities their behaviour contributed to,
because an affinity is a statement about a pair of products rather than about a person.

This is the decision most likely to be questioned, and it is defensible only under GDPR's
anonymisation carve-out — which holds only if the aggregate genuinely cannot single the person out.
With the host's three-order co-occurrence threshold, a small store's affinity set may be effectively
personal: a pair asserted from three orders in a shop with four customers is close to naming one of
them.

So the carve-out is not assumed, it is enforced. A derived claim about people is withheld unless a
configured floor of **distinct subjects** stands behind it — `recommendations.k_anonymity.minimum_subjects`,
defaulted conservatively to 5. The floor is cheap: both generating strategies already compute a
distinct-subject count in the same aggregate query that computes the evidence count, so enforcing it
costs a comparison rather than a second pass. A run reports how many claims it withheld, so an
operator can see the floor working rather than infer it from an empty table.

Content similarity is exempt, and the exemption is the argument in miniature: an overlap between two
products' categories is a statement about the catalogue and contains no person to single out. The
floor applies to claims that describe subjects, which is what `Strategy::describesSubjects()` names.

## Consequences

- A host that wants recommendations from analytics data writes one `SignalSource` adapter, and the
  two modules' retention policies stay independent.
- A small store gets fewer recommendations rather than a leaky aggregate, and can see in the run
  report that this is what happened.
- Lowering the floor is a configuration change an operator can make, and a decision they own.
