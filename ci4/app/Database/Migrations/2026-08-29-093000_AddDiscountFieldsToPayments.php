<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Panel brief, section 3: "Add a discounted payment option for advance/bulk
 * payments." Purely additive — nothing reads these columns yet; the Advance
 * Payment form/table rework (next phase) is what populates and displays
 * them.
 */
class AddDiscountFieldsToPayments extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('payments')) {
            return;
        }

        $fields = array_map(static fn ($field) => $field->name, $this->db->getFieldData('payments'));

        $newColumns = [];
        if (! in_array('discount_amount', $fields, true)) {
            $newColumns['discount_amount'] = [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => true,
                'default'    => 0,
            ];
        }
        if (! in_array('discount_reason', $fields, true)) {
            $newColumns['discount_reason'] = [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ];
        }

        if ($newColumns !== []) {
            $this->forge->addColumn('payments', $newColumns);
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('payments')) {
            return;
        }

        $fields = array_map(static fn ($field) => $field->name, $this->db->getFieldData('payments'));
        $dropColumns = array_values(array_intersect(['discount_amount', 'discount_reason'], $fields));

        if ($dropColumns !== []) {
            $this->forge->dropColumn('payments', $dropColumns);
        }
    }
}
