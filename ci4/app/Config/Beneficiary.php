<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Fix Prompts Task 6: the relationship dropdown for a beneficiary row.
 * The label IS the value (e.g. 'Daughter'), not a coded key - existing
 * free-text relationship data already stored in beneficiaries.relationship
 * (spouse/child/parent/sibling/other, or genuinely free text from before
 * this dropdown existed) stays human-readable either way, and a value not
 * in this list just falls back to the 'Other' + free-text pattern (see
 * ClientRegistrationController's beneficiary loop).
 */
class Beneficiary extends BaseConfig
{
    /**
     * @var array<string, string> value => label (identical - see above)
     */
    public array $relationships = [
        'Husband'         => 'Husband',
        'Wife'            => 'Wife',
        'Son'             => 'Son',
        'Daughter'        => 'Daughter',
        'Father'          => 'Father',
        'Mother'          => 'Mother',
        'Sibling'         => 'Sibling',
        'Brother'         => 'Brother',
        'Sister'          => 'Sister',
        'Grandfather'     => 'Grandfather',
        'Grandmother'     => 'Grandmother',
        'Grandson'        => 'Grandson',
        'Granddaughter'   => 'Granddaughter',
        'Uncle'           => 'Uncle',
        'Auntie'          => 'Auntie',
        'Nephew'          => 'Nephew',
        'Niece'           => 'Niece',
        'Cousin'          => 'Cousin',
        'Father-in-law'   => 'Father-in-law',
        'Mother-in-law'   => 'Mother-in-law',
        'Son-in-law'      => 'Son-in-law',
        'Daughter-in-law' => 'Daughter-in-law',
        'Brother-in-law'  => 'Brother-in-law',
        'Sister-in-law'   => 'Sister-in-law',
        'Stepfather'      => 'Stepfather',
        'Stepmother'      => 'Stepmother',
        'Stepson'         => 'Stepson',
        'Stepdaughter'    => 'Stepdaughter',
        'Guardian'        => 'Guardian',
        'Other'           => 'Other',
    ];
}
