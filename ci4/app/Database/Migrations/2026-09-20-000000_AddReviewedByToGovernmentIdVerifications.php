<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Manual ID review (no Anthropic API key configured = every verification
 * comes back 'needs_review'): which Staff/Branch Admin account actually
 * looked at the ID and compared it against the applicant's typed details.
 * Nullable - automatic 'verified'/'failed' results (when a key IS
 * configured) never go through a human, so this stays empty for those.
 */
class AddReviewedByToGovernmentIdVerifications extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('reviewed_by', 'government_id_verifications')) {
            $this->forge->addColumn('government_id_verifications', [
                'reviewed_by' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                    'after' => 'mismatch_reason',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('reviewed_by', 'government_id_verifications')) {
            $this->forge->dropColumn('government_id_verifications', 'reviewed_by');
        }
    }
}
