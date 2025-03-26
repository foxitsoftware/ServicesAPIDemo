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
// This file contains an example to demonstrate how to use Foxit Cloud API to check the PDF pages whether is scanned or not.

class Pages_is_scanned {
    private $clientId;
    private $secretId;
    private $sn;
    private $inputFilePath;
    private $baseUrl;

    public function __construct() {
        $this->clientId = '';
        $this->secretId = '';
        $this->sn = 'testsn';
        $this->inputFilePath = '../input_files/AboutFoxit_ocr.pdf';
        $this->baseUrl = 'https://servicesapi.foxitsoftware.cn/api';
    }

    private function buildUri($endpoint) {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
    }

    private function getCredentialsParams($credentialsPath) {
        $credentials = json_decode(file_get_contents($credentialsPath), true);
        $this->clientId = $credentials['client_credentials']['client_id'];
        $this->secretId = $credentials['client_credentials']['secret_id'];
    }

    private function pagesIsScannedTask($inputFile) {
        $queryParams = [
            'clientId' => $this->clientId,
            'pageRange' => "all",
        ];
        ksort($queryParams);
        $queryString = http_build_query($queryParams) . '&sk=' . rawurlencode($this->secretId);
        $this->sn = md5($queryString);

        $params = [
            'sn' => $this->sn,
            'clientId' => $this->clientId
        ];

        $file = new CURLFile($inputFile, 'application/pdf', basename($inputFile));
        $postData = [
            'inputDocument' => $file,
			'pageRange' => "all"
        ];

        $ch = curl_init($this->buildUri('document/pagesIsScanned') . '?' . http_build_query($params));
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("HTTP Error: $httpCode");
        }

        $responseData = json_decode($response, true);
        if ($responseData['code'] === 0) {
            return $responseData['data']['taskInfo']['taskId'];
        } else {
            throw new Exception($responseData['msg']);
        }
    }

    private function getTaskInfo($taskId) {
        $queryParams = [
            'clientId' => $this->clientId,
            'taskId' => $taskId,
        ];
        ksort($queryParams);
        $queryString = http_build_query($queryParams) . '&sk=' . rawurlencode($this->secretId);
        $this->sn = md5($queryString);

        $params = [
            'sn' => $this->sn,
            'clientId' => $this->clientId,
            'taskId' => $taskId
        ];

        $ch = curl_init($this->buildUri('task') . '?' . http_build_query($params));
	    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("HTTP Error: $httpCode");
        }

        $responseData = json_decode($response, true);
        if ($responseData['code'] === 0) {
            $percentage = $responseData['data']['taskInfo']['percentage'];
            $pagesIsScannedResult = $percentage === 100 ? $responseData['data']['taskInfo']['pagesIsScannedResult'] : 0;
            echo "Task process is: $percentage%\n";
            return [$pagesIsScannedResult, $percentage];
        } else {
            throw new Exception($responseData['msg']);
        }
    }

    private function pollForResult($taskId, $intervalInMilliseconds = 2000) {
        while (true) {
            try {
                list($pagesIsScannedResult, $percentage) = $this->getTaskInfo($taskId);
                if ($percentage === 100) {
                    echo "Task completed.\n";
                    return $pagesIsScannedResult;
                }
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'The task is running') !== false) {
                    echo "Task is running, retry in $intervalInMilliseconds milliseconds\n";
                } else {
                    throw $e;
                }
            }
            usleep($intervalInMilliseconds * 1000);
        }
    }

    public function start() {
        try {
            $this->getCredentialsParams('../foxit_cloud_api_credentials.json');
            $taskId = $this->pagesIsScannedTask($this->inputFilePath);
            $pagesIsScannedResult = $this->pollForResult($taskId);
			$jsonData = json_encode($pagesIsScannedResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
			echo $jsonData;
			echo "\n"
            echo "Scanned PDF file successfully!\n";
        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }
}

$pages_is_scanned = new Pages_is_scanned();
$pages_is_scanned->start();