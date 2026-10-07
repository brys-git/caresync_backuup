<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the Collector role (panel brief, section 6: "Add a Collector account type
 * in user management").
 *
 * `roles` predates CodeIgniter's migration system entirely — it was created by
 * hand and no migration in this project's history builds it, so a fresh
 * environment has no way to get it. This migration both formalizes the table
 * (guarded, so it's a no-op anywhere it already exists) and adds role_id 5.
 * role_id is left to auto-increment on the fresh-create path and stated
 * explicitly on the existing-table path, so both converge on the same ids:
 * 1=Admin, 2=Branch Admin, 3=Staff, 4=Plan Holder, 5=Collector.
 */
class AddCollectorRole extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('roles')) {
            $this->forge->addField([
                'role_id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'auto_increment' => true,
                ],
                'role_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                ],
            ]);
            $this->forge->addPrimaryKey('role_id');
            $this->forge->createTable('roles', true);

            $this->db->table('roles')->insertBatch([
                ['role_name' => 'Admin'],
                ['role_name' => 'Branch Admin'],
                ['role_name' => 'Staff'],
                ['role_name' => 'Plan Holder'],
            ]);
        }

        $exists = $this->db->table('roles')->where('role_id', 5)->get()->getRowArray();
        if (! $exists) {
            $this->db->table('roles')->insert([
                'role_id'   => 5,
                'role_name' => 'Collector',
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('roles')) {
            $this->db->table('roles')->where('role_id', 5)->delete();
        }
    }
}
