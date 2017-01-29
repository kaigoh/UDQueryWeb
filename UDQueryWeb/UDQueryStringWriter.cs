using System.IO;
using System.Text;

namespace UDQueryWeb
{
    public sealed class UDQueryStringWriter : StringWriter
    {
        public override Encoding Encoding { get { return Encoding.UTF8; } }
    }
}
