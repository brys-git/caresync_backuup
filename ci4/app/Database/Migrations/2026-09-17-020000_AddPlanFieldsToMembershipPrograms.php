<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Fix Prompts Task 5A (Plan Builder). A "plan" = one membership_programs
 * row + exactly one entitled package (a packages row, flagged
 * is_damayan_entitlement) + its inclusions (package_items). This adds the
 * plan-level fields that row needs to actually describe a sellable plan:
 * its price, its term (derived as ceil(plan_price / monthly_fee) - not
 * stored independently of that formula, but kept as a real column so it
 * doesn't have to be recomputed on every read), and which package it
 * entitles.
 *
 * 'description' is NOT added here - it already exists on this table live
 * (schema drift predating this migration, same pattern this project has
 * hit before: a column added by hand outside the migration system). This
 * migration only adds what's actually missing.
 */
class AddPlanFieldsToMembershipPrograms extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('membership_programs')) {
            return;
        }

        $fields = [];

        if (! $this->db->fieldExists('plan_price', 'membership_programs')) {
            $fields['plan_price'] = [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => 14500.00,
                'null'       => false,
                'after'      => 'monthly_fee',
            ];
        }

        if (! $this->db->fieldExists('term_months', 'membership_programs')) {
            $fields['term_months'] = [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 61,
                'null'       => false,
                'after'      => 'plan_price',
            ];
        }

        if (! $this->db->fieldExists('package_id', 'membership_programs')) {
            // Signed, matching packages.package_id exactly (INT(11), not
            // unsigned) - a mismatched signedness is the one thing that
            // silently breaks a foreign key add on this project (see the
            // collector_assignments migration fix).
            $fields['package_id'] = [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
                'after'      => 'term_months',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('membership_programs', $fields);
        }

        // package_id exists now either way (was already there, or was just
        // added above) - re-checking fieldExists() here would be wrong:
        // BaseConnection::getFieldNames() caches a table's column list per
        // connection and never invalidates it after addColumn() runs in
        // this same request, so it would still (incorrectly) report false
        // immediately after just adding the column. backfillExistingProgram()
        // is itself a no-op if a package_id value is already set, so it's
        // safe to always call.
        $this->backfillExistingProgram();
    }

    /**
     * The one pre-existing Damayan row predates plan_price/term_months/
     * package_id entirely - give it real values instead of leaving them at
     * column defaults picked for new rows. package_id resolves to whichever
     * package actually has is_damayan_entitlement=1 today, not the
     * MembershipService::DEFAULT_PACKAGE_ID=1 assumption - package ids are
     * not guaranteed to start at 1 after a catalog reseed (same reasoning
     * the is_damayan_entitlement flag itself was added for).
     */
    private function backfillExistingProgram(): void
    {
        $program = $this->db->table('membership_programs')
            ->select('program_id, package_id')
            ->orderBy('program_id', 'ASC')
            ->get()
            ->getRowArray();

        if (! $program || ! empty($program['package_id'])) {
            return;
        }

        $packageId = null;
        if ($this->db->tableExists('packages')) {
            $entitlementPackage = $this->db->table('packages')
                ->select('package_id')
                ->where('is_damayan_entitlement', 1)
                ->orderBy('package_id', 'ASC')
                ->get()
                ->getRowArray();

            if ($entitlementPackage) {
                $packageId = (int) $entitlementPackage['package_id'];
            } else {
                $fallback = $this->db->table('packages')
                    ->select('package_id')
                    ->where('package_id', 1)
                    ->get()
                    ->getRowArray();
                $packageId = $fallback ? 1 : null;
            }
        }

        $this->db->table('membership_programs')
            ->where('program_id', (int) $program['program_id'])
            ->update([
                'plan_price'   => 14500.00,
                'term_months'  => 61,
                'package_id'   => $packageId,
            ]);
    }

    public function down()
    {
        if (! $this->db->tableExists('membership_programs')) {
            return;
        }

        foreach (['plan_price', 'term_months', 'package_id'] as $column) {
            if ($this->db->fieldExists($column, 'membership_programs')) {
                $this->forge->dropColumn('membership_programs', $column);
            }
        }
    }
}
