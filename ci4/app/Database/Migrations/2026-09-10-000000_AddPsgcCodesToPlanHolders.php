<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * PSGC Cloud address integration for the Applicant Information form
 * (client/plan_registration.php). plan_holders.address_barangay and
 * .address_city already hold the human-readable barangay/city names -
 * reused as-is, not duplicated. This only adds the two PSGC codes needed
 * to validate the Town/City -> Barangay relationship server-side and to
 * restore the saved selection when an applicant re-opens the form to edit.
 */
class AddPsgcCodesToPlanHolders extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('plan_holders')) {
            return;
        }

        $newColumns = [];

        if (! $this->db->fieldExists('city_municipality_code', 'plan_holders')) {
            $newColumns['city_municipality_code'] = [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'after'      => 'address_city',
            ];
        }

        if (! $this->db->fieldExists('barangay_code', 'plan_holders')) {
            $newColumns['barangay_code'] = [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'after'      => 'address_barangay',
            ];
        }

        if (! empty($newColumns)) {
            $this->forge->addColumn('plan_holders', $newColumns);
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('plan_holders')) {
            return;
        }

        foreach (['city_municipality_code', 'barangay_code'] as $column) {
            if ($this->db->fieldExists($column, 'plan_holders')) {
                $this->forge->dropColumn('plan_holders', $column);
            }
        }
    }
}
