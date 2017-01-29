using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.Threading.Tasks;
using System.Xml.Serialization;

namespace UDQueryWeb
{
    [XmlRoot("Column")]
    public class UDQueryDataColumn
    {
        public string Key { get; set; }
        public int Location { get; set; }
        public string Value { get; set; }
    }
}
