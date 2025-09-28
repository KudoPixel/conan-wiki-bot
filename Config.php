<?php

/**
 * Project Configuration Manager.
 *
 * This class handles loading environment variables from both the system's environment
 * and a local .env file (if it exists), providing a unified way to access configuration.
 */

use Dotenv\Dotenv;

class Config
{
    /**
     * @var bool Flag to ensure environment variables are loaded only once.
     */
    private static $isLoaded = false;

    /**
     * Loads environment variables.
     * It first attempts to load from a .env file for local development.
     * It will gracefully continue if the file doesn't exist, allowing the app
     * to rely solely on system-level environment variables in production.
     */
    public static function loadEnvironment(): void
    {
        if (self::$isLoaded) {
            return;
        }

        // Determine the project root directory.
        $rootDir = __DIR__;

        // Load .env file ONLY if it exists (for local development)
        if (file_exists($rootDir . '/.env')) {
            $dotenv = Dotenv::createImmutable($rootDir);
            $dotenv->load();
        }

        // Mark as loaded regardless of whether .env was found or not.
        self::$isLoaded = true;
    }

    /**
     * Retrieves a value from the environment variables.
     *
     * It checks variables set by the system first, then falls back to those
     * loaded from the .env file.
     *
     * @param string $key The key of the environment variable (e.g., 'TELEGRAM_BOT_TOKEN').
     * @return string The value of the environment variable.
     * @throws Exception if the environment has not been loaded or the key is missing.
     */
    public static function get(string $key): string
    {
        if (!self::$isLoaded) {
            // Ensure environment is loaded before attempting to access variables
            self::loadEnvironment();
        }

        // phpdotenv loads into $_ENV, but getenv() is a reliable way to check all sources.
        // We'll check $_ENV first as phpdotenv populates it.
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            // Log the error for easier debugging on the server.
            error_log("FATAL CONFIG ERROR: Configuration key '{$key}' not found in environment.");
            throw new \Exception("Configuration key '{$key}' not found in environment.");
        }

        return (string)$value;
    }

    /**
     * Retrieves the Telegram Bot Token.
     *
     * @return string
     */
    public static function getTelegramToken(): string
    {
        return self::get('TELEGRAM_BOT_TOKEN');
    }

    /**
     * Retrieves the Gemini API Key.
     *
     * @return string
     */
    public static function getGeminiKey(): string
    {
        return self::get('GEMINI_API_KEY');
    }

    /**
     * Retrieves the numeric Chat ID for the error reporting channel.
     *
     * @return string
     */
    public static function getTelegramErrorChatId(): string
    {
        return self::get('TELEGRAM_ERROR_CHAT_ID');
    }

    /**
     * Retrieves the absolute path to the Gemini configuration JSON file.
     *
     * @return string
     */
    public static function getGeminiConfigPath(): string
    {
        // Path is relative to the project root directory
        return __DIR__ . '/GeminiConfig.json';
    }
}
