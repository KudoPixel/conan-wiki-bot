<?php

namespace App\Clients;

use Config; // Assume Config is available globally or via autoloading
use App\Utils\Logger; // Assume Logger is available via App\Utils namespace

/**
 * Handles communication with the Gemini API.
 * This class is responsible for sending the user prompt along with the 
 * defined configuration (from GeminiConfig.json) and processing the raw API response.
 * It uses the injected Logger for robust error tracking.
 */
class GeminiClient
{
    // The standard API endpoint for generating content
    private const API_URL = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent";

    private string $apiKey;
    private Logger $logger;
    private array $geminiConfig;

    /**
     * Constructor for GeminiClient.
     * Dependencies are injected (Logger) to maintain loose coupling.
     *
     * @param Logger $logger The logger utility instance.
     */
    public function __construct(Logger $logger)
    {
        // Get credentials and configuration data via the centralized Config class
        $this->apiKey = Config::getGeminiKey();
        $this->logger = $logger;

        // Load the model's behavioral configuration from JSON on instantiation
        try {
            $this->geminiConfig = $this->loadGeminiConfig();
        } catch (\Exception $e) {
            $this->logger->critical("FATAL: Cannot load Gemini configuration.", ['error' => $e->getMessage()]);
            // Note: In a real app, this should throw an exception to halt execution
            // if the service cannot be initialized.
            $this->geminiConfig = [];
        }
    }

    /**
     * Loads the model generation configuration from the dedicated JSON file.
     *
     * @return array The parsed configuration array.
     * @throws \Exception If the configuration file cannot be read or decoded.
     */
    private function loadGeminiConfig(): array
    {
        $path = Config::getGeminiConfigPath();

        if (!file_exists($path)) {
            throw new \Exception("Gemini configuration file not found at: " . $path);
        }

        $configJson = file_get_contents($path);
        $configArray = json_decode($configJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Failed to decode GeminiConfig.json: " . json_last_error_msg());
        }

        return $configArray;
    }

    /**
     * Sends a prompt to the Gemini API and returns the generated text.
     * This method combines the prompt with the predefined system and generation configuration.
     *
     * @param string $prompt The user's query text.
     * @return string The generated text response from the model, or a user-friendly error message.
     */
    public function generateContent(string $prompt): string
    {
        if (empty($this->geminiConfig)) {
            return "❌ AI service is not properly configured. Cannot process request.";
        }

        $url = self::API_URL . "?key=" . $this->apiKey;

        // Safely extract system instruction text
        $systemInstructionText = $this->geminiConfig['systemInstruction']['parts'][0]['text'] ?? '';
        
        // Load tools configuration
        $tools = $this->geminiConfig['tools'] ?? [];

        // 🚀 تغییر: بررسی و اصلاح ساختار 'tools' برای جلوگیری از خطای 'googleSearch:[]'
        // اگر googleSearch وجود داشت، مطمئن می شویم که مقدار آن به عنوان یک شیء JSON ({} در PHP) کد شود.
        // با استفاده از JSON_FORCE_OBJECT در json_encode این تضمین حاصل نمی شود، پس باید در ساختار داده اعمال شود.
        // با این حال، PHP اگر آرایه خالی نباشد، آن را به {} تبدیل می کند. بهترین کار برای اطمینان این است که
        // مطمئن شویم اگر ابزار جستجو وجود دارد، آرایه نباشد و یک شیء خالی به آن دهیم (یا یک آرایه انجمنی خالی)
        if (!empty($tools)) {
            foreach ($tools as $key => $tool) {
                if (isset($tool['googleSearch']) && is_array($tool['googleSearch']) && empty($tool['googleSearch'])) {
                    // آرایه انجمنی خالی در PHP هنگام json_encode به {} تبدیل می شود.
                    $tools[$key]['googleSearch'] = new \stdClass(); 
                }
            }
        }


        // Build the full request payload
        $payload = [
            'contents' => [
                [
                    'role' => 'user', // اضافه شد
                    'parts' => [['text' => $prompt]]
                ],
            ],
            'generationConfig' => $this->geminiConfig['generationConfig'] ?? [],
            'tools' => $tools, // استفاده از متغیر اصلاح شده tools
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemInstructionText]
                ]
            ]
        ];

        // استفاده از JSON_FORCE_OBJECT برای تضمین ساختار صحیح نیست، اما استفاده از stdClass موثر است.
        $payloadJson = json_encode($payload);

        $this->logger->info("Sending request to Gemini API.", ['prompt' => substr($prompt, 0, 50) . '...']);
        $this->logger->debug("Gemini Payload.", ['payload' => $payloadJson]);
        // --- cURL Execution ---
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        // --- End cURL Execution ---

        if ($response === false || $httpCode !== 200) {
            $errorMessage = $response === false ? $curlError : "HTTP Code: " . $httpCode;
            $this->logger->error("Gemini API Request Failed.", ['error' => $errorMessage, 'http_code' => $httpCode, 'response' => $response]);
            return "❌ A critical error occurred while accessing the AI service. (Code: {$httpCode})";
        }

        $responseData = json_decode($response, true);

        // Check for API errors reported within the successful HTTP response body (e.g., key expired)
        if (isset($responseData['error'])) {
            $errorDetail = $responseData['error']['message'] ?? 'Unknown API Error';
            $this->logger->error("Gemini API returned an error in response body.", ['detail' => $errorDetail]);
            return "⚠️ The AI service reported an issue: " . $errorDetail;
        }

        // Safely extract the generated text
        $generatedText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($generatedText) {
            $this->logger->debug("Gemini response successfully received.");
            return $generatedText;
        }

        // Handle cases where the response is successful but no text candidate exists (e.g., safety block)
        $this->logger->warning("Gemini response received, but no text content found (Possible Safety Block).", ['response' => $response]);
        return "😔 The AI could not generate a valid response for your query (Safety check failed).";
    }
}
