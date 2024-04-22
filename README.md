# Examples for Foxit PDF Cloud API

These examples show you how to access Foxit PDF Cloud API via REST APIs . It requires a Foxit provided credential for authentication. Once you’ve completed the Getting credentials workflow, you can start to call REST APIs directly in your language of choice per the instructions of sections below.

## About Foxit Cloud API
The Foxit Cloud API provides modern cloud-based capabilities for PDF manipulation. they are implemented as REST APIs and can be invoked by any http client. Follow this guide to get a developer credential and get familiar with the API usage. 

## Getting credentials
You’ll need a client ID to use the Foxit PDF Editor Cloud APIs. To get one, the workflow is as follows:
* STEP1: Register a Foxit account and login [Foxit Developer console](https://cloudapi.foxitsoftware.cn/dev-console).
* STEP2: Activate your account and get 10000 for trial.
* STEP3: Create your own project.
* STEP4: Copy Client Id for later usage.
* STEP5: 
         If you simulate Dotnet development, Copy Client Id to client_id attribute of dotnet_examples/simple_demo/create_pdf_from_others/foxit_cloud_api_credentials.json
         If you simulate Python development, Copy Client Id to client_id attribute of python_examples/simple_demo/foxit_cloud_api_credentials.json
         If you simulate Node.js development, Copy Client Id to client_id attribute of node.js_examples/simple_demo/foxit_cloud_api_credentials.json
         If you simulate Java development, Copy Client Id to client_id attribute of java_examples/foxit_cloud_api_credentials.json
## Prerequisites

### Dotnet
* .NET Core: version 2.1 or above.
* Requires Visual studio or .NET Core CLI to be installed to be able to run the examples projects.
* It depend on RestSharp v106, RestSharp v107 changes the library API surface and its behaviour significantly. We advise looking at [v107](https://restsharp.dev/v107/) docs to understand how to migrate to the latest version of RestSharp.

### Python

* Python : Version 2.7 or 3.6+. Download python Installation package, please visit the website [python.org](https://www.python.org/). 
* Install Requests, simply run the following command in your terminal :
```
$ python -m pip install requests
```

### Node.js
* Install NodeJs version >= 12.0. Download Node.js Installation package, please visit the website [nodejs.org](https://nodejs.org/en/download/).
* Install dependencies, here we use the popular http-client library "axios".

### Java
* Java JDK : Version 8 or above.
* Requires Maven to be installed. Download Maven Installation package, please visit the website [maven.apache.org](https://maven.apache.org/install.html).
* Add the Maven Path to your system environment path.

### Curl
 * Download the curl binary from [curl.se](https://curl.se/download.html).
 * Add the curl binary to your system environment path.

## Running the examples

### Dotnet

#### create_pdf_from_others
The examples create new task from a DOC file to PDF file. 
```
$ cd dotnet_examples/simple_demo/create_pdf_from_others/
$ dotnet run create_pdf_from_others.csproj
```

### Python
#### create_pdf_from_others
The example create new task from a DOC file to PDF file. 
```
$ cd python_examples/simple_demo/create_pdf_from_others/
$ python create_pdf_from_others.py
```
### Node.js
#### create_pdf_from_others
The example create new task from a DOC file to PDF file. 
```
$ cd node.js_examples/simple_demo/create_pdf_from_others/
$ npm install axios form-data
$ node create_pdf_from_others.js
```
### Java
Before running the examples, run the following command to build the project, the dependencies of the project which defined in pom.xml  will be downloaded.
```
$ cd java_examples/
$ mvn clean install
```

#### create_pdf_from_word
The example create new task from a DOC file to PDF file. 
```
$ cd java_examples/
$ mvn exec:java -Dexec.mainClass="com.foxit.simple_demo.create_pdf_from_others.Create_pdf_from_others" -Dexec.cleanupDaemonThreads=false
```

### Curl
#### create_pdf_from_others
The example create new task from a DOC file to PDF file.

* 1. Call the document manipulation API, eg. /document/create.
     Replace the value "AboutFoxit.doc" for inputDocument with your own input document path. 
```
$ curl -X POST --header "Content-Type: multipart/form-data" --header "Accept: application/json" -F inputDocument=@"AboutFoxit.doc" -F format=word  "https://servicesapi.foxitsoftware.cn.com/api/document/create?sn=testsn&clientId=01fxxxxxx16a7"
```
* 2. Call "/task" API to get task status using taskId returned by step 1.
```
$ curl -X GET --header "Accept: application/json" "https://servicesapi.foxitsoftware.cn/api/task?sn=testsn&clientId=01fxxxxxx16a7&taskId=622xxxxxxdaf8"
```
* 3. Call "/download" API to download doc using docId returned by step 2.

     Replace the value "AboutFoxit.pdf" for fileName with name of the downloaded file. 

     Replace the value "AboutFoxit.pdf" for --output with your own output document path. 

```
$ curl --location --request GET "https://servicesapi.foxitsoftware.cn/api/download?sn=testsn&clientId=01fxxxxxx16a7&docId=622xxxxxx60d7&fileName=AboutFoxit.pdf" --output "AboutFoxit.pdf"
```
## License
Copyright (c) Foxit Software. All rights reserved.

Licensed under the MIT license.

