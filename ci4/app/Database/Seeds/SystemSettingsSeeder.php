<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    public function run()
    {
        $data = [
            // Payment Settings
            [
                'setting_key' => 'minimum_payment',
                'setting_value' => '240.00',
                'category' => 'payment',
                'data_type' => 'decimal',
                'description' => 'Minimum payment amount allowed',
                'is_active' => 1,
            ],
            [
                'setting_key' => 'maximum_advance_months',
                'setting_value' => '12',
                'category' => 'payment',
                'data_type' => 'integer',
                'description' => 'Maximum months of advance payment allowed',
                'is_active' => 1,
            ],
            [
                'setting_key' => 'delinquent_threshold_months',
                'setting_value' => '3',
                'category' => 'payment',
                'data_type' => 'integer',
                'description' => 'Months of non-payment before marking as delinquent',
                'is_active' => 1,
            ],
            [
                'setting_key' => 'payment_reminder_days',
                'setting_value' => '5',
                'category' => 'payment',
                'data_type' => 'integer',
                'description' => 'Days before due date to send payment reminder',
                'is_active' => 1,
            ],
            [
                'setting_key' => 'payment_gateway',
                'setting_value' => 'stripe',
                'category' => 'payment',
                'data_type' => 'string',
                'description' => 'Payment gateway provider (stripe, paypal, etc)',
                'is_active' => 1,
            ],

            // Service Settings
            [
                'setting_key' => 'service_advance_notice_days',
                'setting_value' => '7',
                'category' => 'service',
                'data_type' => 'integer',
                'description' => 'Days advance notice for service scheduling',
                'is_active' => 1,
            ],
            [
                'setting_key' => 'service_cancellation_deadline_hours',
                'setting_value' => '24',
                'category' => 'service',
                'data_type' => 'integer',
                'description' => 'Hours before service to allow cancellation',
                'is_active' => 1,
            ],

            // Security Settings
            [
                'setting_key' => 'password_expiry_days',
                'setting_value' => '90',
                'category' => 'security',
                'data_type' => 'integer',
                'description' => 'Days before password requires change',
                'is_active' => 1,
            ],
            [
                'setting_key' => 'session_timeout_minutes',
                'setting_value' => '30',
                'category' => 'security',
                'data_type' => 'integer',
                'description' => 'Session timeout in minutes',
                'is_active' => 1,
            ],
            [
                'setting_key' => 'max_login_attempts',
                'setting_value' => '5',
                'category' => 'security',
                'data_type' => 'integer',
                'description' => 'Maximum failed login attempts before lockout',
                'is_active' => 1,
            ],
            [
                'setting_key' => 'account_lockout_minutes',
                'setting_value' => '15',
                'category' => 'security',
                'data_type' => 'integer',
                'description' => 'Minutes to lock account after max failed attempts',
                'is_active' => 1,
            ],
            [
                'setting_key' => 'enable_two_factor',
                'setting_value' => '0',
                'category' => 'security',
                'data_type' => 'boolean',
                'description' => 'Enable two-factor authentication',
                'is_active' => 1,
            ],
            [
                'setting_key' => 'api_rate_limit_requests',
                'setting_value' => '1000',
                'category' => 'security',
                'data_type' => 'integer',
                'description' => 'API requests allowed per hour',
                'is_active' => 1,
            ],

            // System Settings
            [
                'setting_key' => 'timezone',
                'setting_value' => 'Asia/Manila',
                'category' => 'system',
                'data_type' => 'string',
                'description' => 'System timezone',
                'is_active' => 1,
            ],
            [
                'setting_key' => 'currency',
                'setting_value' => '₱',
                'category' => 'system',
                'data_type' => 'string',
                'description' => 'System currency symbol',
                'is_active' => 1,
            ],
            [
                'setting_key' => 'company_name',
                'setting_value' => 'KaaGapay',
                'category' => 'system',
                'data_type' => 'string',
                'description' => 'Company name for display',
                'is_active' => 1,
            ],
            [
                'setting_key' => 'support_email',
                'setting_value' => 'support@kaagapay.com',
                'category' => 'system',
                'data_type' => 'string',
                'description' => 'Support contact email',
                'is_active' => 1,
            ],
            [
                'setting_key' => 'notification_retention_days',
                'setting_value' => '30',
                'category' => 'system',
                'data_type' => 'integer',
                'description' => 'Days to retain notifications',
                'is_active' => 1,
            ],
            [
                'setting_key' => 'audit_log_retention_days',
                'setting_value' => '90',
                'category' => 'system',
                'data_type' => 'integer',
                'description' => 'Days to retain audit logs',
                'is_active' => 1,
            ],
            [
                'setting_key' => 'enable_maintenance_mode',
                'setting_value' => '0',
                'category' => 'system',
                'data_type' => 'boolean',
                'description' => 'Enable maintenance mode',
                'is_active' => 1,
            ],
            [
                'setting_key' => 'app_version',
                'setting_value' => '1.0.0',
                'category' => 'system',
                'data_type' => 'string',
                'description' => 'Application version',
                'is_active' => 1,
            ],
        ];

        // Seed the data
        $this->db->table('system_settings')->insertBatch($data);
    }
}
