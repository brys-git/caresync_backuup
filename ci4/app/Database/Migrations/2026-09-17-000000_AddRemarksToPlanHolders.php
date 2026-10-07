<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Collection List remarks: a persistent free-text note per plan holder that
 * a collector or branch admin can leave while working the collection list
 * (e.g. "not home, try weekends", "promised to pay by the 25th"). One field,
 * overwritten on each save - no per-visit history, matching the simple
 * "sticky note on the client" scope this feature was asked for.
 */
class AddRemarksToPlanHolders extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('remarks', 'plan_holders')) {
            $this->forge->addColumn('plan_holders', [
                'remarks' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('remarks', 'plan_holders')) {
            $this->forge->dropColumn('plan_holders', 'remarks');
        }
    }
}
