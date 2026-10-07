<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnforceSinglePlanHolderPerUser extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('plan_holders') || ! $this->db->fieldExists('user_id', 'plan_holders')) {
            return;
        }

        $schema = (string) $this->db->database;

        $this->db->transStart();

        $duplicateUsers = $this->db->query(
            'SELECT user_id FROM plan_holders WHERE user_id IS NOT NULL GROUP BY user_id HAVING COUNT(*) > 1'
        )->getResultArray();

        if (! empty($duplicateUsers)) {
            $dependentTables = $this->db->query(
                "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND COLUMN_NAME = 'plan_holder_id' AND TABLE_NAME <> 'plan_holders'",
                [$schema]
            )->getResultArray();

            foreach ($duplicateUsers as $duplicateUser) {
                $userId = (int) ($duplicateUser['user_id'] ?? 0);
                if ($userId <= 0) {
                    continue;
                }

                $profiles = $this->db->query(
                    'SELECT ph.plan_holder_id, (SELECT COUNT(*) FROM plans p WHERE p.plan_holder_id = ph.plan_holder_id) AS plan_count FROM plan_holders ph WHERE ph.user_id = ? ORDER BY plan_count DESC, ph.plan_holder_id DESC',
                    [$userId]
                )->getResultArray();

                if (count($profiles) < 2) {
                    continue;
                }

                $keeperId = (int) ($profiles[0]['plan_holder_id'] ?? 0);
                if ($keeperId <= 0) {
                    continue;
                }

                for ($i = 1, $count = count($profiles); $i < $count; $i++) {
                    $duplicateId = (int) ($profiles[$i]['plan_holder_id'] ?? 0);
                    if ($duplicateId <= 0 || $duplicateId === $keeperId) {
                        continue;
                    }

                    foreach ($dependentTables as $tableRow) {
                        $tableName = (string) ($tableRow['TABLE_NAME'] ?? '');
                        if ($tableName === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
                            continue;
                        }

                        $this->db->query(
                            'UPDATE `' . $tableName . '` SET plan_holder_id = ? WHERE plan_holder_id = ?',
                            [$keeperId, $duplicateId]
                        );
                    }

                    $this->db->query('DELETE FROM plan_holders WHERE plan_holder_id = ?', [$duplicateId]);
                }
            }
        }

        $indexExists = $this->db->query(
            "SHOW INDEX FROM plan_holders WHERE Key_name = 'ux_plan_holders_user_id'"
        )->getRowArray();

        if (! $indexExists) {
            $this->db->query('ALTER TABLE plan_holders ADD UNIQUE KEY ux_plan_holders_user_id (user_id)');
        }

        $this->db->transComplete();
    }

    public function down()
    {
        if (! $this->db->tableExists('plan_holders')) {
            return;
        }

        $indexExists = $this->db->query(
            "SHOW INDEX FROM plan_holders WHERE Key_name = 'ux_plan_holders_user_id'"
        )->getRowArray();

        if ($indexExists) {
            $this->db->query('ALTER TABLE plan_holders DROP INDEX ux_plan_holders_user_id');
        }
    }
}
