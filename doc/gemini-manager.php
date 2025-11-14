<?php

// ===================================================================
// PART 1: GEMINI API INTERACTION CLASS
// This class handles all the technical communication with the Google API.
// No changes are needed here.
// ===================================================================

class GeminiFileSearchAPI
{
    private string $apiKey;
    private const API_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';
    private const UPLOAD_API_BASE_URL = 'https://generativelanguage.googleapis.com/upload/v1beta';

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    private function request(string $url, string $method = 'GET', ?string $body = null, array $headers = [])
    {
        $ch = curl_init();
        $defaultHeaders = ['Content-Type: application/json'];
        $url = $url . (strpos($url, '?') === false ? '?' : '&') . 'key=' . urlencode($this->apiKey);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpcode >= 400) {
            throw new \Exception("HTTP Error {$httpcode}: {$response}");
        }
        return json_decode($response, true);
    }

    public function listFileStores(): array
    {
        return $this->request(self::API_BASE_URL . "/fileSearchStores");
    }

    public function deleteFileStore(string $name): void
    {
        $this->request(self::API_BASE_URL . "/{$name}?force=true", 'DELETE');
    }

    private function getMimeType(string $filePath): string
    {
        $mimeTypes = [
            'pdf' => 'application/pdf', 'txt' => 'text/plain', 'md' => 'text/markdown',
            'html' => 'text/html', 'json' => 'application/json', 'csv' => 'text/csv',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    public function uploadFile(string $storeName, string $filePath): array
    {
        $url = self::UPLOAD_API_BASE_URL . "/{$storeName}:uploadToFileSearchStore";
        $ch = curl_init();
        $postFields = ['file' => new \CURLFile($filePath, $this->getMimeType($filePath), basename($filePath))];
        $urlWithParams = $url . '?key=' . urlencode($this->apiKey);

        curl_setopt($ch, CURLOPT_URL, $urlWithParams);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode >= 400) {
            throw new \Exception("HTTP Error {$httpcode}: {$response}");
        }
        return json_decode($response, true);
    }

    public function getOperation(string $name): array
    {
        return $this->request(self::API_BASE_URL . "/{$name}");
    }

    public function createFileStore(string $displayName): array
    {
        $body = json_encode(['displayName' => $displayName]);
        return $this->request(self::API_BASE_URL . "/fileSearchStores", 'POST', $body);
    }

    public function listFileStoreDocuments(string $fileStoreName): array
    {
        return $this->request(self::API_BASE_URL . "/{$fileStoreName}/documents");
    }

    public function deleteFileStoreDocument(string $documentName): void
    {
        $this->request(self::API_BASE_URL . "/{$documentName}?force=true", 'DELETE');
    }
}


// ===================================================================
// PART 2: INTERACTIVE COMMAND LINE INTERFACE CLASS
// This class is responsible for the user-facing experience.
// ===================================================================

class InteractiveGeminiCLI
{
    private GeminiFileSearchAPI $api;

    public function __construct(string $apiKey)
    {
        $this->api = new GeminiFileSearchAPI($apiKey);
        $this->clearScreen();
    }

    public function run(): void
    {
        while (true) {
            $this->displayMenu();
            $choice = strtolower(readline("Enter your choice: "));
            $this->clearScreen();

            try {
                switch ($choice) {
                    case '1': $this->handleListStorages(); break;
                    case '2': $this->handleCreateStorage(); break;
                    case '3': $this->handleDeleteStorage(); break;
                    case '4': $this->handleListDocuments(); break;
                    case '5': $this->handleUploadFiles(); break;
                    case '6': $this->handleDeleteDocument(); break;
                    case 'q': echo "👋 Goodbye!\n"; exit;
                    default: echo "❌ Invalid choice. Please try again.\n";
                }
            } catch (Exception $e) {
                echo "\n--- 🚨 AN ERROR OCCURRED 🚨 ---\n";
                echo $e->getMessage() . "\n";
                echo "---------------------------------\n\n";
            }
            readline("\nPress Enter to continue...");
            $this->clearScreen();
        }
    }

