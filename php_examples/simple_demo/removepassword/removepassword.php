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
// This file contains an example to demonstrate how to use Foxit Cloud API to remove password pdf files.

class Removepassword
{
    private $clientId = '';
    private $secretId = '';
    private $sn = 'testsn';
    private $inputFilePath = '../input_files/AboutFoxit_123.pdf';
    private $outputFilePath = '../output_files/removepassword/AboutFoxit_RemovePassword.pdf';
    private $baseUrl = 'https://servicesapi.foxitsoftware.cn/api';
	
    // Concatenate strings using '/', build URI.
    private function buildUri($endpoint) {
        return $this->baseUrl . '/' . $endpoint;
    }
	
    public function getCredentialsParams($credentialsPath)
    {
        $jsonStr = file_get_contents($credentialsPath);
        $jsonData = json_decode($jsonStr, true);
        $this->clientId = $jsonData['client_credentials']['client_id'];
        $this->secretId = $jsonData['client_credentials']['secret_id'];
    }
	
    public function removePasswordTask($inputFilePath, $password)
    {
        $queryParams = [
            'clientId' => $this->clientId,
            'password' => $password,
        ];
        ksort($queryParams);
        $queryString = http_build_query($queryParams) . '&sk=' . rawurlencode($this->secretId);
        $this->sn = md5($queryString);

        $params = [
            'sn' => $this->sn,
            'clientId' => $this->clientId
        ];

        $file = new CURLFile($inputFilePath, 'application/pdf', basename($inputFilePath));
        $postData = [
            'password' => $password,
            'inputDocument' => $file
        ];

        $ch = curl_init($this->buildUri('document/removePassword') . '?' . http_build_query($params));
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
        $queryString = http_build_query($queryParams) . '&sk=' . urlencode($this->secretId);
        $this->sn = md5($queryString);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->buildUri('task') . '?' . http_build_query(['sn' => $this->sn, 'clientId' => $this->clientId, 'taskId' => $taskId]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception("cURL Error: " . curl_error($ch));
        }
        curl_close($ch);

        $response = json_decode($response, true);
        if ($response['code'] === 0) {
            $percentage = $response['data']['taskInfo']['percentage'];
            $docId = $percentage === 100 ? $response['data']['taskInfo']['docId'] : 0;
            echo "Task progress: $percentage%\n";
            return [$docId, $percentage];
        } else {
            throw new Exception($response['task_info']);
        }
    }

    private function pollForDocId($taskId, $intervalInMilliseconds = 2000) {
        while (true) {
            try {
                list($docId, $percentage) = $this->getTaskInfo($taskId);
                if ($percentage === 100) {
                    echo "Task completed.\n";
                    return $docId;
                }
            } catch (Exception $e) {
                echo "Error: " . $e->getMessage() . "\n";
            }
            usleep($intervalInMilliseconds * 1000);
        }
    }

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

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->buildUri('download') . '?' . http_build_query(['sn' => $this->sn, 'clientId' => $this->clientId, 'docId' => $docId, 'fileName' => $filename]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception("cURL Error: " . curl_error($ch));
        }
        curl_close($ch);

        file_put_contents($outputFilePath, $response);
        echo "Download completed.\n";
    }

    // Start the manipulation process
    public function start() {
        try {
            $outputPath = dirname($this->outputFilePath);
            if (!file_exists($outputPath)) {
                mkdir($outputPath, 0777, true);
            }

            $this->getCredentialsParams('../foxit_cloud_api_credentials.json');
            $taskId = $this->removePasswordTask($this->inputFilePath, "123");
            $docId = $this->pollForDocId($taskId);
            $this->downloadFileByDocId($docId, $this->outputFilePath);
            echo "Remove password successfully!\n";
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
}

$removePassword = new Removepassword();
$removePassword->start();

?>
