/**
 * @fileoverview Configuration module — constants, default values, status
 * enums, and encrypted CRUD helpers over ScriptProperties. Secret values
 * are transparently encrypted via Utils.encryptSecret_() before storage.
 */

// ============================================================
// 1) ثوابت النظام (System Constants)
// ============================================================

/**
 * Property keys used for ScriptProperties storage.
 * @enum {string}
 */
const PROP_KEYS = Object.freeze({
  // --- cPanel connection (PHP Bridge) ---
  CPANEL_HOST:          'cpanel_host',
  CPANEL_USERNAME:      'cpanel_username',
  CPANEL_BRIDGE_URL:    'cpanel_bridge_url',
  CPANEL_BRIDGE_SECRET: 'cpanel_bridge_secret', // encrypted
  CPANEL_SOURCE_PATH:   'cpanel_source_path',

  // --- Google Drive target ---
  ROOT_DRIVE_FOLDER_ID: 'root_drive_folder_id',

  // --- Behavior ---
  SOURCE_DELETE_MODE:   'source_delete_mode',
  FILE_TYPE_FILTER:     'file_type_filter',
  MAX_RETRIES:          'max_retries',
  BANDWIDTH_LIMIT_MB:   'bandwidth_limit_mb',

  // --- Schedule ---
  SCHEDULE_FREQUENCY:   'schedule_frequency',
  SCHEDULE_TIME:        'schedule_time',

  // --- Notifications ---
  NOTIFICATION_EMAIL:   'notification_email',
  DAILY_REPORT_ENABLED: 'daily_report_enabled',
  ALERT_ON_FAILURE:     'alert_on_failure',

  // --- Runtime state ---
  ARCHIVE_STATUS:       'archive_status',
  LAST_RUN_TIMESTAMP:   'last_run_timestamp',
  CHECKPOINT_FILE_ID:   'checkpoint_file_id',
  LOG_SHEET_ID:         'log_sheet_id',
  DEDUP_SHEET_ID:       'dedup_sheet_id',
  PENDING_RETRY_PATHS:  'pending_retry_paths',

  // --- Security (internal) ---
  MASTER_KEY:           'master_key',
  CSRF_TOKEN_SALT:      'csrf_token_salt',
});

/**
 * Overall archive session status.
 * @enum {string}
 */
const ARCHIVE_STATUS = Object.freeze({
  IDLE:    'IDLE',
  ACTIVE:  'ACTIVE',
  PAUSED:  'PAUSED',
  ERROR:   'ERROR',
});

/**
 * Per-file archival outcome used in Logger sheet.
 * @enum {string}
 */
const FILE_STATUS = Object.freeze({
  SUCCESS:            'SUCCESS',
  SKIPPED_DUPLICATE:  'SKIPPED_DUPLICATE',
  VERSIONED:          'VERSIONED',
  FAILED:             'FAILED',
  PENDING_MANUAL:     'PENDING_MANUAL',
  CHECKSUM_MISMATCH:  'CHECKSUM_MISMATCH',
});

/**
 * Schedule frequency options exposed in the settings UI.
 * @enum {string}
 */
const SCHEDULE_FREQ = Object.freeze({
  HOURLY:  'HOURLY',
  DAILY:   'DAILY',
  WEEKLY:  'WEEKLY',
  CUSTOM:  'CUSTOM',
});

/**
 * System operational limits tuned for Gmail personal accounts
 * (6-minute hard execution limit, 50 MB Blob ceiling).
 */
const LIMITS = Object.freeze({
  MAX_EXECUTION_MS:       6 * 60 * 1000,
  TIME_BUDGET_RESERVE_MS: 60 * 1000,
  MAX_BLOB_SIZE:          50 * 1024 * 1024,
  CHUNK_SIZE:             10 * 1024 * 1024,
  DRIVE_MULTIPART_MAX:    5 * 1024 * 1024, // Drive multipart ceiling
  MAX_FILES_PER_BATCH:    50,
  MAX_CONSECUTIVE_FAILS:  20,
  RETRY_BASE_DELAY_MS:    1000,
  RETRY_MAX_DELAY_MS:     60000,
});

/**
 * Default values applied on first run for any missing keys.
 */
