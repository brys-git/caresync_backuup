<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPaymentTypeToPayments extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('payments')) {
            return;
        }

        // Check if payments.payment_type already exists
        $fields = $this->db->getFieldData('payments');
        $hasPaymentType = false;
        foreach ($fields as $field) {
            if ($field->name === 'payment_type') {
                $hasPaymentType = true;
                break;
            }
        }

        if ($hasPaymentType) {
            return;
        }

        $this->forge->addColumn('payments', [
            'payment_type' => [
                'type' => 'ENUM',
                'constraint' => ['initial_registration', 'monthly_contribution', 'service_payment', 'addon_payment'],
                'default' => 'monthly_contribution',
                'null' => false,
                'comment' => 'Payment classification for reporting and filtering',
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->tableExists('payments')) {
            $this->forge->dropColumn('payments', 'payment_type');
        }
    }
}
