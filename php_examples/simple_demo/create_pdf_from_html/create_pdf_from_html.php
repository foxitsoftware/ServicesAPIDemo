<?php

# Copyright (C) 2003-2025, Foxit Software Inc..
# All Rights Reserved.
#
# http://www.foxitsoftware.com
#
# The following code is copyrighted and contains proprietary information and trade secrets of Foxit Software Inc..
# You cannot distribute any part of Foxit Cloud API to any third party or general public,
# unless there is a separate license agreement with Foxit Software Inc. which explicitly grants you such rights.
#
# This file contains an example to demonstrate how to use Foxit Cloud API to create pdf from html.

class CreatePdfFromHtml {
    private $clientId = '';
    private $secretId = '';
    private $sn = 'testsn';
    private $url = 'https://developers.foxitsoftware.cn/';
    private $outputFilePath = '../output_files/create_pdf_from_html/SDKDevelopers.pdf';
    private $baseUrl = 'https://servicesapi.foxitsoftware.cn/api';

    private function buildUri($endpoint) {
        return $this->baseUrl . '/' . $endpoint;
    }

    private function getCredentialsParams($credentialsPath) {
        $content = file_get_contents($credentialsPath);
        $data = json_decode($content, true);
        $this->clientId = $data['client_credentials']['client_id'];
        $this->secretId = $data['client_credentials']['secret_id'];
    }

    private function createPdfTask($url, $format = "url") {
        $payloadString = json_encode([
            "width" => 640,
            "height" => 900,
            "rotate" => 0,
            "pageMode" => 1,
            "pageScaling" => 1
        ]);

        $queryParams = [
            'clientId' => $this->clientId,
            'config' => $payloadString,
            'format' => $format,
            'url' => $url
        ];

        ksort($queryParams);
        $queryString = http_build_query($queryParams) . '&sk=' . urlencode($this->secretId);
        $this->sn = md5($queryString);

        $params = ['sn' => $this->sn, 'clientId' => $this->clientId];
        $postFields = ['url' => $url, 'format' => $format, 'config' => $payloadString];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->buildUri('document/createFromHtml') . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception("cURL Error: " . curl_error($ch));
        }
        curl_close($ch);

        $response = json_decode($response, true);
        if ($response['code'] === 0) {
            return $response['data']['taskInfo']['taskId'];
        } else {
            throw new Exception($response['msg']);
        }
    }

    private function getTaskInfo($taskId) {
        $queryParams = [
            'clientId' => $this->clientId,
            'taskId' => $taskId
        ];

        ksort($queryParams);
        $queryString = http_build_query($queryParams) . '&sk=' . urlencode($this->secretId);
        $this->sn = md5($queryString);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->buildUri('task') . '?' . http_build_query(['sn' => $this->sn, 'clientId' => $this->clientId, 'taskId' => $taskId]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
            'fileName' => $filename
        ];

        ksort($queryParams);
        $queryString = http_build_query($queryParams) . '&sk=' . urlencode($this->secretId);
        $this->sn = md5($queryString);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->buildUri('download') . '?' . http_build_query(['sn' => $this->sn, 'clientId' => $this->clientId, 'docId' => $docId, 'fileName' => $filename]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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

            $this->getCredentialsParams('../foxit_cloud_api_credentials.json');
            $taskId = $this->createPdfTask($this->url);
            $docId = $this->pollForDocId($taskId);
            $this->downloadFileByDocId($docId, $this->outputFilePath);
            echo "PDF created successfully!\n";
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
}

$converter = new CreatePdfFromHtml();
$converter->start();
?>
