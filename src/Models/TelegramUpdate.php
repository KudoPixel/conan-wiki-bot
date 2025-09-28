<?php

namespace App\Models;

/**
 * Data Transfer Object (DTO) representing a single update received from the Telegram API.
 * This object sanitizes and simplifies the raw JSON payload into usable properties 
 * for the core bot logic.
 */
class TelegramUpdate
{
    // Properties are set to allow read-only access from outside after instantiation.
    public readonly string $chatId;
    public readonly string $text;
    public readonly ?int $userId; // Unique identifier for the user who sent the message.

    /**
     * Attempts to parse a raw Telegram update array and extract essential data.
     * * @param array $data The raw array decoded from Telegram's JSON payload.
     */
    public function __construct(array $data)
    {
        // 1. Extract the Message object from the Update
        $message = $data['message'] ?? $data['edited_message'] ?? null;

        // 2. Extract Chat ID and Text
        $this->chatId = $message['chat']['id'] ?? '';
        $this->text = trim($message['text'] ?? '');

        // 3. Extract User ID
        $this->userId = $message['from']['id'] ?? null;
    }

    /**
     * Static factory method to create an instance of TelegramUpdate from a raw data array.
     * This method resolves the 'Undefined method fromArray' error (P1013) in webhook.php.
     *
     * @param array $data The raw update data array from json_decode(file_get_contents('php://input'), true).
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Retrieves the chat ID.
     *
     * @return string
     */
    public function getChatId(): string
    {
        return $this->chatId;
    }

    /**
     * Checks if the update contains a valid text message and chat ID.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return !empty($this->chatId) && !empty($this->text);
    }
}
