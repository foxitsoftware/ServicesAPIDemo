// Copyright (C) 2003-2024, Foxit Software Inc..
// All Rights Reserved.
//
// http://www.foxitsoftware.com
//
// The following code is copyrighted and contains proprietary information and trade secrets of Foxit Software Inc..
// You cannot distribute any part of Foxit Cloud API to any third party or general public,
// unless there is a separate license agreement with Foxit Software Inc. which explicitly grants you such rights.
//
// This file contains an example to demonstrate how to use Foxit Cloud API to flatten pdf file.
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

// The signature of parameters, clientId and secret Id. (we ignore this parameter  on trial version，input any string is ok)
const sn = 'testsn'

// TODO: replace with your own input doc path and output file path
const inputFilePath = '../input_files/Annot_all.pdf'
const outputFilePath = '../output_files/flatten/flatten.pdf'

// create axios instance and setup common request config 
const request = axios.create({
  baseURL: 'https://serviceapi-devcn.connectedpdf.com/api/',
  timeout: 60 * 1000,
 
  // common params for every api call
  params: {
      sn,
      clientId
  },
});

function flattenPDFTask(inputFilePath){
  const pageRange = 'all';
  const querystring = require('querystring');
  const crypto = require('crypto'); 
  let queryParams = {
  'clientId': clientId,
  'pageRange': pageRange,
  };

  const sortedParams = Object.fromEntries(Object.entries(queryParams).sort());
  // Stringify the parameters
  const querystringified = querystring.stringify(sortedParams);

  const queryStringWithSecret = querystringified + '&sk=' + secretId;
  // Generate signature using md5
  request.defaults.params.sn = crypto.createHash('md5').update(queryStringWithSecret).digest('hex');

  const readStream = fs.createReadStream(inputFilePath)
  readStream.on('error',function (err) {
    console.log('read input file error');
    console.log(err.message)
    process.exit(1)
  })

  const formData = new FormData()
  formData.append('inputDocument', readStream)
  formData.append('pageRange', 'all');

  //Upload a file and create a new workflow task.
  return request({
    method: 'post',
    url: '/document/flatten',
    headers: formData.getHeaders(),
    data: formData
  }).then(function (res) {

    const resultData = res.data

    // Read the api doc about all result codes
    if(resultData.code === 0){
      return resultData.data.taskInfo.taskId
    }
  }).catch(function (err) {
    console.log("Flatten task error:", err.response.data);
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


function pollForDocId(taskId, intervalInMilliSeconds = 2000){
  return new Promise(function(resolve, reject){
    // poll for task status and once task is completed resolve the promise with docId
    let timeout
    function poll(){
      if(timeout){clearTimeout(timeout)}
      getTaskInfo(taskId).then(function(taskInfo){
        if(taskInfo.percentage === 100){
          console.log("Task completed.")
          resolve(taskInfo.docId)
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
 
function downloadFileByDocId(docId, outputFilePath){
  const writeStream = fs.createWriteStream(outputFilePath)
  
  const querystring = require('querystring');
  const crypto = require('crypto'); 
  let queryParams = {
  'clientId': clientId,
  'docId': docId,
  };

  const sortedParams = Object.fromEntries(Object.entries(queryParams).sort());
  // Stringify the parameters
  const querystringified = querystring.stringify(sortedParams);

  const queryStringWithSecret = querystringified + '&sk=' + secretId;
  // Generate signature using md5
  const sn = crypto.createHash('md5').update(queryStringWithSecret).digest('hex');
 
  writeStream.on('error',function (err) {
    console.log(err.message)
    process.exit(1)
  })
  // Download the convert streams.
  request({
    method: 'get',
    url: '/download',
    responseType: 'stream',
    params: {sn, docId}
  }).then(function (res) {
    res.data.pipe(writeStream)
 
  }).catch(function (err) {
    console.log(err);
  })
  // Save the convert file.
  return new Promise(function(resolve, reject){
    writeStream.on('finish', function(){
      console.log('Download stream finished');
      resolve()
    })
    writeStream.on('error',function (err) {
      console.log(err.message)
      reject(err)
    })
  })
}
 
async function start(){
  outPath = path.dirname(outputFilePath);  
  if(!fs.existsSync(outPath)){
    fs.mkdirSync(outPath);            
  }          
  const taskId = await flattenPDFTask(inputFilePath)
  const docId = await pollForDocId(taskId)
  await downloadFileByDocId(docId, outputFilePath)
}
 
start().then(function(){
  console.log("Flatten PDF file successfully!");
  process.exit(0)
}).catch(function(err){
  console.log("Flatten PDF file failed: " + err);
  process.exit(1)
})