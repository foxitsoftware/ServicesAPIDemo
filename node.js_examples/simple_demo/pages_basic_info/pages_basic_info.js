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
// NOTE: make sure you have NodeJs version >= 12.0 installed.
// You can use any http-client library you like,  here we use the popular "axios".


const FormData = require('form-data');
const axios = require('axios');
const fs = require('fs');
const path = require('path');

// Get clientId and secretId form the json file.
const credentials_params = require('../foxit_cloud_api_credentials.json');
const clientId = credentials_params.client_credentials.client_id;
const secretId = credentials_params.client_credentials.secret_id;

// The signature of parameters will be calculated in the actual interface call in combination with the secret Id.
const sn = 'testsn'

// TODO: replace with your own input doc path and output file path
const inputFilePath = '../input_files/AboutFoxit_ocr.pdf'

// create axios instance and setup common request config 
const request = axios.create({
  baseURL: 'https://servicesapi.foxitsoftware.cn/api/',
  timeout: 60 * 1000,
 
  // common params for every api call
  params: {
      sn,
      clientId
  },
});

function pagesIsScannedTask(input_file){
  const readStream = fs.createReadStream(inputFilePath)
  readStream.on('error',function (err) {
    console.log('read input file error');
    console.log(err.message)
    process.exit(1)
  })

  const formData = new FormData()
  formData.append('inputDocument', readStream)

  formData.append('pageRange', 'all');

  const querystring = require('querystring');
  const crypto = require('crypto'); 
  let queryParams = {
  'clientId': clientId,
  'pageRange': "all",
  };

  const sortedParams = Object.fromEntries(Object.entries(queryParams).sort());
  // Stringify the parameters
  const querystringified = querystring.stringify(sortedParams);

  const queryStringWithSecret = querystringified + '&sk=' + secretId;
  // Generate signature using md5
  request.defaults.params.sn = crypto.createHash('md5').update(queryStringWithSecret).digest('hex');
  
  formData.append('pageRange', 'all');

  //Upload a file and create a new workflow task.
  return request({
    method: 'post',
    url: '/document/pagesIsScanned',
    headers: formData.getHeaders(),
    data: formData
  }).then(function (res) {

    const resultData = res.data

    // Read the api doc about all result codes
    if(resultData.code === 0){
      return resultData.data.taskInfo.taskId
    }
  }).catch(function (err) {
    console.log("PagesIsScanned task error:", err.response.data);
    throw err
  })
}

function getTaskInfo(taskId){
  const querystring = require('querystring');
  const crypto = require('crypto'); 
  let queryParams = {
  'clientId': clientId,
  'taskId': taskId,
  };

  const sortedParams = Object.fromEntries(Object.entries(queryParams).sort());
  // Stringify the parameters
  const querystringified = querystring.stringify(sortedParams);

  const queryStringWithSecret = querystringified + '&sk=' + secretId;
  // Generate signature using md5
  const sn = crypto.createHash('md5').update(queryStringWithSecret).digest('hex');
  
  return request({
    method: 'get',
    url: '/task',
    params: {sn, taskId}
  }).then(function (res) {
    const resultData = res.data
    if(resultData.code === 0){
      const taskInfo = resultData.data.taskInfo
      console.log("Task process is:", taskInfo.percentage)
      return taskInfo
    }else{
      throw new Error(resultData.code + resultData.message)
    }
  }).catch(function (err) {
    throw err
  })
}


function pollForResult(taskId, intervalInMilliSeconds = 2000){
  return new Promise(function(resolve, reject){
    // poll for task status and once task is completed resolve the promise with pagesIsScannedResult
    let timeout
    function poll(){
      if(timeout){clearTimeout(timeout)}
      getTaskInfo(taskId).then(function(taskInfo){
        if(taskInfo.percentage === 100){
          console.log("Task completed.")
          resolve(taskInfo.pagesIsScannedResult)
        }else{
          setTimeout(poll, intervalInMilliSeconds)
        }
      }).catch(function(err){ 
        // when task is running, the task api will return error
        // if task is running, try to get taskInfo later 
        if(err.response.data.data.detail.indexOf('The task is running') > -1){
          console.log("Task is running, retry in ", intervalInMilliSeconds, " miliseconds");
          setTimeout(poll, intervalInMilliSeconds)
        }
      })
    }
    poll()
  })
}

async function start(){
  const taskId = await pagesIsScannedTask(inputFilePath)
  const pagesIsScannedResult = await pollForResult(taskId)
  console.log(JSON.stringify(pagesIsScannedResult, null, 2));
}
 
start().then(function(){
  console.log("Check scanned pages successfully!");
  process.exit(0)
}).catch(function(err){
  console.log("Check scanned pages: " + err);
  process.exit(1)
})