    private function displayMenu(): void
    {
        echo "=========================================\n";
        echo "      🚀 Gemini File Store Manager      \n";
        echo "=========================================\n";
        echo "📂 1. List all File Stores\n";
        echo "✨ 2. Create a new File Store\n";
        echo "🗑️  3. Delete a File Store\n";
        echo "-----------------------------------------\n";
        echo "📄 4. List Documents in a Store\n";
        echo "⬆️  5. Upload Files to a Store\n";
        echo "❌ 6. Delete a Document from a Store\n";
        echo "-----------------------------------------\n";
        echo "🚪 q. Quit\n";
        echo "=========================================\n";
    }

    // --- Action Handlers ---

    private function handleListStorages(): void
    {
        echo "📂 Fetching File Stores...\n\n";
        $response = $this->api->listFileStores();
        $storages = $response['fileSearchStores'] ?? [];

        if (empty($storages)) {
            echo "No File Stores found.\n";
            return;
        }
        echo "Available File Stores:\n";
        printf("%-35s | %s\n", "Display Name", "ID (name)");
        echo str_repeat('-', 80) . "\n";
        foreach ($storages as $storage) {
            printf("%-35s | %s\n", $storage['displayName'], $storage['name']);
        }
    }

    private function handleCreateStorage(): void
    {
        echo "✨ Create a New File Store\n";
        $displayName = readline("Enter a display name for the new store: ");
        if (empty($displayName)) {
            echo "❌ Name cannot be empty.\n";
            return;
        }
        echo "Creating store '{$displayName}'...\n";
        $result = $this->api->createFileStore($displayName);
        echo "✅ Success! Store created with ID: {$result['name']}\n";
    }

    private function handleDeleteStorage(): void
    {
        echo "🗑️  Delete a File Store\n";
        $store = $this->selectStore("Select a store to DELETE:");
        if (!$store) return; // User cancelled

        $confirm = strtolower(readline("🚨 Are you sure you want to permanently delete '{$store['displayName']}'? [y/n]: "));
        if ($confirm === 'y') {
            $this->api->deleteFileStore($store['name']);
            echo "✅ Store '{$store['displayName']}' has been deleted.\n";
        } else {
            echo "Operation cancelled.\n";
        }
    }

    private function handleListDocuments(): void
    {
        echo "📄 List Documents in a Store\n";
        $store = $this->selectStore("Select a store to view its documents:");
        if (!$store) return; // User cancelled

        $response = $this->api->listFileStoreDocuments($store['name']);
        $documents = $response['documents'] ?? [];
        if (empty($documents)) {
            echo "This store ('{$store['displayName']}') contains no documents.\n";
            return;
        }
        echo "Documents in '{$store['displayName']}':\n";
        foreach ($documents as $doc) {
            echo "----------------------------------------\n";
            echo "  📄 Name: {$doc['displayName']}\n";
            echo "     ID: {$doc['name']}\n";
            echo "     Type: {$doc['mimeType']}\n";
            echo "     Size: " . number_format((int)$doc['sizeBytes']) . " bytes\n";
        }
        echo "----------------------------------------\n";
    }

