<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Reusable Government ID Verification component (see App\Services\
 * GovernmentIdVerificationService). Phase 1 wires this into the Client/
 * Plan Holder self-service registration wizard only (`user_id` always
 * refers to an already-existing `users` row by the time verification
 * happens, since that flow requires being logged in first) - the same
 * table/service is designed to be reused by Staff/Branch Admin/Admin
 * account creation in a later phase without any schema change.
 *
 * Deliberately lean: no raw OCR payload, no full extracted address, no
 * full ID number is stored - only what's needed to show a verification
 * result later and support a manual staff review ("do not store
 * unnecessary extracted information").
 */
class CreateGovernmentIdVerifications extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('government_id_verifications')) {
            return;
        }

        $this->forge->addField([
            'verification_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                // Matches users.user_id exactly (plain signed INT, not
                // unsigned) - MySQL requires identical column types for a
                // foreign key.
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'id_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'file_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'original_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'mime_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'verification_status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'verified', 'needs_review', 'failed'],
                'default'    => 'pending',
            ],
            'match_result' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'extracted_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'extracted_birth_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'extracted_gender' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'extracted_id_number_masked' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'mismatch_reason' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'verification_attempts' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 1,
            ],
            'verified_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('verification_id', true);
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'user_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('government_id_verifications', true);
    }

    public function down()
    {
        if ($this->db->tableExists('government_id_verifications')) {
            $this->forge->dropTable('government_id_verifications', true);
        }
    }
}
