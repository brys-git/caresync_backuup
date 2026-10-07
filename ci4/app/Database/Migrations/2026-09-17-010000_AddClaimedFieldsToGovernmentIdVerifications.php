<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Fix Prompts, Task 2: stores a fingerprint of exactly what was compared
 * at verification time (the applicant's claimed first/middle/last name,
 * date of birth, and sex) alongside the existing extracted_* columns.
 *
 * Needed for GovernmentIdVerificationService::isValidForSubmission() to
 * detect "the applicant edited their details after verifying" - without
 * this, a registration could be verified against one identity and then
 * silently submitted under a different one, since only the extracted (ID
 * side) values were ever recorded, never what was actually claimed.
 */
class AddClaimedFieldsToGovernmentIdVerifications extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('government_id_verifications')) {
            return;
        }

        $fields = [];

        if (! $this->db->fieldExists('claimed_first_name', 'government_id_verifications')) {
            $fields['claimed_first_name'] = [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'after'      => 'mismatch_reason',
            ];
        }

        if (! $this->db->fieldExists('claimed_middle_name', 'government_id_verifications')) {
            $fields['claimed_middle_name'] = [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'after'      => 'claimed_first_name',
            ];
        }

        if (! $this->db->fieldExists('claimed_last_name', 'government_id_verifications')) {
            $fields['claimed_last_name'] = [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'after'      => 'claimed_middle_name',
            ];
        }

        if (! $this->db->fieldExists('claimed_date_of_birth', 'government_id_verifications')) {
            $fields['claimed_date_of_birth'] = [
                'type'  => 'DATE',
                'null'  => true,
                'after' => 'claimed_last_name',
            ];
        }

        if (! $this->db->fieldExists('claimed_gender', 'government_id_verifications')) {
            $fields['claimed_gender'] = [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'after'      => 'claimed_date_of_birth',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('government_id_verifications', $fields);
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('government_id_verifications')) {
            return;
        }

        foreach (['claimed_first_name', 'claimed_middle_name', 'claimed_last_name', 'claimed_date_of_birth', 'claimed_gender'] as $column) {
            if ($this->db->fieldExists($column, 'government_id_verifications')) {
                $this->forge->dropColumn('government_id_verifications', $column);
            }
        }
    }
}
