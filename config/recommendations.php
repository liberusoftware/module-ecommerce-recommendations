<?php

return [
    'seams' => [
        // Where interactions are pulled from. Unbound, the module ingests
        // nothing and says so; it does not invent page-view tracking.
        'signal_source' => null,

        // Where a product reference is resolved to stock, suppression and
        // classification. Unbound, those three exclusions are not applied and
        // the placement records that they were not.
        'catalogue' => null,

        // Where a shopper's live cart is read. Unbound, the already-in-cart
        // exclusion is not applied and the placement records that.
        'shopper' => null,
    ],

    // The smallest number of distinct subjects that may stand behind a derived
    // affinity. Below it the claim is withheld, because an aggregate that can
    // single a person out is not anonymous.
    'k_anonymity' => [
        'minimum_subjects' => 5,
    ],

    // How long a subject-keyed signal is kept. Null is not zero: it is a host
    // that never said, and pruning refuses on that basis.
    'retention' => [
        'signal_days' => null,
    ],

    'serve' => [
        // Candidates read per request, as a multiple of the requested count, so
        // exclusions have something to remove without a second query.
        'candidate_overfetch' => 3,
    ],
];
