<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateResourceAssignmentsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'assignment_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'schedule_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'staff_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'vehicle_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'resource_type' => [
                'type' => 'ENUM',
                'constraint' => ['staff', 'vehicle', 'equipment'],
                'default' => 'staff',
            ],
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['assigned', 'in_use', 'completed', 'cancelled'],
                'default' => 'assigned',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('assignment_id', true);
        $this->forge->createTable('resource_assignments');
    }

    public function down()
    {
        $this->forge->dropTable('resource_assignments');
    }
}
