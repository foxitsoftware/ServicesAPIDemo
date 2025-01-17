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
// This file contains an example to demonstrate how to use Foxit Cloud API to compare pdf files.

class ComparePDF {
    private $clientId = '';
    private $secretId = '';
    private $sn = 'testsn';
    private $inputFilePath1 = '../input_files/test_base.pdf';
    private $inputFilePath2 = '../input_files/test_compared.pdf';
    private $outputFilePath = '../output_files/compare/CompareResultFiles.json';
    private $baseUrl = 'https://servicesapi.foxitsoftware.cn/api';

    // Build full URI by combining the base URL and endpoint
    private function buildUri($endpoint) {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
    }

    // Load clientId and secretId from JSON file
    private function loadCredentials($credentialsPath) {
        $data = json_decode(file_get_contents($credentialsPath), true);
        $this->clientId = $data['client_credentials']['client_id'];
        $this->secretId = $data['client_credentials']['secret_id'];
    }

    // Create a new comparison task
    private function createCompareTask($inputFileBase, $inputFileCompare) {
        $resultType = "json";
        $compareType = "all";

        $queryParams = [
            'clientId' => $this->clientId,
            'resultType' => $resultType,
            'compareType' => $compareType,
        ];

        ksort($queryParams); // Sort params
        $queryString = http_build_query($queryParams) . '&sk=' . urlencode($this->secretId);
        $this->sn = md5($queryString);

        $params = [
            'sn' => $this->sn,
            'clientId' => $this->clientId,
        ];
		
        $postData = [
            'resultType' => $resultType,
			'compareType' => $compareType,
            'inputBaseDocument' => curl_file_create($inputFileBase, 'application/pdf', basename($inputFileBase)),
            'inputCompareDocument' => curl_file_create($inputFileCompare, 'application/pdf', basename($inputFileCompare)),
        ];
		
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->buildUri('document/compare') . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
		curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
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

    // Get task info by task ID
    private function getTaskInfo($taskId) {
        $queryParams = [
            'clientId' => $this->clientId,
            'taskId' => $taskId,
        ];

        ksort($queryParams);
        $queryString = http_build_query($queryParams) . '&sk=' . urlencode($this->secretId);
        $this->sn = md5($queryString);

        $params = [
            'sn' => $this->sn,
            'clientId' => $this->clientId,
            'taskId' => $taskId,
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
            return [
                'docId' => $responseData['data']['taskInfo']['docId'] ?? 0,
                'percentage' => $responseData['data']['taskInfo']['percentage'],
            ];
        } else {
            throw new Exception($responseData['msg']);
        }
    }

    // Poll until the task is complete
    private function pollForDocId($taskId, $intervalMs = 2000) {
        while (true) {
            try {
                $taskInfo = $this->getTaskInfo($taskId);
                if ($taskInfo['percentage'] === 100) {
                    echo "Task completed.\n";
                    return $taskInfo['docId'];
                }
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'The task is running') !== false) {
                    echo "Task is running, retrying in {$intervalMs} ms...\n";
                } else {
                    throw $e;
                }
            }
            usleep($intervalMs * 1000); // Sleep in microseconds
        }
    }

    // Download the comparison result file by doc ID
    private function downloadFileByDocId($docId, $outputFilePath) {
        $filename = basename($outputFilePath);
        $queryParams = [
            'clientId' => $this->clientId,
            'docId' => $docId,
            'fileName' => $filename,
        ];

        ksort($queryParams);
        $queryString = http_build_query($queryParams) . '&sk=' . urlencode($this->secretId);
        $this->sn = md5($queryString);

        $params = [
            'sn' => $this->sn,
            'clientId' => $this->clientId,
            'docId' => $docId,
            'fileName' => $filename,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->buildUri('download') . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        file_put_contents($outputFilePath, $response);
        curl_close($ch);

        echo "Download finished.\n";
    }

    // Main process
    public function start() {
        try {
            $outputPath = dirname($this->outputFilePath);
            if (!is_dir($outputPath)) {
                mkdir($outputPath, 0777, true);
            }

            $this->loadCredentials('../foxit_cloud_api_credentials.json');
            $taskId = $this->createCompareTask($this->inputFilePath1, $this->inputFilePath2);
            $docId = $this->pollForDocId($taskId);
            $this->downloadFileByDocId($docId, $this->outputFilePath);
            echo "Compare PDF files successfully!\n";
        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }
}

// Start the comparison
$compare = new ComparePDF();
$compare->start();