    private function handleUploadFiles(): void
    {
        echo "⬆️  Upload Files to a Store\n";
        $store = $this->selectStore("Select a destination store for your files:");
        if (!$store) return; // User cancelled

        $folderPath = readline("Enter the full path to the folder with your files: ");
        if (!is_dir($folderPath)) {
            echo "❌ Error: Folder '{$folderPath}' not found.\n";
            return;
        }

        $files = new DirectoryIterator($folderPath);
        foreach ($files as $fileinfo) {
            if ($fileinfo->isFile()) {
                $filePath = $fileinfo->getRealPath();
                echo "\n⬆️  Uploading: {$filePath}...\n";
                try {
                    $operation = $this->api->uploadFile($store['name'], $filePath);
                    if (isset($operation['name'])) {
                        while (!($operation['done'] ?? false)) {
                            echo "⏳ Processing... (operation: {$operation['name']})\n";
                            sleep(3);
                            $operation = $this->api->getOperation($operation['name']);
                        }
                        echo "✅ File uploaded successfully.\n";
                    }
                } catch (Exception $e) {
                    echo "🚨 Error uploading {$filePath}: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    private function handleDeleteDocument(): void
    {
        echo "❌ Delete a Document from a Store\n";
        $store = $this->selectStore("First, select the store containing the document:");
        if (!$store) return;

        $document = $this->selectDocument($store, "Now, select the document to DELETE:");
        if (!$document) return;

        $confirm = strtolower(readline("🚨 Are you sure you want to permanently delete '{$document['displayName']}'? [y/n]: "));
        if ($confirm === 'y') {
            $this->api->deleteFileStoreDocument($document['name']);
            echo "✅ Document '{$document['displayName']}' has been deleted.\n";
        } else {
            echo "Operation cancelled.\n";
        }
    }
    
    // --- Helper Methods for Selection ---

    /**
     * Displays a numbered list of file stores and prompts the user to select one.
     * @param string $prompt The message to display to the user.
     * @return array|null The selected store array, or null if cancelled.
     */
    private function selectStore(string $prompt): ?array
    {
        $storages = ($this->api->listFileStores())['fileSearchStores'] ?? [];
        return $this->handleSelection($prompt, $storages, 'displayName');
    }

    /**
     * Displays a numbered list of documents from a specific store for selection.
     * @param array $store The store to list documents from.
     * @param string $prompt The message to display to the user.
     * @return array|null The selected document array, or null if cancelled.
     */
    private function selectDocument(array $store, string $prompt): ?array
    {
        $documents = ($this->api->listFileStoreDocuments($store['name']))['documents'] ?? [];
        return $this->handleSelection($prompt, $documents, 'displayName');
    }

    /**
     * Generic helper to display a list of items and handle user selection by number.
     * @param string $prompt The leading message.
     * @param array $items The array of items to list.
     * @param string $displayKey The key in the item array to use for display.
     * @return array|null The selected item, or null if the user cancels or the list is empty.
     */
    private function handleSelection(string $prompt, array $items, string $displayKey): ?array
    {
        if (empty($items)) {
            echo "No items found to select.\n";
            return null;
        }

        echo $prompt . "\n";
        foreach ($items as $index => $item) {
            echo "  [" . ($index + 1) . "] " . $item[$displayKey] . "\n";
        }
        echo "  [0] Cancel\n";

        while (true) {
            $choice = readline("Your choice: ");
            if (!is_numeric($choice) || $choice < 0 || $choice > count($items)) {
                echo "❌ Invalid input. Please enter a number from the list.\n";
                continue;
            }
            if ($choice == 0) {
                echo "Operation cancelled.\n";
                return null;
            }
            return $items[$choice - 1];
        }
    }

    private function clearScreen(): void
    {
        strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? system('cls') : system('clear');
    }
}


// ===================================================================
// PART 3: APPLICATION ENTRY POINT
// This block runs when the script is executed.
// ===================================================================

/**
 * Securely prompts the user for their API key, hiding the input.
 * @return string The entered API key.
 */
function promptForApiKey(): string
{
    echo "🔑 Please enter your Gemini API Key: ";
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        system('stty -echo');
    }
    $apiKey = trim(fgets(STDIN));
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        system('stty echo');
    }
    echo "\n";
    return $apiKey;
}

// 1. Prompt for the API key after the script starts
$apiKey = promptForApiKey();
if (empty($apiKey)) {
    die("❌ API Key not provided. Exiting.\n");
}

// 2. Run the main application
try {
    $cli = new InteractiveGeminiCLI($apiKey);
    $cli->run();
} catch (Exception $e) {
    echo "🚨 A critical error occurred: " . $e->getMessage() . "\n";
}
