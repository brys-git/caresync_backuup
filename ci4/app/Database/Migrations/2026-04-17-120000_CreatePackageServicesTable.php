<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePackageServicesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'package_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => false,
            ],
            'service_list_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => false,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('package_id');
        $this->forge->addKey('service_list_id');
        $this->forge->addForeignKey('package_id', 'packages', 'package_id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('service_list_id', 'service_list', 'service_list_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('package_services', true);
    }

    public function down()
    {
        $this->forge->dropTable('package_services', true);
    }
}
