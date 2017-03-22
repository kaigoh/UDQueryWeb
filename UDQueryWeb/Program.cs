using System;
using System.Collections;
using System.Net;
using System.Text;
using IBMU2.UODOTNET;
using System.IO;
using System.IO.Compression;
using System.Collections.Generic;
using System.Xml.Serialization;

namespace UDQueryWeb
{
    class Program
    {
        static void Main(string[] args)
        {
            WebServer wsRaw = new WebServer(RawQuery, "http://localhost:8098/rawquery/");
            WebServer wsXML = new WebServer(XMLQuery, "http://localhost:8098/xmlquery/");
            WebServer wsStatus = new WebServer(ServerStatus, "http://localhost:8098/status/");
            wsRaw.Run();
            wsXML.Run();
            wsStatus.Run();
            Console.WriteLine("RTS UDQuery Web (C) Kai Gohegan, 2015 - Press any key to quit.");
            Console.ReadKey();
            wsRaw.Stop();
            wsXML.Stop();
            wsStatus.Stop();
        }

        public static string ServerStatus(HttpListenerRequest request)
        {
            return "up";
        }

        public static string RawQuery(HttpListenerRequest request)
        {
            StringBuilder response = new StringBuilder();
            // Add XML declaration to the response
            response.Append("<?xml version=\"1.0\" ?>");
            // Get the request variables, GET and POST
            Hashtable vars = GetRequestValues(request);
            // Build the response
            response.Append("<udqueryweb>");
            if (vars.Count > 0)
            {
                try
                {
                    // Username
                    string username = vars["username"].ToString();
                    // Password
                    string password = vars["password"].ToString();
                    // Server
                    string server = vars["server"].ToString();
                    // Path
                    string path = vars["path"].ToString();
                    // Query
                    string query = vars["query"].ToString();

                    // Output to console
                    Console.WriteLine("Raw client connected! Server: " + server + ", Query: " + query);

                    // Connect to unidata database
                    UniSession uniSes = UniObjects.OpenSession(server, username, password, path);

                    // Create UniCommand
                    UniCommand uniCom = uniSes.CreateUniCommand();

                    // Execute the command
                    uniCom.Command = query;
                    uniCom.Execute();

                    // Send the results back to the requester
                    response.Append("<response>success</response><query><![CDATA[" + query + "]]></query><message><![CDATA[" + compressString(uniCom.Response.Trim()) + "]]></message>");

                    // Close the session down
                    uniSes.Dispose();

                }
                catch(Exception ex)
                {
                    // Send error, no config supplied
                    response.Append("<response>error</response><query /><message>" + ex.Message + "</message>");
                    // Output to console
                    Console.WriteLine("Error: " + ex.Message);
                }
            }
            else
            {
                // Send error, no config supplied
                response.Append("<response>error</response><message>No configuration or query supplied with request</message>");
                // Output to console
                Console.WriteLine("Error: Config and query not supplied");
            }
            response.Append("</udqueryweb>");
            return response.ToString();
        }

