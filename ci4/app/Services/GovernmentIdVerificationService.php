<?php

namespace App\Services;

use CodeIgniter\HTTP\Files\UploadedFile;
use Config\GovernmentIdVerification;
use Config\Services;
use App\Models\GovernmentIdVerificationModel;

/**
 * GovernmentIdVerificationService
 *
 * Reusable identity-verification component, built to be shared by every
 * CareSync registration flow (Client/Plan Holder first, Staff/Branch
 * Admin/Admin account creation in a later phase) rather than duplicated
 * per role. A registration controller only ever calls verify() - it never
 * touches file storage, the OCR provider, or the matching logic directly,
 * so the provider (currently Claude vision, via the Anthropic API) can be
 * swapped later without any registration form being rewritten.
 *
 * Storage/security follow the same pattern already established by
 * service_application_documents (App\Controllers\Client\
 * ClientServiceController): files live under WRITEPATH (outside the
 * public webroot), with a randomly generated filename - never a
 * predictable or publicly-reachable path. Only a masked ID number and a
 * short extracted name/DOB/gender are retained for later staff review;
 * no raw OCR payload or full ID number is stored.
 */
class GovernmentIdVerificationService
{
    private GovernmentIdVerification $config;
    private GovernmentIdVerificationModel $model;

    public function __construct()
    {
        $this->config = config(GovernmentIdVerification::class);
        $this->model = new GovernmentIdVerificationModel();
    }

    /**
     * @return array<string, string> value => label, for a <select>
     */
    public function idTypes(): array
    {
        return $this->config->idTypes;
    }

    public function latestForUser(int $userId): ?array
    {
        return $this->model->latestForUser($userId);
    }

    /**
     * Manual ID review queue for Staff/Branch Admin: every plan holder in
     * this branch whose LATEST Government ID verification is still
     * 'needs_review' - the fallback status every submission gets while no
     * Anthropic API key is configured (see runExtractionAndCompare()), but
     * also the real status a configured key can still return for a
     * genuinely low-confidence match. Only the latest attempt per user_id
     * counts (a re-verify supersedes an older needs_review row for the
     * same person), and only plan holders (the review is meaningless for
     * an account with no plan_holder row at all - nothing to compare
     * against yet).
     *
     * @return list<array<string, mixed>>
     */
    public function listNeedingReview(int $branchId): array
    {
        $sql = 'SELECT giv.verification_id, giv.user_id, giv.id_type, giv.created_at,
                    giv.claimed_first_name, giv.claimed_middle_name, giv.claimed_last_name,
                    giv.claimed_date_of_birth, giv.claimed_gender,
                    ph.plan_holder_id, u.first_name, u.last_name, u.email
                FROM government_id_verifications giv
                INNER JOIN (
                    SELECT user_id, MAX(verification_id) AS latest_id
                    FROM government_id_verifications
                    GROUP BY user_id
                ) latest ON latest.user_id = giv.user_id AND latest.latest_id = giv.verification_id
                INNER JOIN plan_holders ph ON ph.user_id = giv.user_id
                INNER JOIN users u ON u.user_id = giv.user_id
                WHERE giv.verification_status = \'needs_review\'
                    AND ph.branch_id = ?
                ORDER BY giv.created_at ASC';

        return db_connect()->query($sql, [$branchId])->getResultArray();
    }

    /**
     * How many plan holders in this branch are awaiting a manual ID
     * review - for a nav badge, same idea as the existing pending_approvals/
     * pending_requests counts elsewhere in this app.
     */
    public function countNeedingReview(int $branchId): int
    {
        return count($this->listNeedingReview($branchId));
    }

