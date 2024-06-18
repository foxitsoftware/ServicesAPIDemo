// Copyright (C) 2003-2024, Foxit Software Inc..
// All Rights Reserved.
//
// http://www.foxitsoftware.com
//
// The following code is copyrighted and contains proprietary information and trade secrets of Foxit Software Inc..
// You cannot distribute any part of Foxit Cloud API to any third party or general public,
// unless there is a separate license agreement with Foxit Software Inc. which explicitly grants you such rights.
//
// This file contains an example to demonstrate how to use Foxit Cloud API to extract text from pdf files.
// NOTE: before using this demo, you need to add the NuGet package RestSharp 110.2.0 and Newtonsoft.Json latest version.


using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Net;
using System.Security.Cryptography;
using System.Text;
using System.Threading.Tasks;
using RestSharp;

namespace ExtractTextFromPDFCS
{
    public class RestException : Exception
    {
        public RestException(RestResponse r, string message, Exception innerException)
          : base(message, innerException)
        {
            response = r;
        }
        public RestResponse response { get; private set; }
    }

    class ExtractTextFromPDF
    {
        private string client_id = "";
        private string secret_id = "";
        // The signature of parameters, clientId and secret Id. (we ignore this parameter  on trial version，input any string is ok)
        private string sn = "testsn";
        // TODO: replace with your own input doc path and output file path
        private const string input_file_path = "../input_files/AboutFoxit.pdf";
        private const string output_file_path = "../output_files/extract_text_from_pdf/AboutFoxit.txt";

        //TODO: replace with server base url
        private IRestClient client = new RestClient(new RestClientOptions() { MaxTimeout = 60 * 1000, BaseUrl = new System.Uri("https://servicesapi.foxitsoftware.cn/api") });

        // Read clientId and secretId form the json file.
        private void GetCredentialsParams(string credentials_path)
        {
            string content = File.ReadAllText(credentials_path);
            dynamic json = Newtonsoft.Json.Linq.JToken.Parse(content) as dynamic;
            client_id = json.client_credentials.client_id;
            secret_id = json.client_credentials.secret_id;
        }

        static string GenerateMD5(string input)
        {
            using (MD5 md5 = MD5.Create())
            {
                byte[] inputbytes = Encoding.UTF8.GetBytes(input);
                byte[] hashbytes = md5.ComputeHash(inputbytes);
                return BitConverter.ToString(hashbytes).Replace("-", "").ToLower();
            }
        }

        private string ExtractPDFTask(string input_file_path, string mode = "extractText", string page_range = "")
        {
            var query_params = new Dictionary<string, string>
            {
                { "clientId", client_id },
                { "mode", mode},
            };
            if (page_range != "")
                query_params.Add("pageRange", page_range);

            var sorted_params = query_params.OrderBy(kv => kv.Key).ToDictionary(kv => kv.Key, kv => kv.Value);
            var query_string = string.Join("&", sorted_params.Select(kv => $"{kv.Key}={WebUtility.UrlEncode(kv.Value)}"));
            query_string += "&sk=" + secret_id;
            sn = GenerateMD5(query_string);

            var request = new RestRequest("document/extract", Method.Post);
            request
              .AddHeader("Accept", "application/json")
              .AddQueryParameter("sn", sn)
              .AddQueryParameter("clientId", client_id)
              .AddFile("inputDocument", input_file_path, "multipart/form-data")
              .AddParameter("mode", mode);
            if (page_range != "")
                request.AddParameter("pageRange", page_range);

            // Upload a file and create a new workflow task.
            var response = client.ExecuteAsync(request);
            if (response.Result.IsSuccessful &&
                  response.Result.ResponseStatus == ResponseStatus.Completed)
            {
                dynamic json = Newtonsoft.Json.Linq.JToken.Parse(response.Result.Content) as dynamic;
                if (json.code == 0) return json.data.taskInfo.taskId;
            }

            string message = string.Format("http response error: {0} : {1}",
                   response.Result.ErrorMessage, response.Result.Content);
            throw new RestException(response.Result, message, response.Result.ErrorException);
        }

