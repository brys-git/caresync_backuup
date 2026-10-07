<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Services & Packages Page Redesign - seeds the catalog data that was
 * deleted earlier this session: the Balik Probinsya service (with its two
 * route prices) and the five individual casket packages, each with the
 * same 8-item inclusions list. Idempotent - skips anything that already
 * exists by name, safe to re-run.
 */
class ServicesPackagesCatalogSeeder extends Seeder
{
    /** Identical inclusions list required on every package details page. */
    private const INCLUSIONS = [
        ['item_name' => 'Casket', 'description' => 'The casket for this package.'],
        ['item_name' => 'Flowers', 'description' => 'Floral arrangement for the viewing.'],
        ['item_name' => 'Tarpaulin', 'description' => 'Memorial tarpaulin/streamer.'],
        ['item_name' => 'Viewing Setup: Lights', 'description' => 'Lighting for the viewing area.'],
        ['item_name' => 'Viewing Setup: Curtains', 'description' => 'Curtain backdrop for the viewing area.'],
        ['item_name' => 'Viewing Setup: Registry Stand', 'description' => 'Guest registry stand.'],
        ['item_name' => 'Embalming', 'description' => 'Professional embalming service.'],
        ['item_name' => 'Body Preparation', 'description' => 'Preparation and dressing of the deceased.'],
        ['item_name' => 'Death Certificate Assistance', 'description' => 'Help securing the death certificate.'],
        ['item_name' => 'Burial Permit Assistance', 'description' => 'Help securing the burial permit.'],
    ];

    public function run()
    {
        $this->seedPackages();
        $this->seedBalikProbinsya();
    }

    private function seedPackages(): void
    {
        $packages = [
            [
                'package_name' => 'Regular Wood Casket',
                'description' => 'The standard Damayan Plan Holder entitlement casket package.',
                'base_price' => 20000,
                'is_damayan_entitlement' => 1,
                'image_path' => 'assets/images/regular-wood-casket.jfif',
            ],
            [
                'package_name' => 'Oversized Wood Casket',
                'description' => 'A larger wood casket for oversized requirements.',
                'base_price' => 50000,
                'is_damayan_entitlement' => 0,
                'image_path' => 'assets/images/oversized-wood-casket.jfif',
            ],
            [
                'package_name' => 'Regular Half-Glass Metal Casket',
                'description' => 'A metal casket with a half-glass viewing window.',
                'base_price' => 45000,
                'is_damayan_entitlement' => 0,
                'image_path' => 'assets/images/regular-half-glass-metal-casket.jfif',
            ],
            [
                'package_name' => 'Regular Full-Glass Metal Casket',
                'description' => 'A metal casket with a full-glass viewing window.',
                'base_price' => 75000,
                'is_damayan_entitlement' => 0,
                'image_path' => 'assets/images/regular-full-glass-metal-casket.jfif',
            ],
            [
                'package_name' => 'Oversized Metal Casket',
                'description' => 'A larger metal casket for oversized requirements.',
                'base_price' => 200000,
                'is_damayan_entitlement' => 0,
                'image_path' => 'assets/images/oversized-metal-casket.jfif',
            ],
        ];

        foreach ($packages as $package) {
            $existing = $this->db->table('packages')
                ->where('package_name', $package['package_name'])
                ->get()
                ->getRowArray();

            if ($existing) {
                $packageId = (int) $existing['package_id'];
            } else {
                $this->db->table('packages')->insert([
                    'package_name' => $package['package_name'],
                    'description' => $package['description'],
                    'base_price' => $package['base_price'],
                    'is_customizable' => 0,
                    'is_available' => 1,
                    'is_damayan_entitlement' => $package['is_damayan_entitlement'],
                    'image_path' => $package['image_path'],
                    'status' => 'approved',
                ]);
                $packageId = (int) $this->db->insertID();
            }

            $hasItems = $this->db->table('package_items')
                ->where('package_id', $packageId)
                ->countAllResults();

            if ($hasItems === 0) {
                foreach (self::INCLUSIONS as $item) {
                    $this->db->table('package_items')->insert([
                        'package_id' => $packageId,
                        'item_name' => $item['item_name'],
                        'description' => $item['description'],
                    ]);
                }
            }
        }
    }

    private function seedBalikProbinsya(): void
    {
        $serviceName = 'Balik Probinsya Program';

        $existing = $this->db->table('service_list')
            ->where('service_name', $serviceName)
            ->get()
            ->getRowArray();

        if ($existing) {
            $serviceListId = (int) $existing['service_list_id'];
        } else {
            $this->db->table('service_list')->insert([
                'service_name' => $serviceName,
                'description' => 'Transport of the deceased from Metro Manila or Batangas to Mindoro.',
                'base_price' => 35000,
                'status' => 'active',
                'is_available' => 1,
                'image_path' => '/uploads/services/balik-probinsya.jpg',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $serviceListId = (int) $this->db->insertID();
        }

        $hasRoutes = $this->db->table('service_routes')
            ->where('service_list_id', $serviceListId)
            ->countAllResults();

        if ($hasRoutes === 0) {
            $routes = [
                ['route_name' => 'Manila to Mindoro', 'price' => 35000, 'sort_order' => 1],
                ['route_name' => 'Batangas to Mindoro', 'price' => 30000, 'sort_order' => 2],
            ];

            foreach ($routes as $route) {
                $this->db->table('service_routes')->insert([
                    'service_list_id' => $serviceListId,
                    'route_name' => $route['route_name'],
                    'price' => $route['price'],
                    'sort_order' => $route['sort_order'],
                ]);
            }
        }
    }
}
