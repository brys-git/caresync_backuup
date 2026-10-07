<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The GCash account (number + QR) a collector receives client payments on.
 * Clients pay advance payments via GCash only, but nothing ever told them
 * WHERE to send the money - the collector assigned to their barangay
 * (collector_assignments) is who verifies the reference number against their
 * own GCash history, so that collector's account is the one the client's
 * Make Payment page shows. One row per collector; QR image lives under
 * WRITEPATH/uploads/gcash_qr/ like every other upload in this app.
 */
class CreateCollectorGcashAccounts extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('collector_gcash_accounts')) {
            return;
        }

        $this->forge->addField([
            'gcash_account_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            // Signed to match users.user_id exactly, or MySQL refuses the FK (errno 150).
            'user_id'          => ['type' => 'INT', 'constraint' => 11],
            'gcash_number'     => ['type' => 'VARCHAR', 'constraint' => 20],
            'account_name'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'qr_path'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('gcash_account_id', true);
        $this->forge->addUniqueKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'user_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('collector_gcash_accounts');
    }

    public function down()
    {
        $this->forge->dropTable('collector_gcash_accounts', true);
    }
}
