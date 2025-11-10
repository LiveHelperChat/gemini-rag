# Gemini RAG

This repository contains a script to index your own files to gemini file store. Perhaps in the future I'll make extension from all this repository.

## High Level Gemini documentation 
* 
* https://ai.google.dev/gemini-api/docs/file-search

## Gemini API level integration

* https://ai.google.dev/api/file-search/file-search-stores
* https://ai.google.dev/api/file-search/documents

# How to integrate with LHC

##  Rest API and sample bot you can download from.

* https://doc.livehelperchat.com/docs/bot/gemini-integration

## Under `Tools` section add another item.

```json
{
  "file_search": {
    "file_search_store_names": [
      "fileSearchStores/sd-someidrandom"
    ]
  }
}
```

Also delete `knowledge_base` if you have one.

In `Output parsing > Tool call` add second condition
    
```text
candidates:0:content:parts:0:functionCall:name != google:file_search
```

##  Make sure to change model to `gemini-flash-latest`

File search tool works only with the last model.

## Adjust system instructions. 

```
You are a helpful assistant. You can answer questions only from https://doc.livehelperchat.com. If you don't know answer instruct visitor that you can answer questions only related to live helper chat and file search. Do not answer questions based on general knowledge base. You will answer with one most related answer to visitor question. Your answer should not exceed 100 words. You should include link for more information about your answer. Use `file_search` tool to answer generic questions.
```

## How to index your own document to Gemini file search

This is independent script and should be run directly from `doc` folder. 

E.g `cd ./doc/ && php gemini-cli.php ...`

### List existing storage

This will list all existing storage.

> php gemini-cli.php --action=list --key=YOUR_API_KEY

### Create a storage

This will create a storage with display name of LHC

> php gemini-cli.php --action=create --storage-name=lhc --key=YOUR_API_KEY

### Upload files to a storage

This will upload all files from a specified folder to the storage. The storage must already exist.

> php gemini-cli.php --action=upload --storage-name=lhc --folder=./docs --key=YOUR_API_KEY

The script will process each file in the folder and upload it to Gemini's file search store. It supports various file types including PDF, text files, markdown, HTML, JSON, XML, CSV, and Microsoft Office documents.

### List documents in a storage

This will list all documents currently stored in the specified storage.

> php gemini-cli.php --action=list-documents --storage-name=lhc --key=YOUR_API_KEY

### Delete a storage

This will delete the entire storage and all documents within it.

> php gemini-cli.php --action=delete --storage-name=lhc --key=YOUR_API_KEY

### Delete a document

This will delete a specific document from a storage. You need to provide the full document name.

> php gemini-cli.php --action=delete-document --storage-name=DOCUMENT_NAME --key=YOUR_API_KEY

## Prerequisites

- PHP 7.4 or higher with curl extension enabled
- A valid Gemini API key from Google AI Studio

## Supported File Types

The script automatically detects MIME types for the following file extensions:

- **Documents**: PDF (.pdf), Microsoft Word (.doc, .docx), Microsoft Excel (.xls, .xlsx), Microsoft PowerPoint (.ppt, .pptx)
- **Text Files**: Plain text (.txt), Markdown (.md, .markdown), HTML (.html, .htm), JSON (.json), XML (.xml), CSV (.csv)

## Security Note

Keep your API key secure and never commit it to version control. Use environment variables or secure key management systems in production.

## API Reference

For more detailed information about the Gemini File Search API, see:
- [File Search Overview](https://ai.google.dev/gemini-api/docs/file-search)
- [File Search Stores API](https://ai.google.dev/api/file-search/file-search-stores)
- [Documents API](https://ai.google.dev/api/file-search/documents)