<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreatePendingPlanHolderRegistrations extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('pending_plan_holder_registrations')) {
            return;
        }

        $this->forge->addField([
            'pending_registration_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type' => 'INT',
            ],
            'branch_id' => [
                'type' => 'INT',
            ],
            'address_no' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
            ],
            'address_street' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'address_barangay' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'address_city' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'date_of_birth' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'place_of_birth' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'age' => [
                'type' => 'INT',
                'null' => true,
            ],
            'gender' => [
                'type' => 'VARCHAR',
                'constraint' => 10,
                'null' => true,
            ],
            'civil_status' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
            ],
            'citizenship' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
            ],
            'height' => [
                'type' => 'DECIMAL',
                'constraint' => '5,2',
                'null' => true,
            ],
            'weight' => [
                'type' => 'DECIMAL',
                'constraint' => '5,2',
                'null' => true,
            ],
            'spouse_name' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'spouse_birthdate' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'spouse_occupation' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'senior_citizen_id' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
            ],
            'organization_affiliation' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['pending', 'approved', 'rejected'],
                'default' => 'pending',
            ],
            'rejection_notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'reviewed_by' => [
                'type' => 'INT',
                'null' => true,
            ],
            'reviewed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addKey('pending_registration_id', true);
        $this->forge->addKey('user_id');
        $this->forge->addKey('branch_id');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('user_id', 'users', 'user_id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'branch_id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('reviewed_by', 'users', 'user_id', 'SET NULL', 'CASCADE');

        $this->forge->createTable('pending_plan_holder_registrations', true);
    }

    public function down()
    {
        $this->forge->dropTable('pending_plan_holder_registrations', true);
    }
}
