<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateActivityLogsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('activity_logs')) {
            return;
        }

        $this->forge->addField([
            'log_id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => true,
                'comment' => 'User who performed the action',
            ],
            'action' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => false,
                'comment' => 'Action performed (approved, rejected, created, updated, deleted)',
            ],
            'module' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => false,
                'comment' => 'Module affected (payment, service, package, plan_holder)',
            ],
            'target_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => true,
                'comment' => 'ID of the record affected',
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'Detailed description of the action',
            ],
            'old_values' => [
                'type' => 'JSON',
                'null' => true,
                'comment' => 'Previous values of modified fields',
            ],
            'new_values' => [
                'type' => 'JSON',
                'null' => true,
                'comment' => 'New values of modified fields',
            ],
            'ip_address' => [
                'type' => 'VARCHAR',
                'constraint' => 45,
                'null' => true,
                'comment' => 'IP address of the user',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addKey('log_id', true);
        $this->forge->addKey('user_id');
        $this->forge->addKey('module');
        $this->forge->addKey('target_id');
        $this->forge->addKey('created_at');
        $this->forge->createTable('activity_logs');
    }

    public function down()
    {
        $this->forge->dropTable('activity_logs');
    }
}
