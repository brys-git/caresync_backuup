/**
 * Reusable Government ID Verification widget - upload-or-camera-capture,
 * preview, and automatic verification (AJAX) with a VERIFIED/NEEDS
 * REVIEW/FAILED result display. Used by every CareSync registration flow
 * that needs this step (client/plan_registration.php first; users/
 * create.php, plan_holders/register.php, branch_admin/clients/
 * register.php, staff/clients/register.php next) so the upload/camera/
 * matching UX only has to be built once. If the verification provider or
 * endpoint shape ever changes, only GovernmentIdVerificationService/
 * IdVerificationController (server side) need to change - this widget
 * just posts an image + a bit of form data and renders whatever status/
 * message comes back.
 *
 * Verification runs AUTOMATICALLY (no button click needed) once an image
 * is selected/captured, an ID type is chosen, and getIdentity() reports
 * enough to check (first + last name always; date of birth too, but only
 * on pages that actually collect it - see identityReady() below). It
 * re-runs automatically, debounced, whenever anything in the surrounding
 * <form> changes afterward (a generic form-level listener, not a fixed
 * list of field ids, so this works unmodified across every page's
 * different field layout - e.g. plan_holders/register.php's "existing
 * user" vs "new user" field sets). The small "Re-check ID" button stays
 * only as a manual fallback - identical to what auto-verification does,
 * just user-triggered.
 *
 * Usage:
 *   initIdVerificationWidget({
 *     endpoint: '/api/id-verification/verify',
 *     csrfName: 'csrf_test_name', csrfValue: '...',
 *     getIdentity: () => ({ first_name, middle_name, last_name, date_of_birth, gender }),
 *       // Omit a key entirely (rather than '') on a page that has no such
 *       // field at all (e.g. users/create.php has no date_of_birth input) -
 *       // that's how the widget knows not to wait on it before auto-running.
 *     resultFieldId: 'government_id_verification_id', // hidden field the
 *       AJAX response's id/token gets written into
 *     resultFieldKey: 'verification_id' | 'pending_token', // which key
 *       of the JSON response to write into resultFieldId
 *     initialStatus: null|'verified'|'needs_review'|'failed', // to show a
 *       "previous attempt on file" banner on page load, if any
 *   });
 *
 * Returns null if the page doesn't have the expected markup, otherwise:
 *   { wasAttempted, getStatus, isRunning, isStale }
 */
