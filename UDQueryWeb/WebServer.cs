using System;
using System.Collections.Generic;
using System.Net;
using System.Text;
using System.Threading;

namespace UDQueryWeb
{
    /**
     * Code came from https://codehosting.net/blog/BlogEngine/post/Simple-C-Web-Server
     * Note that permission was not sought to use this code because:
     * "This little bit of code has proved quite popular, and recently I have been
     * asked if it has been released under any particular license. Since it was such a
     * small bit of code and just an evolution of the MSDN documentation found here, I
     * didn't really think about a license for it. But I am happy to share this code,
     * so to make the situation clear I'm releasing it under the MIT License."
     */
    public class WebServer
    {
        private readonly HttpListener _listener = new HttpListener();
        private readonly Func<HttpListenerRequest, string> _responderMethod;
        private List<string> _urls = new List<string>();

        public WebServer(string[] prefixes, Func<HttpListenerRequest, string> method)
        {
            if (!HttpListener.IsSupported)
                throw new NotSupportedException(
                    "Needs Windows XP SP2, Server 2003 or later.");

            // URI prefixes are required, for example 
            // "http://localhost:8080/index/".
            if (prefixes == null || prefixes.Length == 0)
                throw new ArgumentException("prefixes");

            // A responder method is required
            if (method == null)
                throw new ArgumentException("method");

            foreach (string s in prefixes)
            {
                _listener.Prefixes.Add(s);
                _urls.Add(s);
            }

            _responderMethod = method;
            _listener.Start();
        }

        public WebServer(Func<HttpListenerRequest, string> method, params string[] prefixes)
            : this(prefixes, method)
        { }

        public void Run()
        {
            ThreadPool.QueueUserWorkItem((o) =>
            {
                try
                {
                    while (_listener.IsListening)
                    {
                        ThreadPool.QueueUserWorkItem((c) =>
                        {
                            var ctx = c as HttpListenerContext;
                            try
                            {
                                string rstr = _responderMethod(ctx.Request);
                                byte[] buf = Encoding.UTF8.GetBytes(rstr);
                                ctx.Response.ContentLength64 = buf.Length;
                                ctx.Response.OutputStream.Write(buf, 0, buf.Length);
                            }
                            catch { } // suppress any exceptions
                            finally
                            {
                                // always close the stream
                                ctx.Response.OutputStream.Close();
                            }
                        }, _listener.GetContext());
                    }
                }
                catch { } // suppress any exceptions
            });
        }

        public void Stop()
        {
            _listener.Stop();
            _listener.Close();
        }

        public string[] URLs()
        {
            return _urls.ToArray();           
        }

    }
}
