<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * "Existing member records": past plan details, payments, remittances,
 * services, documents and notes for members who were already with Kaagapay
 * before CareSync, encoded by Staff / Branch Admin.
 *
 * Past payments are deliberately kept in their OWN table instead of the live
 * `payments` table. Everything that reports on live money (remittance,
 * collections, commission, dashboards, GCash approval queues, overdue
 * notices) reads `payments`, so a past record can never be counted as a live
 * transaction or trigger a notification/commission by accident. Only the
 * pieces that must reflect history - months paid, balance, coverage and the
 * client's payment history - read the verified rows from here (see
 * LegacyRecordService).
 *
 * Every table carries the same audit/verification columns:
 *   record_status  pending_verification (encoded by Staff) | verified | rejected
 *   source         legacy_manual | legacy_import
 *   encoded_by/at, verified_by/at, rejection_reason
 */
class CreateLegacyMemberRecords extends Migration
{
    public function up()
    {
        $audit = [
            'record_status'    => ['type' => 'ENUM', 'constraint' => ['pending_verification', 'verified', 'rejected'], 'default' => 'pending_verification'],
            'source'           => ['type' => 'ENUM', 'constraint' => ['legacy_manual', 'legacy_import'], 'default' => 'legacy_manual'],
            'encoded_by'       => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'encoded_at'       => ['type' => 'DATETIME', 'null' => true],
            'verified_by'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'verified_at'      => ['type' => 'DATETIME', 'null' => true],
            'rejection_reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ];

        $id = static fn (string $name) => [$name => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true]];

        // 1. Membership and plan (one per plan holder) ---------------------
        $this->forge->addField($id('legacy_plan_record_id') + [
            'plan_holder_id'          => ['type' => 'INT', 'constraint' => 11],
            'plan_id'                 => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'package_id'              => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'plan_name'               => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'contract_number'         => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'original_membership_date' => ['type' => 'DATE'],
            'monthly_contribution'    => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'payment_frequency'       => ['type' => 'ENUM', 'constraint' => ['monthly', 'quarterly', 'semi_annual', 'annual'], 'default' => 'monthly'],
            'total_contract_amount'   => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            'declared_payments_made'  => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'plan_status'             => ['type' => 'ENUM', 'constraint' => ['active', 'lapsed', 'forfeited', 'fully_paid'], 'default' => 'active'],
            'assigned_collector_id'   => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'original_branch_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
        ] + $audit);
        $this->forge->addKey('legacy_plan_record_id', true);
        $this->forge->addUniqueKey('plan_holder_id');
        $this->forge->createTable('legacy_plan_records', true);

        // 2. Payment history ----------------------------------------------
        $this->forge->addField($id('legacy_payment_id') + [
            'plan_holder_id'  => ['type' => 'INT', 'constraint' => 11],
            'plan_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'branch_id'       => ['type' => 'INT', 'constraint' => 11],
            'payment_date'    => ['type' => 'DATE'],
            'amount'          => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'months_covered'  => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'period_month'    => ['type' => 'TINYINT', 'constraint' => 4],
            'period_year'     => ['type' => 'SMALLINT', 'constraint' => 6],
            'or_number'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'channel'         => ['type' => 'ENUM', 'constraint' => ['branch', 'collector', 'gcash', 'bank'], 'default' => 'branch'],
            'collected_by'    => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'remarks'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'cycle_number'    => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'import_batch'    => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
        ] + $audit);
        $this->forge->addKey('legacy_payment_id', true);
        $this->forge->addKey(['plan_holder_id', 'record_status']);
        $this->forge->addKey(['branch_id', 'or_number']);
        $this->forge->createTable('legacy_payments', true);

        // 3. Remittances ---------------------------------------------------
        $this->forge->addField($id('legacy_remittance_id') + [
            'plan_holder_id'   => ['type' => 'INT', 'constraint' => 11],
            'branch_id'        => ['type' => 'INT', 'constraint' => 11],
            'remittance_date'  => ['type' => 'DATE'],
            'collector_id'     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'collector_name'   => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'amount'           => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'reference_number' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'covers_payments'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'remarks'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ] + $audit);
        $this->forge->addKey('legacy_remittance_id', true);
        $this->forge->addKey(['plan_holder_id', 'record_status']);
        $this->forge->createTable('legacy_remittances', true);

        // 4. Services / claims already availed -----------------------------
        $this->forge->addField($id('legacy_service_id') + [
            'plan_holder_id' => ['type' => 'INT', 'constraint' => 11],
            'branch_id'      => ['type' => 'INT', 'constraint' => 11],
            'service_name'   => ['type' => 'VARCHAR', 'constraint' => 150],
            'service_date'   => ['type' => 'DATE'],
            'service_status' => ['type' => 'ENUM', 'constraint' => ['completed', 'ongoing', 'cancelled'], 'default' => 'completed'],
            'amount'         => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            'remarks'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ] + $audit);
        $this->forge->addKey('legacy_service_id', true);
        $this->forge->addKey(['plan_holder_id', 'record_status']);
        $this->forge->createTable('legacy_services', true);

        // 5. Documents (files live under WRITEPATH, never the public folder)
        $this->forge->addField($id('legacy_document_id') + [
            'plan_holder_id' => ['type' => 'INT', 'constraint' => 11],
            'branch_id'      => ['type' => 'INT', 'constraint' => 11],
            'doc_type'       => ['type' => 'ENUM', 'constraint' => ['ledger_card', 'contract', 'receipt', 'id', 'other'], 'default' => 'other'],
            'original_name'  => ['type' => 'VARCHAR', 'constraint' => 200],
            'stored_name'    => ['type' => 'VARCHAR', 'constraint' => 120],
            'mime_type'      => ['type' => 'VARCHAR', 'constraint' => 80],
            'file_size'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
        ] + $audit);
        $this->forge->addKey('legacy_document_id', true);
        $this->forge->addKey(['plan_holder_id', 'record_status']);
        $this->forge->createTable('legacy_documents', true);

        // 6. Notes ---------------------------------------------------------
        $this->forge->addField($id('legacy_note_id') + [
            'plan_holder_id' => ['type' => 'INT', 'constraint' => 11],
            'branch_id'      => ['type' => 'INT', 'constraint' => 11],
            'note'           => ['type' => 'TEXT'],
        ] + $audit);
        $this->forge->addKey('legacy_note_id', true);
        $this->forge->addKey(['plan_holder_id', 'record_status']);
        $this->forge->createTable('legacy_notes', true);

        // 7. Beneficiaries encoded from history go in the normal table, tagged.
        if (! $this->db->fieldExists('contact_number', 'beneficiaries')) {
            $this->forge->addColumn('beneficiaries', [
                'contact_number' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'source'         => ['type' => 'ENUM', 'constraint' => ['registration', 'legacy_manual'], 'default' => 'registration'],
                'encoded_by'     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'encoded_at'     => ['type' => 'DATETIME', 'null' => true],
            ]);
        }
    }

    public function down()
    {
        foreach (['legacy_notes', 'legacy_documents', 'legacy_services', 'legacy_remittances', 'legacy_payments', 'legacy_plan_records'] as $table) {
            $this->forge->dropTable($table, true);
        }

        foreach (['contact_number', 'source', 'encoded_by', 'encoded_at'] as $column) {
            if ($this->db->fieldExists($column, 'beneficiaries')) {
                $this->forge->dropColumn('beneficiaries', $column);
            }
        }
    }
}
