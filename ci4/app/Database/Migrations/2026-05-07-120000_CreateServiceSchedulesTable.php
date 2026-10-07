<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateServiceSchedulesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'schedule_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'service_application_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'service_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'service_time' => [
                'type' => 'TIME',
                'null' => true,
            ],
            'branch_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['pending', 'scheduled', 'ongoing', 'completed', 'cancelled'],
                'default' => 'pending',
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

        $this->forge->addKey('schedule_id', true);
        $this->forge->createTable('service_schedules');
    }

    public function down()
    {
        $this->forge->dropTable('service_schedules');
    }
}
