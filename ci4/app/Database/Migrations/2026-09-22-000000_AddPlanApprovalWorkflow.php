<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lets Branch Admin create a plan too (previously System Admin only via
 * PlanBuilderService/PlanBuilder::canManage()), but a Branch-Admin-submitted
 * plan must be approved by System Admin before it's selectable at
 * registration. Mirrors the existing pending_packages/pending_services
 * approve/reject pattern, but as columns directly on membership_programs
 * (not a separate shadow table) - a plan bundles a package + its inclusion
 * items in one PlanBuilderService::save() transaction already, so staging
 * that whole bundle a second time in a pending_plans table would duplicate
 * a lot of logic for no real benefit; the row simply stays invisible to
 * registration/is_active=0 until approved.
 *
 * approval_status defaults to 'approved' so the one pre-existing Damayan
 * row (and anything System Admin creates going forward, which never needs
 * external approval) keeps working exactly as before.
 */
class AddPlanApprovalWorkflow extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('membership_programs')) {
            return;
        }

        $fields = [];

        if (! $this->db->fieldExists('approval_status', 'membership_programs')) {
            $fields['approval_status'] = [
                'type'       => 'ENUM',
                'constraint' => ['approved', 'pending', 'rejected'],
                'default'    => 'approved',
                'null'       => false,
                'after'      => 'is_active',
            ];
        }

        if (! $this->db->fieldExists('submitted_by', 'membership_programs')) {
            $fields['submitted_by'] = [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
                'after'      => 'approval_status',
            ];
        }

        if (! $this->db->fieldExists('submitted_by_branch_id', 'membership_programs')) {
            $fields['submitted_by_branch_id'] = [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
                'after'      => 'submitted_by',
            ];
        }

        if (! $this->db->fieldExists('reviewed_by', 'membership_programs')) {
            $fields['reviewed_by'] = [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
                'after'      => 'submitted_by_branch_id',
            ];
        }

        if (! $this->db->fieldExists('reviewed_at', 'membership_programs')) {
            $fields['reviewed_at'] = [
                'type'       => 'DATETIME',
                'null'       => true,
                'after'      => 'reviewed_by',
            ];
        }

        if (! $this->db->fieldExists('rejection_reason', 'membership_programs')) {
            $fields['rejection_reason'] = [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'reviewed_at',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('membership_programs', $fields);
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('membership_programs')) {
            return;
        }

        foreach (['approval_status', 'submitted_by', 'submitted_by_branch_id', 'reviewed_by', 'reviewed_at', 'rejection_reason'] as $column) {
            if ($this->db->fieldExists($column, 'membership_programs')) {
                $this->forge->dropColumn('membership_programs', $column);
            }
        }
    }
}
