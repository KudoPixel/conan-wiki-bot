<?php

/**
 * Project Configuration Manager.
 *
 * This class handles the loading of environment variables from the .env file
 * and provides static access to all necessary configuration parameters,
 * ensuring clean separation of concerns.
 */

use Dotenv\Dotenv;

class Config
{
    /**
     * @var bool Flag to ensure environment variables are loaded only once.
     */
    private static $isLoaded = false;

    /**
     * Loads the environment variables from the .env file.
     * This method must be called before accessing any environment variables.
     * If the .env file is not found, it throws an exception.
     */
    public static function loadEnvironment(): void
    {
        if (self::$isLoaded) {
            return;
        }

        // Determine the root directory of the project.
        $rootDir = __DIR__;
        
        // Load the .env file using the vlucas/phpdotenv package.
        try {
            $dotenv = Dotenv::createImmutable($rootDir);
            $dotenv->load();
            self::$isLoaded = true;
        } catch (\Exception $e) {
            // In a production environment, you might log this error instead of throwing.
            // For development, stopping execution is helpful.
            error_log("FATAL CONFIG ERROR: Could not load .env file. Details: " . $e->getMessage());
            die("FATAL ERROR: Environment configuration missing.");
        }
    }

    /**
     * Retrieves a value from the environment variables.
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
        
        $value = $_ENV[$key] ?? null;

        if ($value === null) {
            throw new \Exception("Configuration key '{$key}' not found in environment.");
        }

        return $value;
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
