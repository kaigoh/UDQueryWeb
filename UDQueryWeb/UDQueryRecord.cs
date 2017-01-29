using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.Threading.Tasks;

namespace UDQueryWeb
{
    [Serializable]
    public class UDQueryRecord
    {
        public string ID { get; set; }
        public List<UDQueryKeyValue> Columns { get; set; }
    }
}
