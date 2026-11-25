<?php
// Role constants
const ROLE_ADMIN               = 'ADMIN';
const ROLE_PANITERA_GUGATAN    = 'PANITERA_GUGATAN';
const ROLE_PANITERA_PERMOHONAN = 'PANITERA_PERMOHONAN';
const ROLE_PANITERA_HUKUM      = 'PANITERA_HUKUM';
const ROLE_KASIR               = 'KASIR';

const ALL_ROLES = [
    ROLE_ADMIN, ROLE_PANITERA_GUGATAN, ROLE_PANITERA_PERMOHONAN,
    ROLE_PANITERA_HUKUM, ROLE_KASIR
];

const ROLE_LABELS = [
    ROLE_ADMIN               => 'Administrator',
    ROLE_PANITERA_GUGATAN    => 'Panitera Muda Gugatan',
    ROLE_PANITERA_PERMOHONAN => 'Panitera Muda Permohonan',
    ROLE_PANITERA_HUKUM      => 'Panitera Muda Hukum',
    ROLE_KASIR               => 'Kasir',
];

// Category
const CAT_GUGATAN    = 'GUGATAN';
const CAT_PERMOHONAN = 'PERMOHONAN';
const CAT_HUKUM      = 'HUKUM';
const ALL_CATEGORIES = [CAT_GUGATAN, CAT_PERMOHONAN, CAT_HUKUM];
const CATEGORY_LABELS = [
    CAT_GUGATAN    => 'Gugatan',
    CAT_PERMOHONAN => 'Permohonan',
    CAT_HUKUM      => 'Layanan Hukum',
];

// Cost types
const COST_GUGATAN    = 'GUGATAN';
const COST_PERMOHONAN = 'PERMOHONAN';
const COST_PNBP       = 'PNBP';
const ALL_COST_TYPES  = [COST_GUGATAN, COST_PERMOHONAN, COST_PNBP];

// Calculation type
const CALC_FIXED         = 'FIXED';
const CALC_PER_PENGGUGAT = 'PER_PENGGUGAT';
const CALC_PER_TERGUGAT  = 'PER_TERGUGAT';
const CALC_PER_PEMOHON   = 'PER_PEMOHON';
const ALL_CALC_TYPES = [CALC_FIXED, CALC_PER_PENGGUGAT, CALC_PER_TERGUGAT, CALC_PER_PEMOHON];
const CALC_LABELS = [
    CALC_FIXED         => 'Tetap (x1)',
    CALC_PER_PENGGUGAT => 'Per Penggugat',
    CALC_PER_TERGUGAT  => 'Per Tergugat',
    CALC_PER_PEMOHON   => 'Per Pemohon',
];

// Status
const STATUS_DRAFT     = 'DRAFT';
const STATUS_PUBLISHED = 'PUBLISHED';
const STATUS_ACTIVE    = 'ACTIVE';
const STATUS_INACTIVE  = 'INACTIVE';

// Feedback
const FB_PENDING   = 'PENDING';
const FB_PUBLISHED = 'PUBLISHED';
const FB_ARCHIVED  = 'ARCHIVED';