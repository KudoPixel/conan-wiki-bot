<?php

namespace App\Clients;

use App\Utils\Logger;
use Config;

/**
 * The Telegram API Client.
 * Responsible for handling all outgoing requests to the Telegram Bot API 
 * using the provided bot token. It has a single responsibility: communication.
 */
class TelegramClient
{
    private string $telegramToken;
    private Logger $logger;

    /**
     * Initializes the client and securely fetches the bot token.
     * * @param Logger $logger Injected logging utility for tracking actions and errors.
     */
    public function __construct(Logger $logger)
    {
        // Dependency Injection for Logger (Loose Coupling)
        $this->logger = $logger; 
        
        try {
            // Fetch token from centralized config manager
            $this->telegramToken = Config::getTelegramToken();
        } catch (\Exception $e) {
            $this->logger->error("FATAL: Telegram token not found in config.", ['exception' => $e->getMessage()]);
            // If token is missing, the client remains unusable.
            $this->telegramToken = ''; 
        }
    }

    /**
     * Sends a text message to a specified chat ID.
     *
     * @param int|string $chatId The unique identifier for the target chat.
     * @param string $text The text of the message to be sent.
     * @param string $parseMode The parse mode for the message (e.g., 'MarkdownV2', 'HTML').
     * @return array|false The API response decoded as an array, or false on cURL failure.
     */
    public function sendMessage($chatId, string $text, string $parseMode = 'Markdown'): array|false
    {
        if (empty($this->telegramToken)) {
            $this->logger->error("TelegramClient cannot send message: Token is empty.", ['chat_id' => $chatId]);
            return false;
        }

        $url = "https://api.telegram.org/bot{$this->telegramToken}/sendMessage";
        
        $payload = json_encode([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            // Disable notification for error logs sent to the channel (if needed)
            'disable_notification' => false 
        ]);

        $this->logger->info("Sending message to Telegram...", ['chat_id' => $chatId, 'text_length' => strlen($text)]);

        // --- cURL setup ---
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        // --- End cURL setup ---

        if ($response === false) {
            $this->logger->error("cURL error during sendMessage.", ['chat_id' => $chatId, 'error' => $error]);
            return false;
        }

        $responseData = json_decode($response, true);

        if ($httpCode !== 200 || !$responseData['ok']) {
            $this->logger->error("Telegram API Error Response.", [
                'http_code' => $httpCode,
                'response' => $responseData,
                'chat_id' => $chatId
            ]);
            return $responseData;
        }

        $this->logger->info("Message successfully sent.", ['chat_id' => $chatId, 'message_id' => $responseData['result']['message_id'] ?? 'N/A']);
        return $responseData;
    }
}
