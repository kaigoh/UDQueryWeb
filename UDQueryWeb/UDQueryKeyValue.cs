using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.Threading.Tasks;

namespace UDQueryWeb
{
    [Serializable]
    public class UDQueryKeyValue
    {
        public string Key { get; set; }
        public string Value { get; set; }
        public string[] MultiValue { get; set; }
    }
}
