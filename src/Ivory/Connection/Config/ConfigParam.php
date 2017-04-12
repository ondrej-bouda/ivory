<?php
namespace Ivory\Connection\Config;

/**
 * All PostgreSQL standard configuration parameters.
 *
 * The constant values reflect the configuration parameter names.
 *
 * @see http://www.postgresql.org/docs/9.4/static/runtime-config.html
 */
class ConfigParam
{
    //region Ivory-specifics
    /** Decimal separator used for <tt>money</tt> values. */
    const MONEY_DEC_SEP = '__' . __NAMESPACE__ . '_OPT_MONEY_DEC_SEP__';
    //endregion
    //region Specials
    const IS_SUPERUSER = 'is_superuser';
    const SESSION_AUTHORIZATION = 'session_authorization';
    //endregion
    //region File Location Settings
    const DATA_DIRECTORY = 'data_directory';
    const CONFIG_FILE = 'config_file';
    const HBA_FILE = 'hba_file';
    const IDENT_FILE = 'ident_file';
    const EXTERNAL_PID_FILE = 'external_pid_file';
    //endregion
    //region Connection Settings
    const LISTEN_ADDRESSES = 'listen_addresses';
    const PORT = 'port';
    const MAX_CONNECTIONS = 'max_connections';
    const SUPERUSER_RESERVED_CONNECTIONS = 'superuser_reserved_connections';
    const UNIX_SOCKET_DIRECTORIES = 'unix_socket_directories';
    const UNIX_SOCKET_GROUP = 'unix_socket_group';
    const UNIX_SOCKET_PERMISSIONS = 'unix_socket_permissions';
    const BONJOUR = 'bonjour';
    const BONJOUR_NAME = 'bonjour_name';
    const TCP_KEEPALIVES_IDLE = 'tcp_keepalives_idle';
    const TCP_KEEPALIVES_INTERVAL = 'tcp_keepalives_interval';
    const TCP_KEEPALIVES_COUNT = 'tcp_keepalives_count';
    //endregion
    //region Authentication Settings
    const AUTHENTICATION_TIMEOUT = 'authentication_timeout';
    const SSL = 'ssl';
    const SSL_CA_FILE = 'ssl_ca_file';
    const SSL_CERT_FILE = 'ssl_cert_file';
    const SSL_CRL_FILE = 'ssl_crl_file';
    const SSL_KEY_FILE = 'ssl_key_file';
    const SSL_RENEGOTIATION_LIMIT = 'ssl_renegotiation_limit';
    const SSL_CIPHERS = 'ssl_ciphers';
    const SSL_PREFER_SERVER_CIPHERS = 'ssl_prefer_server_ciphers';
    const SSL_ECDH_CURVE = 'ssl_ecdh_curve';
    const PASSWORD_ENCRYPTION = 'password_encryption';
    const KRB_SERVER_KEYFILE = 'krb_server_keyfile';
    const KRB_CASEINS_USERS = 'krb_caseins_users';
    const DB_USER_NAMESPACE = 'db_user_namespace';
    //endregion
    //region Resource Consumption Settings
    const SHARED_BUFFERS = 'shared_buffers';
    const HUGE_PAGES = 'huge_pages';
    const TEMP_BUFFERS = 'temp_buffers';
    const MAX_PREPARED_TRANSACTIONS = 'max_prepared_transactions';
    const WORK_MEM = 'work_mem';
    const MAINTENANCE_WORK_MEM = 'maintenance_work_mem';
    const AUTOVACUUM_WORK_MEM = 'autovacuum_work_mem';
    const MAX_STACK_DEPTH = 'max_stack_depth';
    const DYNAMIC_SHARED_MEMORY_TYPE = 'dynamic_shared_memory_type';
    const TEMP_FILE_LIMIT = 'temp_file_limit';
    const MAX_FILES_PER_PROCESS = 'max_files_per_process';
    const VACUUM_COST_DELAY = 'vacuum_cost_delay';
    const VACUUM_COST_PAGE_HIT = 'vacuum_cost_page_hit';
    const VACUUM_COST_PAGE_MISS = 'vacuum_cost_page_miss';
    const VACUUM_COST_PAGE_DIRTY = 'vacuum_cost_page_dirty';
    const VACUUM_COST_LIMIT = 'vacuum_cost_limit';
    const BGWRITER_DELAY = 'bgwriter_delay';
    const BGWRITER_LRU_MAXPAGES = 'bgwriter_lru_maxpages';
    const BGWRITER_LRU_MULTIPLIER = 'bgwriter_lru_multiplier';
    const EFFECTIVE_IO_CONCURRENCY = 'effective_io_concurrency';
    const MAX_WORKER_PROCESSES = 'max_worker_processes';
    //endregion
    //region Write Ahead Log Settings
    const WAL_LEVEL = 'wal_level';
    const FSYNC = 'fsync';
    const SYNCHRONOUS_COMMIT = 'synchronous_commit';
    const WAL_SYNC_METHOD = 'wal_sync_method';
    const FULL_PAGE_WRITES = 'full_page_writes';
    const WAL_LOG_HINTS = 'wal_log_hints';
    const WAL_BUFFERS = 'wal_buffers';
    const WAL_WRITER_DELAY = 'wal_writer_delay';
    const COMMIT_DELAY = 'commit_delay';
    const COMMIT_SIBLINGS = 'commit_siblings';
    const CHECKPOINT_SEGMENTS = 'checkpoint_segments';
    const CHECKPOINT_TIMEOUT = 'checkpoint_timeout';
    const CHECKPOINT_COMPLETION_TARGET = 'checkpoint_completion_target';
    const CHECKPOINT_WARNING = 'checkpoint_warning';
    const ARCHIVE_MODE = 'archive_mode';
    const ARCHIVE_COMMAND = 'archive_command';
    const ARCHIVE_TIMEOUT = 'archive_timeout';
    //endregion
    //region Replication Settings
    const MAX_WAL_SENDERS = 'max_wal_senders';
    const MAX_REPLICATION_SLOTS = 'max_replication_slots';
    const WAL_KEEP_SEGMENTS = 'wal_keep_segments';
    const WAL_SENDER_TIMEOUT = 'wal_sender_timeout';
    const SYNCHRONOUS_STANDBY_NAMES = 'synchronous_standby_names';
    const VACUUM_DEFER_CLEANUP_AGE = 'vacuum_defer_cleanup_age';
    const HOT_STANDBY = 'hot_standby';
    const MAX_STANDBY_ARCHIVE_DELAY = 'max_standby_archive_delay';
    const MAX_STANDBY_STREAMING_DELAY = 'max_standby_streaming_delay';
    const WAL_RECEIVER_STATUS_INTERVAL = 'wal_receiver_status_interval';
    const HOT_STANDBY_FEEDBACK = 'hot_standby_feedback';
    const WAL_RECEIVER_TIMEOUT = 'wal_receiver_timeout';
    //endregion
    //region Query Planning Settings
    const ENABLE_BITMAPSCAN = 'enable_bitmapscan';
    const ENABLE_HASHAGG = 'enable_hashagg';
    const ENABLE_HASHJOIN = 'enable_hashjoin';
    const ENABLE_INDEXSCAN = 'enable_indexscan';
    const ENABLE_INDEXONLYSCAN = 'enable_indexonlyscan';
    const ENABLE_MATERIAL = 'enable_material';
    const ENABLE_MERGEJOIN = 'enable_mergejoin';
    const ENABLE_NESTLOOP = 'enable_nestloop';
    const ENABLE_SEQSCAN = 'enable_seqscan';
    const ENABLE_SORT = 'enable_sort';
    const ENABLE_TIDSCAN = 'enable_tidscan';
    const SEQ_PAGE_COST = 'seq_page_cost';
    const RANDOM_PAGE_COST = 'random_page_cost';
    const CPU_TUPLE_COST = 'cpu_tuple_cost';
    const CPU_INDEX_TUPLE_COST = 'cpu_index_tuple_cost';
    const CPU_OPERATOR_COST = 'cpu_operator_cost';
    const EFFECTIVE_CACHE_SIZE = 'effective_cache_size';
    const GEQO = 'geqo';
    const GEQO_THRESHOLD = 'geqo_threshold';
    const GEQO_EFFORT = 'geqo_effort';
    const GEQO_POOL_SIZE = 'geqo_pool_size';
    const GEQO_GENERATIONS = 'geqo_generations';
    const GEQO_SELECTION_BIAS = 'geqo_selection_bias';
    const GEQO_SEED = 'geqo_seed';
    const DEFAULT_STATISTICS_TARGET = 'default_statistics_target';
    const CONSTRAINT_EXCLUSION = 'constraint_exclusion';
    const CURSOR_TUPLE_FRACTION = 'cursor_tuple_fraction';
    const FROM_COLLAPSE_LIMIT = 'from_collapse_limit';
    const JOIN_COLLAPSE_LIMIT = 'join_collapse_limit';
    //endregion
    //region Error Reporting and Logging Settings
    const LOG_DESTINATION = 'log_destination';
    const LOGGING_COLLECTOR = 'logging_collector';
    const LOG_DIRECTORY = 'log_directory';
    const LOG_FILENAME = 'log_filename';
    const LOG_FILE_MODE = 'log_file_mode';
    const LOG_ROTATION_AGE = 'log_rotation_age';
    const LOG_ROTATION_SIZE = 'log_rotation_size';
    const LOG_TRUNCATE_ON_ROTATION = 'log_truncate_on_rotation';
    const SYSLOG_FACILITY = 'syslog_facility';
    const SYSLOG_IDENT = 'syslog_ident';
    const EVENT_SOURCE = 'event_source';
    const CLIENT_MIN_MESSAGES = 'client_min_messages';
    const LOG_MIN_MESSAGES = 'log_min_messages';
    const LOG_MIN_ERROR_STATEMENT = 'log_min_error_statement';
    const LOG_MIN_DURATION_STATEMENT = 'log_min_duration_statement';
    const APPLICATION_NAME = 'application_name';
    const DEBUG_PRINT_PARSE = 'debug_print_parse';
    const DEBUG_PRINT_REWRITTEN = 'debug_print_rewritten';
    const DEBUG_PRINT_PLAN = 'debug_print_plan';
    const DEBUG_PRETTY_PRINT = 'debug_pretty_print';
    const LOG_CHECKPOINTS = 'log_checkpoints';
    const LOG_CONNECTIONS = 'log_connections';
    const LOG_DISCONNECTIONS = 'log_disconnections';
    const LOG_DURATION = 'log_duration';
    const LOG_ERROR_VERBOSITY = 'log_error_verbosity';
    const LOG_HOSTNAME = 'log_hostname';
    const LOG_LINE_PREFIX = 'log_line_prefix';
    const LOG_LOCK_WAITS = 'log_lock_waits';
    const LOG_STATEMENT = 'log_statement';
    const LOG_TEMP_FILES = 'log_temp_files';
    const LOG_TIMEZONE = 'log_timezone';
    //endregion
    //region Run-time Statistics Settings
    const TRACK_ACTIVITIES = 'track_activities';
    const TRACK_ACTIVITY_QUERY_SIZE = 'track_activity_query_size';
    const TRACK_COUNTS = 'track_counts';
    const TRACK_IO_TIMING = 'track_io_timing';
    const TRACK_FUNCTIONS = 'track_functions';
    const UPDATE_PROCESS_TITLE = 'update_process_title';
    const STATS_TEMP_DIRECTORY = 'stats_temp_directory';
    const LOG_STATEMENT_STATS = 'log_statement_stats';
    const LOG_PARSER_STATS = 'log_parser_stats';
    const LOG_PLANNER_STATS = 'log_planner_stats';
    const LOG_EXECUTOR_STATS = 'log_executor_stats';
    //endregion
    //region Automatic Vacuuming Settings
    const AUTOVACUUM = 'autovacuum';
    const LOG_AUTOVACUUM_MIN_DURATION = 'log_autovacuum_min_duration';
    const AUTOVACUUM_MAX_WORKERS = 'autovacuum_max_workers';
    const AUTOVACUUM_NAPTIME = 'autovacuum_naptime';
    const AUTOVACUUM_VACUUM_THRESHOLD = 'autovacuum_vacuum_threshold';
    const AUTOVACUUM_ANALYZE_THRESHOLD = 'autovacuum_analyze_threshold';
    const AUTOVACUUM_VACUUM_SCALE_FACTOR = 'autovacuum_vacuum_scale_factor';
    const AUTOVACUUM_ANALYZE_SCALE_FACTOR = 'autovacuum_analyze_scale_factor';
    const AUTOVACUUM_FREEZE_MAX_AGE = 'autovacuum_freeze_max_age';
    const AUTOVACUUM_MULTIXACT_FREEZE_MAX_AGE = 'autovacuum_multixact_freeze_max_age';
    const AUTOVACUUM_VACUUM_COST_DELAY = 'autovacuum_vacuum_cost_delay';
    const AUTOVACUUM_VACUUM_COST_LIMIT = 'autovacuum_vacuum_cost_limit';
    //endregion
    //region Client Connection Settings
    /** The explicitly set search path. See also {@link IConnConfig::getEffectiveSearchPath()}. */
    const SEARCH_PATH = 'search_path';
    const ROW_SECURITY = 'row_security';
    const DEFAULT_TABLESPACE = 'default_tablespace';
    const TEMP_TABLESPACES = 'temp_tablespaces';
    const CHECK_FUNCTION_BODIES = 'check_function_bodies';
    const DEFAULT_TRANSACTION_ISOLATION = 'default_transaction_isolation';
    const DEFAULT_TRANSACTION_READ_ONLY = 'default_transaction_read_only';
    const DEFAULT_TRANSACTION_DEFERRABLE = 'default_transaction_deferrable';
    const SESSION_REPLICATION_ROLE = 'session_replication_role';
    const STATEMENT_TIMEOUT = 'statement_timeout';
    const LOCK_TIMEOUT = 'lock_timeout';
    const VACUUM_FREEZE_TABLE_AGE = 'vacuum_freeze_table_age';
    const VACUUM_FREEZE_MIN_AGE = 'vacuum_freeze_min_age';
    const VACUUM_MULTIXACT_FREEZE_TABLE_AGE = 'vacuum_multixact_freeze_table_age';
    const VACUUM_MULTIXACT_FREEZE_MIN_AGE = 'vacuum_multixact_freeze_min_age';
    const BYTEA_OUTPUT = 'bytea_output';
    const XMLBINARY = 'xmlbinary';
    const XMLOPTION = 'xmloption';
    const GIN_PENDING_LIST_LIMIT = 'gin_pending_list_limit';
    const DATE_STYLE = 'DateStyle';
    const INTERVAL_STYLE = 'IntervalStyle';
    const TIME_ZONE = 'TimeZone';
    const TIMEZONE_ABBREVIATIONS = 'timezone_abbreviations';
    const EXTRA_FLOAT_DIGITS = 'extra_float_digits';
    const CLIENT_ENCODING = 'client_encoding';
    const LC_MESSAGES = 'lc_messages';
    const LC_MONETARY = 'lc_monetary';
    const LC_NUMERIC = 'lc_numeric';
    const LC_TIME = 'lc_time';
    const DEFAULT_TEXT_SEARCH_CONFIG = 'default_text_search_config';
    const LOCAL_PRELOAD_LIBRARIES = 'local_preload_libraries';
    const SESSION_PRELOAD_LIBRARIES = 'session_preload_libraries';
    const SHARED_PRELOAD_LIBRARIES = 'shared_preload_libraries';
    const DYNAMIC_LIBRARY_PATH = 'dynamic_library_path';
    const GIN_FUZZY_SEARCH_LIMIT = 'gin_fuzzy_search_limit';
    //endregion
    //region Lock Management Settings
    const DEADLOCK_TIMEOUT = 'deadlock_timeout';
    const MAX_LOCKS_PER_TRANSACTION = 'max_locks_per_transaction';
    const MAX_PRED_LOCKS_PER_TRANSACTION = 'max_pred_locks_per_transaction';
    //endregion
    //region Version and Platform Compatibility Settings
    const ARRAY_NULLS = 'array_nulls';
    const BACKSLASH_QUOTE = 'backslash_quote';
    const DEFAULT_WITH_OIDS = 'default_with_oids';
    const ESCAPE_STRING_WARNING = 'escape_string_warning';
    const LO_COMPAT_PRIVILEGES = 'lo_compat_privileges';
    const QUOTE_ALL_IDENTIFIERS = 'quote_all_identifiers';
    const SQL_INHERITANCE = 'sql_inheritance';
    const STANDARD_CONFORMING_STRINGS = 'standard_conforming_strings';
    const SYNCHRONIZE_SEQSCANS = 'synchronize_seqscans';
    const TRANSFORM_NULL_EQUALS = 'transform_null_equals';
    //endregion
    //region Error Handling Settings
    const EXIT_ON_ERROR = 'exit_on_error';
    const RESTART_AFTER_CRASH = 'restart_after_crash';
    //endregion
    //region Preset Options
    const BLOCK_SIZE = 'block_size';
    const DATA_CHECKSUMS = 'data_checksums';
    const INTEGER_DATETIMES = 'integer_datetimes';
    const LC_COLLATE = 'lc_collate';
    const LC_CTYPE = 'lc_ctype';
    const MAX_FUNCTION_ARGS = 'max_function_args';
    const MAX_IDENTIFIER_LENGTH = 'max_identifier_length';
    const MAX_INDEX_KEYS = 'max_index_keys';
    const SEGMENT_SIZE = 'segment_size';
    const SERVER_ENCODING = 'server_encoding';
    const SERVER_VERSION = 'server_version';
    const SERVER_VERSION_NUM = 'server_version_num';
    const WAL_BLOCK_SIZE = 'wal_block_size';
    const WAL_SEGMENT_SIZE = 'wal_segment_size';
    //endregion
    //region Developer Options
    const ALLOW_SYSTEM_TABLE_MODS = 'allow_system_table_mods';
    const DEBUG_ASSERTIONS = 'debug_assertions';
    const IGNORE_SYSTEM_INDEXES = 'ignore_system_indexes';
    const POST_AUTH_DELAY = 'post_auth_delay';
    const PRE_AUTH_DELAY = 'pre_auth_delay';
    const TRACE_NOTIFY = 'trace_notify';
    const TRACE_RECOVERY_MESSAGES = 'trace_recovery_messages';
    const TRACE_SORT = 'trace_sort';
    const TRACE_LOCKS = 'trace_locks';
    const TRACE_LWLOCKS = 'trace_lwlocks';
    const TRACE_USERLOCKS = 'trace_userlocks';
    const TRACE_LOCK_OIDMIN = 'trace_lock_oidmin';
    const TRACE_LOCK_TABLE = 'trace_lock_table';
    const DEBUG_DEADLOCKS = 'debug_deadlocks';
    const LOG_BTREE_BUILD_STATS = 'log_btree_build_stats';
    const WAL_DEBUG = 'wal_debug';
    const IGNORE_CHECKSUM_FAILURE = 'ignore_checksum_failure';
    const ZERO_DAMAGED_PAGES = 'zero_damaged_pages';
    //endregion


