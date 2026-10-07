<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Claim form redesign: each uploaded file is tagged with what it is
 * (death_certificate | beneficiary_id | other), and a claim submitted without
 * a death certificate is flagged so staff know to chase it up. The
 * certificate doesn't block submission, so this flag is the only way staff
 * can see it.
 */
class AddClaimDocumentTypeAndDeathCertificateFlag extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('service_applications') && ! $this->db->fieldExists('awaiting_death_certificate', 'service_applications')) {
            $this->forge->addColumn('service_applications', [
                'awaiting_death_certificate' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'unsigned' => true,
                    'null' => false,
                    'default' => 0,
                    'after' => 'application_amount',
                ],
            ]);
        }

        if ($this->db->tableExists('service_application_documents') && ! $this->db->fieldExists('document_type', 'service_application_documents')) {
            $this->forge->addColumn('service_application_documents', [
                'document_type' => [
                    'type' => 'VARCHAR',
                    'constraint' => 30,
                    'null' => true,
                    'after' => 'application_id',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('service_applications') && $this->db->fieldExists('awaiting_death_certificate', 'service_applications')) {
            $this->forge->dropColumn('service_applications', 'awaiting_death_certificate');
        }

        if ($this->db->tableExists('service_application_documents') && $this->db->fieldExists('document_type', 'service_application_documents')) {
            $this->forge->dropColumn('service_application_documents', 'document_type');
        }
    }
}
