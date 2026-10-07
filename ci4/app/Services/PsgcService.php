<?php

namespace App\Services;

/**
 * PsgcService
 *
 * Oriental Mindoro city/municipality + barangay lookups, read from the local
 * app/Data/OrientalMindoroAddress.php file (PSA PSGC 2025 Q2) instead of the
 * remote PSGC Cloud API this used to call over HTTP. Scope is deliberately
 * narrowed to Oriental Mindoro (15 LGUs, 426 barangays) - every plan holder
 * this app registers lives in the province the branches operate in, so the
 * nationwide PSGC list was never actually needed.
 *
 * Shared by Api\AddressController (the browser-facing search/list endpoints
 * for the Applicant Information form's address dropdowns) and
 * PlanHolderRegistrationService::register() (server-side validation that a
 * submitted Town/City -> Barangay pair is real and actually related - a
 * client can't be trusted to send back only codes this service itself
 * handed out). Both keep working unchanged: same method names, same
 * ['code' => ..., 'name' => ...] return shape.
 *
 * Accepts either the current 10-digit PSGC code or the legacy 9-digit
 * old_code on input and matches against both, so plan holders saved before
 * this switch (all of whom used the 10-digit code - checked directly
 * against the live plan_holders table before writing this) keep validating,
 * and so would any stray 9-digit code from further back.
 */
class PsgcService
{
    private const DATA_PATH = APPPATH . 'Data/OrientalMindoroAddress.php';

    /** @var array<string, mixed>|null */
    private static ?array $data = null;

    /** @var array<string, array<string, mixed>>|null keyed by both code and old_code */
    private static ?array $citiesByCode = null;

    /** @var array<string, array{code: string, name: string, city_code: string}>|null keyed by both code and old_code */
    private static ?array $barangaysByCode = null;

    /**
     * @return array<int, array{code: string, name: string}>
     */
    public function getCities(): ?array
    {
        $this->load();

        return array_map(
            static fn (array $city): array => ['code' => $city['code'], 'name' => $city['name']],
            self::$data['cities']
        );
    }

    /**
     * @return array<int, array{code: string, name: string}>|null null if
     *   $cityCode isn't a real Oriental Mindoro city/municipality code.
     */
    public function getBarangaysForCity(string $cityCode): ?array
    {
        $this->load();

        $city = $this->findCity($cityCode);
        if ($city === null) {
            return null;
        }

        return array_map(
            static fn (array $barangay): array => ['code' => $barangay['code'], 'name' => $barangay['name']],
            $city['barangays']
        );
    }

    /**
     * True only if $cityCode is a real Oriental Mindoro city/municipality
     * and $barangayCode is one of its actual barangays. Used to validate a
     * submitted registration before saving - never trusts codes sent from
     * the browser at face value.
     */
    public function isBarangayInCity(string $cityCode, string $barangayCode): bool
    {
        $this->load();

        $city = $this->findCity($cityCode);
        if ($city === null) {
            return false;
        }

        $barangay = self::$barangaysByCode[trim($barangayCode)] ?? null;

        return $barangay !== null && $barangay['city_code'] === $city['code'];
    }

    /**
     * @return array<string, mixed>|null the city row (with its own
     *   'barangays' list) matched by either code format, or null.
     */
    private function findCity(string $cityCode): ?array
    {
        $cityCode = trim($cityCode);
        if ($cityCode === '') {
            return null;
        }

        return self::$citiesByCode[$cityCode] ?? null;
    }

    private function load(): void
    {
        if (self::$data !== null) {
            return;
        }

        self::$data = require self::DATA_PATH;
        self::$citiesByCode = [];
        self::$barangaysByCode = [];

        foreach (self::$data['cities'] as $city) {
            self::$citiesByCode[$city['code']] = $city;
            self::$citiesByCode[$city['old_code']] = $city;

            foreach ($city['barangays'] as $barangay) {
                $row = ['code' => $barangay['code'], 'name' => $barangay['name'], 'city_code' => $city['code']];
                self::$barangaysByCode[$barangay['code']] = $row;
                self::$barangaysByCode[$barangay['old_code']] = $row;
            }
        }
    }
}
