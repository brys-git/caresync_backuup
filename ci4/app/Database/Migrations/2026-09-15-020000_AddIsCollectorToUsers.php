<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Collector capability (Collector feature, part A): a Staff member (role_id 3)
 * can also be a collector without a second account, so cash they record still
 * carries their own user_id in the audit trail. Mirrors the existing
 * users.is_plan_holder TINYINT(1) pattern - same shape, same "one account,
 * multiple hats" idea.
 *
 * role_id 5 (Collector, see AddCollectorRole) is unaffected: a pure Collector
 * account doesn't need this flag set to pass can_collect() - see
 * caresync_helper.php.
 */
class AddIsCollectorToUsers extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('is_collector', 'users')) {
            $this->forge->addColumn('users', [
                'is_collector' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                    'null'       => false,
                    'after'      => 'is_plan_holder',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('is_collector', 'users')) {
            $this->forge->dropColumn('users', 'is_collector');
        }
    }
}
