<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddVerificationStatusToBeneficiaries extends Migration
{
    public function up()
    {
        $this->forge->addColumn('beneficiaries', [
            'verification_status' => [
                'type' => 'ENUM',
                'constraint' => ['pending', 'verified', 'rejected'],
                'default' => 'pending',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('beneficiaries', ['verification_status']);
    }
}
