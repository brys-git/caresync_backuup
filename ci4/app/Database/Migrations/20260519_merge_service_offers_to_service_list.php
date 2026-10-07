<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class MergeServiceOffersToServiceList extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // If there is no service_offers table, nothing to do
        if (! $db->tableExists('service_offers')) {
            return;
        }

        // Ensure service_list exists. If not, create a minimal schema compatible with existing usage.
        if (! $db->tableExists('service_list')) {
            $this->forge->addField([
                'service_list_id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'service_name' => ['type' => 'VARCHAR', 'constraint' => 100],
                'description' => ['type' => 'TEXT', 'null' => true],
                'base_price' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
                'status' => ['type' => "ENUM('active','inactive')", 'default' => 'active'],
                'is_available' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at' => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP'],
                'updated_at' => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP'],
            ]);
            $this->forge->addKey('service_list_id', true);
            $this->forge->createTable('service_list');
        }

        // Copy rows from service_offers into service_list (map columns)
        // Use COALESCE on base_price to avoid NULL issues; set is_available = 1 for imported offers.
        $db->query("INSERT INTO service_list (service_name, `description`, base_price, `status`, is_available, created_at, updated_at)
                    SELECT service_name, `description`, COALESCE(base_price, 0.00), `status`, 1, created_at, created_at
                    FROM service_offers");

        // Drop the old table
        $this->forge->dropTable('service_offers', true);
    }

    public function down()
    {
        // Recreate service_offers (best-effort) so the migration is reversible.
        if (! db_connect()->tableExists('service_offers')) {
            $this->forge->addField([
                'offer_id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'service_name' => ['type' => 'VARCHAR', 'constraint' => 100],
                'description' => ['type' => 'TEXT', 'null' => true],
                'base_price' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
                'status' => ['type' => "ENUM('active','inactive')", 'default' => 'active'],
                'created_at' => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP'],
            ]);
            $this->forge->addKey('offer_id', true);
            $this->forge->createTable('service_offers');
        }

        // Move back rows that look like imported offers (best-effort): those where updated_at = created_at
        $db = \Config\Database::connect();
        if ($db->tableExists('service_list')) {
            $db->query("INSERT INTO service_offers (service_name, `description`, base_price, `status`, created_at)
                        SELECT service_name, `description`, base_price, `status`, created_at
                        FROM service_list
                        WHERE created_at = updated_at");
        }
    }
}
