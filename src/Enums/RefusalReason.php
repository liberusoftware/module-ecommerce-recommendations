<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Enums;

/**
 * A recommender's failure mode is silence, and silence reads as an empty
 * result. Every empty answer names the precondition that produced it.
 */
enum RefusalReason: string
{
    case NoSignalSourceBound = 'no_signal_source_bound';
    case NoCatalogueReaderBound = 'no_catalogue_reader_bound';
    case NoSignalsRecorded = 'no_signals_recorded';
    case NoGenerationRun = 'no_generation_run';
    case NoAffinitiesForAnchor = 'no_affinities_for_anchor';
    case AllCandidatesExcluded = 'all_candidates_excluded';
    case ManualIsNotGenerated = 'manual_is_not_generated';
    case RunAlreadyFinished = 'run_already_finished';
    case RetentionWindowUnknown = 'retention_window_unknown';
    case SubjectReferenceRequired = 'subject_reference_required';
    case AnchorRequired = 'anchor_required';
    case AnchorRecommendsItself = 'anchor_recommends_itself';
    case ProductReferenceRequired = 'product_reference_required';
    case NotThisTenants = 'not_this_tenants';
}
