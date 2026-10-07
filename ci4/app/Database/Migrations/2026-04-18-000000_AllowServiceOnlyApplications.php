<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AllowServiceOnlyApplications extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('service_list_id', 'service_applications')) {
            $this->db->query('ALTER TABLE service_applications ADD COLUMN service_list_id INT(11) NULL AFTER plan_holder_id');
        }

        $this->db->query('ALTER TABLE service_applications MODIFY package_id INT(11) NULL');

        if ($this->db->fieldExists('package_id', 'services')) {
            $this->db->query('ALTER TABLE services MODIFY package_id INT(11) NULL');
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('package_id', 'services')) {
            $this->db->query('ALTER TABLE services MODIFY package_id INT(11) NOT NULL');
        }

        $this->db->query('ALTER TABLE service_applications MODIFY package_id INT(11) NOT NULL');

        if ($this->db->fieldExists('service_list_id', 'service_applications')) {
            $this->db->query('ALTER TABLE service_applications DROP COLUMN service_list_id');
        }
    }
}