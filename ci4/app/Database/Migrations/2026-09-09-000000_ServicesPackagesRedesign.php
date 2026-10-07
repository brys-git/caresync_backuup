<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Services & Packages Page Redesign.
 *
 * Adds what the new client-facing catalog needs on top of the existing
 * packages/service_list/service_applications/plans tables, all additive
 * and idempotent per this project's established migration pattern:
 *
 * - packages.is_damayan_entitlement: robust flag for "this is the
 *   standard Damayan Plan Holder entitlement package" (the Regular Wood
 *   Casket), instead of relying only on MembershipService::
 *   DEFAULT_PACKAGE_ID's assumed id=1.
 * - packages.image_path / service_list.image_path: placeholder image
 *   slots for the new card/detail designs.
 * - service_routes: Balik Probinsya's per-route pricing (Manila->Mindoro,
 *   Batangas->Mindoro), modeled like package_versions rather than bolting
 *   two columns onto service_list.
 * - service_applications: burial-attire add-on selection, selected route,
 *   and the computed Damayan benefit credit / final amount at submission
 *   time (a front-end preview concept, separate from ServiceBalanceService's
 *   real post-approval contribution-based math, which is untouched).
 * - plans.last_damayan_claim_at: audit/display only - eligibility itself is
 *   computed live from months_paid * monthly_fee vs the contribution
 *   target, reset to 0 on claim approval the same way the existing
 *   overdue/forfeiture policy already resets months_paid.
 */
class ServicesPackagesRedesign extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('packages')) {
            if (! $this->db->fieldExists('is_damayan_entitlement', 'packages')) {
                $this->forge->addColumn('packages', [
                    'is_damayan_entitlement' => [
                        'type'       => 'TINYINT',
                        'constraint' => 1,
                        'null'       => false,
                        'default'    => 0,
                        'after'      => 'is_available',
                    ],
                ]);
            }

            if (! $this->db->fieldExists('image_path', 'packages')) {
                $this->forge->addColumn('packages', [
                    'image_path' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 255,
                        'null'       => true,
                    ],
                ]);
            }
        }

        if ($this->db->tableExists('service_list') && ! $this->db->fieldExists('image_path', 'service_list')) {
            $this->forge->addColumn('service_list', [
                'image_path' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
            ]);
        }

        if (! $this->db->tableExists('service_routes')) {
            $this->forge->addField([
                'route_id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'service_list_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => false,
                ],
                'route_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                ],
                'price' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'default'    => 0,
                ],
                'sort_order' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                ],
            ]);
            $this->forge->addKey('route_id', true);
            $this->forge->addKey('service_list_id');
            $this->forge->addForeignKey('service_list_id', 'service_list', 'service_list_id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('service_routes', true);
        }

        if ($this->db->tableExists('service_applications')) {
            $newColumns = [];

            if (! $this->db->fieldExists('burial_attire_selected', 'service_applications')) {
                $newColumns['burial_attire_selected'] = [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'null'       => false,
                    'default'    => 0,
                ];
            }

            if (! $this->db->fieldExists('burial_attire_price', 'service_applications')) {
                $newColumns['burial_attire_price'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'null'       => true,
                ];
            }

            if (! $this->db->fieldExists('selected_route_id', 'service_applications')) {
                $newColumns['selected_route_id'] = [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                ];
            }

            if (! $this->db->fieldExists('damayan_benefit_applied', 'service_applications')) {
                $newColumns['damayan_benefit_applied'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'null'       => true,
                ];
            }

            if (! $this->db->fieldExists('application_amount', 'service_applications')) {
                $newColumns['application_amount'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'null'       => true,
                ];
            }

            if (! empty($newColumns)) {
                $this->forge->addColumn('service_applications', $newColumns);
            }
        }

        if ($this->db->tableExists('plans') && ! $this->db->fieldExists('last_damayan_claim_at', 'plans')) {
            $this->forge->addColumn('plans', [
                'last_damayan_claim_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('packages')) {
            if ($this->db->fieldExists('is_damayan_entitlement', 'packages')) {
                $this->forge->dropColumn('packages', 'is_damayan_entitlement');
            }
            if ($this->db->fieldExists('image_path', 'packages')) {
                $this->forge->dropColumn('packages', 'image_path');
            }
        }

        if ($this->db->tableExists('service_list') && $this->db->fieldExists('image_path', 'service_list')) {
            $this->forge->dropColumn('service_list', 'image_path');
        }

        if ($this->db->tableExists('service_routes')) {
            $this->forge->dropTable('service_routes', true);
        }

        if ($this->db->tableExists('service_applications')) {
            foreach (['burial_attire_selected', 'burial_attire_price', 'selected_route_id', 'damayan_benefit_applied', 'application_amount'] as $column) {
                if ($this->db->fieldExists($column, 'service_applications')) {
                    $this->forge->dropColumn('service_applications', $column);
                }
            }
        }

        if ($this->db->tableExists('plans') && $this->db->fieldExists('last_damayan_claim_at', 'plans')) {
            $this->forge->dropColumn('plans', 'last_damayan_claim_at');
        }
    }
}
