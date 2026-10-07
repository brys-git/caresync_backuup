<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Records the removal of 16 tables identified as unused during the
 * 2026-09-28 codebase cleanup - zero rows and zero code references
 * (raw queries, models, or otherwise) for every one of them. The actual
 * DROP already happened directly against kaagapay_db as part of that
 * cleanup (mysqldump backup taken first: kaagapay_db_backup_20260928_231706.sql
 * at the repo root); this migration exists so the change is tracked like
 * any other schema change and so a fresh environment (e.g. a future
 * deploy) ends up with the same schema. dropTable(..., true) is
 * if-exists, so running this where the tables are already gone (this DB)
 * is a no-op.
 *
 * Dropped:
 * - service_offers            (superseded by service_list)
 * - plans_backup              (ad-hoc manual snapshot, not migration-managed)
 * - deceased                  (not migration-managed, zero references)
 * - resource_assignments      (migrated in but never wired to any feature)
 * - api_keys, rate_limits, user_sessions, system_settings, audit_logs,
 *   payment_transactions, service_logs, email_logs
 *                             (only referenced by the dead Security/Settings/
 *                              Audit/APIKey service cluster, itself removed
 *                              in the same cleanup)
 * - service_calendar, hearses, embalmers, staff_schedules
 *                             (only referenced by the dead SchedulingService,
 *                              itself removed in the same cleanup)
 */
class DropUnusedTables extends Migration
{
    private array $tables = [
        'service_offers',
        'plans_backup',
        'deceased',
        'resource_assignments',
        'api_keys',
        'rate_limits',
        'user_sessions',
        'system_settings',
        'audit_logs',
        'payment_transactions',
        'service_logs',
        'email_logs',
        'service_calendar',
        'hearses',
        'embalmers',
        'staff_schedules',
    ];

    public function up()
    {
        foreach ($this->tables as $table) {
            $this->forge->dropTable($table, true);
        }
    }

    public function down()
    {
        // Irreversible: recreating these tables' original schemas is not
        // worth doing for tables that had zero code references and (with
        // the exception of plans_backup, 6 rows) zero data. Restore from
        // kaagapay_db_backup_20260928_231706.sql if ever actually needed.
    }
}