    const TYPEMAP = [
        self::MONEY_DEC_SEP => ConfigParamType::STRING,

        self::IS_SUPERUSER => ConfigParamType::BOOL,
        self::SESSION_AUTHORIZATION => ConfigParamType::STRING,

        self::DATA_DIRECTORY => ConfigParamType::STRING,
        self::CONFIG_FILE => ConfigParamType::STRING,
        self::HBA_FILE => ConfigParamType::STRING,
        self::IDENT_FILE => ConfigParamType::STRING,
        self::EXTERNAL_PID_FILE => ConfigParamType::STRING,

        self::LISTEN_ADDRESSES => ConfigParamType::STRING,
        self::PORT => ConfigParamType::INTEGER,
        self::MAX_CONNECTIONS => ConfigParamType::INTEGER,
        self::SUPERUSER_RESERVED_CONNECTIONS => ConfigParamType::INTEGER,
        self::UNIX_SOCKET_DIRECTORIES => ConfigParamType::STRING,
        self::UNIX_SOCKET_GROUP => ConfigParamType::STRING,
        self::UNIX_SOCKET_PERMISSIONS => ConfigParamType::INTEGER,
        self::BONJOUR => ConfigParamType::BOOL,
        self::BONJOUR_NAME => ConfigParamType::STRING,
        self::TCP_KEEPALIVES_IDLE => ConfigParamType::INTEGER_WITH_UNIT,
        self::TCP_KEEPALIVES_INTERVAL => ConfigParamType::INTEGER_WITH_UNIT,
        self::TCP_KEEPALIVES_COUNT => ConfigParamType::INTEGER,

        self::AUTHENTICATION_TIMEOUT => ConfigParamType::INTEGER_WITH_UNIT,
        self::SSL => ConfigParamType::BOOL,
        self::SSL_CA_FILE => ConfigParamType::STRING,
        self::SSL_CERT_FILE => ConfigParamType::STRING,
        self::SSL_CRL_FILE => ConfigParamType::STRING,
        self::SSL_KEY_FILE => ConfigParamType::STRING,
        self::SSL_RENEGOTIATION_LIMIT => ConfigParamType::INTEGER_WITH_UNIT,
        self::SSL_CIPHERS => ConfigParamType::STRING,
        self::SSL_PREFER_SERVER_CIPHERS => ConfigParamType::BOOL,
        self::SSL_ECDH_CURVE => ConfigParamType::STRING,
        self::PASSWORD_ENCRYPTION => ConfigParamType::BOOL,
        self::KRB_SERVER_KEYFILE => ConfigParamType::STRING,
        self::KRB_CASEINS_USERS => ConfigParamType::BOOL,
        self::DB_USER_NAMESPACE => ConfigParamType::BOOL,

        self::SHARED_BUFFERS => ConfigParamType::INTEGER_WITH_UNIT,
        self::HUGE_PAGES => ConfigParamType::ENUM,
        self::TEMP_BUFFERS => ConfigParamType::INTEGER_WITH_UNIT,
        self::MAX_PREPARED_TRANSACTIONS => ConfigParamType::INTEGER,
        self::WORK_MEM => ConfigParamType::INTEGER_WITH_UNIT,
        self::MAINTENANCE_WORK_MEM => ConfigParamType::INTEGER_WITH_UNIT,
        self::AUTOVACUUM_WORK_MEM => ConfigParamType::INTEGER_WITH_UNIT,
        self::MAX_STACK_DEPTH => ConfigParamType::INTEGER_WITH_UNIT,
        self::DYNAMIC_SHARED_MEMORY_TYPE => ConfigParamType::ENUM,
        self::TEMP_FILE_LIMIT => ConfigParamType::INTEGER_WITH_UNIT,
        self::MAX_FILES_PER_PROCESS => ConfigParamType::INTEGER,
        self::VACUUM_COST_DELAY => ConfigParamType::INTEGER_WITH_UNIT,
        self::VACUUM_COST_PAGE_HIT => ConfigParamType::INTEGER,
        self::VACUUM_COST_PAGE_MISS => ConfigParamType::INTEGER,
        self::VACUUM_COST_PAGE_DIRTY => ConfigParamType::INTEGER,
        self::VACUUM_COST_LIMIT => ConfigParamType::INTEGER,
        self::BGWRITER_DELAY => ConfigParamType::INTEGER_WITH_UNIT,
        self::BGWRITER_LRU_MAXPAGES => ConfigParamType::INTEGER,
        self::BGWRITER_LRU_MULTIPLIER => ConfigParamType::REAL,
        self::EFFECTIVE_IO_CONCURRENCY => ConfigParamType::INTEGER,
        self::MAX_WORKER_PROCESSES => ConfigParamType::INTEGER,

        self::WAL_LEVEL => ConfigParamType::ENUM,
        self::FSYNC => ConfigParamType::BOOL,
        self::SYNCHRONOUS_COMMIT => ConfigParamType::ENUM,
        self::WAL_SYNC_METHOD => ConfigParamType::ENUM,
        self::FULL_PAGE_WRITES => ConfigParamType::BOOL,
        self::WAL_LOG_HINTS => ConfigParamType::BOOL,
        self::WAL_BUFFERS => ConfigParamType::INTEGER_WITH_UNIT,
        self::WAL_WRITER_DELAY => ConfigParamType::INTEGER_WITH_UNIT,
        self::COMMIT_DELAY => ConfigParamType::INTEGER,
        self::COMMIT_SIBLINGS => ConfigParamType::INTEGER,
        self::CHECKPOINT_SEGMENTS => ConfigParamType::INTEGER,
        self::CHECKPOINT_TIMEOUT => ConfigParamType::INTEGER_WITH_UNIT,
        self::CHECKPOINT_COMPLETION_TARGET => ConfigParamType::REAL,
        self::CHECKPOINT_WARNING => ConfigParamType::INTEGER_WITH_UNIT,
        self::ARCHIVE_MODE => ConfigParamType::BOOL,
        self::ARCHIVE_COMMAND => ConfigParamType::STRING,
        self::ARCHIVE_TIMEOUT => ConfigParamType::INTEGER_WITH_UNIT,

        self::MAX_WAL_SENDERS => ConfigParamType::INTEGER,
        self::MAX_REPLICATION_SLOTS => ConfigParamType::INTEGER,
        self::WAL_KEEP_SEGMENTS => ConfigParamType::INTEGER,
        self::WAL_SENDER_TIMEOUT => ConfigParamType::INTEGER_WITH_UNIT,
        self::SYNCHRONOUS_STANDBY_NAMES => ConfigParamType::STRING,
        self::VACUUM_DEFER_CLEANUP_AGE => ConfigParamType::INTEGER,
        self::HOT_STANDBY => ConfigParamType::BOOL,
        self::MAX_STANDBY_ARCHIVE_DELAY => ConfigParamType::INTEGER_WITH_UNIT,
        self::MAX_STANDBY_STREAMING_DELAY => ConfigParamType::INTEGER_WITH_UNIT,
        self::WAL_RECEIVER_STATUS_INTERVAL => ConfigParamType::INTEGER_WITH_UNIT,
        self::HOT_STANDBY_FEEDBACK => ConfigParamType::BOOL,
        self::WAL_RECEIVER_TIMEOUT => ConfigParamType::INTEGER_WITH_UNIT,

        self::ENABLE_BITMAPSCAN => ConfigParamType::BOOL,
        self::ENABLE_HASHAGG => ConfigParamType::BOOL,
        self::ENABLE_HASHJOIN => ConfigParamType::BOOL,
        self::ENABLE_INDEXSCAN => ConfigParamType::BOOL,
        self::ENABLE_INDEXONLYSCAN => ConfigParamType::BOOL,
        self::ENABLE_MATERIAL => ConfigParamType::BOOL,
        self::ENABLE_MERGEJOIN => ConfigParamType::BOOL,
        self::ENABLE_NESTLOOP => ConfigParamType::BOOL,
        self::ENABLE_SEQSCAN => ConfigParamType::BOOL,
        self::ENABLE_SORT => ConfigParamType::BOOL,
        self::ENABLE_TIDSCAN => ConfigParamType::BOOL,
        self::SEQ_PAGE_COST => ConfigParamType::REAL,
        self::RANDOM_PAGE_COST => ConfigParamType::REAL,
        self::CPU_TUPLE_COST => ConfigParamType::REAL,
        self::CPU_INDEX_TUPLE_COST => ConfigParamType::REAL,
        self::CPU_OPERATOR_COST => ConfigParamType::REAL,
        self::EFFECTIVE_CACHE_SIZE => ConfigParamType::INTEGER_WITH_UNIT,
        self::GEQO => ConfigParamType::BOOL,
        self::GEQO_THRESHOLD => ConfigParamType::INTEGER,
        self::GEQO_EFFORT => ConfigParamType::INTEGER,
        self::GEQO_POOL_SIZE => ConfigParamType::INTEGER,
        self::GEQO_GENERATIONS => ConfigParamType::INTEGER,
        self::GEQO_SELECTION_BIAS => ConfigParamType::REAL,
        self::GEQO_SEED => ConfigParamType::REAL,
        self::DEFAULT_STATISTICS_TARGET => ConfigParamType::INTEGER,
        self::CONSTRAINT_EXCLUSION => ConfigParamType::ENUM,
        self::CURSOR_TUPLE_FRACTION => ConfigParamType::REAL,
        self::FROM_COLLAPSE_LIMIT => ConfigParamType::INTEGER,
        self::JOIN_COLLAPSE_LIMIT => ConfigParamType::INTEGER,

        self::LOG_DESTINATION => ConfigParamType::STRING,
        self::LOGGING_COLLECTOR => ConfigParamType::BOOL,
        self::LOG_DIRECTORY => ConfigParamType::STRING,
        self::LOG_FILE_MODE => ConfigParamType::STRING,
        self::LOG_FILE_MODE => ConfigParamType::INTEGER,
        self::LOG_ROTATION_AGE => ConfigParamType::INTEGER_WITH_UNIT,
        self::LOG_ROTATION_SIZE => ConfigParamType::INTEGER_WITH_UNIT,
        self::LOG_TRUNCATE_ON_ROTATION => ConfigParamType::BOOL,
        self::SYSLOG_FACILITY => ConfigParamType::ENUM,
        self::SYSLOG_IDENT => ConfigParamType::STRING,
        self::EVENT_SOURCE => ConfigParamType::STRING,
        self::CLIENT_MIN_MESSAGES => ConfigParamType::ENUM,
        self::LOG_MIN_MESSAGES => ConfigParamType::ENUM,
        self::LOG_MIN_ERROR_STATEMENT => ConfigParamType::ENUM,
        self::LOG_MIN_DURATION_STATEMENT => ConfigParamType::INTEGER_WITH_UNIT,
        self::APPLICATION_NAME => ConfigParamType::STRING,
        self::DEBUG_PRINT_PARSE => ConfigParamType::BOOL,
        self::DEBUG_PRINT_REWRITTEN => ConfigParamType::BOOL,
        self::DEBUG_PRINT_PLAN => ConfigParamType::BOOL,
        self::DEBUG_PRETTY_PRINT => ConfigParamType::BOOL,
        self::LOG_CHECKPOINTS => ConfigParamType::BOOL,
        self::LOG_CONNECTIONS => ConfigParamType::BOOL,
        self::LOG_DISCONNECTIONS => ConfigParamType::BOOL,
        self::LOG_DURATION => ConfigParamType::BOOL,
        self::LOG_ERROR_VERBOSITY => ConfigParamType::ENUM,
        self::LOG_HOSTNAME => ConfigParamType::BOOL,
        self::LOG_LINE_PREFIX => ConfigParamType::STRING,
        self::LOG_LOCK_WAITS => ConfigParamType::BOOL,
        self::LOG_STATEMENT => ConfigParamType::ENUM,
        self::LOG_TEMP_FILES => ConfigParamType::INTEGER_WITH_UNIT,
        self::LOG_TIMEZONE => ConfigParamType::STRING,

        self::TRACK_ACTIVITIES => ConfigParamType::BOOL,
        self::TRACK_ACTIVITY_QUERY_SIZE => ConfigParamType::INTEGER,
        self::TRACK_COUNTS => ConfigParamType::BOOL,
        self::TRACK_IO_TIMING => ConfigParamType::BOOL,
        self::TRACK_FUNCTIONS => ConfigParamType::ENUM,
        self::UPDATE_PROCESS_TITLE => ConfigParamType::BOOL,
        self::STATS_TEMP_DIRECTORY => ConfigParamType::STRING,
        self::LOG_STATEMENT_STATS => ConfigParamType::BOOL,
        self::LOG_PARSER_STATS => ConfigParamType::BOOL,
        self::LOG_PLANNER_STATS => ConfigParamType::BOOL,
        self::LOG_EXECUTOR_STATS => ConfigParamType::BOOL,

        self::AUTOVACUUM => ConfigParamType::BOOL,
        self::LOG_AUTOVACUUM_MIN_DURATION => ConfigParamType::INTEGER_WITH_UNIT,
        self::AUTOVACUUM_MAX_WORKERS => ConfigParamType::INTEGER,
        self::AUTOVACUUM_NAPTIME => ConfigParamType::INTEGER_WITH_UNIT,
        self::AUTOVACUUM_VACUUM_THRESHOLD => ConfigParamType::INTEGER,
        self::AUTOVACUUM_ANALYZE_THRESHOLD => ConfigParamType::INTEGER,
        self::AUTOVACUUM_VACUUM_SCALE_FACTOR => ConfigParamType::REAL,
        self::AUTOVACUUM_ANALYZE_SCALE_FACTOR => ConfigParamType::REAL,
        self::AUTOVACUUM_FREEZE_MAX_AGE => ConfigParamType::INTEGER,
        self::AUTOVACUUM_MULTIXACT_FREEZE_MAX_AGE => ConfigParamType::INTEGER,
        self::AUTOVACUUM_VACUUM_COST_DELAY => ConfigParamType::INTEGER_WITH_UNIT,
        self::AUTOVACUUM_VACUUM_COST_LIMIT => ConfigParamType::INTEGER,

        self::SEARCH_PATH => ConfigParamType::STRING,
        self::DEFAULT_TABLESPACE => ConfigParamType::STRING,
        self::TEMP_TABLESPACES => ConfigParamType::STRING,
        self::CHECK_FUNCTION_BODIES => ConfigParamType::BOOL,
        self::DEFAULT_TRANSACTION_ISOLATION => ConfigParamType::ENUM,
        self::DEFAULT_TRANSACTION_READ_ONLY => ConfigParamType::BOOL,
        self::DEFAULT_TRANSACTION_DEFERRABLE => ConfigParamType::BOOL,
        self::SESSION_REPLICATION_ROLE => ConfigParamType::ENUM,
        self::STATEMENT_TIMEOUT => ConfigParamType::INTEGER_WITH_UNIT,
        self::LOCK_TIMEOUT => ConfigParamType::INTEGER_WITH_UNIT,
        self::VACUUM_FREEZE_TABLE_AGE => ConfigParamType::INTEGER,
        self::VACUUM_FREEZE_MIN_AGE => ConfigParamType::INTEGER,
        self::VACUUM_MULTIXACT_FREEZE_TABLE_AGE => ConfigParamType::INTEGER,
        self::VACUUM_MULTIXACT_FREEZE_MIN_AGE => ConfigParamType::INTEGER,
        self::BYTEA_OUTPUT => ConfigParamType::ENUM,
        self::XMLBINARY => ConfigParamType::ENUM,
        self::XMLOPTION => ConfigParamType::ENUM,
        self::DATE_STYLE => ConfigParamType::STRING,
        self::INTERVAL_STYLE => ConfigParamType::ENUM,
        self::TIME_ZONE => ConfigParamType::STRING,
        self::TIMEZONE_ABBREVIATIONS => ConfigParamType::STRING,
        self::EXTRA_FLOAT_DIGITS => ConfigParamType::INTEGER,
        self::CLIENT_ENCODING => ConfigParamType::STRING,
        self::LC_MESSAGES => ConfigParamType::STRING,
        self::LC_MONETARY => ConfigParamType::STRING,
        self::LC_NUMERIC => ConfigParamType::STRING,
        self::LC_TIME => ConfigParamType::STRING,
        self::DEFAULT_TEXT_SEARCH_CONFIG => ConfigParamType::STRING,
        self::LOCAL_PRELOAD_LIBRARIES => ConfigParamType::STRING,
        self::SESSION_PRELOAD_LIBRARIES => ConfigParamType::STRING,
        self::SHARED_PRELOAD_LIBRARIES => ConfigParamType::STRING,
        self::DYNAMIC_LIBRARY_PATH => ConfigParamType::STRING,
        self::GIN_FUZZY_SEARCH_LIMIT => ConfigParamType::INTEGER,

        self::DEADLOCK_TIMEOUT => ConfigParamType::INTEGER_WITH_UNIT,
        self::MAX_LOCKS_PER_TRANSACTION => ConfigParamType::INTEGER,
        self::MAX_PRED_LOCKS_PER_TRANSACTION => ConfigParamType::INTEGER,

        self::ARRAY_NULLS => ConfigParamType::BOOL,
        self::BACKSLASH_QUOTE => ConfigParamType::ENUM,
        self::DEFAULT_WITH_OIDS => ConfigParamType::BOOL,
        self::ESCAPE_STRING_WARNING => ConfigParamType::BOOL,
        self::LO_COMPAT_PRIVILEGES => ConfigParamType::BOOL,
        self::QUOTE_ALL_IDENTIFIERS => ConfigParamType::BOOL,
        self::SQL_INHERITANCE => ConfigParamType::BOOL,
        self::STANDARD_CONFORMING_STRINGS => ConfigParamType::BOOL,
        self::SYNCHRONIZE_SEQSCANS => ConfigParamType::BOOL,
        self::TRANSFORM_NULL_EQUALS => ConfigParamType::BOOL,

        self::EXIT_ON_ERROR => ConfigParamType::BOOL,
        self::RESTART_AFTER_CRASH => ConfigParamType::BOOL,

        self::BLOCK_SIZE => ConfigParamType::INTEGER,
        self::DATA_CHECKSUMS => ConfigParamType::BOOL,
        self::INTEGER_DATETIMES => ConfigParamType::BOOL,
        self::LC_COLLATE => ConfigParamType::STRING,
        self::LC_CTYPE => ConfigParamType::STRING,
        self::MAX_FUNCTION_ARGS => ConfigParamType::INTEGER,
        self::MAX_IDENTIFIER_LENGTH => ConfigParamType::INTEGER,
        self::MAX_INDEX_KEYS => ConfigParamType::INTEGER,
        self::SEGMENT_SIZE => ConfigParamType::INTEGER_WITH_UNIT,
        self::SERVER_ENCODING => ConfigParamType::STRING,
        self::SERVER_VERSION => ConfigParamType::STRING,
        self::SERVER_VERSION_NUM => ConfigParamType::INTEGER,
        self::WAL_BLOCK_SIZE => ConfigParamType::INTEGER,
        self::WAL_SEGMENT_SIZE => ConfigParamType::INTEGER_WITH_UNIT,

        self::ALLOW_SYSTEM_TABLE_MODS => ConfigParamType::BOOL,
        self::DEBUG_ASSERTIONS => ConfigParamType::BOOL,
        self::IGNORE_SYSTEM_INDEXES => ConfigParamType::BOOL,
        self::POST_AUTH_DELAY => ConfigParamType::INTEGER_WITH_UNIT,
        self::PRE_AUTH_DELAY => ConfigParamType::INTEGER_WITH_UNIT,
        self::TRACE_NOTIFY => ConfigParamType::BOOL,
        self::TRACE_RECOVERY_MESSAGES => ConfigParamType::ENUM,
        self::TRACE_SORT => ConfigParamType::BOOL,
        self::TRACE_LOCKS => ConfigParamType::BOOL,
        self::TRACE_LWLOCKS => ConfigParamType::BOOL,
        self::TRACE_USERLOCKS => ConfigParamType::BOOL,
        self::TRACE_LOCK_OIDMIN => ConfigParamType::INTEGER,
        self::TRACE_LOCK_TABLE => ConfigParamType::INTEGER,
        self::DEBUG_DEADLOCKS => ConfigParamType::BOOL,
        self::LOG_BTREE_BUILD_STATS => ConfigParamType::BOOL,
        self::WAL_DEBUG => ConfigParamType::BOOL,
        self::IGNORE_CHECKSUM_FAILURE => ConfigParamType::BOOL,
        self::ZERO_DAMAGED_PAGES => ConfigParamType::BOOL,
    ];
}