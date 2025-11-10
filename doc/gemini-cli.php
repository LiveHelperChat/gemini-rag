<?php

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

        $defaultHeaders = [
            'Content-Type: application/json'
        ];

        // Add API key as query parameter (avoid URL encoding)
        $url = $url . (strpos($url, '?') === false ? '?' : '&') . 'key=' . urlencode($this->apiKey);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

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
        $url = self::API_BASE_URL . "/fileSearchStores";
        return $this->request($url);
    }

    public function getFileStoreByName(string $displayName): ?array
    {
        $stores = $this->listFileStores();
        if (isset($stores['fileSearchStores'])) {
            foreach ($stores['fileSearchStores'] as $store) {
                if ($store['displayName'] === $displayName) {
                    return $store;
                }
            }
        }
        return null;
    }

    public function deleteFileStore(string $name): void
    {
        $url = self::API_BASE_URL . "/{$name}?force=true";
        $this->request($url, 'DELETE');
    }

    private function getMimeType(string $filePath): string
    {
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'md' => 'text/markdown',
            'markdown' => 'text/markdown',
            'html' => 'text/html',
            'htm' => 'text/html',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'csv' => 'text/csv',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    public function uploadFile(string $storeName, string $filePath): array
    {
        $url = self::UPLOAD_API_BASE_URL . "/{$storeName}:uploadToFileSearchStore";

        $ch = curl_init();

        // Determine MIME type based on file extension
        $mimeType = $this->getMimeType($filePath);

        // Use CURLFile for proper multipart handling
        $postFields = [
            'file' => new \CURLFile($filePath, $mimeType, basename($filePath))
        ];
        
        // Add API key as query parameter
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
        $url = self::API_BASE_URL . "/{$name}";
        return $this->request($url);
    }

    public function createFileStore(string $displayName): array
    {
        $url = self::API_BASE_URL . "/fileSearchStores";
        $body = json_encode([
            'displayName' => $displayName
        ]);
        return $this->request($url, 'POST', $body);
    }

    public function listFileStoreDocuments(string $fileStoreName): array
    {
        $url = self::API_BASE_URL . "/{$fileStoreName}/documents";
        return $this->request($url);
    }

    public function deleteFileStoreDocument(string $documentName): void
    {
        $url = self::API_BASE_URL . "/{$documentName}?force=true";
        $this->request($url, 'DELETE');
    }
}

$options = getopt("", ["action:", "storage-name:", "folder:", "key:"]);
$action = $options['action'] ?? null;
$storageName = $options['storage-name'] ?? null;
$folder = $options['folder'] ?? null;
$apiKey =  $options['key'] ?? null;

if (!$apiKey) {
    die("Error: GEMINI_API_KEY environment variable not set.\n");
}

$api = new GeminiFileSearchAPI($apiKey);

