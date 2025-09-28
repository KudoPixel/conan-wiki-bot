<?php

// The first line must load Composer's autoloader for packages and namespaced classes.
require_once __DIR__ . '/vendor/autoload.php';

// FIX: Explicitly require the Config class since it is not namespaced
// and resides in the project root, ensuring it's available before use.
require_once __DIR__ . '/Config.php'; 

use App\Utils\Logger;
use App\Clients\GeminiClient;
use App\Clients\TelegramClient;
use App\Services\BotHandler;
use App\Models\TelegramUpdate;

// --- 1. SETUP: Configuration and Logger Initialization ---

// Loads environment variables from .env and sets up the global configuration.
\Config::loadEnvironment(); 

// Retrieve the base logger instance
$log = \App\Utils\Logger::getInstance();

try {
    // Check if the request method is POST (required for Telegram Webhook)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        $log->info('Method Not Allowed', ['method' => $_SERVER['REQUEST_METHOD']]);
        die('Method Not Allowed');
    }

    // Read and decode the raw JSON payload from Telegram
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);

    if (empty($data)) {
        http_response_code(200);
        $log->info('Empty payload received');
        die('OK');
    }
    
    // --- 2. DEPENDENCY INJECTION & CLIENT INSTANTIATION ---

    // Instantiate Clients with injected dependencies
    // NOTE: Order matters when injecting into BotHandler constructor.
    $telegramClient = new TelegramClient($log);
    $geminiClient = new GeminiClient($log);
    
    // Instantiate the Bot Handler, injecting all necessary clients/dependencies
    // Assuming BotHandler constructor takes (GeminiClient, TelegramClient, Logger)
    $botHandler = new BotHandler(
        $telegramClient, // Inject TelegramClient (was GeminiClient here before)
        $geminiClient, // Inject GeminiClient (was TelegramClient here before)
        $log
    );
    
    // --- 3. DATA PROCESSING & EXECUTION ---

    // Create a clean TelegramUpdate object from the raw data
    $update = \App\Models\TelegramUpdate::fromArray($data);
    
    if ($update->isValid()) {
        $log->info('Processing message', [
            'chat_id' => $update->chatId, 
            'text_length' => strlen($update->text)
        ]);
        
        // Execute the main bot logic
        $botHandler->handle($update);
    } else {
        $log->info('Received non-text or invalid update.');
    }

} catch (\Exception $e) {
    // --- 4. GLOBAL ERROR HANDLING ---
    $log->error('Fatal exception during webhook processing.', [
        'exception_class' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    // Return a 200 OK status to Telegram to prevent continuous retries
    http_response_code(200);
    die('An error occurred, log reported.');

}
