<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Collector capability (part B): which area a collector is responsible for.
 * users.is_collector (or role_id 5) only says WHETHER someone collects: this
 * table says WHERE. One row per collector per assigned area, so a collector
 * can cover more than one barangay if needed and a branch admin can see the
 * whole assignment map for their branch.
 *
 * barangay_code is nullable: a branch-wide collector (no area narrowing yet)
 * has a row with branch_id set and barangay_code null, rather than no row at
 * all - that keeps "is this user a collector for this branch" a single query
 * shape whether or not area assignment has been done yet.
 *
 * Caveat inherited from SQL, not a bug here: MySQL treats every NULL as
 * distinct for a unique index, so uniq_collector_area does NOT stop two
 * branch-wide (barangay_code NULL) rows for the same user+branch - only
 * barangay-specific duplicates are actually blocked by the DB. Any code that
 * inserts a branch-wide assignment must check-then-insert itself.
 */
class CreateCollectorAssignments extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('collector_assignments')) {
            return;
        }

        $this->forge->addField([
            'assignment_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            // NOT unsigned, deliberately: a foreign key's column must match
            // the referenced column exactly, and users.user_id/
            // branches.branch_id are both plain signed INT(11) - mismatched
            // signedness is exactly what errno 150 ("Foreign key constraint
            // is incorrectly formed") means, hit and fixed while running
            // this migration for real.
            'user_id' => [
                'type'     => 'INT',
                'constraint' => 11,
            ],
            'branch_id' => [
                'type'     => 'INT',
                'constraint' => 11,
            ],
            'barangay_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'assigned_at' => [
                'type' => 'DATETIME',
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
            ],
        ]);

        $this->forge->addPrimaryKey('assignment_id');
        $this->forge->addKey('user_id');
        $this->forge->addKey('branch_id');
        $this->forge->addKey('barangay_code');
        // A collector can't have the same area assigned twice.
        $this->forge->addUniqueKey(['user_id', 'branch_id', 'barangay_code'], 'uniq_collector_area');
        $this->forge->addForeignKey('user_id', 'users', 'user_id', '', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'branch_id', '', 'CASCADE');
        $this->forge->createTable('collector_assignments');
    }

    public function down()
    {
        $this->forge->dropTable('collector_assignments', true);
    }
}
