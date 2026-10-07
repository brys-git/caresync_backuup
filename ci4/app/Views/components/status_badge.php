<?php
/**
 * Backwards-compatible wrapper.
 *
 * The 40-odd views already calling view('components/status_badge', [...])
 * keep working and silently pick up the new five-tone vocabulary.
 * New code should call cs_status() directly.
 */
echo cs_status((string) ($status ?? 'unknown'), isset($label) ? (string) $label : null);
