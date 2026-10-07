<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMembershipProgramsTable extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('membership_programs')) {
            $this->forge->addField([
                'program_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'program_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 150,
                ],
                'monthly_fee' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,2',
                    'default' => 240.00,
                ],
                'is_active' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 1,
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
            $this->forge->addKey('program_id', true);
            $this->forge->createTable('membership_programs', true);
        }

        $exists = $this->db->table('membership_programs')
            ->select('program_id')
            ->limit(1)
            ->get()
            ->getRowArray();

        if (! $exists) {
            $this->db->table('membership_programs')->insert([
                'program_name' => 'Damayan Burial Program',
                'monthly_fee' => 240.00,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('membership_programs')) {
            $this->forge->dropTable('membership_programs', true);
        }
    }
}