switch ($action) {
    case 'list':
        echo "Listing file storages...\n";
        try {
            $stores = $api->listFileStores();
            print_r($stores);
        } catch (Exception $e) {
            echo "Error listing storages: " . $e->getMessage() . "\n";
        }
        break;

    case 'delete':
        if (!$storageName) {
            die("Usage: php gemini-cli.php --action=delete --storage-name=<name>\n");
        }
        echo "Deleting storage '{$storageName}'...\n";
        try {
            $store = $api->getFileStoreByName($storageName);
            if ($store) {
                $api->deleteFileStore($store['name']);
                echo "Storage '{$storageName}' deleted successfully.\n";
            } else {
                echo "Storage '{$storageName}' not found.\n";
            }
        } catch (Exception $e) {
            echo "Error deleting storage: " . $e->getMessage() . "\n";
        }
        break;

    case 'upload':
        if (!$storageName || !$folder) {
            die("Usage: php gemini-cli.php --action=upload --storage-name=<name> --folder=<path>\n");
        }
        if (!is_dir($folder)) {
            die("Error: Folder '{$folder}' not found.\n");
        }
        echo "Uploading files from '{$folder}' to '{$storageName}'...\n";
        try {
            $store = $api->getFileStoreByName($storageName);
            if (!$store) {
                die("Error: Storage '{$storageName}' not found.\n");
            }

            $files = new DirectoryIterator($folder);
            foreach ($files as $fileinfo) {
                if (!$fileinfo->isDot() && $fileinfo->isFile()) {
                    $filePath = $fileinfo->getRealPath();
                    echo "Uploading {$filePath}...\n";
                    try {
                        $operation = $api->uploadFile($store['name'], $filePath);
                        if (isset($operation['name'])) {
                             while (!($operation['done'] ?? false)) {
                                echo "Processing... (operation: {$operation['name']})\n";
                                sleep(2);
                                $operation = $api->getOperation($operation['name']);
                            }
                            echo "File {$filePath} uploaded successfully.\n";
                        } else {
                            echo "\n";
                            print_r($operation);
                        }

                    } catch (Exception $e) {
                        echo "Error uploading {$filePath}: " . $e->getMessage() . "\n";
                    }
                }
            }
        } catch (Exception $e) {
            echo "Error during upload: " . $e->getMessage() . "\n";
        }
        break;

    case 'create':
        if (!$storageName) {
            die("Usage: php gemini-cli.php --action=create --storage-name=<name>\n");
        }
        echo "Creating storage '{$storageName}'...\n";
        try {
            $store = $api->createFileStore($storageName);
            if (isset($store['name'])) {
                echo "Storage '{$storageName}' created successfully with name: {$store['name']}\n";
            } else {
                echo "Error creating storage '{$storageName}'.\n";
                print_r($store);
            }
        } catch (Exception $e) {
            echo "Error creating storage: " . $e->getMessage() . "\n";
        }
        break;

    case 'list-documents':
        if (!$storageName) {
            die("Usage: php gemini-cli.php --action=list-documents --storage-name=<name>\n");
        }
        echo "Listing documents in storage '{$storageName}'...\n";
        try {
            $store = $api->getFileStoreByName($storageName);
            if (!$store) {
                die("Error: Storage '{$storageName}' not found.\n");
            }
            $response = $api->listFileStoreDocuments($store['name']);
            $documents = $response['documents'] ?? [];
            
            if (!empty($documents)) {
                echo "Found " . count($documents) . " document(s):\n\n";
                foreach ($documents as $doc) {
                    echo "  - Display Name: {$doc['displayName']}\n";
                    echo "    Name: {$doc['name']}\n";
                    echo "    State: {$doc['state']}\n";
                    echo "    MIME Type: {$doc['mimeType']}\n";
                    echo "    Size: " . number_format((int)$doc['sizeBytes']) . " bytes\n";
                    echo "    Created: {$doc['createTime']}\n";
                    echo "    Updated: {$doc['updateTime']}\n";
                    echo "\n";
                }
            } else {
                echo "No documents found in storage '{$storageName}'.\n";
            }
        } catch (Exception $e) {
            echo "Error listing documents: " . $e->getMessage() . "\n";
        }
        break;

    case 'delete-document':
        if (!$storageName) {
            die("Usage: php gemini-cli.php --action=delete-document --storage-name=<document-name>\n");
        }
        echo "Deleting document '{$storageName}'...\n";
        try {
            $api->deleteFileStoreDocument($storageName);
            echo "Document '{$storageName}' deleted successfully.\n";
        } catch (Exception $e) {
            echo "Error deleting document: " . $e->getMessage() . "\n";
        }
        break;

    default:
        echo "Invalid action. Available actions: list, delete, upload, create, list-documents, delete-document\n";
        echo "Usage:\n";
        echo "  php gemini-cli.php --action=list\n";
        echo "  php gemini-cli.php --action=delete --storage-name=<name>\n";
        echo "  php gemini-cli.php --action=upload --storage-name=<name> --folder=<path>\n";
        echo "  php gemini-cli.php --action=create --storage-name=<name>\n";
        echo "  php gemini-cli.php --action=list-documents --storage-name=<name>\n";
        echo "  php gemini-cli.php --action=delete-document --storage-name=<document-name>\n";
        break;
}
