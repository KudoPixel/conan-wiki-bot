<?php

namespace App\Utils; 

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord; // <--- تغییر ۱: افزودن کلاس LogRecord
use Config; 

/**
 * Custom Monolog Handler to push error logs to a dedicated Telegram channel.
 * This class formats log records into a Telegram message and sends it via cURL.
 */
class TelegramErrorHandler extends AbstractProcessingHandler
{
    private string $telegramToken;
    private string $chatId;

    /**
     * Constructor for the TelegramErrorHandler.
     *
     * @param Level $level The minimum logging level to handle. Defaults to Error.
     */
    public function __construct(Level $level = Level::Error)
    {
        parent::__construct($level, true); 
        
        // Fetches configuration safely
        try {
            $this->telegramToken = Config::getTelegramToken();
            $this->chatId = Config::getTelegramErrorChatId();
        } catch (\Exception $e) {
            // Fallback if config is missing, prevents subsequent errors.
            $this->telegramToken = '';
            $this->chatId = '';
            error_log("FATAL: Telegram error handler config missing: " . $e->getMessage());
        }
    }

    /**
     * Writes the record down to the log of the implementing handler.
     *
     * @param LogRecord $record The record object from Monolog. <-- تغییر ۲: امضای متد به LogRecord تغییر کرد
     * @return void
     */
    protected function write(LogRecord $record): void 
    {
        // Prevent sending logs if configuration is missing
        if (empty($this->telegramToken) || empty( $this->chatId)) {
            return;
        }

        // Format the log record into a readable Telegram message
        $message = sprintf(
            "🚨 *CONAN WIKI BOT ERROR ALERT* 🚨\n" .
            "*Level:* %s\n" .
            "*Time:* %s\n" .
            "*Message:* %s\n" .
            "*Context:* ```\n%s\n```",
            $record->level->getName(), // <--- تغییر ۳: دسترسی به Level از طریق شیء
            $record->datetime->format('Y-m-d H:i:s'),
            $record->message,
            json_encode($record->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) // <--- دسترسی به Context از طریق شیء
        );

        // --- cURL Execution for Telegram API ---
        $url = "https://api.telegram.org/bot{$this->telegramToken}/sendMessage";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'chat_id' => $this->chatId,
            'text' => $message,
            'parse_mode' => 'HTML' 
        ]));
        
        // Execute and ignore response, we just want to send the log.
        curl_exec($ch); 
        curl_close($ch);
        // --- End cURL Execution ---
    }
}
