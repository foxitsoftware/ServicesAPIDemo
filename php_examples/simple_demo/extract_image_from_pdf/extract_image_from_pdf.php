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
// This file contains an example to demonstrate how to use Foxit Cloud API to extract image from pdf files.

class ExtractImageFromPdf {
    private $clientId;
    private $secretId;
    private $sn = 'testsn';
    private $inputFilePath = '../input_files/PDF2Img.pdf';
    private $outputFilePath = '../output_files/extract_image_from_pdf/Image.zip';
    private $baseUrl = 'https://servicesapi.foxitsoftware.cn/api';

    public function __construct() {
        // Load credentials from a file
        $this->getCredentialsParams('../foxit_cloud_api_credentials.json');
    }

    private function buildUri($endpoint) {
        return $this->baseUrl . '/' . $endpoint;
    }

    private function getCredentialsParams($credentialsPath) {
        $credentials = json_decode(file_get_contents($credentialsPath), true);
        $this->clientId = $credentials['client_credentials']['client_id'];
        $this->secretId = $credentials['client_credentials']['secret_id'];
    }

    public function extractImageFromPdfTask($inputFilePath, $mode = "extractImages", $pageRange = "all") {
        $queryParams = [
            'clientId' => $this->clientId,
            'mode' => $mode,
            'pageRange' => $pageRange,
        ];
        ksort($queryParams);
        $queryString = http_build_query($queryParams);
        $queryString .= '&sk=' . urlencode($this->secretId);
        $this->sn = md5($queryString);

        $params = [
            'sn' => $this->sn,
            'clientId' => $this->clientId
        ];
        $payload = [
            'mode' => $mode,
            'pageRange' => $pageRange
        ];
        $filename = basename($inputFilePath);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->buildUri('document/extract') . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'mode' => $mode,
			'pageRange' => $pageRange,
            'inputDocument' => new CURLFile($inputFilePath, 'application/pdf', $filename),
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        curl_close($ch);

        $rJson = json_decode($response, true);
        if ($rJson['code'] == 0) {
            return $rJson['data']['taskInfo']['taskId'];
        } else {
            throw new Exception($rJson['msg']);
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
            throw new Exception($response['msg']);
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


    public function start() {
        try {
            $outputPath = dirname($this->outputFilePath);
            if (!file_exists($outputPath)) {
                mkdir($outputPath, 0777, true);
            }

            $taskId = $this->extractImageFromPdfTask($this->inputFilePath);
            $docId = $this->pollForDocid($taskId);
            $this->downloadFileByDocid($docId, $this->outputFilePath);
			echo "Extract image from PDF successfully!\n";
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage() . "\n";
        }
    }
}

$extractImageFromPdf = new ExtractImageFromPdf();
$extractImageFromPdf->start();
?>
