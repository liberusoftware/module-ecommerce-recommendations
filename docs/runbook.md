# Runbook

## Nothing is being recommended

Serve a placement and read its `refusal`. It is never null on an empty answer.

| Refusal | What to do |
|---|---|
| `no_signal_source_bound` | Nothing has ever been recorded and no source is bound. Either bind `recommendations.seams.signal_source`, or call `Actions\RecordSignal` from wherever the host observes interactions |
| `no_signals_recorded` | A source is bound and offered nothing. Run `IngestSignals` for a window you expect data in and read its `offered` count |
| `no_generation_run` | Signals exist and no run has succeeded. Run `Actions\RunGeneration` per tenant per strategy, and check whether an earlier run is sitting in `failed` with a `failure_reason` |
| `no_affinities_for_anchor` | The generator ran and this anchor has nothing. Usually the anonymity floor: check the run's `withheld_below_floor` |
| `all_candidates_excluded` | Candidates existed. Read the placement's entries: each excluded one names its reason |

## A run reports `withheld_below_floor` far above `asserted`

The store has less traffic than `recommendations.k_anonymity.minimum_subjects` demands. That is the
floor working, not a fault. Lowering it is an operator decision with a privacy cost — see
`docs/adr/0001-observation-and-inference.md` — and content similarity is exempt from the floor, so a
store with a classified catalogue can produce recommendations at any traffic level.

## A content-similarity run fails immediately

`failure_reason` is `no_catalogue_reader_bound`. Bind `recommendations.seams.catalogue`. Unlike the
other strategies, content similarity has no input at all without the catalogue, so it fails the run
rather than succeeding with nothing.

## Recommendations went stale and nothing retracted them

Supersession happens inside a run, per strategy. A strategy whose run stopped being scheduled keeps
its last claims standing and active. Check `recommendations_generation_runs` for the newest
`succeeded` row per `(tenant_id, strategy)`; if it is old, the schedule stopped.

## A pair keeps coming back after being withdrawn

`WithdrawAffinity` supersedes; the next successful run for that strategy reasserts it if the evidence
is still there. A manual affinity is not touched by any run, so a withdrawn manual claim stays
withdrawn until a merchandiser records it again.

## `PruneExpiredSignals` refuses

`recommendations.retention.signal_days` is null — the host never said. Set it. Note the report's
`unbounded` count: those are subject-keyed signals written while the window was null. They carry no
`retain_until` and pruning will never reach them; `Actions\ForgetSubject` still will.

## A shopper says they were shown something odd

`Queries\ExplainPlacement` with the tenant and the placement id returns the stored row: what was
shown, in order, with the strategy and both scores per entry, and every candidate that was excluded
with its reason. The placement was written before it was returned, so this is the same row the
shopper saw and not a reconstruction.

## Placements are growing without bound

They are the audit trail; nothing prunes them on its own except `ForgetSubject`, which removes a
person's. If retention on placements matters to the host, that is a scheduled delete on
`recommendations_placements` by `served_at`, and it takes the entries with it by cascade.

## Two strategies disagree about a pair

They are allowed to: the strategy is part of the unique key. The serve path normalises each
strategy's scores against the candidate set and ranks them together, with manual above all of them.
`Queries\ListAffinities` with `activeOnly: false` shows every claim on an anchor, superseded ones
included.
