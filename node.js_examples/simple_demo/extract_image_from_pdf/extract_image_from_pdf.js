// Copyright (C) 2003-2024, Foxit Software Inc..
// All Rights Reserved.
//
// http://www.foxitsoftware.com
//
// The following code is copyrighted and contains proprietary information and trade secrets of Foxit Software Inc..
// You cannot distribute any part of Foxit Cloud API to any third party or general public,
// unless there is a separate license agreement with Foxit Software Inc. which explicitly grants you such rights.
//
// This file contains an example to demonstrate how to use Foxit Cloud API to extract image from pdf files.
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
const inputFilePath = '../input_files/PDF2Img.pdf'
const outputFilePath = '../output_files/extract_image_from_pdf/Image.zip'

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

function extractPDFTask(inputFilePath,  mode = 'extractImages', page_range = ""){
  const readStream = fs.createReadStream(inputFilePath)
  readStream.on('error',function (err) {
    console.log('read input file error');
    console.log(err.message)
    process.exit(1)
  })

  const formData = new FormData()
  formData.append('inputDocument', readStream)
  formData.append('mode', mode)
  if (page_range != '') formData.append('pageRange', page_range)
  
  //Upload a file and create a new workflow task.
  return request({
    method: 'post',
    url: '/document/extract',
    headers: formData.getHeaders(),
    data: formData
  }).then(function (res) {

    const resultData = res.data

    // Read the api doc about all result codes
    if(resultData.code === 0){
      return resultData.data.taskInfo.taskid
    }
  }).catch(function (err) {
    console.log("Extract image from pdf task error:", err.response.data);
    throw err
  })
}

function getTaskInfo(taskId){
  return request({
    method: 'get',
    url: '/task',
    params: {taskId}
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
          resolve(taskInfo.docid)
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
 
  writeStream.on('error',function (err) {
    console.log(err.message)
    process.exit(1)
  })
  // Download the convert streams.
  request({
    method: 'get',
    url: '/download',
    responseType: 'stream',
    params: {docId}
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
  const taskId = await extractPDFTask(inputFilePath)
  const docId = await pollForDocId(taskId)
  await downloadFileByDocId(docId, outputFilePath)
}
 
start().then(function(){
  console.log("Extract image from PDF successfully!");
  process.exit(0)
}).catch(function(err){
  console.log("Extract image from PDF failed: " + err);
  process.exit(1)
})