        private string GetTaskInfo(string task_id)
        {
            var query_params = new Dictionary<string, string>
            {
                { "clientId", client_id },
                { "taskId", task_id }
            };
            var sorted_params = query_params.OrderBy(kv => kv.Key).ToDictionary(kv => kv.Key, kv => kv.Value);
            var query_string = string.Join("&", sorted_params.Select(kv => $"{kv.Key}={WebUtility.UrlEncode(kv.Value)}"));
            query_string += "&sk=" + secret_id;
            sn = GenerateMD5(query_string);

            RestRequest request = new RestRequest("task", Method.Get);
            request
              .AddHeader("Accept", "application/json")
              .AddQueryParameter("sn", sn)
              .AddQueryParameter("clientId", client_id)
              .AddQueryParameter("taskId", task_id);
            var response = client.ExecuteAsync(request);
            if (response.Result.IsSuccessful &&
                  response.Result.ResponseStatus == ResponseStatus.Completed)
            {
                dynamic json = Newtonsoft.Json.Linq.JToken.Parse(response.Result.Content) as dynamic;
                if (json.code == 0)
                {
                    Console.WriteLine("Task process is: {0}", json.data.taskInfo.percentage);
                    string task_info = Convert.ToString(json.data.taskInfo);
                    return task_info;
                }
            }

            string message = string.Format("http response error: {0} : {1}",
                   response.Result.StatusDescription, response.Result.Content);
            throw new RestException(response.Result, message, response.Result.ErrorException);
        }

        private string PollForDocId(string task_id, int interval_in_miliseconds = 2000)
        {
            do
            {
                try
                {
                    string task_info = GetTaskInfo(task_id);
                    dynamic json = Newtonsoft.Json.Linq.JToken.Parse(task_info) as dynamic;
                    int percentage = json.percentage;
                    if (percentage == 100)
                    {
                        Console.WriteLine("Task completed.");
                        return json.docId;
                    }
                }
                catch (RestException e)
                {
                    dynamic tmp = Newtonsoft.Json.Linq.JToken.Parse(e.response.Content) as dynamic;
                    string detail = tmp.data.detail;
                    // when task is running, the task api will return error
                    // if task is running, try to get taskInfo later 
                    if (detail.IndexOf("The task is running") > -1)
                        Console.WriteLine("Task is running, retry in {0} miliseconds", interval_in_miliseconds);
                    else
                        throw e;
                }
                Task.Delay(interval_in_miliseconds).Wait();
            } while (true);
        }

        private void DownLoadFileByDocId(string doc_id, string output_file_path)
        {
            var query_params = new Dictionary<string, string>
            {
               { "clientId", client_id },
               { "docId", doc_id },
               { "fileName", Path.GetFileName(output_file_path)}
            };
            var sorted_params = query_params.OrderBy(kv => kv.Key).ToDictionary(kv => kv.Key, kv => kv.Value);
            var query_string = string.Join("&", sorted_params.Select(kv => $"{kv.Key}={WebUtility.UrlEncode(kv.Value)}"));
            query_string += "&sk=" + secret_id;
            sn = GenerateMD5(query_string);

            var request = new RestRequest("download", Method.Get);
            request
              .AddQueryParameter("sn", sn)
              .AddQueryParameter("clientId", client_id)
              .AddQueryParameter("docId", doc_id)
              .AddQueryParameter("fileName", Path.GetFileName(output_file_path));
            var response = client.ExecuteAsync(request);
            if (response.Result.IsSuccessful &&
                  response.Result.ResponseStatus == ResponseStatus.Completed)
            {
                var fs = new FileStream(output_file_path, FileMode.Create);
                fs.Write(response.Result.RawBytes);
                fs.Close();
                Console.WriteLine("Download stream finished.");
            }
            else
            {
                string message = string.Format("http response error: {0}",
                     response.Result.StatusDescription);
                throw new RestException(response.Result, message, response.Result.ErrorException);
            }
        }

        public static void Start()
        {
            string output_path = Path.GetDirectoryName(output_file_path);
            if (Directory.Exists(output_path) == false)
                Directory.CreateDirectory(output_path);

            try
            {
                ExtractTextFromPDF extract_text_from_pdf = new ExtractTextFromPDF();
                extract_text_from_pdf.GetCredentialsParams(Directory.GetCurrentDirectory() + "/foxit_cloud_api_credentials.json");
                string task_id = extract_text_from_pdf.ExtractPDFTask(input_file_path);
                string doc_id = extract_text_from_pdf.PollForDocId(task_id);
                extract_text_from_pdf.DownLoadFileByDocId(doc_id, output_file_path);
                Console.WriteLine("Extract text from PDF successfully!");
            }
            catch (RestException e)
            {
                Console.WriteLine(e.Message);
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
            }
        }

        static void Main(string[] args)
        {
            ExtractTextFromPDF.Start();
        }
    }
}
