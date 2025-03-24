<?php
/**
 * Logger class for the WebSocket server.
 *
 * @package    Jackalopes\Server
 */

namespace Jackalopes\Server;

class Logger {
    /**
     * Log levels
     */
    const DEBUG = 0;
    const INFO = 1;
    const WARN = 2;
    const ERROR = 3;
    
    /**
     * Current log level
     *
     * @var int
     */
    protected $level;
    
    /**
     * Log file path
     *
     * @var string
     */
    protected $logFile;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Get log level from WordPress options
        $this->level = $this->getLogLevelFromOption();
        
        // Set log file
        $this->logFile = $this->getLogFilePath();
    }
    
    /**
     * Log debug message
     *
     * @param string $message
     */
    public function debug($message) {
        $this->log($message, self::DEBUG);
    }
    
    /**
     * Log info message
     *
     * @param string $message
     */
    public function info($message) {
        $this->log($message, self::INFO);
    }
    
    /**
     * Log warning message
     *
     * @param string $message
     */
    public function warn($message) {
        $this->log($message, self::WARN);
    }
    
    /**
     * Log error message
     *
     * @param string $message
     */
    public function error($message) {
        $this->log($message, self::ERROR);
    }
    
    /**
     * Log a message
     *
     * @param string $message
     * @param int $level
     */
    protected function log($message, $level) {
        if ($level < $this->level) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $levelName = $this->getLevelName($level);
        $formattedMessage = "[{$timestamp}] [{$levelName}] {$message}" . PHP_EOL;
        
        // Log to file
        file_put_contents($this->logFile, $formattedMessage, FILE_APPEND);
        
        // Also output to console in CLI mode
        if (php_sapi_name() === 'cli') {
            echo $formattedMessage;
        }
    }
    
    /**
     * Get the name of a log level
     *
     * @param int $level
     * @return string
     */
    protected function getLevelName($level) {
        switch ($level) {
            case self::DEBUG:
                return 'DEBUG';
            case self::INFO:
                return 'INFO';
            case self::WARN:
                return 'WARN';
            case self::ERROR:
                return 'ERROR';
            default:
                return 'UNKNOWN';
        }
    }
    
    /**
     * Get log level from WordPress option
     *
     * @return int
     */
    protected function getLogLevelFromOption() {
        if (!function_exists('get_option')) {
            return self::INFO; // Default to INFO if not in WordPress context
        }
        
        $option = get_option('jackalopes_server_log_level', 'info');
        
        switch ($option) {
            case 'debug':
                return self::DEBUG;
            case 'info':
                return self::INFO;
            case 'warn':
                return self::WARN;
            case 'error':
                return self::ERROR;
            default:
                return self::INFO;
        }
    }
    
    /**
     * Get the log file path
     *
     * @return string
     */
    protected function getLogFilePath() {
        if (defined('JACKALOPES_SERVER_PLUGIN_DIR')) {
            return JACKALOPES_SERVER_PLUGIN_DIR . 'server.log';
        }
        
        // Fallback if not in WordPress context
        return dirname(__DIR__) . '/server.log';
    }
} 