        public static string XMLQuery(HttpListenerRequest request)
        {
            StringBuilder response = new StringBuilder();
            // Add XML declaration to the response
            response.Append("<?xml version=\"1.0\" ?>");
            // Get the request variables, GET and POST
            Hashtable vars = GetRequestValues(request);
            // Build the response
            response.Append("<udqueryweb>");
            if (vars.Count > 0)
            {
                try
                {
                    // Username
                    string username = vars["username"].ToString();
                    // Password
                    string password = vars["password"].ToString();
                    // Server
                    string server = vars["server"].ToString();
                    // Path
                    string path = vars["path"].ToString();
                    // File
                    string file = vars["file"].ToString();
                    // Query
                    string query = vars["query"].ToString();
                    // Fields
                    string[] fields = vars["fields"].ToString().Split('|');
                    // I-type fields
                    List<string> iFields = new List<string>();
                    // Other fields
                    List<string> otherFields = new List<string>();

                    // Process the fields
                    foreach(string fRaw in fields)
                    {
                        string[] fieldType = fRaw.Split('#');
                        if(fieldType.Length > 1)
                        {
                            switch(fieldType[1].ToLower())
                            {
                                case "i":
                                    iFields.Add(fieldType[0]);
                                    break;
                                default:
                                    otherFields.Add(fieldType[0]);
                                    break;
                            }
                        } else
                        {
                            otherFields.Add(fieldType[0]);
                        }
                    }

                    // Output to console
                    Console.WriteLine("XML client connected! Server: " + server + ", Query: " + query);

                    // I-type records need some investigation...
                    if (iFields.Count > 0)
                    {
                        Console.WriteLine("Warning: I-type fields are currently ignored!");
                    }

                    // Connect to unidata database
                    UniSession uniSes = UniObjects.OpenSession(server, username, password, path);

                    // Connect to file
                    UniFile uniFile = uniSes.CreateUniFile(file);

                    // Get command
                    UniCommand uniCmd = uniSes.CreateUniCommand();
                    uniCmd.Command = query;
                    uniCmd.Execute();

                    // Clean up the response
                    string responseRaw = uniCmd.Response.Replace(file, "");

                    // Create UDQueryDataset
                    UDQueryDataset uds = new UDQueryDataset();
                    uds.Records = new List<UDQueryRecord>();

                    if (responseRaw.Trim().Length > 0)
                    {
                        // Get IDs returned from above query
                        string[] ids = responseRaw.Split(new string[] { "\n", "\r\n" }, StringSplitOptions.RemoveEmptyEntries);

                        // Iterate through IDs...
                        List<string> cleanIDs = new List<string>();
                        foreach (string id in ids)
                        {
                            if (id.Trim().Length > 0)
                            {
                                if (id.Trim().Split(' ').Length == 1)
                                {
                                    string[] checkID = id.Trim().Split(new string[] { " " }, StringSplitOptions.RemoveEmptyEntries);
                                    string cleanedID = null;
                                    if (checkID.Length > 1)
                                    {
                                        cleanedID = checkID[1].Trim();
                                    }
                                    else
                                    {
                                        cleanedID = id.Trim();
                                    }
                                    if (!cleanIDs.Contains(cleanedID))
                                    {
                                        cleanIDs.Add(cleanedID);
                                    }
                                }
                            }
                        }

                        // Fetch data from UniData
                        UniDataSet ds = uniFile.ReadRecords(cleanIDs.ToArray(), otherFields.ToArray());
                        Console.WriteLine("\tSending " + ds.RowCount + " records to client...");
                        if (ds.RowCount > 0)
                        {
                            foreach (UniRecord record in ds)
                            {
                                try
                                {
                                    UDQueryRecord row = new UDQueryRecord();
                                    string[] otherFieldsClean = otherFields.ToArray();
                                    row.ID = record.RecordID;
                                    row.Columns = new List<UDQueryKeyValue>();
                                    for (int i = 1; i <= otherFieldsClean.Length; i++)
                                    {
                                        UDQueryKeyValue column = new UDQueryKeyValue();
                                        column.Key = otherFieldsClean[i - 1];
                                        if (record.Record.Count(i) > 1)
                                        {
                                            List<string> mvalues = new List<string>();
                                            for (int mv = 0; mv <= record.Record.Count(i); mv++)
                                            {
                                                mvalues.Add(record.Record.Extract(i, (mv + 1)).ToString());
                                            }
                                            column.MultiValue = mvalues.ToArray();
                                        }
                                        else
                                        {
                                            column.Value = record.Record.Extract(i).ToString();
                                        }
                                        row.Columns.Add(column);
                                    }
                                    uds.Records.Add(row);
                                }
                                catch(Exception recordEx)
                                {
                                    // Output to console
                                    Console.WriteLine("Record error: " + recordEx.Message);
                                }
                            }
                        }

                    }

                    // Serialize the list to XML
                    XmlSerializer xs = new XmlSerializer(typeof(UDQueryDataset));
                    string xml = null;
                    using (UDQueryStringWriter sr = new UDQueryStringWriter())
                    {
                        xs.Serialize(sr, uds);
                        xml = sr.ToString();
                    }

                    // Send the results back to the requester
                    response.Append("<response>success</response><file><![CDATA[" + file + "]]></file><message><![CDATA[" + compressString(xml) + "]]></message>");

                    // Close the session down
                    uniSes.Dispose();

                }
                catch (Exception ex)
                {
                    // Send error, no config supplied
                    response.Append("<response>error</response><query /><message>" + ex.Message + "</message>");
                    // Output to console
                    Console.WriteLine("Query Error: " + ex.Message);
                }
            }
            else
            {
                // Send error, no config supplied
                response.Append("<response>error</response><message>No configuration or query supplied with request</message>");
                // Output to console
                Console.WriteLine("Error: Config and query not supplied");
            }
            response.Append("</udqueryweb>");
            return response.ToString();
        }

        private static Hashtable GetRequestValues(HttpListenerRequest request)
        {
            Hashtable formVars = new Hashtable();
            for (int x = 0; x < request.QueryString.Count; x++)
            {
                if (!formVars.ContainsKey(request.QueryString.Keys[x]))
                {
                    formVars.Add(request.QueryString.Keys[x], Uri.UnescapeDataString(request.QueryString[x]));
                }
            }
            return formVars;
        }

        private static string compressString(string text)
        {
            byte[] buffer = Encoding.UTF8.GetBytes(text);
            string output = String.Empty;
            try
            {
                using (MemoryStream ms = new MemoryStream())
                {
                    using (GZipStream zip = new GZipStream(ms, CompressionMode.Compress, true))
                    {
                        zip.Write(buffer, 0, buffer.Length);
                    }

                    output = Convert.ToBase64String(ms.ToArray());
                }
                return output;
            }
            catch (Exception e)
            {
                Console.WriteLine("Error: " + e.Message);
                return null;
            }
        }

    }
}
