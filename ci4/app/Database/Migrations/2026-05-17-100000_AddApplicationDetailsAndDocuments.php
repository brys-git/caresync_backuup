<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddApplicationDetailsAndDocuments extends Migration
{
    public function up()
    {
        // add columns to service_applications if not present
        if ($this->db->tableExists('service_applications')) {
            $fields = [];
            if (! $this->db->fieldExists('deceased_name', 'service_applications')) {
                $fields['deceased_name'] = [
                    'type' => 'VARCHAR',
                    'constraint' => 150,
                    'null' => true,
                ];
            }
            if (! $this->db->fieldExists('deceased_date_of_death', 'service_applications')) {
                $fields['deceased_date_of_death'] = [
                    'type' => 'DATE',
                    'null' => true,
                ];
            }
            if (! $this->db->fieldExists('deceased_address', 'service_applications')) {
                $fields['deceased_address'] = [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                ];
            }
            if (! $this->db->fieldExists('relationship_to_deceased', 'service_applications')) {
                $fields['relationship_to_deceased'] = [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                ];
            }
            if (! $this->db->fieldExists('beneficiary_name', 'service_applications')) {
                $fields['beneficiary_name'] = [
                    'type' => 'VARCHAR',
                    'constraint' => 150,
                    'null' => true,
                ];
            }
            if (! $this->db->fieldExists('beneficiary_contact', 'service_applications')) {
                $fields['beneficiary_contact'] = [
                    'type' => 'VARCHAR',
                    'constraint' => 50,
                    'null' => true,
                ];
            }
            if (! $this->db->fieldExists('application_notes', 'service_applications')) {
                $fields['application_notes'] = [
                    'type' => 'TEXT',
                    'null' => true,
                ];
            }

            if (! empty($fields)) {
                $this->forge->addColumn('service_applications', $fields);
            }
        }

        // create documents table
        if (! $this->db->tableExists('service_application_documents')) {
            $this->db->query(
                "CREATE TABLE `service_application_documents` (
                    `document_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `application_id` INT UNSIGNED NOT NULL,
                    `filename` VARCHAR(255) NOT NULL,
                    `original_name` VARCHAR(255) NOT NULL,
                    `mime_type` VARCHAR(100) NULL,
                    `path` VARCHAR(500) NOT NULL,
                    `uploaded_by` INT UNSIGNED NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`document_id`),
                    KEY `idx_app_doc_application` (`application_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
            );
        }
    }

    public function down()
    {
        if ($this->db->tableExists('service_application_documents')) {
            $this->db->query('DROP TABLE `service_application_documents`');
        }

        if ($this->db->tableExists('service_applications')) {
            $cols = ['deceased_name','deceased_date_of_death','deceased_address','relationship_to_deceased','beneficiary_name','beneficiary_contact','application_notes'];
            foreach ($cols as $col) {
                if ($this->db->fieldExists($col, 'service_applications')) {
                    $this->forge->dropColumn('service_applications', $col);
                }
            }
        }
    }
}
