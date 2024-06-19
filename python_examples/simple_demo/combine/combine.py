# Copyright (C) 2003-2024, Foxit Software Inc..
# All Rights Reserved.
#
# http://www.foxitsoftware.com
#
# The following code is copyrighted and contains proprietary information and trade secrets of Foxit Software Inc..
# You cannot distribute any part of Foxit Cloud API to any third party or general public,
# unless there is a separate license agreement with Foxit Software Inc. which explicitly grants you such rights.
#
# This file contains an example to demonstrate how to use Foxit Cloud API to combine pdf files to one pdf file.
# NOTE: The first step to install requests, simply run this simply command "python -m pip install requests".

import os
import time
import json
import requests
import urllib.parse
import hashlib

class Combine:
    def __init__(self):

        self.client_id = ''
        self.secret_id = ''
        # The signature of parameters, clientId and secretId.
        # (we ignore this parameter on trial version, input any string is ok)
        self.sn = 'testsn'
        # TODO: replace with your own input doc path and output file path
        self.input_file_path = '../input_files/combine.zip'
        self.output_file_path = '../output_files/combine/CombineResultFiles.pdf'

        # TODO: replace with server base url
        self.base_url = 'https://servicesapi.foxitsoftware.cn/api'

    # Concatenate strings using '/', build uri.
    def build_uri(self, endpoint):
        return '/'.join([self.base_url, endpoint])

    # Get clientId and secretId form the json file.
    def get_credentials_params(self, credentials_path):
        with open(credentials_path, 'r') as fd:
            load_dict = json.load(fd)
            self.client_id = load_dict['client_credentials']['client_id']
            self.secret_id = load_dict['client_credentials']['secret_id']     
            
    def combine_task(self, input_zip_file):
        configString = "{\r\n  \"isAddBookmark\": true,\r\n  \"isAddTOC\": false,\r\n  \"isContinueMerge\": true,\r\n  \"isRetainPageNum\": false,\r\n  \"bookmarkLevels\": \"1-4\" \r\n}"
        payload = {'config': configString}
        filename = os.path.basename(input_zip_file)
        files = {
            ('inputZipDocument', (filename, open(input_zip_file,'rb'), 'application/zip'))
            }

        queryParams = {
            'clientId': self.client_id,
            'config': configString,
        }
        sortedParams = dict(sorted(queryParams.items()))
        queryString = urllib.parse.urlencode(sortedParams)
        queryString += '&sk=' + urllib.parse.quote(self.secret_id)
        self.sn = hashlib.md5(queryString.encode('utf-8')).hexdigest()

        params = {'sn':self.sn, 'clientId':self.client_id}
        # Upload a file and create a new workflow task.
        # In the event you are posting a very large file as a multipart/form-data request, 
        # you may want to stream the request. By default, requests does not support this, 
        # but there is a separate package which does - requests-toolbelt.
        response = requests.request("POST", self.build_uri('document/combine'), 
                    params=params, data=payload, files=files, timeout=60*1000)
        response.raise_for_status()
        r_json = response.json()
        if(r_json['code'] == 0):
            return r_json['data']['taskInfo']['taskId']
        else:
            raise Exception(r_json[u'msg'])
        

    def get_task_info(self, task_id):
        queryParams = {
            'clientId': self.client_id,
            'taskId': task_id,
        }
        sortedParams = dict(sorted(queryParams.items()))
        queryString = urllib.parse.urlencode(sortedParams)
        queryString += '&sk=' + urllib.parse.quote(self.secret_id)
        self.sn = hashlib.md5(queryString.encode('utf-8')).hexdigest()
    
        params = {'sn':self.sn, 'clientId':self.client_id, 'taskId':task_id}
        response = requests.request("GET", self.build_uri('task'), 
                    params=params, timeout=60*1000)
        response.raise_for_status()
        
        r_json = response.json()
        if(r_json['code'] == 0):
            percentage = r_json['data']['taskInfo']['percentage']
            docid = 0
            if percentage == 100:
              docid = r_json['data']['taskInfo']['docId']
            print('Task process is: %d' % percentage)
            return docid, percentage
        else:
            raise Exception(r_json['task_info'])

    def poll_for_docid(self, task_id, interval_in_miliseconds=2000):
        while True:
            try:                
                docid, percentage = self.get_task_info(task_id)
                if percentage == 100:
                    print('Task completed.')
                    return docid                       
            except requests.exceptions.HTTPError as e:
                r_json = e.response.json()
                # when task is running, the task api will return error.
                # if task is running, try to get taskInfo later.
                if(r_json['data']['detail'].find('The task is running') > -1):
                    print('Task is running, retry in %d miliseconds' % interval_in_miliseconds)
                else:
                    raise e
            except Exception as e:
                raise e
            time.sleep(float(interval_in_miliseconds / 1000))

    def down_load_file_by_docid(self, doc_id, output_file_path):
        filename = os.path.basename(output_file_path)
        queryParams = {
            'clientId': self.client_id,
            'docId': doc_id,
            'fileName':filename
        }
        sortedParams = dict(sorted(queryParams.items()))
        queryString = urllib.parse.urlencode(sortedParams)
        queryString += '&sk=' + urllib.parse.quote(self.secret_id)
        self.sn = hashlib.md5(queryString.encode('utf-8')).hexdigest()

        params = {'sn':self.sn, 'clientId':self.client_id, 'docId':doc_id, 'fileName':filename }
        # Download the convert streams.
        response = requests.request("GET", self.build_uri('download'),
                    params=params, timeout=60*1000)
        response.raise_for_status()
        # Save the convert file.
        with open(output_file_path, 'wb') as fd:
            fd.write(response.content)
        print('Download stream finished.')

    def start(self):
        try:
            output_path = os.path.dirname(self.output_file_path)     
            if not os.path.exists(output_path):
                os.makedirs(output_path)

            self.get_credentials_params('../foxit_cloud_api_credentials.json')
            task_id = self.combine_task(self.input_file_path)
            doc_id = self.poll_for_docid(task_id)
            self.down_load_file_by_docid(doc_id, self.output_file_path)
            print('Combine PDF files successfully!')
        except requests.exceptions.Timeout as e:
            print(e)
        except requests.exceptions.HTTPError as e:
            print(e)
        except requests.exceptions.RequestException as e:
            print(e)
        except Exception as e:
            print(e)
        
if __name__ == '__main__':
    combine = Combine()
    combine.start()
