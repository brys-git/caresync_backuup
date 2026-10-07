<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateServiceBalanceWorkflow extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('service_balances')) {
            $this->db->query(
                "CREATE TABLE `service_balances` (
                    `service_balance_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `application_id` INT UNSIGNED NOT NULL,
                    `service_id` INT UNSIGNED NULL,
                    `plan_holder_id` INT UNSIGNED NOT NULL,
                    `branch_id` INT UNSIGNED NOT NULL,
                    `service_type` ENUM('service','package') NOT NULL DEFAULT 'package',
                    `service_name` VARCHAR(150) NOT NULL,
                    `package_name` VARCHAR(150) NULL,
                    `package_cost` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
                    `monthly_fee` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
                    `months_paid` INT NOT NULL DEFAULT 0,
                    `total_contributions` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
                    `assistance_amount` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
                    `remaining_balance` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
                    `installment_amount` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
                    `due_date` DATE NULL,
                    `next_due_date` DATE NULL,
                    `beneficiary_user_id` INT UNSIGNED NULL,
                    `beneficiary_name` VARCHAR(150) NULL,
                    `beneficiary_relationship` VARCHAR(100) NULL,
                    `acknowledgement_notes` TEXT NULL,
                    `beneficiary_acknowledged_at` DATETIME NULL,
                    `acknowledged_by` INT UNSIGNED NULL,
                    `status` ENUM('pending_acknowledgment','active','completed','cancelled') NOT NULL DEFAULT 'pending_acknowledgment',
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`service_balance_id`),
                    UNIQUE KEY `uq_service_balances_application` (`application_id`),
                    KEY `idx_service_balances_plan_holder` (`plan_holder_id`),
                    KEY `idx_service_balances_branch` (`branch_id`),
                    KEY `idx_service_balances_service` (`service_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
            );
        }

        if (! $this->db->tableExists('service_balance_payments')) {
            $this->db->query(
                "CREATE TABLE `service_balance_payments` (
                    `service_balance_payment_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `service_balance_id` INT UNSIGNED NOT NULL,
                    `paid_by_user_id` INT UNSIGNED NULL,
                    `amount` DECIMAL(10,2) NOT NULL,
                    `reference_number` VARCHAR(100) NULL,
                    `payment_method` VARCHAR(50) NULL,
                    `due_date` DATE NULL,
                    `paid_at` DATETIME NULL,
                    `notes` TEXT NULL,
                    `status` ENUM('pending','paid','failed','void') NOT NULL DEFAULT 'paid',
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`service_balance_payment_id`),
                    KEY `idx_service_balance_payments_balance` (`service_balance_id`),
                    KEY `idx_service_balance_payments_paid_by` (`paid_by_user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
            );
        }
    }

    public function down()
    {
        if ($this->db->tableExists('service_balance_payments')) {
            $this->db->query('DROP TABLE `service_balance_payments`');
        }

        if ($this->db->tableExists('service_balances')) {
            $this->db->query('DROP TABLE `service_balances`');
        }
    }
}