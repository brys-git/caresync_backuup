<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Restores `plans.package_id`.
 *
 * A migration named AddPackageIdToPlans ran historically (it's recorded in
 * the migrations table, batch 1) but its file no longer exists in this
 * project, and the column isn't on the live table — it was apparently
 * dropped by hand at some point in favor of `program_id` /
 * `membership_programs`, a simpler single-row stand-in that every plan
 * currently points at. `packages` / `package_versions` is the system that
 * actually supports multiple tiers and price versioning (what "different
 * packages/benefit tiers" and upgrade paths need), so it's the one that
 * should be the real system of record going forward — this migration just
 * re-adds the column that connects a plan to it. Wiring an actual
 * package-picking "Create Plan" UI on top of this, and migrating billing
 * logic off program_id, is later-phase work.
 *
 * Backfilled via the existing `version_id` column, which — unlike
 * package_id — was never removed: every plan already points at a real
 * package_versions row, and that row names its package. Nothing about this
 * backfill is guesswork.
 */
class RestorePackageIdOnPlans extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('plans')) {
            return;
        }

        $fields = array_map(static fn ($field) => $field->name, $this->db->getFieldData('plans'));

        if (! in_array('package_id', $fields, true)) {
            $this->forge->addColumn('plans', [
                'package_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                    'after'      => 'plan_holder_id',
                ],
            ]);
            // Forge's addKey()/createTable() combo only applies to tables being
            // created fresh; adding an index to an existing table needs a plain
            // ALTER (there's no public Forge helper for this case).
            $this->db->query('ALTER TABLE plans ADD KEY package_id (package_id)');
        }

        if ($this->db->tableExists('package_versions')) {
            $this->db->query(
                'UPDATE plans p
                 INNER JOIN package_versions pv ON pv.version_id = p.version_id
                 SET p.package_id = pv.package_id
                 WHERE p.package_id IS NULL'
            );
        }
    }

    public function down()
    {
        if ($this->db->tableExists('plans') && $this->db->fieldExists('package_id', 'plans')) {
            $this->forge->dropColumn('plans', 'package_id');
        }
    }
}
