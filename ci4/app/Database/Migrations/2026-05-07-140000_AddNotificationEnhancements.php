<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddNotificationEnhancements extends Migration
{
    public function up()
    {
        // Check if notifications table exists and add missing columns
        if ($this->db->tableExists('notifications')) {
            $fields = $this->db->getFieldData('notifications');
            $fieldNames = array_column($fields, 'name');

            // Add notification type column if it doesn't exist
            if (! in_array('notification_type', $fieldNames, true)) {
                $this->forge->addColumn('notifications', [
                    'notification_type' => [
                        'type' => 'ENUM',
                        'constraint' => ['payment', 'membership', 'service', 'schedule', 'system'],
                        'default' => 'system',
                        'after' => 'message',
                    ],
                ]);
            }

            // Add is_read column if it doesn't exist
            if (! in_array('is_read', $fieldNames, true)) {
                $this->forge->addColumn('notifications', [
                    'is_read' => [
                        'type' => 'BOOLEAN',
                        'default' => false,
                        'after' => 'notification_type',
                    ],
                ]);
            }

            // Add priority column if it doesn't exist
            if (! in_array('priority', $fieldNames, true)) {
                $this->forge->addColumn('notifications', [
                    'priority' => [
                        'type' => 'ENUM',
                        'constraint' => ['low', 'normal', 'high', 'urgent'],
                        'default' => 'normal',
                        'after' => 'is_read',
                    ],
                ]);
            }

            // Add read_at column if it doesn't exist
            if (! in_array('read_at', $fieldNames, true)) {
                $this->forge->addColumn('notifications', [
                    'read_at' => [
                        'type' => 'DATETIME',
                        'null' => true,
                        'after' => 'priority',
                    ],
                ]);
            }

            // Add archived column if it doesn't exist
            if (! in_array('is_archived', $fieldNames, true)) {
                $this->forge->addColumn('notifications', [
                    'is_archived' => [
                        'type' => 'BOOLEAN',
                        'default' => false,
                        'after' => 'read_at',
                    ],
                ]);
            }
        } else {
            // Create notifications table if it doesn't exist
            $this->forge->addField([
                'notification_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'user_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                ],
                'message' => [
                    'type' => 'TEXT',
                ],
                'notification_type' => [
                    'type' => 'ENUM',
                    'constraint' => ['payment', 'membership', 'service', 'schedule', 'system'],
                    'default' => 'system',
                ],
                'is_read' => [
                    'type' => 'BOOLEAN',
                    'default' => false,
                ],
                'priority' => [
                    'type' => 'ENUM',
                    'constraint' => ['low', 'normal', 'high', 'urgent'],
                    'default' => 'normal',
                ],
                'read_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'is_archived' => [
                    'type' => 'BOOLEAN',
                    'default' => false,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'default' => date('Y-m-d H:i:s'),
                ],
            ]);
            $this->forge->addKey('notification_id', true);
            $this->forge->addKey('user_id');
            $this->forge->addKey('is_read');
            $this->forge->addKey('notification_type');
            $this->forge->createTable('notifications');
        }
    }

    public function down()
    {
        // Remove added columns if rolling back
        if ($this->db->tableExists('notifications')) {
            $fields = $this->db->getFieldData('notifications');
            $fieldNames = array_column($fields, 'name');

            if (in_array('notification_type', $fieldNames, true)) {
                $this->forge->dropColumn('notifications', 'notification_type');
            }
            if (in_array('is_read', $fieldNames, true)) {
                $this->forge->dropColumn('notifications', 'is_read');
            }
            if (in_array('priority', $fieldNames, true)) {
                $this->forge->dropColumn('notifications', 'priority');
            }
            if (in_array('read_at', $fieldNames, true)) {
                $this->forge->dropColumn('notifications', 'read_at');
            }
            if (in_array('is_archived', $fieldNames, true)) {
                $this->forge->dropColumn('notifications', 'is_archived');
            }
        }
    }
}
