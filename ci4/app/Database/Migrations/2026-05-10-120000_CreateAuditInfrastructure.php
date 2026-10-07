<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuditInfrastructure extends Migration
{
    public function up()
    {
        // Create audit logs table
        if (! db_connect()->tableExists('audit_logs')) {
            $this->forge->addField([
            'log_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'auto_increment' => true,
            ],
            'table_name' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => false,
            ],
            'record_id' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => false,
            ],
            'action' => [
                'type' => 'ENUM',
                'constraint' => ['INSERT', 'UPDATE', 'DELETE'],
                'null' => false,
            ],
            'old_values' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'new_values' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'changed_by' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
            ],
            'ip_address' => [
                'type' => 'VARCHAR',
                'constraint' => 45,
                'null' => true,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'changed_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
                'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addPrimaryKey('log_id');
        $this->forge->addForeignKey('changed_by', 'users', 'user_id', 'CASCADE', 'SET NULL');
        $this->forge->addKey('table_name');
        $this->forge->addKey('changed_at');
        $this->forge->addKey(['table_name', 'record_id']);
            $this->forge->createTable('audit_logs');
        }

        // Create payment transactions table
        if (! db_connect()->tableExists('payment_transactions')) {
            $this->forge->addField([
            'transaction_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'auto_increment' => true,
            ],
            'payment_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => false,
            ],
            'old_status' => [
                'type' => 'ENUM',
                'constraint' => ['pending', 'completed', 'failed', 'refunded', 'cancelled'],
                'null' => false,
            ],
            'new_status' => [
                'type' => 'ENUM',
                'constraint' => ['pending', 'completed', 'failed', 'refunded', 'cancelled'],
                'null' => false,
            ],
            'reason' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'changed_by' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
            ],
            'ip_address' => [
                'type' => 'VARCHAR',
                'constraint' => 45,
                'null' => true,
            ],
            'transitioned_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
                'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addPrimaryKey('transaction_id');
        $this->forge->addForeignKey('payment_id', 'payments', 'payment_id', 'CASCADE');
        $this->forge->addForeignKey('changed_by', 'users', 'user_id', 'SET NULL', 'SET NULL');
        $this->forge->addKey('payment_id');
        $this->forge->addKey('transitioned_at');
            $this->forge->createTable('payment_transactions');
        }

        // Create service logs table
        if (! db_connect()->tableExists('service_logs')) {
            $this->forge->addField([
            'log_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'auto_increment' => true,
            ],
            'service_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => false,
            ],
            'old_status' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => false,
            ],
            'new_status' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => false,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'changed_by' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
            ],
            'ip_address' => [
                'type' => 'VARCHAR',
                'constraint' => 45,
                'null' => true,
            ],
            'logged_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
                'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addPrimaryKey('log_id');
        $this->forge->addForeignKey('service_id', 'services', 'service_id', 'CASCADE');
        $this->forge->addForeignKey('changed_by', 'users', 'user_id', 'SET NULL', 'SET NULL');
        $this->forge->addKey('service_id');
        $this->forge->addKey('logged_at');
            $this->forge->createTable('service_logs');
        }

        // Create email logs table
        if (! db_connect()->tableExists('email_logs')) {
            $this->forge->addField([
            'email_log_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'auto_increment' => true,
            ],
            'recipient' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => false,
            ],
            'subject' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => false,
            ],
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['sent', 'failed', 'bounced'],
                'null' => false,
            ],
            'error_message' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
            ],
            'sent_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
                'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addPrimaryKey('email_log_id');
        $this->forge->addForeignKey('user_id', 'users', 'user_id', 'SET NULL', 'SET NULL');
        $this->forge->addKey('recipient');
        $this->forge->addKey('status');
        $this->forge->addKey('sent_at');
            $this->forge->createTable('email_logs');
        }
    }

    public function down()
    {
        $this->forge->dropTable('email_logs', true);
        $this->forge->dropTable('service_logs', true);
        $this->forge->dropTable('payment_transactions', true);
        $this->forge->dropTable('audit_logs', true);
    }
}
