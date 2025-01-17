<?php

// Copyright (C) 2003-2025, Foxit Software Inc..
// All Rights Reserved.
//
// http://www.foxitsoftware.com
//
// The following code is copyrighted and contains proprietary information and trade secrets of Foxit Software Inc..
// You cannot distribute any part of Foxit Cloud API to any third party or general public,
// unless there is a separate license agreement with Foxit Software Inc. which explicitly grants you such rights.
//
// This file contains an example to demonstrate how to use Foxit Cloud API to convert pdf files to other files.

class ConvertPdfToOthers {
    private $clientId = '';
    private $secretId = '';
    private $sn = 'testsn';
    private $inputFilePath = '../input_files/AboutFoxit.pdf';
    private $outputFilePath = '../output_files/convert_pdf_to_others/Image.zip';
    private $baseUrl = 'https://servicesapi.foxitsoftware.cn/api';

    // Build the complete URI
    private function buildUri($endpoint) {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
    }

    // Load credentials from a JSON file
    private function getCredentialsParams($credentialsPath) {
        $jsonContent = file_get_contents($credentialsPath);
        $credentials = json_decode($jsonContent, true);
        $this->clientId = $credentials['client_credentials']['client_id'];
        $this->secretId = $credentials['client_credentials']['secret_id'];
    }

    // Create a task for converting the PDF
    private function convertpdfTask($inputFile, $format = "image") {
        $payloadString = '{\r\n  \"dpi\": 96,\r\n  \"pageRange\": \"all\" \r\n}';

        $queryParams = [
            'clientId' => $this->clientId,
            'config' => $payloadString,
            'format' => $format
        ];
        ksort($queryParams);
        $queryString = http_build_query($queryParams) . '&sk=' . urlencode($this->secretId);
        $this->sn = md5($queryString);

        $params = ['sn' => $this->sn, 'clientId' => $this->clientId];
        $payload = ['format' => $format, 'config' => $payloadString];
        $files = [
            'inputDocument' => curl_file_create($inputFile, 'application/pdf', basename($inputFile))
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->buildUri('document/convert') . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_POSTFIELDS, array_merge($payload, $files));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        $responseData = json_decode($response, true);
        curl_close($ch);

        if ($responseData['code'] === 0) {
            return $responseData['data']['taskInfo']['taskId'];
        } else {
            throw new Exception($responseData['msg']);
        }
    }

    // Get task info
    private function getTaskInfo($taskId) {
        $queryParams = [
            'clientId' => $this->clientId,
            'taskId' => $taskId
        ];

        ksort($queryParams);
        $queryString = http_build_query($queryParams) . '&sk=' . urlencode($this->secretId);
        $this->sn = md5($queryString);

        $params = [
            'sn' => $this->sn,
            'clientId' => $this->clientId,
            'taskId' => $taskId
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->buildUri('task') . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        $responseData = json_decode($response, true);
        curl_close($ch);

        if ($responseData['code'] === 0) {
            $percentage = $responseData['data']['taskInfo']['percentage'];
            $docId = ($percentage === 100) ? $responseData['data']['taskInfo']['docId'] : 0;
            echo "Task progress: $percentage%\n";
            return [$docId, $percentage];
        } else {
            throw new Exception($responseData['msg']);
        }
    }
    // Poll until task is completed
    private function pollForDocId($taskId, $intervalMs = 2000) {
        while (true) {
            list($docId, $percentage) = $this->getTaskInfo($taskId);

            if ($percentage === 100) {
                echo "Task completed.\n";
                return $docId;
            }

            usleep($intervalMs * 1000); // Wait before retrying
        }
    }

    // Download file using docId
    private function downloadFileByDocId($docId, $outputFilePath) {
        $filename = basename($outputFilePath);
        $queryParams = [
            'clientId' => $this->clientId,
            'docId' => $docId,
            'fileName' => $filename
        ];

        ksort($queryParams);
        $queryString = http_build_query($queryParams) . '&sk=' . urlencode($this->secretId);
        $this->sn = md5($queryString);

        $params = [
            'sn' => $this->sn,
            'clientId' => $this->clientId,
            'docId' => $docId,
            'fileName' => $filename
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->buildUri('download') . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        file_put_contents($outputFilePath, $response);
        curl_close($ch);

        echo "Download completed.\n";
    }

    // Start the conversion process
    public function start() {
        try {
            $outputPath = dirname($this->outputFilePath);
            if (!file_exists($outputPath)) {
                mkdir($outputPath, 0777, true);
            }

            $this->getCredentialsParams('../foxit_cloud_api_credentials.json');
            $taskId = $this->convertpdfTask($this->inputFilePath);
            $docId = $this->pollForDocId($taskId);
            $this->downloadFileByDocId($docId, $this->outputFilePath);
            echo "Convert PDF to image successfully!\n";
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
}

$converter = new ConvertPdfToOthers();
$converter->start();
?>
