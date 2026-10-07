<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCashPaymentRecords extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'cash_record_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'branch_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'client_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'months_covered' => [
                'type' => 'INT',
                'constraint' => 3,
                'default' => 1,
            ],
            'amount' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
            ],
            'receipt_number' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'unique' => true,
            ],
            'recorded_by' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'recorded_date' => [
                'type' => 'DATE',
            ],
            'verified' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
            ],
            'verified_date' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('cash_record_id', true);
        $this->forge->addKey('branch_id');
        // receipt_number already gets a unique index from 'unique' => true above;
        // addKey() here would try to create a second index with the same
        // auto-generated name ("Duplicate key name 'receipt_number'").
        $this->forge->addKey('verified');
        $this->forge->createTable('cash_payment_records');
    }

    public function down()
    {
        $this->forge->dropTable('cash_payment_records');
    }
}
