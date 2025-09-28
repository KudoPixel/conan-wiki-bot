<?php

namespace App\Utils; // <--- FIX: Added Namespace for App\Utils

use Monolog\Logger as MonoLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Level; // <--- FIX: Used for modern Monolog level constants
// Note: TelegramErrorHandler is in the same namespace (App\Utils)
use App\Utils\TelegramErrorHandler; 

/**
 * Centralized logging utility based on Monolog.
 *
 * This class sets up multiple handlers for different outputs (file, Telegram),
 * ensuring robust, standardized, and environment-aware error reporting.
 */
class Logger
{
    private MonoLogger $logger;
    private static ?Logger $instance = null; // Singleton instance

    private function __construct()
    {
        // 1. Core Logger Setup
        $this->logger = new MonoLogger('CONAN_BOT');

        // 2. File Handler (Writes all logs to file for full history)
        try {
            // FIX: Using Monolog\Level::Debug
            $streamHandler = new StreamHandler('logs/app.log', Level::Debug); 
            $this->logger->pushHandler($streamHandler);
        } catch (\Exception $e) {
            error_log("Failed to set up file logging: " . $e->getMessage());
        }

        // 3. Telegram Handler (Sends CRITICAL and ERROR to a dedicated channel)
        try {
            // FIX: Using Monolog\Level::Error
            $telegramHandler = new TelegramErrorHandler(Level::Error); 
            $this->logger->pushHandler($telegramHandler);
        } catch (\Exception $e) {
             error_log("Failed to set up Telegram logging: " . $e->getMessage());
        }
    }

    // Prevents cloning and unserialization for Singleton pattern
    private function __clone() {}
    public function __wakeup() {}

    /**
     * Gets the singleton instance of the Logger.
     * @return Logger
     */
    public static function getInstance(): Logger
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // --- Proxy Methods (Delegating calls to the Monolog instance) ---

    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }
}