    /**
     * One verification row for the review detail page, plus enough of the
     * plan holder's own submitted Applicant Information to compare against
     * by eye - scoped to $branchId so a reviewer can never open a record
     * outside their own branch by guessing a verification_id in the URL.
     */
    public function getForReview(int $verificationId, int $branchId): ?array
    {
        $row = db_connect()->table('government_id_verifications giv')
            ->select('giv.*, ph.plan_holder_id, ph.branch_id, ph.address_no, ph.address_street,
                ph.address_barangay, ph.address_city, ph.civil_status, ph.citizenship,
                u.first_name, u.last_name, u.middle_name, u.email, u.contact_number')
            ->join('plan_holders ph', 'ph.user_id = giv.user_id', 'inner')
            ->join('users u', 'u.user_id = giv.user_id', 'inner')
            ->where('giv.verification_id', $verificationId)
            ->get()
            ->getRowArray();

        if (! $row || (int) ($row['branch_id'] ?? 0) !== $branchId) {
            return null;
        }

        return $row;
    }

    /**
     * Records a Staff/Branch Admin's manual decision after comparing the ID
     * image against the applicant's typed details by eye. This never
     * un-does the account/plan that submission already created (a 'needs_
     * review' result was never a hard block - see isValidForSubmission()) -
     * it's an audit record of a human actually having checked, and (on a
     * mismatch) a signal to follow up with the client.
     */
    public function markReviewed(int $verificationId, string $status, int $reviewerId): bool
    {
        if (! in_array($status, ['verified', 'failed'], true)) {
            return false;
        }

        return (bool) $this->model->update($verificationId, [
            'verification_status' => $status,
            'reviewed_by' => $reviewerId,
            'verified_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * True only when a verification is usable to let a registration
     * proceed AND still matches what's about to be submitted. A wizard's
     * step-gating is client-side UX only - this is the real server-side
     * gate every registration flow (self-service and staff-assisted)
     * must call right before creating/finalizing the account:
     *   - status must be 'verified' or 'needs_review' ('failed' always
     *     blocks; a missing verification is handled by the caller before
     *     this is even called).
     *   - every claimed_* value stored at verification time must still
     *     equal what's being submitted now - if the applicant edited
     *     their name/DOB/sex after verifying, the old result no longer
     *     describes what's being registered.
     *
     * @param array $verification A row from latestForUser() (or a
     *   commitPending()'d sidecar re-read the same shape).
     * @param array{first_name?:string, middle_name?:string, last_name?:string, date_of_birth?:string, gender?:string} $submitted
     */
    public function isValidForSubmission(array $verification, array $submitted): bool
    {
        $status = (string) ($verification['verification_status'] ?? '');
        if (! in_array($status, ['verified', 'needs_review'], true)) {
            return false;
        }

        $fieldsMatch = $this->sameText($verification['claimed_first_name'] ?? null, $submitted['first_name'] ?? null)
            && $this->sameText($verification['claimed_last_name'] ?? null, $submitted['last_name'] ?? null)
            && $this->sameText($verification['claimed_middle_name'] ?? null, $submitted['middle_name'] ?? null)
            && $this->sameText($verification['claimed_gender'] ?? null, $submitted['gender'] ?? null);

        if (! $fieldsMatch) {
            return false;
        }

        $storedDob = $this->nullableString($verification['claimed_date_of_birth'] ?? null);
        $submittedDob = $this->nullableString($submitted['date_of_birth'] ?? null);
        if ($storedDob === null && $submittedDob === null) {
            return true;
        }

        if ($storedDob === null || $submittedDob === null) {
            return false;
        }

        return $this->normalizeDate($storedDob) === $this->normalizeDate($submittedDob);
    }

    /**
     * Case-insensitive, trimmed equality, treating null/'' as the same
     * "nothing provided" value (an optional middle name left blank should
     * not itself count as a mismatch).
     */
    private function sameText(?string $a, ?string $b): bool
    {
        $a = mb_strtolower(trim((string) $a));
        $b = mb_strtolower(trim((string) $b));

        return $a === $b;
    }

    /**
     * Validate, store, OCR-extract, and compare an uploaded/captured ID
     * image against the identity entered on the registration form. Never
     * throws - a provider outage or bad image always resolves to a
     * usable status (never blocks the registration form itself from
     * being submitted; 'needs_review' just means a human should look at
     * it afterward).
     *
     * For a user verifying their OWN identity (the account already
     * exists - e.g. Client/Plan Holder self-service registration).
     *
     * @param array{first_name:string, middle_name?:string, last_name:string, date_of_birth?:string, gender?:string} $claimedIdentity
     * @return array{status: string, message: string, verification_id: ?int}
     */
    public function verify(int $userId, string $idType, UploadedFile $file, array $claimedIdentity): array
    {
        if ($userId <= 0) {
            return $this->result('failed', 'Unable to process verification. Please try again.');
        }

        $fileError = $this->validateRequest($idType, $file);
        if ($fileError !== null) {
            return $this->result('failed', $fileError);
        }

        $stored = $this->storeFileTo('' . $userId, $file);
        if ($stored === null) {
            log_message('error', 'GovernmentIdVerificationService::verify - failed to store uploaded ID image for user ' . $userId);

            return $this->result('failed', 'Unable to save the uploaded image. Please try again.');
        }

        $outcome = $this->runExtractionAndCompare($idType, $stored, $claimedIdentity);
        $outcome['fields'] = array_merge($outcome['fields'], $this->claimedFields($claimedIdentity));

        $verificationId = $this->persist($userId, $idType, $stored, $outcome['fields']);

        return $this->result($outcome['status'], $outcome['message'], $verificationId);
    }

    /**
     * Same as verify(), but for a creator (Admin/Branch Admin/Staff)
     * verifying SOMEONE ELSE'S identity as part of creating a brand new
     * account on Users::create() - the new user_id doesn't exist yet at
     * upload time, so the result is stashed under a random, unguessable
     * token instead of a user_id and only persisted for real once
     * commitPending() is called after the account is actually created.
     * Nothing about the extracted/matched result is ever trusted back
     * from the browser - only the token, which just points at what this
     * service itself already computed and saved server-side.
     *
     * @param array{first_name:string, middle_name?:string, last_name:string, date_of_birth?:string, gender?:string} $claimedIdentity
     * @return array{status: string, message: string, pending_token: ?string}
     */
    public function verifyPending(string $idType, UploadedFile $file, array $claimedIdentity): array
    {
        $fileError = $this->validateRequest($idType, $file);
        if ($fileError !== null) {
            return ['status' => 'failed', 'message' => $fileError, 'pending_token' => null];
        }

        $token = bin2hex(random_bytes(20));
        $stored = $this->storeFileTo('_pending' . DIRECTORY_SEPARATOR . $token, $file);
        if ($stored === null) {
            log_message('error', 'GovernmentIdVerificationService::verifyPending - failed to store uploaded ID image.');

            return ['status' => 'failed', 'message' => 'Unable to save the uploaded image. Please try again.', 'pending_token' => null];
        }

        $outcome = $this->runExtractionAndCompare($idType, $stored, $claimedIdentity);
        $outcome['fields'] = array_merge($outcome['fields'], $this->claimedFields($claimedIdentity));

        $sidecar = array_merge([
            'id_type'       => $idType,
            'file_path'     => $stored['relative_path'],
            'original_name' => $stored['original_name'],
            'mime_type'     => $stored['mime_type'],
        ], $outcome['fields']);

        $sidecarPath = dirname($stored['full_path']) . DIRECTORY_SEPARATOR . 'result.json';
        if (file_put_contents($sidecarPath, json_encode($sidecar)) === false) {
            log_message('error', 'GovernmentIdVerificationService::verifyPending - failed to write sidecar for token ' . $token);

            return ['status' => 'failed', 'message' => 'Unable to save the verification result. Please try again.', 'pending_token' => null];
        }

        return ['status' => $outcome['status'], 'message' => $outcome['message'], 'pending_token' => $token];
    }

    /**
     * Re-reads a not-yet-committed verifyPending() result (its sidecar
     * JSON) without moving/committing anything - lets a staff-assisted
     * registration controller run the same isValidForSubmission() check
     * the self-service flow uses, right before it creates the account,
     * without having to invent a fake "verification row" shape by hand.
     */
    public function peekPending(string $pendingToken): ?array
    {
        $pendingToken = preg_replace('/[^a-f0-9]/', '', $pendingToken) ?? '';
        if ($pendingToken === '') {
            return null;
        }

        $sidecarPath = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'government_ids' . DIRECTORY_SEPARATOR . '_pending' . DIRECTORY_SEPARATOR . $pendingToken . DIRECTORY_SEPARATOR . 'result.json';
        if (! is_file($sidecarPath)) {
            return null;
        }

        $sidecar = json_decode((string) file_get_contents($sidecarPath), true);

        return is_array($sidecar) ? $sidecar : null;
    }

    /**
     * Finalizes a verifyPending() result once the real account has been
     * created - moves the temp image into the user's own directory and
     * writes the government_id_verifications row with the real user_id.
     * Returns null (logged, non-fatal) if the token is missing/expired/
     * already committed - account creation itself must never fail just
     * because of this.
     */
    public function commitPending(string $pendingToken, int $userId): ?int
    {
        $pendingToken = preg_replace('/[^a-f0-9]/', '', $pendingToken) ?? '';
        if ($pendingToken === '' || $userId <= 0) {
            return null;
        }

        $pendingDir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'government_ids' . DIRECTORY_SEPARATOR . '_pending' . DIRECTORY_SEPARATOR . $pendingToken;
        $sidecarPath = $pendingDir . DIRECTORY_SEPARATOR . 'result.json';

        if (! is_file($sidecarPath)) {
            log_message('warning', 'GovernmentIdVerificationService::commitPending - no pending verification found for token.');

            return null;
        }

        $sidecar = json_decode((string) file_get_contents($sidecarPath), true);
        if (! is_array($sidecar) || empty($sidecar['file_path'])) {
            return null;
        }

        $oldFullPath = WRITEPATH . str_replace('/', DIRECTORY_SEPARATOR, (string) $sidecar['file_path']);
        $targetDir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'government_ids' . DIRECTORY_SEPARATOR . $userId;

        if (! is_dir($targetDir) && ! mkdir($targetDir, 0755, true) && ! is_dir($targetDir)) {
            return null;
        }

        $newFullPath = $targetDir . DIRECTORY_SEPARATOR . basename($oldFullPath);
        if (is_file($oldFullPath) && ! @rename($oldFullPath, $newFullPath)) {
            return null;
        }

        $relativePath = str_replace('\\', '/', str_replace(WRITEPATH, '', $newFullPath));

        $verificationId = (int) $this->model->insert([
            'user_id'                     => $userId,
            'id_type'                     => (string) ($sidecar['id_type'] ?? 'other'),
            'file_path'                   => $relativePath,
            'original_name'               => $sidecar['original_name'] ?? null,
            'mime_type'                   => $sidecar['mime_type'] ?? null,
            'verification_status'         => (string) ($sidecar['verification_status'] ?? 'needs_review'),
            'match_result'                => $sidecar['match_result'] ?? null,
            'extracted_name'              => $sidecar['extracted_name'] ?? null,
            'extracted_birth_date'        => $sidecar['extracted_birth_date'] ?? null,
            'extracted_gender'            => $sidecar['extracted_gender'] ?? null,
            'extracted_id_number_masked'  => $sidecar['extracted_id_number_masked'] ?? null,
            'mismatch_reason'             => $sidecar['mismatch_reason'] ?? null,
            'claimed_first_name'          => $sidecar['claimed_first_name'] ?? null,
            'claimed_middle_name'         => $sidecar['claimed_middle_name'] ?? null,
            'claimed_last_name'           => $sidecar['claimed_last_name'] ?? null,
            'claimed_date_of_birth'       => $sidecar['claimed_date_of_birth'] ?? null,
            'claimed_gender'              => $sidecar['claimed_gender'] ?? null,
            'verification_attempts'       => 1,
            'verified_at'                 => $sidecar['verified_at'] ?? null,
        ], true);

        // Best-effort cleanup - a leftover empty _pending/{token} dir is
        // harmless, never worth failing account creation over.
        @unlink($sidecarPath);
        @rmdir($pendingDir);

        return $verificationId > 0 ? $verificationId : null;
    }

    /**
     * @param array{first_name?:string, middle_name?:string, last_name?:string, date_of_birth?:string, gender?:string} $claimed
     * @return array<string, ?string>
     */
    private function claimedFields(array $claimed): array
    {
        return [
            'claimed_first_name'    => $this->nullableString($claimed['first_name'] ?? null),
            'claimed_middle_name'   => $this->nullableString($claimed['middle_name'] ?? null),
            'claimed_last_name'     => $this->nullableString($claimed['last_name'] ?? null),
            'claimed_date_of_birth' => $this->toDbDate($claimed['date_of_birth'] ?? null),
            'claimed_gender'        => $this->nullableString($claimed['gender'] ?? null),
        ];
    }

    /**
     * Shared by verify() and verifyPending() - runs OCR extraction and
     * identity matching and returns the DB-shaped fields plus a status/
     * message, without touching the database itself (the two callers
     * persist differently: immediately vs. stashed-then-committed).
     *
     * @param array{full_path: string, relative_path: string, original_name: string, mime_type: string} $stored
     * @param array{first_name?:string, middle_name?:string, last_name?:string, date_of_birth?:string, gender?:string} $claimedIdentity
     * @return array{status: string, message: string, fields: array<string, mixed>}
     */
    private function runExtractionAndCompare(string $idType, array $stored, array $claimedIdentity): array
    {
        if ($this->config->anthropicApiKey === '') {
            log_message('info', 'GovernmentIdVerificationService - no Anthropic API key configured, marking needs_review.');

            return [
                'status'  => 'needs_review',
                'message' => 'Your ID was received. Staff will review it as part of your application.',
                'fields'  => [
                    'verification_status' => 'needs_review',
                    'mismatch_reason'      => 'Automatic verification is not configured.',
                ],
            ];
        }

        // Real MIME type of the stored bytes, not whatever the browser
        // claimed to have sent - validateFile() already checked this file
        // against $this->config->allowedMimeTypes the same way.
        $realMime = @mime_content_type($stored['full_path']);
        $mimeType = is_string($realMime) ? $realMime : $stored['mime_type'];

        $extraction = $this->extractFromImage($stored['full_path'], $mimeType);

        if ($extraction === null) {
            return [
                'status'  => 'needs_review',
                'message' => 'We could not automatically verify this ID right now. Your registration can still be submitted - staff will review it.',
                'fields'  => [
                    'verification_status' => 'needs_review',
                    'mismatch_reason'      => 'Automatic verification was temporarily unavailable.',
                ],
            ];
        }

        if (! $extraction['readable']) {
            return [
                'status'  => 'needs_review',
                'message' => 'The ID image is difficult to read. Please upload a clearer image or take another photo.',
                'fields'  => [
                    'verification_status' => 'needs_review',
                    'mismatch_reason'      => 'Image quality too low to read.',
                ],
            ];
        }

        $comparison = $this->compareIdentity($extraction, $claimedIdentity);

        $extractedDisplayName = $extraction['full_name']
            ?? trim(implode(' ', array_filter([$extraction['first_name'] ?? null, $extraction['middle_name'] ?? null, $extraction['last_name'] ?? null])));

        return [
            'status'  => $comparison['status'],
            'message' => $comparison['message'],
            'fields'  => [
                'verification_status'        => $comparison['status'],
                'match_result'                => $comparison['match_result'],
                'extracted_name'              => $extractedDisplayName !== '' ? $extractedDisplayName : null,
                'extracted_birth_date'        => $this->toDbDate($extraction['date_of_birth']),
                'extracted_gender'            => $extraction['gender'],
                'extracted_id_number_masked'  => $this->maskIdNumber($extraction['id_number']),
                'mismatch_reason'             => $comparison['reason'],
                'verified_at'                 => $comparison['status'] === 'verified' ? date('Y-m-d H:i:s') : null,
            ],
        ];
    }

    private function validateRequest(string $idType, UploadedFile $file): ?string
    {
        if (! array_key_exists($idType, $this->config->idTypes)) {
            return 'Please select a valid ID type.';
        }

        return $this->validateFile($file);
    }

    // ------------------------------------------------------------------
    // File handling
    // ------------------------------------------------------------------

    private function validateFile(UploadedFile $file): ?string
    {
        if (! $file->isValid() || $file->getError() !== UPLOAD_ERR_OK) {
            return 'The uploaded file could not be read. Please try again.';
        }

        if ($file->getSize() > $this->config->maxFileSizeBytes) {
            return 'The image is too large. Please upload a file under ' . (int) ($this->config->maxFileSizeBytes / 1024 / 1024) . 'MB.';
        }

        // Never trust the extension/client-reported MIME type alone -
        // check the file's actual content.
        $realMime = @mime_content_type($file->getTempName());
        if (! is_string($realMime) || ! in_array($realMime, $this->config->allowedMimeTypes, true)) {
            return 'Please upload a JPG, PNG, or WEBP image.';
        }

        return null;
    }

    /**
     * @param string $subDir Either a user_id, or '_pending/{token}' for a
     * not-yet-created account.
     * @return array{full_path: string, relative_path: string, original_name: string, mime_type: string}|null
     */
    private function storeFileTo(string $subDir, UploadedFile $file): ?array
    {
        $targetDir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'government_ids' . DIRECTORY_SEPARATOR . $subDir;

        if (! is_dir($targetDir) && ! mkdir($targetDir, 0755, true) && ! is_dir($targetDir)) {
            return null;
        }

        $newName = $file->getRandomName();
        if (! $file->move($targetDir, $newName)) {
            return null;
        }

        $fullPath = $targetDir . DIRECTORY_SEPARATOR . $newName;

        return [
            'full_path'     => $fullPath,
            'relative_path' => str_replace('\\', '/', str_replace(WRITEPATH, '', $fullPath)),
            'original_name' => (string) $file->getClientName(),
            'mime_type'     => (string) $file->getClientMimeType(),
        ];
    }

    // ------------------------------------------------------------------
    // OCR extraction (Claude vision via the Anthropic Messages API)
    // ------------------------------------------------------------------

    /**
     * @return array{readable: bool, full_name: ?string, first_name: ?string, middle_name: ?string, last_name: ?string, date_of_birth: ?string, gender: ?string, id_number: ?string}|null
     * null only on a provider/network failure - a readable=false result
     * (poor image quality) is a normal, non-null outcome.
     */
    private function extractFromImage(string $filePath, string $mimeType): ?array
    {
        try {
            $imageData = base64_encode((string) file_get_contents($filePath));
            if ($imageData === '') {
                return null;
            }

            $prompt = <<<PROMPT
You are extracting identity information from a photo of a Philippine government-issued ID for an identity verification step during account registration. This is a legitimate identity-verification workflow, not identity theft.

Respond with ONLY a single JSON object (no prose, no markdown code fences) with exactly these keys:
{"readable": true|false, "full_name": string|null, "first_name": string|null, "middle_name": string|null, "last_name": string|null, "date_of_birth": string|null, "gender": string|null, "id_number": string|null}

Rules:
- "readable" is false only if the image is too blurry, dark, cropped, or otherwise unreadable to extract any information from - not merely because a particular field is absent from this ID type.
- "full_name" is the person's full name exactly as printed on the ID.
- If the ID's layout prints the name in separate labeled fields (e.g. Last Name / First Name / Middle Name, as on a PhilID, driver's license, or UMID), ALSO populate "first_name", "middle_name", and "last_name" individually from those fields (use null for "middle_name" if the ID prints none). If the ID only prints one combined name field, leave "first_name", "middle_name", and "last_name" all null and rely on "full_name" alone.
- "date_of_birth" must be in YYYY-MM-DD format if present.
- "gender" is "male" or "female" if printed on the ID.
- "id_number" is the ID/document number if printed on the ID.
- Only include information actually printed on the ID. If a field does not appear on this type of ID, use null for it rather than guessing.
PROMPT;

            $response = Services::curlrequest(['timeout' => 30])->post($this->config->endpoint, [
                'http_errors' => false,
                'headers' => [
                    'x-api-key'          => $this->config->anthropicApiKey,
                    'anthropic-version'  => $this->config->apiVersion,
                    'content-type'       => 'application/json',
                ],
                'json' => [
                    'model'      => $this->config->model,
                    'max_tokens' => 1024,
                    'messages'   => [
                        [
                            'role'    => 'user',
                            'content' => [
                                [
                                    'type'   => 'image',
                                    'source' => [
                                        'type'       => 'base64',
                                        'media_type' => $mimeType,
                                        'data'       => $imageData,
                                    ],
                                ],
                                ['type' => 'text', 'text' => $prompt],
                            ],
                        ],
                    ],
                ],
            ]);

            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                log_message('error', 'GovernmentIdVerificationService - Anthropic API returned HTTP ' . $status . ': ' . $response->getBody());

                return null;
            }

            $body = json_decode((string) $response->getBody(), true);
            $text = $body['content'][0]['text'] ?? null;
            if (! is_string($text) || trim($text) === '') {
                return null;
            }

            if (! preg_match('/\{.*\}/s', $text, $matches)) {
                log_message('error', 'GovernmentIdVerificationService - could not find a JSON object in the model response.');

                return null;
            }

            $parsed = json_decode($matches[0], true);
            if (! is_array($parsed)) {
                return null;
            }

            return [
                'readable'      => (bool) ($parsed['readable'] ?? false),
                'full_name'     => $this->nullableString($parsed['full_name'] ?? null),
                'first_name'    => $this->nullableString($parsed['first_name'] ?? null),
                'middle_name'   => $this->nullableString($parsed['middle_name'] ?? null),
                'last_name'     => $this->nullableString($parsed['last_name'] ?? null),
                'date_of_birth' => $this->nullableString($parsed['date_of_birth'] ?? null),
                'gender'        => $this->nullableString($parsed['gender'] ?? null),
                'id_number'     => $this->nullableString($parsed['id_number'] ?? null),
            ];
        } catch (\Throwable $e) {
            log_message('error', 'GovernmentIdVerificationService::extractFromImage failed: ' . $e->getMessage());

            return null;
        }
    }

    // ------------------------------------------------------------------
    // Matching
    // ------------------------------------------------------------------

    /**
     * @param array{full_name:?string, first_name:?string, middle_name:?string, last_name:?string, date_of_birth:?string, gender:?string} $extracted
     * @param array{first_name?:string, middle_name?:string, last_name?:string, date_of_birth?:string, gender?:string} $claimed
     * @return array{status: string, match_result: string, reason: ?string, message: string}
     */
    private function compareIdentity(array $extracted, array $claimed): array
    {
        $hasSeparateExtractedName = $this->nullableString($extracted['first_name'] ?? null) !== null
            && $this->nullableString($extracted['last_name'] ?? null) !== null;

        $reasons = [];
        $hardMismatch = false;
        $nameScore = 1.0;

        if ($hasSeparateExtractedName) {
            $lastScore = $this->nameSimilarity(
                $this->normalizeName((string) $extracted['last_name']),
                $this->normalizeName((string) ($claimed['last_name'] ?? ''))
            );
            $firstScore = $this->nameSimilarity(
                $this->normalizeName((string) $extracted['first_name']),
                $this->normalizeName((string) ($claimed['first_name'] ?? ''))
            );
            $nameScore = min($lastScore, $firstScore);

            if ($lastScore < 0.55 || $firstScore < 0.55) {
                $hardMismatch = true;
                $reasons[] = 'The first/last name on the ID does not match the name entered.';
            } elseif ($nameScore < 0.85) {
                $reasons[] = 'The first/last name on the ID does not closely match the name entered.';
            }

            $middleMismatch = $this->middleNameMismatch(
                $this->nullableString($extracted['middle_name'] ?? null),
                $this->nullableString($claimed['middle_name'] ?? null)
            );
            if ($middleMismatch) {
                $hardMismatch = true;
                $reasons[] = 'The middle name on the ID does not match the name entered.';
            }
        } else {
            $extractedName = $this->normalizeName((string) ($extracted['full_name'] ?? ''));
            $claimedFullName = trim(
                (string) ($claimed['first_name'] ?? '') . ' '
                . (string) ($claimed['middle_name'] ?? '') . ' '
                . (string) ($claimed['last_name'] ?? '')
            );
            $claimedName = $this->normalizeName($claimedFullName);

            if ($extractedName === '' || $claimedName === '') {
                return [
                    'status'       => 'needs_review',
                    'match_result' => 'incomplete',
                    'reason'       => 'A name could not be confirmed on the ID.',
                    'message'      => 'We could not read a name on this ID. Staff will review your submission.',
                ];
            }

            $nameScore = $this->nameSimilarity($extractedName, $claimedName);
            if ($nameScore < 0.55) {
                $hardMismatch = true;
                $reasons[] = 'The name on the ID does not match the name entered.';
            } elseif ($nameScore < 0.85) {
                $reasons[] = 'The name on the ID does not closely match the name entered.';
            }
        }

        // Date of birth: exact match once both sides actually have one to
        // compare - if the ID prints no DOB, or the form has none, it's
        // simply not checked, not treated as a pass or a fail.
        $extractedDob = $this->nullableString($extracted['date_of_birth'] ?? null);
        $claimedDob = $this->nullableString($claimed['date_of_birth'] ?? null);
        if ($extractedDob !== null && $claimedDob !== null
            && $this->normalizeDate($extractedDob) !== $this->normalizeDate($claimedDob)) {
            $hardMismatch = true;
            $reasons[] = "Date of birth on ID ({$this->normalizeDate($extractedDob)}) does not match entered date ({$this->normalizeDate($claimedDob)}).";
        }

        // Sex: not checked when the claimed value is 'Other' (nothing on
        // a Philippine government ID to compare that against) or either
        // side doesn't normalize to male/female.
        $extractedSex = $this->normalizeSex($extracted['gender'] ?? null);
        $claimedSex = $this->normalizeSex($claimed['gender'] ?? null);
        $claimedIsOther = strtolower(trim((string) ($claimed['gender'] ?? ''))) === 'other';
        if (! $claimedIsOther && $extractedSex !== null && $claimedSex !== null && $extractedSex !== $claimedSex) {
            $hardMismatch = true;
            $reasons[] = "Sex on ID ({$extractedSex}) does not match entered sex ({$claimedSex}).";
        }

        if ($hardMismatch) {
            return [
                'status'       => 'failed',
                'match_result' => 'mismatch',
                'reason'       => implode(' ', $reasons),
                'message'      => 'This ID does not match what you entered: ' . implode(' ', $reasons) . ' Please double-check your details, or upload the correct ID.',
            ];
        }

        if ($nameScore >= 0.85) {
            return [
                'status'       => 'verified',
                'match_result' => 'match',
                'reason'       => null,
                'message'      => 'Government ID verified.',
            ];
        }

        return [
            'status'       => 'needs_review',
            'match_result' => 'partial_match',
            'reason'       => $reasons !== [] ? implode(' ', $reasons) : 'Name matched closely but not exactly.',
            'message'      => 'Your ID could not be automatically confirmed with full confidence. Staff will review your submission.',
        ];
    }

    /**
     * True only when both sides actually have a middle name to compare
     * and they don't reasonably match. An ID printing no middle name at
     * all is not a mismatch - many Philippine IDs omit it or print only
     * an initial.
     */
    private function middleNameMismatch(?string $extractedMiddle, ?string $claimedMiddle): bool
    {
        if ($extractedMiddle === null || $claimedMiddle === null) {
            return false;
        }

        $extractedNorm = $this->normalizeName($extractedMiddle);
        $claimedNorm = $this->normalizeName($claimedMiddle);

        if ($extractedNorm === '' || $claimedNorm === '') {
            return false;
        }

        $isInitialMatch = mb_strlen($extractedNorm) === 1 && mb_substr($claimedNorm, 0, 1) === $extractedNorm;

        if ($isInitialMatch || $this->nameSimilarity($extractedNorm, $claimedNorm) >= 0.85) {
            return false;
        }

        return true;
    }

    /**
     * Uppercase, punctuation-stripped, whitespace-collapsed - tolerant of
     * "JUAN DELA CRUZ" vs "Juan D. Dela Cruz" style differences.
     */
    private function normalizeName(string $name): string
    {
        $name = mb_strtoupper(trim($name));
        $name = preg_replace('/[.,]/', '', $name) ?? $name;
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;

        return trim($name);
    }

    /**
     * Token-set similarity, 0.0-1.0. Tolerates a middle name appearing as
     * just an initial on either side (e.g. "JUAN D CRUZ" ~ "JUAN DELA
     * CRUZ") and single-character OCR typos per token.
     */
    private function nameSimilarity(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }

        $tokensA = array_values(array_filter(explode(' ', $a)));
        $tokensB = array_values(array_filter(explode(' ', $b)));

        if ($tokensA === [] || $tokensB === []) {
            return 0.0;
        }

        $remaining = $tokensB;
        $matched = 0;

        foreach ($tokensA as $tokenA) {
            foreach ($remaining as $i => $tokenB) {
                $isInitialMatch = (mb_strlen($tokenA) === 1 && mb_substr($tokenB, 0, 1) === $tokenA)
                    || (mb_strlen($tokenB) === 1 && mb_substr($tokenA, 0, 1) === $tokenB);

                if ($tokenA === $tokenB || $isInitialMatch || levenshtein($tokenA, $tokenB) <= 1) {
                    $matched++;
                    unset($remaining[$i]);
                    break;
                }
            }
        }

        return $matched / max(count($tokensA), count($tokensB));
    }

    /**
     * Normalizes a sex/gender value to exactly 'male', 'female', or null
     * (not printed / not recognized - e.g. an ID with no sex field, or a
     * value that isn't clearly one of the two).
     */
    private function normalizeSex(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        if ($value === 'male' || $value === 'm') {
            return 'male';
        }

        if ($value === 'female' || $value === 'f') {
            return 'female';
        }

        return null;
    }

    private function normalizeDate(string $date): string
    {
        $ts = strtotime($date);

        return $ts !== false ? date('Y-m-d', $ts) : trim($date);
    }

    private function toDbDate(?string $date): ?string
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        $ts = strtotime($date);

        return $ts !== false ? date('Y-m-d', $ts) : null;
    }

    /**
     * Keeps only the last 4 characters - "do not display complete ID
     * numbers unnecessarily".
     */
    private function maskIdNumber(?string $idNumber): ?string
    {
        if ($idNumber === null) {
            return null;
        }

        $clean = preg_replace('/\s+/', '', $idNumber) ?? $idNumber;
        $len = mb_strlen($clean);

        if ($len === 0) {
            return null;
        }

        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return str_repeat('*', $len - 4) . mb_substr($clean, -4);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    // ------------------------------------------------------------------
    // Persistence
    // ------------------------------------------------------------------

    private function persist(int $userId, string $idType, array $stored, array $extra): int
    {
        $existing = $this->model->latestForUser($userId);
        $attempts = $existing ? ((int) ($existing['verification_attempts'] ?? 0) + 1) : 1;

        $data = array_merge([
            'user_id'               => $userId,
            'id_type'                => $idType,
            'file_path'              => $stored['relative_path'],
            'original_name'          => $stored['original_name'],
            'mime_type'              => $stored['mime_type'],
            'verification_attempts'  => $attempts,
        ], $extra);

        return (int) $this->model->insert($data, true);
    }

    /**
     * @return array{status: string, message: string, verification_id: ?int}
     */
    private function result(string $status, string $message, ?int $verificationId = null): array
    {
        return [
            'status'           => $status,
            'message'          => $message,
            'verification_id'  => $verificationId,
        ];
    }
}
