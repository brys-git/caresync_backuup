<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTypeToNotifications extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('notifications')) {
            return;
        }

        // Check if notifications.type already exists
        $fields = $this->db->getFieldData('notifications');
        $hasType = false;
        foreach ($fields as $field) {
            if ($field->name === 'type') {
                $hasType = true;
                break;
            }
        }

        if ($hasType) {
            return;
        }

        $this->forge->addColumn('notifications', [
            'type' => [
                'type' => 'ENUM',
                'constraint' => ['payment_approved', 'payment_rejected', 'service_approved', 'service_rejected', 'registration_pending', 'service_completed', 'general'],
                'default' => 'general',
                'null' => false,
                'after' => 'message',
                'comment' => 'Notification classification for filtering and display',
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->tableExists('notifications')) {
            $this->forge->dropColumn('notifications', 'type');
        }
    }
}
