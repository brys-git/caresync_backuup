<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateServiceSchedulingTablesExpanded extends Migration
{
    public function up()
    {
        // Hearses Table
        if (! $this->db->tableExists('hearses')) {
            $this->forge->addField([
                'hearse_id' => [
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
                'hearse_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => '100',
                ],
                'plate_number' => [
                    'type' => 'VARCHAR',
                    'constraint' => '20',
                    'unique' => true,
                ],
                'model_year' => [
                    'type' => 'INT',
                    'constraint' => 4,
                    'unsigned' => true,
                    'null' => true,
                ],
                'capacity' => [
                    'type' => 'INT',
                    'constraint' => 3,
                    'unsigned' => true,
                    'default' => 1,
                ],
                'status' => [
                    'type' => 'ENUM',
                    'constraint' => ['available', 'unavailable', 'maintenance', 'retired'],
                    'default' => 'available',
                ],
                'last_maintenance' => [
                    'type' => 'DATE',
                    'null' => true,
                ],
                'remarks' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
            ]);
            $this->forge->addKey('hearse_id', true);
            $this->forge->addKey('branch_id');
            $this->forge->addKey('status');
            $this->forge->createTable('hearses');
        }

        // Embalmers Table
        if (! $this->db->tableExists('embalmers')) {
            $this->forge->addField([
                'embalmer_id' => [
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
                'first_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => '50',
                ],
                'last_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => '50',
                ],
                'license_number' => [
                    'type' => 'VARCHAR',
                    'constraint' => '50',
                    'null' => true,
                ],
                'license_expiry' => [
                    'type' => 'DATE',
                    'null' => true,
                ],
                'contact_number' => [
                    'type' => 'VARCHAR',
                    'constraint' => '30',
                    'null' => true,
                ],
                'status' => [
                    'type' => 'ENUM',
                    'constraint' => ['available', 'busy', 'unavailable', 'inactive'],
                    'default' => 'available',
                ],
                'experience_years' => [
                    'type' => 'INT',
                    'constraint' => 3,
                    'unsigned' => true,
                    'null' => true,
                ],
                'remarks' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
            ]);
            $this->forge->addKey('embalmer_id', true);
            $this->forge->addKey('branch_id');
            $this->forge->addKey('status');
            $this->forge->createTable('embalmers');
        }

        // Staff Schedules Table
        if (! $this->db->tableExists('staff_schedules')) {
            $this->forge->addField([
                'schedule_id' => [
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
                'branch_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                ],
                'schedule_date' => [
                    'type' => 'DATE',
                ],
                'start_time' => [
                    'type' => 'TIME',
                ],
                'end_time' => [
                    'type' => 'TIME',
                ],
                'duty_type' => [
                    'type' => 'ENUM',
                    'constraint' => ['regular', 'on-call', 'training', 'meeting', 'other'],
                    'default' => 'regular',
                ],
                'status' => [
                    'type' => 'ENUM',
                    'constraint' => ['scheduled', 'assigned', 'completed', 'cancelled'],
                    'default' => 'scheduled',
                ],
                'remarks' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
            ]);
            $this->forge->addKey('schedule_id', true);
            $this->forge->addKey('user_id');
            $this->forge->addKey('branch_id');
            $this->forge->addKey('schedule_date');
            $this->forge->addKey('status');
            $this->forge->createTable('staff_schedules');
        }

        // Service Calendar (Funeral Events) Table
        if (! $this->db->tableExists('service_calendar')) {
            $this->forge->addField([
                'calendar_id' => [
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
                'service_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                ],
                'plan_holder_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                ],
                'event_type' => [
                    'type' => 'ENUM',
                    'constraint' => ['funeral', 'viewing', 'burial', 'other'],
                    'default' => 'funeral',
                ],
                'event_date' => [
                    'type' => 'DATE',
                ],
                'event_time' => [
                    'type' => 'TIME',
                    'null' => true,
                ],
                'location' => [
                    'type' => 'VARCHAR',
                    'constraint' => '200',
                    'null' => true,
                ],
                'status' => [
                    'type' => 'ENUM',
                    'constraint' => ['scheduled', 'in-progress', 'completed', 'cancelled'],
                    'default' => 'scheduled',
                ],
                'hearse_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                ],
                'embalmer_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                ],
                'assigned_staff_ids' => [
                    'type' => 'JSON',
                    'null' => true,
                ],
                'remarks' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
            ]);
            $this->forge->addKey('calendar_id', true);
            $this->forge->addKey('branch_id');
            $this->forge->addKey('service_id');
            $this->forge->addKey('plan_holder_id');
            $this->forge->addKey('event_date');
            $this->forge->addKey('status');
            $this->forge->createTable('service_calendar');
        }
    }

    public function down()
    {
        $this->forge->dropTable('service_calendar', true);
        $this->forge->dropTable('staff_schedules', true);
        $this->forge->dropTable('embalmers', true);
        $this->forge->dropTable('hearses', true);
    }
}