const DEFAULTS = Object.freeze({
  [PROP_KEYS.SOURCE_DELETE_MODE]:   'false',
  [PROP_KEYS.MAX_RETRIES]:          '3',
  [PROP_KEYS.BANDWIDTH_LIMIT_MB]:   '500',
  [PROP_KEYS.SCHEDULE_FREQUENCY]:   SCHEDULE_FREQ.DAILY,
  [PROP_KEYS.SCHEDULE_TIME]:        '02:00',
  [PROP_KEYS.DAILY_REPORT_ENABLED]: 'true',
  [PROP_KEYS.ALERT_ON_FAILURE]:     'true',
  [PROP_KEYS.FILE_TYPE_FILTER]:     '*',
  [PROP_KEYS.ARCHIVE_STATUS]:       ARCHIVE_STATUS.IDLE,
});

/**
 * Keys whose values are encrypted before persistence. getConfig()
 * and updateConfig() handle transparent encryption/decryption.
 */
const ENCRYPTED_KEYS = Object.freeze([
  PROP_KEYS.CPANEL_BRIDGE_SECRET,
]);

// ============================================================
// 2) CRUD على ScriptProperties
// ============================================================

/**
 * Retrieve a single config value, decrypting transparently if needed.
 * @param {string} key One of PROP_KEYS values.
 * @return {string|null} Stored value, or null if unset.
 * @throws {Error} If decryption fails (tampered envelope).
 */
function getConfig(key) {
  const raw = PropertiesService.getScriptProperties().getProperty(key);
  if (raw === null) return null;
  if (ENCRYPTED_KEYS.includes(key)) {
    try {
      return decryptSecret_(raw);
    } catch (err) {
      console.error('[Config] decrypt failed for ' + key + ': ' + err);
      throw new Error('Failed to decrypt: ' + key);
    }
  }
  return raw;
}

/**
 * Persist a single config value. Encrypts if key is in ENCRYPTED_KEYS.
 * @param {string} key One of PROP_KEYS values.
 * @param {string} value Value to store (will be coerced to string).
 */
function setConfig(key, value) {
  const stored = ENCRYPTED_KEYS.includes(key)
    ? encryptSecret_(String(value))
    : String(value);
  PropertiesService.getScriptProperties().setProperty(key, stored);
}

/**
 * Retrieve all configs as a plain object.
 * @param {boolean} [maskSecrets=false] If true, replaces secret values
 *     with a bullet mask (safe for rendering in the settings UI).
 * @return {!Object<string, string>}
 */
function getAllConfig(maskSecrets) {
  const all = PropertiesService.getScriptProperties().getProperties();
  const result = {};
  for (const k in all) {
    if (ENCRYPTED_KEYS.includes(k)) {
      if (maskSecrets) {
        result[k] = all[k] ? '••••••••' : '';
      } else {
        try { result[k] = decryptSecret_(all[k]); }
        catch (e) { result[k] = ''; }
      }
    } else {
      result[k] = all[k];
    }
  }
  return result;
}

/**
 * Bulk-update configs from an object. Silently ignores unknown keys.
 * @param {!Object<string, string>} updates
 */
function updateConfig(updates) {
  const valid = Object.values(PROP_KEYS);
  const batch = {};
  for (const k in updates) {
    if (!valid.includes(k)) continue;
    batch[k] = ENCRYPTED_KEYS.includes(k)
      ? encryptSecret_(String(updates[k]))
      : String(updates[k]);
  }
  if (Object.keys(batch).length > 0) {
    PropertiesService.getScriptProperties().setProperties(batch);
  }
}

/**
 * Remove a config entry.
 * @param {string} key
 */
function deleteConfig(key) {
  PropertiesService.getScriptProperties().deleteProperty(key);
}

/**
 * Populate any missing default values. Safe to call on every startup;
 * existing values are never overwritten.
 */
function initializeDefaults() {
  const existing = PropertiesService.getScriptProperties().getProperties();
  const toSet = {};
  for (const k in DEFAULTS) {
    if (!(k in existing)) toSet[k] = DEFAULTS[k];
  }
  if (Object.keys(toSet).length > 0) {
    PropertiesService.getScriptProperties().setProperties(toSet);
  }
}

/**
 * Verify that all required configs for archiving are present.
 * @return {{ok: boolean, missing: !Array<string>}}
 */
function validateConfig() {
  const required = [
    PROP_KEYS.CPANEL_BRIDGE_URL,
    PROP_KEYS.CPANEL_BRIDGE_SECRET,
    PROP_KEYS.CPANEL_SOURCE_PATH,
    PROP_KEYS.ROOT_DRIVE_FOLDER_ID,
    PROP_KEYS.NOTIFICATION_EMAIL,
  ];
  const missing = required.filter((k) => !getConfig(k));
  return { ok: missing.length === 0, missing: missing };
}
