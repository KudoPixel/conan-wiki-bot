<?php

namespace App\Services;

use App\Clients\GeminiClient;
use App\Clients\TelegramClient;
use App\Models\TelegramUpdate;
use App\Utils\Logger;
use Exception;

/**
 * The core business logic handler for the bot.
 * This class orchestrates the communication between Telegram and Gemini, 
 * decides on responses, and manages conversation flow.
 */
class BotHandler
{
    private TelegramClient $telegramClient;
    private GeminiClient $geminiClient;
    private Logger $logger;

    /**
     * Dependency Injection: Receives all necessary clients and tools.
     * This ensures the class is loosely coupled and easily testable.
     *
     * @param TelegramClient $telegramClient Client for sending messages back to Telegram.
     * @param GeminiClient $geminiClient Client for interacting with the Gemini API.
     * @param Logger $logger Logging utility.
     */
    public function __construct(
        TelegramClient $telegramClient,
        GeminiClient $geminiClient,
        Logger $logger
    ) {
        $this->telegramClient = $telegramClient;
        $this->geminiClient = $geminiClient;
        $this->logger = $logger;
    }

    /**
     * Main entry point for processing a Telegram update.
     * * @param TelegramUpdate $update The sanitized data model of the incoming message.
     * @return bool True if handling was successful.
     */
    public function handle(TelegramUpdate $update): bool
    {
        // 1. Validate the update
        if (!$update->isValid()) {
            $this->logger->info("Received an invalid or non-text update. Ignoring.");
            return true;
        }

        $this->logger->info("Processing message.", ['chat_id' => $update->chatId, 'text' => $update->text]);
        
        $responseText = '';

        // 2. Handle predefined commands (The Switchboard)
        if (str_starts_with($update->text, '/')) {
            $responseText = $this->handleCommand($update);
        }

        // 3. Handle general inquiries via Gemini
        if (empty($responseText)) {
            $responseText = $this->handleGeminiInquiry($update);
        }

        // 4. Send the final response
        if (!empty($responseText)) {
            $this->telegramClient->sendMessage($update->chatId, $responseText);
        }

        return true;
    }

    /**
     * Handles specific bot commands like /start.
     */
    private function handleCommand(TelegramUpdate $update): string
    {
        $command = strtok($update->text, ' ');
        $responseText = '';

        switch ($command) {
            case '/start':
                $responseText = $this->getStartMessage($update->userId);
                break;
            case '/help':
                $responseText = $this->getHelpMessage();
                break;
            // Future commands can be added here
            default:
                // If it's a command but not recognized, send it to Gemini
                return ''; 
        }

        return $responseText;
    }

    /**
     * Sends the user's message to Gemini and processes the response.
     */
    private function handleGeminiInquiry(TelegramUpdate $update): string
    {
        try {
            // Call the Gemini Client
            $response = $this->geminiClient->generateContent($update->text);

            if ($response === false) {
                // GeminiClient already logged the error, inform the user gently.
                return $this->getErrorMessage();
            }

            return $response;

        } catch (Exception $e) {
            $this->logger->error("Unhandled exception during Gemini processing.", ['exception' => $e->getMessage()]);
            return $this->getErrorMessage();
        }
    }

    // --- Static Response Messages ---

    private function getStartMessage(?int $userId): string
    {
        $idText = $userId ? "User ID: `{$userId}`" : "User ID: N/A";

        return "*Welcome to the Detective Conan Wiki Bot!* 🕵️‍♂️\n" .
               "I'm here to provide accurate, deep information about the world of Detective Conan and Case Closed, based on the Gemini API.\n\n" .
               "Ask me anything about characters, plots, episodes, or the Black Organization!\n" .
               "Example: _Who is the boss of the Black Organization?_\n\n" .
               "{$idText}";
    }
    
    private function getHelpMessage(): string
    {
        return "*Help & Usage* 💡\n" .
               "Simply send me your question. I can answer complex lore and plot questions.\n" .
               "Available Commands:\n" .
               "• `/start` - Show the welcome message.\n" .
               "• `/help` - Show this help message.";
    }

    private function getErrorMessage(): string
    {
        return "🚨 *Error!* 🚨\n" .
               "I apologize, but I encountered a critical error while processing your request. The Black Organization seems to have interfered! Please try again later. The developer has been notified.";
    }
}