function initIdVerificationWidget(config) {
    const idTypeSelect = document.getElementById('id_type');
    const idFileInput = document.getElementById('idFileInput');
    const btnChooseUpload = document.getElementById('btnChooseUpload');
    const btnChooseCamera = document.getElementById('btnChooseCamera');
    const cameraPanel = document.getElementById('cameraPanel');
    const cameraVideo = document.getElementById('cameraVideo');
    const cameraError = document.getElementById('cameraError');
    const btnCapture = document.getElementById('btnCapture');
    const btnCancelCamera = document.getElementById('btnCancelCamera');
    const captureCanvas = document.getElementById('captureCanvas');
    const idPreviewWrap = document.getElementById('idPreviewWrap');
    const idPreviewImg = document.getElementById('idPreviewImg');
    const btnRetake = document.getElementById('btnRetake');
    const btnVerifyId = document.getElementById('btnVerifyId');
    const verifyHint = document.getElementById('verifyHint');
    const verificationResult = document.getElementById('verificationResult');
    const resultField = document.getElementById(config.resultFieldId);

    if (!idTypeSelect || !btnVerifyId) {
        return null;
    }

    let selectedBlob = null;
    let cameraStream = null;
    let attempted = !!config.initialStatus;
    let lastStatus = config.initialStatus || null;
    let isRunning = false;
    let isStale = false;
    let abortController = null;
    let identityDebounce = null;
    let lastCheckedKey = null;

    function esc(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    /**
     * Normalizes whatever config.getIdentity() returns for this page.
     * _hasDob records whether the caller's object even had a
     * "date_of_birth" key at all - that's the signal this widget uses to
     * tell "this page has no such field" (never wait on it) apart from
     * "this page has the field but it's empty so far" (wait for it).
     */
    function identitySnapshot() {
        const identity = (typeof config.getIdentity === 'function' ? config.getIdentity() : {}) || {};

        return {
            first_name: identity.first_name || '',
            middle_name: identity.middle_name || '',
            last_name: identity.last_name || '',
            date_of_birth: identity.date_of_birth || '',
            gender: identity.gender || '',
            _hasDob: Object.prototype.hasOwnProperty.call(identity, 'date_of_birth'),
        };
    }

    function identityReady(identity) {
        if (!identity.first_name || !identity.last_name) {
            return false;
        }
        if (identity._hasDob && !identity.date_of_birth) {
            return false;
        }
        return true;
    }

    function fingerprint(identity) {
        return [idTypeSelect.value, identity.first_name, identity.middle_name, identity.last_name, identity.date_of_birth, identity.gender].join('|');
    }

    function resetPreview() {
        selectedBlob = null;
        lastCheckedKey = null;
        lastStatus = null;
        isStale = false;
        idPreviewWrap.classList.add('d-none');
        idPreviewImg.src = '';
        verifyHint.textContent = 'Choose or capture an image first.';
        verifyHint.classList.remove('text-danger');
        verificationResult.classList.add('d-none');
        updateButtonState();
    }

    function setPreview(blob) {
        selectedBlob = blob;
        idPreviewImg.src = URL.createObjectURL(blob);
        idPreviewWrap.classList.remove('d-none');
        cameraPanel.classList.add('d-none');
        stopCamera();
        lastCheckedKey = null;
        maybeAutoVerify();
    }

    function stopCamera() {
        if (cameraStream) {
            cameraStream.getTracks().forEach(function (track) { track.stop(); });
            cameraStream = null;
        }
    }

    function updateButtonState() {
        const canRun = !!selectedBlob && !!idTypeSelect.value;
        btnVerifyId.disabled = !canRun || isRunning;
        btnVerifyId.textContent = isRunning ? 'Checking...' : (attempted ? 'Re-check ID' : 'Verify now');
    }

    function showChecking() {
        verificationResult.className = 'alert alert-secondary';
        verificationResult.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Checking your ID…';
        verificationResult.classList.remove('d-none');
    }

    function showResult(status, message) {
        const map = {
            verified: { cls: 'alert-success', icon: 'ti-circle-check', title: 'ID verified' },
            needs_review: { cls: 'alert-warning', icon: 'ti-alert-triangle', title: 'Needs staff review' },
            failed: { cls: 'alert-danger', icon: 'ti-circle-x', title: 'Doesn’t match' },
        };
        const info = map[status] || map.needs_review;
        verificationResult.className = 'alert ' + info.cls;
        verificationResult.innerHTML = '<i class="ti ' + info.icon + ' me-1"></i><strong>' + info.title + '</strong><div class="small mt-1">' + esc(message || '') + '</div>';
        verificationResult.classList.remove('d-none');
        attempted = true;
        lastStatus = status;
        isStale = false;
        verifyHint.textContent = '';
        verifyHint.classList.remove('text-danger');
        updateButtonState();
    }

    if (config.initialStatus) {
        showResult(config.initialStatus, 'A previous verification attempt is on file. You can retake or re-verify if needed.');
    }

    function runVerification(identity, key) {
        if (abortController) {
            abortController.abort();
        }
        abortController = new AbortController();
        isRunning = true;
        updateButtonState();
        showChecking();

        const ext = selectedBlob.type === 'image/png' ? 'png' : (selectedBlob.type === 'image/webp' ? 'webp' : 'jpg');
        const formData = new FormData();
        formData.append('id_image', selectedBlob, 'id_image.' + ext);
        formData.append('id_type', idTypeSelect.value);
        formData.append('first_name', identity.first_name);
        formData.append('middle_name', identity.middle_name);
        formData.append('last_name', identity.last_name);
        formData.append('date_of_birth', identity.date_of_birth);
        formData.append('gender', identity.gender);
        formData.append(config.csrfName, config.csrfValue);

        fetch(config.endpoint, { method: 'POST', body: formData, signal: abortController.signal })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                isRunning = false;
                lastCheckedKey = key;

                if (!result.ok && !result.data.status) {
                    showResult('needs_review', result.data.error || 'Unable to verify your ID right now. Please try again.');
                    return;
                }

                if (resultField) {
                    resultField.value = result.data[config.resultFieldKey] || '';
                }
                showResult(result.data.status, result.data.message);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') {
                    // Superseded by a newer check already in flight - that
                    // one's own .then/.catch will settle isRunning/state.
                    return;
                }
                isRunning = false;
                updateButtonState();
                showResult('needs_review', 'Unable to reach the verification service. Please check your connection and try again.');
            });
    }

    function maybeAutoVerify() {
        updateButtonState();

        if (!selectedBlob || !idTypeSelect.value) {
            return;
        }

        const identity = identitySnapshot();
        if (!identityReady(identity)) {
            return;
        }

        const key = fingerprint(identity);
        if (key === lastCheckedKey) {
            return;
        }

        if (attempted && lastStatus) {
            isStale = true;
            verifyHint.textContent = 'Details changed — re-checking…';
            verifyHint.classList.remove('text-danger');
        }

        runVerification(identity, key);
    }

    btnChooseUpload.addEventListener('click', function () {
        idFileInput.click();
    });

    idFileInput.addEventListener('change', function () {
        if (idFileInput.files && idFileInput.files[0]) {
            setPreview(idFileInput.files[0]);
        }
    });

    btnChooseCamera.addEventListener('click', function () {
        cameraError.classList.add('d-none');
        cameraPanel.classList.remove('d-none');
        idPreviewWrap.classList.add('d-none');

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            cameraError.textContent = 'Camera is not available on this browser/device. Please upload a file instead.';
            cameraError.classList.remove('d-none');
            return;
        }

        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
            .then(function (stream) {
                cameraStream = stream;
                cameraVideo.srcObject = stream;
            })
            .catch(function () {
                cameraError.textContent = 'Camera access was denied or is unavailable. Please upload a file instead.';
                cameraError.classList.remove('d-none');
                cameraPanel.classList.add('d-none');
            });
    });

    btnCancelCamera.addEventListener('click', function () {
        stopCamera();
        cameraPanel.classList.add('d-none');
    });

    btnCapture.addEventListener('click', function () {
        if (!cameraVideo.videoWidth) {
            return;
        }
        captureCanvas.width = cameraVideo.videoWidth;
        captureCanvas.height = cameraVideo.videoHeight;
        captureCanvas.getContext('2d').drawImage(cameraVideo, 0, 0);
        captureCanvas.toBlob(function (blob) {
            if (blob) {
                setPreview(blob);
            }
        }, 'image/jpeg', 0.92);
    });

    btnRetake.addEventListener('click', function () {
        resetPreview();
        idFileInput.value = '';
    });

    // Manual fallback - identical to what auto-verification does, just
    // user-triggered (e.g. to force a re-check with no actual field
    // change, or to retry sooner than the debounce would fire).
    btnVerifyId.addEventListener('click', function () {
        if (!selectedBlob || !idTypeSelect.value) {
            return;
        }
        lastCheckedKey = null;
        maybeAutoVerify();
    });

    idTypeSelect.addEventListener('change', function () {
        lastCheckedKey = null;
        maybeAutoVerify();
    });

    // Generic, field-agnostic watcher: every page using this widget has a
    // different field layout (plan_holders/register.php even has two
    // parallel field sets depending on its "existing"/"new" mode toggle),
    // so rather than requiring each page to enumerate its own identity
    // field ids, this listens for any change bubbling up from inside the
    // same <form> and re-reads getIdentity() itself. id_type/the file
    // input already have their own immediate (non-debounced) handlers
    // above, so they're excluded here to avoid double-triggering.
    const hostForm = idTypeSelect.closest('form');
    if (hostForm) {
        hostForm.addEventListener('input', onFormFieldChanged);
        hostForm.addEventListener('change', onFormFieldChanged);
    }

    function onFormFieldChanged(evt) {
        if (evt.target === idTypeSelect || evt.target === idFileInput) {
            return;
        }
        window.clearTimeout(identityDebounce);
        identityDebounce = window.setTimeout(maybeAutoVerify, 800);
    }

    updateButtonState();

    return {
        wasAttempted: function () { return attempted; },
        getStatus: function () { return lastStatus; },
        isRunning: function () { return isRunning; },
        isStale: function () { return isStale; },
    };
}
