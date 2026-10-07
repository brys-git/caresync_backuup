<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateBranchManagementApprovalWorkflow extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('is_available', 'service_list')) {
            $this->db->query('ALTER TABLE service_list ADD COLUMN is_available TINYINT(1) NOT NULL DEFAULT 1 AFTER status');
        }

        if (! $this->db->fieldExists('is_available', 'packages')) {
            $this->db->query('ALTER TABLE packages ADD COLUMN is_available TINYINT(1) NOT NULL DEFAULT 1 AFTER is_customizable');
        }

        if (! $this->db->tableExists('pending_services')) {
            $this->forge->addField([
                'pending_service_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'service_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                ],
                'description' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'base_price' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,2',
                    'default' => '0.00',
                ],
                'requested_status' => [
                    'type' => 'ENUM',
                    'constraint' => ['active', 'inactive'],
                    'default' => 'active',
                ],
                'created_by' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                ],
                'status' => [
                    'type' => 'ENUM',
                    'constraint' => ['pending', 'approved', 'rejected'],
                    'default' => 'pending',
                ],
                'created_at' => [
                    'type' => 'TIMESTAMP',
                    'null' => false,
                    'default' => new RawSql('CURRENT_TIMESTAMP'),
                ],
            ]);

            $this->forge->addKey('pending_service_id', true);
            $this->forge->addKey('created_by');
            $this->forge->createTable('pending_services', true);
        }

        if (! $this->db->tableExists('pending_packages')) {
            $this->forge->addField([
                'pending_package_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'package_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                ],
                'description' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'base_price' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,2',
                    'default' => '0.00',
                ],
                'is_customizable' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 1,
                ],
                'initial_effective_date' => [
                    'type' => 'DATE',
                    'null' => true,
                ],
                'service_list_ids' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_by' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                ],
                'status' => [
                    'type' => 'ENUM',
                    'constraint' => ['pending', 'approved', 'rejected'],
                    'default' => 'pending',
                ],
                'created_at' => [
                    'type' => 'TIMESTAMP',
                    'null' => false,
                    'default' => new RawSql('CURRENT_TIMESTAMP'),
                ],
            ]);

            $this->forge->addKey('pending_package_id', true);
            $this->forge->addKey('created_by');
            $this->forge->createTable('pending_packages', true);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('pending_packages')) {
            $this->forge->dropTable('pending_packages', true);
        }

        if ($this->db->tableExists('pending_services')) {
            $this->forge->dropTable('pending_services', true);
        }

        if ($this->db->fieldExists('is_available', 'packages')) {
            $this->db->query('ALTER TABLE packages DROP COLUMN is_available');
        }

        if ($this->db->fieldExists('is_available', 'service_list')) {
            $this->db->query('ALTER TABLE service_list DROP COLUMN is_available');
        }
    }
}